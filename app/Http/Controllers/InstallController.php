<?php

namespace App\Http\Controllers;

use App\Jobs\SyncProvidersJob;
use App\Models\User;
use App\Services\GetStocks\GetStocksClient;
use App\Services\GetStocks\GetStocksException;
use App\Settings\GeneralSettings;
use App\Settings\GetStocksSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * One-shot install wizard rendered on first boot.
 *
 * Configures admin credentials + GetStocks API token in DB-backed settings,
 * so the app can be deployed via Coolify without a manual .env step.
 *
 * The GetStocks /api/auth/login endpoint does NOT return the token in its
 * response body (despite what their published docs claim) — it emails the
 * token to the registered account. We therefore ask the admin to paste the
 * token directly and validate it via /api/auth/profile.
 */
class InstallController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('install/wizard');
    }

    public function store(
        Request $request,
        GeneralSettings $general,
        GetStocksSettings $getstocks,
        GetStocksClient $client,
    ): RedirectResponse {
        $data = $request->validate([
            'admin_name' => ['required', 'string', 'max:120'],
            'admin_email' => ['required', 'email', 'max:160'],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
            'brand_name' => ['nullable', 'string', 'max:120'],
            'support_email' => ['nullable', 'email', 'max:160'],
            'getstocks_email' => ['required', 'email'],
            'getstocks_token' => ['required', 'string', 'min:20'],
            // Password is kept optional for documentation/future re-auth flows.
            'getstocks_password' => ['nullable', 'string'],
        ]);

        // 1) Persist the GetStocks credentials BEFORE calling the client so that
        //    the client (which reads from settings) picks them up.
        $getstocks->email = $data['getstocks_email'];
        $getstocks->token = $data['getstocks_token'];
        if (! empty($data['getstocks_password'])) {
            $getstocks->password = $data['getstocks_password'];
        }
        $getstocks->save();

        // 2) Validate the token by calling /api/auth/profile.
        try {
            $client->profile();
        } catch (GetStocksException $e) {
            // Roll back the saved token so the wizard stays open.
            $getstocks->token = null;
            $getstocks->save();
            throw ValidationException::withMessages([
                'getstocks_token' => 'Token inválido ou expirado: '.$e->getMessage(),
            ]);
        }

        // 3) Create admin user + role.
        DB::transaction(function () use ($data, $general) {
            $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
            Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

            $admin = User::create([
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => Hash::make($data['admin_password']),
                'email_verified_at' => now(),
            ]);

            $admin->assignRole($adminRole);

            $general->brand_name = $data['brand_name'] ?: $general->brand_name;
            $general->support_email = $data['support_email'] ?: $general->support_email;
            $general->installed = true;
            $general->save();
        });

        // 4) Best-effort: trigger an initial provider sync in the background.
        SyncProvidersJob::dispatch();

        return redirect()->route('login')->with('status', 'Instalação concluída. Faça login com sua conta de admin.');
    }
}
