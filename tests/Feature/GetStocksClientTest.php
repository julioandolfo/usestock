<?php

use App\Services\GetStocks\GetStocksClient;
use App\Services\GetStocks\GetStocksException;
use App\Settings\GetStocksSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    /** @var GetStocksSettings $s */
    $s = app(GetStocksSettings::class);
    $s->base_url = 'https://getstocks.test';
    $s->email = 'demo@demo.com';
    $s->password = 'pwd';
    $s->token = 'preset-token';
    $s->save();
});

it('returns balance payload as-is', function () {
    Http::fake([
        'getstocks.test/api/v1/balance*' => Http::response([
            'email' => 'demo@demo.com',
            'bValue' => '12.5',
            'bBonus' => '0.5',
        ], 200),
    ]);

    $balance = app(GetStocksClient::class)->balance();
    expect($balance)->toMatchArray(['bValue' => '12.5', 'bBonus' => '0.5']);
});

it('re-authenticates on 401 and retries the original call', function () {
    // Wipe any preset token so the client must call /login first.
    /** @var GetStocksSettings $s */
    $s = app(GetStocksSettings::class);
    $s->token = null;
    $s->save();

    Http::fake([
        'getstocks.test/api/auth/login*' => Http::response([
            'status' => 200,
            'result' => ['access_token' => 'new-token', 'token_type' => 'bearer'],
        ], 200),
        'getstocks.test/api/v1/providers*' => Http::response([
            'status' => 200,
            'result' => ['norProvider' => [], 'preProvider' => []],
        ], 200),
    ]);

    $result = app(GetStocksClient::class)->providers();
    expect($result)->toHaveKeys(['norProvider', 'preProvider']);
    app()->forgetInstance(GetStocksSettings::class);
    expect(app(GetStocksSettings::class)->token)->toBe('new-token');
});

it('throws GetStocksException on permanent error', function () {
    Http::fake([
        'getstocks.test/api/v1/getinfo*' => Http::response([
            'status' => 400,
            'message' => ['link' => ['The link field is required.']],
        ], 400),
    ]);

    expect(fn () => app(GetStocksClient::class)->getInfo('https://example.com', true))
        ->toThrow(GetStocksException::class);
});

it('builds correct download URL with token', function () {
    $url = app(GetStocksClient::class)->downloadUrl('abc-123');
    expect($url)->toContain('/api/v1/download/abc-123');
    expect($url)->toContain('token=preset-token');
});
