<?php

namespace App\Services\Downloads;

use App\Models\CreditTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Source-of-truth for credit movements: every change to User->credits_balance
 * MUST go through this class so we always record an audit trail.
 */
class CreditLedger
{
    /**
     * Atomically debit the user's balance. Throws if insufficient funds.
     */
    public function debit(
        User $user,
        int $amount,
        string $type,
        ?string $description = null,
        ?Model $reference = null,
        ?User $actor = null,
        array $metadata = [],
    ): CreditTransaction {
        if ($amount <= 0) {
            throw new RuntimeException('Debit amount must be positive.');
        }

        return DB::transaction(function () use ($user, $amount, $type, $description, $reference, $actor, $metadata) {
            $fresh = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($fresh->credits_balance < $amount) {
                throw new RuntimeException("Insufficient credits: required {$amount}, available {$fresh->credits_balance}.");
            }

            $fresh->decrement('credits_balance', $amount);
            $fresh->refresh();

            return CreditTransaction::create([
                'user_id' => $fresh->id,
                'actor_id' => $actor?->id,
                'type' => $type,
                'amount' => -$amount,
                'balance_after' => $fresh->credits_balance,
                'reference_type' => $reference ? $reference::class : null,
                'reference_id' => $reference?->getKey(),
                'description' => $description,
                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * Atomically credit the user's balance.
     */
    public function credit(
        User $user,
        int $amount,
        string $type,
        ?string $description = null,
        ?Model $reference = null,
        ?User $actor = null,
        array $metadata = [],
    ): CreditTransaction {
        if ($amount <= 0) {
            throw new RuntimeException('Credit amount must be positive.');
        }

        return DB::transaction(function () use ($user, $amount, $type, $description, $reference, $actor, $metadata) {
            $fresh = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $fresh->increment('credits_balance', $amount);
            $fresh->refresh();

            return CreditTransaction::create([
                'user_id' => $fresh->id,
                'actor_id' => $actor?->id,
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $fresh->credits_balance,
                'reference_type' => $reference ? $reference::class : null,
                'reference_id' => $reference?->getKey(),
                'description' => $description,
                'metadata' => $metadata,
            ]);
        });
    }
}
