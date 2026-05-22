<?php

namespace App\Jobs;

use App\Models\Provider;
use App\Services\GetStocks\GetStocksClient;
use App\Services\GetStocks\GetStocksException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncProvidersJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function handle(GetStocksClient $client): void
    {
        try {
            $payload = $client->providers();
        } catch (GetStocksException $e) {
            Log::warning('SyncProvidersJob: '.$e->getMessage());

            return;
        }

        $groups = [
            'norProvider' => false,
            'preProvider' => true,
        ];

        foreach ($groups as $key => $isPremium) {
            foreach ($payload[$key] ?? [] as $row) {
                Provider::updateOrCreate(
                    [
                        'slug' => $row['provSlug'] ?? null,
                        'type' => $row['provType'] ?? null,
                    ],
                    [
                        'name' => $row['provName'] ?? ($row['provSlug'] ?? 'unknown'),
                        'host' => $row['provHost'] ?? null,
                        'logo' => $row['provLogo'] ?? null,
                        'resolution' => $row['provResolution'] ?? null,
                        'license' => $row['provLicense'] ?? null,
                        'upstream_price' => (float) ($row['provPrice'] ?? 0),
                        'upstream_price_bonus' => (float) ($row['provPriceBonus'] ?? 0),
                        'is_premium' => $isPremium,
                        'api_access' => (bool) ($row['isAPIAccess'] ?? true),
                        'synced_at' => now(),
                    ]
                );
            }
        }
    }
}
