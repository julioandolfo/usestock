<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CreditTransaction;
use App\Models\User;
use App\Services\Downloads\CreditLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
