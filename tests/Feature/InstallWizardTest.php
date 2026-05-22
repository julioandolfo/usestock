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
        '*/api/auth/profile*' => Http::response([
            'status' => 200,
            'result' => ['Profile' => ['id' => 1, 'name' => 'Demo', 'email' => 'demo@demo.com']],
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
        'getstocks_token' => 'a-valid-getstocks-access-token-from-email',
    ]);

    $response->assertRedirect(route('login'));

    $admin = User::where('email', 'admin@example.com')->first();
    expect($admin)->not->toBeNull();
    expect($admin->hasRole('admin'))->toBeTrue();
    app()->forgetInstance(GeneralSettings::class);
    app()->forgetInstance(GetStocksSettings::class);
    expect(app(GeneralSettings::class)->installed)->toBeTrue();
    expect(app(GetStocksSettings::class)->token)->toBe('a-valid-getstocks-access-token-from-email');
});

it('does not install if the GetStocks token is invalid', function () {
    /** @var GeneralSettings $g */
    $g = app(GeneralSettings::class);
    $g->installed = false;
    $g->save();

    Http::fake([
        '*/api/auth/profile*' => Http::response(['message' => 'Unauthenticated.'], 401),
        '*/api/auth/login*' => Http::response(['error' => true, 'data' => 'invalid creds'], 401),
    ]);

    $this->post('/install', [
        'admin_name' => 'A',
        'admin_email' => 'a@example.com',
        'admin_password' => 'password',
        'admin_password_confirmation' => 'password',
        'getstocks_email' => 'demo@demo.com',
        'getstocks_token' => 'bad-token-that-will-be-rejected',
    ])->assertSessionHasErrors('getstocks_token');

    expect(User::where('email', 'a@example.com')->exists())->toBeFalse();
    app()->forgetInstance(GeneralSettings::class);
    app()->forgetInstance(GetStocksSettings::class);
    expect(app(GeneralSettings::class)->installed)->toBeFalse();
    expect(app(GetStocksSettings::class)->token)->toBeNull();
});
