<?php

namespace App\Services\Pricing;

use App\Models\PricingRule;
use App\Models\Provider;

class PricingResolver
{
    /**
     * Compute how many internal credits a download costs for a given provider.
     *
     * Rule resolution order:
     *   1. Active rule scoped to that provider
     *   2. Active default rule (provider_id = null)
     *   3. Fallback: ceil(upstream_price * 2), min 1
     */
    public function creditsFor(Provider $provider): int
    {
        $rule = PricingRule::query()
            ->where('active', true)
            ->where(function ($q) use ($provider) {
                $q->where('provider_id', $provider->id)
                    ->orWhereNull('provider_id');
            })
            ->orderByRaw('provider_id IS NULL') // provider-specific first
            ->orderByDesc('id')
            ->first();

        $upstream = (float) ($provider->upstream_price ?? 0);

        if ($rule === null) {
            return max(1, (int) ceil($upstream * 2));
        }

        $value = match ($rule->strategy) {
            'fixed' => (int) ceil((float) $rule->value),
            'multiplier' => (int) ceil($upstream * (float) $rule->value),
            default => 1,
        };

        return max((int) $rule->min_credits, $value);
    }
}
