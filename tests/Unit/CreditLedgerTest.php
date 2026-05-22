<?php

use App\Models\CreditTransaction;
use App\Models\User;
use App\Services\Downloads\CreditLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('debits balance and writes a negative transaction', function () {
    $user = User::factory()->create(['credits_balance' => 100]);
    $ledger = app(CreditLedger::class);

    $tx = $ledger->debit($user, 30, CreditTransaction::TYPE_DOWNLOAD_CHARGE, 'test');

    expect($user->fresh()->credits_balance)->toBe(70);
    expect($tx->amount)->toBe(-30);
    expect($tx->balance_after)->toBe(70);
});

it('credits balance and writes a positive transaction', function () {
    $user = User::factory()->create(['credits_balance' => 100]);
    $ledger = app(CreditLedger::class);

    $tx = $ledger->credit($user, 50, CreditTransaction::TYPE_ADMIN_CREDIT);

    expect($user->fresh()->credits_balance)->toBe(150);
    expect($tx->amount)->toBe(50);
    expect($tx->balance_after)->toBe(150);
});

it('rejects a debit that would overdraw the balance', function () {
    $user = User::factory()->create(['credits_balance' => 10]);
    $ledger = app(CreditLedger::class);

    expect(fn () => $ledger->debit($user, 20, CreditTransaction::TYPE_DOWNLOAD_CHARGE))
        ->toThrow(RuntimeException::class, 'Insufficient credits');

    expect($user->fresh()->credits_balance)->toBe(10);
});

it('rejects non-positive amounts', function () {
    $user = User::factory()->create(['credits_balance' => 100]);
    $ledger = app(CreditLedger::class);

    expect(fn () => $ledger->debit($user, 0, CreditTransaction::TYPE_DOWNLOAD_CHARGE))
        ->toThrow(RuntimeException::class);
    expect(fn () => $ledger->credit($user, -5, CreditTransaction::TYPE_ADMIN_CREDIT))
        ->toThrow(RuntimeException::class);
});
