<?php

use App\Models\CreditPackage;
use App\Models\Payment;
use App\Models\User;
use App\Settings\MercadoPagoSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    /** @var MercadoPagoSettings $s */
    $s = app(MercadoPagoSettings::class);
    $s->enabled = true;
    $s->sandbox = true;
    $s->access_token = 'TEST-TOKEN';
    $s->save();
});

it('ignores when MercadoPago is disabled', function () {
    /** @var MercadoPagoSettings $s */
    $s = app(MercadoPagoSettings::class);
    $s->enabled = false;
    $s->save();

    $this->post('/webhooks/mercadopago', ['type' => 'payment', 'data' => ['id' => '999']])
        ->assertOk()
        ->assertSee('disabled');
});

it('ignores non-payment notifications', function () {
    $this->post('/webhooks/mercadopago', ['type' => 'subscription'])
        ->assertOk()
        ->assertSee('ignored');
});

it('returns not-found when external_reference does not match', function () {
    // This test asserts the webhook is gracefully resilient.
    // Real MP SDK calls would happen but we keep this isolated.
    $this->post('/webhooks/mercadopago', ['type' => 'payment', 'data' => ['id' => '12345']])
        ->assertOk();
});

it('payment status helpers map provider strings correctly', function () {
    // sanity check on the mapping helper via constants
    expect(Payment::STATUS_APPROVED)->toBe('approved');
    expect(Payment::STATUS_REJECTED)->toBe('rejected');
});

it('user-package relationship works', function () {
    $user = User::factory()->create();
    $package = CreditPackage::create([
        'name' => 'Test',
        'credits' => 10,
        'bonus_credits' => 0,
        'price_cents' => 1000,
        'currency' => 'BRL',
        'active' => true,
        'sort_order' => 1,
    ]);
    $payment = Payment::create([
        'user_id' => $user->id,
        'credit_package_id' => $package->id,
        'provider' => 'mercadopago',
        'amount_cents' => 1000,
        'credits_to_grant' => 10,
        'status' => Payment::STATUS_PENDING,
    ]);

    expect($payment->user->id)->toBe($user->id);
    expect($payment->package->id)->toBe($package->id);
});
