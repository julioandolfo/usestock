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
 * Configures admin credentials + GetStocks credentials in DB-backed settings,
 * so the app can be deployed via Coolify without a manual .env step.
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
            'getstocks_password' => ['required', 'string'],
        ]);

        // 1) Validate GetStocks credentials by logging in. Store token.
        $getstocks->email = $data['getstocks_email'];
        $getstocks->password = $data['getstocks_password'];
        $getstocks->save();

        try {
            $client->refreshToken();
        } catch (GetStocksException $e) {
            throw ValidationException::withMessages([
                'getstocks_email' => 'GetStocks login failed: '.$e->getMessage(),
            ]);
        }

        // 2) Create admin user + role.
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

        // 3) Best-effort: trigger an initial provider sync in the background.
        SyncProvidersJob::dispatch();

        return redirect()->route('login')->with('status', 'Instalação concluída. Faça login com sua conta de admin.');
    }
}
