<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\CreditsCreditedMail;
use App\Mail\WelcomeUserMail;
use App\Models\AuditLog;
use App\Models\CreditTransaction;
use App\Models\User;
use App\Services\Downloads\CreditLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $q = User::query()->with('roles:id,name');

        if ($search = $request->string('q')->trim()->toString()) {
            $q->where(function ($w) use ($search) {
                $w->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('banned')) {
            $q->whereNotNull('banned_at');
        }

        return Inertia::render('admin/users/index', [
            'users' => $q->latest()->paginate(20)->withQueryString(),
            'filters' => $request->only(['q', 'banned']),
        ]);
    }

    public function show(User $user): Response
    {
        return Inertia::render('admin/users/show', [
            'user' => $user->load('roles:id,name'),
            'transactions' => CreditTransaction::where('user_id', $user->id)
                ->latest()
                ->limit(50)
                ->get(),
            'downloads' => $user->downloadRequests()
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }

    /**
     * Create a new user from the admin panel. Optionally seeds the account
     * with starting credits through the ledger so the movement shows up in
     * the audit / transactions tables.
     */
    public function store(Request $request, CreditLedger $ledger): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['user', 'admin'])],
            'initial_credits' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);

        $admin = $request->user();
        $plainPassword = $data['password'];

        [$user, $credits] = DB::transaction(function () use ($data, $admin, $ledger) {
            $user = User::create([
                'name' => $data['name'],
                'email' => mb_strtolower(trim($data['email'])),
                'phone' => preg_replace('/\D+/', '', (string) ($data['phone'] ?? '')) ?: null,
                'password' => Hash::make($data['password']),
                'email_verified_at' => now(),
            ]);

            $user->assignRole($data['role']);

            $credits = (int) ($data['initial_credits'] ?? 0);
            if ($credits > 0) {
                $ledger->credit(
                    user: $user,
                    amount: $credits,
                    type: CreditTransaction::TYPE_ADMIN_CREDIT,
                    description: 'Crédito inicial ao criar a conta',
                    actor: $admin,
                );
            }

            AuditLog::create([
                'user_id' => $admin->id,
                'action' => 'user.created',
                'subject_type' => User::class,
                'subject_id' => $user->id,
                'after' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $data['role'],
                    'initial_credits' => $credits,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return [$user, $credits];
        });

        // Welcome e-mail with the temporary password so the admin doesn't
        // need to share it through a side channel.
        try {
            Mail::to($user->email)->send(new WelcomeUserMail($user, $plainPassword));
        } catch (\Throwable $e) {
            Log::warning('Welcome mail failed: '.$e->getMessage(), ['user_id' => $user->id]);
        }

        // If credits were granted upfront, tell the user.
        if ($credits > 0) {
            try {
                Mail::to($user->email)->send(new CreditsCreditedMail(
                    user: $user->fresh(),
                    amount: $credits,
                    balanceAfter: $user->fresh()->credits_balance,
                    reason: 'Crédito inicial ao criar a conta',
                ));
            } catch (\Throwable $e) {
                Log::warning('Credit mail failed: '.$e->getMessage(), ['user_id' => $user->id]);
            }
        }

        return redirect()
            ->route('admin.users.show', $user->id)
            ->with('status', "Usuário {$user->name} criado.");
    }

    public function adjustCredits(Request $request, User $user, CreditLedger $ledger): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'not_in:0'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $amount = (int) $data['amount'];
        $admin = $request->user();
        $description = $data['description'] ?? null;

        try {
            if ($amount > 0) {
                $ledger->credit($user, $amount, CreditTransaction::TYPE_ADMIN_CREDIT, $description, actor: $admin);
            } else {
                $ledger->debit($user, abs($amount), CreditTransaction::TYPE_ADMIN_DEBIT, $description, actor: $admin);
            }
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'user.credits_adjusted',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'after' => ['amount' => $amount, 'description' => $description],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Only mail on credit (positive amount) — debits are silent so we
        // don't spam the user when the admin is correcting a mistake.
        if ($amount > 0) {
            try {
                $fresh = $user->fresh();
                Mail::to($fresh->email)->send(new CreditsCreditedMail(
                    user: $fresh,
                    amount: $amount,
                    balanceAfter: $fresh->credits_balance,
                    reason: $description ?: 'Crédito adicionado pelo administrador',
                ));
            } catch (\Throwable $e) {
                Log::warning('Credit mail failed: '.$e->getMessage(), ['user_id' => $user->id]);
            }
        }

        return back()->with('status', 'Créditos ajustados.');
    }

    public function ban(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $user->update([
            'banned_at' => now(),
            'banned_reason' => $data['reason'] ?? null,
        ]);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'user.banned',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'after' => $data,
            'ip_address' => $request->ip(),
        ]);

        return back()->with('status', 'Usuário banido.');
    }

    public function unban(Request $request, User $user): RedirectResponse
    {
        $user->update(['banned_at' => null, 'banned_reason' => null]);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'user.unbanned',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'ip_address' => $request->ip(),
        ]);

        return back()->with('status', 'Usuário reativado.');
    }

    /**
     * Update name / email / password of an existing user. Each field is
     * optional — only fields that come in the payload are touched.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'email' => ['sometimes', 'required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
        ]);

        $before = [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
        ];

        if (array_key_exists('name', $data)) {
            $user->name = $data['name'];
        }
        if (array_key_exists('email', $data)) {
            $user->email = mb_strtolower(trim($data['email']));
        }
        if (array_key_exists('phone', $data)) {
            $user->phone = preg_replace('/\D+/', '', (string) ($data['phone'] ?? '')) ?: null;
        }
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'user.updated',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'before' => $before,
            'after' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'password_changed' => ! empty($data['password']),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('status', 'Usuário atualizado.');
    }

    public function toggleAdmin(Request $request, User $user): RedirectResponse
    {
        $admin = $request->user();
        if ($admin->is($user)) {
            throw ValidationException::withMessages(['role' => 'Você não pode alterar o próprio papel.']);
        }

        if ($user->hasRole('admin')) {
            $user->removeRole('admin');
            $user->assignRole('user');
            $action = 'user.demoted';
        } else {
            $user->syncRoles(['admin']);
            $action = 'user.promoted';
        }

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => $action,
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'ip_address' => $request->ip(),
        ]);

        return back()->with('status', 'Papel atualizado.');
    }
}
