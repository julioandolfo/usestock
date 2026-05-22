<?php

use App\Models\User;
use App\Settings\GeneralSettings;
use App\Settings\GetStocksSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('blocks /install once the app is installed', function () {
    // setUp sets installed=true
    $this->get('/install')->assertRedirect(route('dashboard'));
});

it('runs the wizard end-to-end and creates the admin', function () {
    /** @var GeneralSettings $g */
    $g = app(GeneralSettings::class);
    $g->installed = false;
    $g->save();

    Http::fake([
        '*/api/auth/login*' => Http::response([
            'status' => 200,
            'result' => ['access_token' => 'tok', 'token_type' => 'bearer'],
        ], 200),
    ]);

    $response = $this->post('/install', [
        'admin_name' => 'Admin',
        'admin_email' => 'admin@example.com',
        'admin_password' => 'password',
        'admin_password_confirmation' => 'password',
        'brand_name' => 'TestStock',
        'support_email' => 'support@example.com',
        'getstocks_email' => 'demo@demo.com',
        'getstocks_password' => 'pwd',
    ]);

    $response->assertRedirect(route('login'));

    $admin = User::where('email', 'admin@example.com')->first();
    expect($admin)->not->toBeNull();
    expect($admin->hasRole('admin'))->toBeTrue();
    expect(app(GeneralSettings::class)->installed)->toBeTrue();
    expect(app(GetStocksSettings::class)->token)->toBe('tok');
});

it('does not install if GetStocks login fails', function () {
    /** @var GeneralSettings $g */
    $g = app(GeneralSettings::class);
    $g->installed = false;
    $g->save();

    Http::fake([
        '*/api/auth/login*' => Http::response(['error' => true, 'data' => 'invalid creds'], 401),
    ]);

    $this->post('/install', [
        'admin_name' => 'A',
        'admin_email' => 'a@example.com',
        'admin_password' => 'password',
        'admin_password_confirmation' => 'password',
        'getstocks_email' => 'demo@demo.com',
        'getstocks_password' => 'wrong',
    ])->assertSessionHasErrors('getstocks_email');

    expect(User::where('email', 'a@example.com')->exists())->toBeFalse();
    expect(app(GeneralSettings::class)->installed)->toBeFalse();
});
