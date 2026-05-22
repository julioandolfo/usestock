<?php

use App\Models\PricingRule;
use App\Models\Provider;
use App\Services\Pricing\PricingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('falls back to default doubling when no rule exists', function () {
    $provider = Provider::create([
        'slug' => 'p',
        'name' => 'P',
        'type' => 'p_x',
        'upstream_price' => 1.0,
    ]);

    $resolver = app(PricingResolver::class);

    expect($resolver->creditsFor($provider))->toBe(2);
});

it('uses a multiplier rule when active and applies ceil', function () {
    PricingRule::create([
        'provider_id' => null,
        'strategy' => 'multiplier',
        'value' => 3.5,
        'min_credits' => 1,
        'active' => true,
    ]);

    $provider = Provider::create([
        'slug' => 'p',
        'name' => 'P',
        'type' => 'p_x',
        'upstream_price' => 0.25, // 0.25 * 3.5 = 0.875 -> ceil = 1
    ]);

    expect(app(PricingResolver::class)->creditsFor($provider))->toBe(1);
});

it('uses a fixed rule when active', function () {
    PricingRule::create([
        'provider_id' => null,
        'strategy' => 'fixed',
        'value' => 7,
        'min_credits' => 1,
        'active' => true,
    ]);

    $provider = Provider::create([
        'slug' => 'p',
        'name' => 'P',
        'type' => 'p_x',
        'upstream_price' => 1.0,
    ]);

    expect(app(PricingResolver::class)->creditsFor($provider))->toBe(7);
});

it('provider-specific rule wins over global', function () {
    $provider = Provider::create([
        'slug' => 'p',
        'name' => 'P',
        'type' => 'p_x',
        'upstream_price' => 1.0,
    ]);

    PricingRule::create(['provider_id' => null, 'strategy' => 'multiplier', 'value' => 10, 'min_credits' => 1, 'active' => true]);
    PricingRule::create(['provider_id' => $provider->id, 'strategy' => 'fixed', 'value' => 2, 'min_credits' => 1, 'active' => true]);

    expect(app(PricingResolver::class)->creditsFor($provider))->toBe(2);
});

it('respects the min_credits floor', function () {
    PricingRule::create([
        'provider_id' => null,
        'strategy' => 'multiplier',
        'value' => 0.5,
        'min_credits' => 5,
        'active' => true,
    ]);

    $provider = Provider::create([
        'slug' => 'p',
        'name' => 'P',
        'type' => 'p_x',
        'upstream_price' => 0.1,
    ]);

    expect(app(PricingResolver::class)->creditsFor($provider))->toBe(5);
});
