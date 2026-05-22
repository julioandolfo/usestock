<?php

namespace App\Services\Downloads;

use App\Jobs\ProcessDownloadJob;
use App\Models\CreditTransaction;
use App\Models\DownloadBatch;
use App\Models\DownloadRequest;
use App\Models\Provider;
use App\Models\User;
use App\Services\Pricing\PricingResolver;
use App\Settings\DownloadSettings;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Single entry-point for creating downloads.
 *
 * Encapsulates:
 *   - per-user concurrency check
 *   - re-download lookup (free if file is still on disk for the same source URL)
 *   - up-front credit hold via the ledger
 *   - batch creation when multiple links are submitted
 */
class DownloadOrchestrator
{
    public function __construct(
        private readonly PricingResolver $pricing,
        private readonly CreditLedger $ledger,
        private readonly DownloadSettings $settings,
    ) {}

    /**
     * @param  array<int, string>  $links
     * @return array{batch: DownloadBatch, items: array<int, DownloadRequest>, reused: int}
     */
    public function submit(User $user, array $links, bool $isPremium, bool $zip = false): array
    {
        if (count($links) === 0) {
            throw new RuntimeException('At least one link is required.');
        }

        $this->assertConcurrencyAvailable($user);

        $perItemEstimate = $this->pricing->creditsFor(new Provider(['upstream_price' => 0]));

        // Re-download lookup: any of these links that the user already has on disk?
        $existing = DownloadRequest::query()
            ->where('user_id', $user->id)
            ->where('status', DownloadRequest::STATUS_READY)
            ->whereNotNull('storage_path')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->whereIn('source_url', $links)
            ->get()
            ->keyBy('source_url');

        $newLinks = array_values(array_filter($links, fn ($l) => ! $existing->has($l)));
        $totalEstimate = $perItemEstimate * count($newLinks);

        if ($user->credits_balance < $totalEstimate) {
            throw new RuntimeException(sprintf(
                'Créditos insuficientes (estimado %d, disponível %d).',
                $totalEstimate,
                $user->credits_balance
            ));
        }

        return DB::transaction(function () use ($user, $links, $isPremium, $zip, $perItemEstimate, $existing) {
            $batch = DownloadBatch::create([
                'user_id' => $user->id,
                'total_items' => count($links),
                'completed_items' => $existing->count(), // re-uses count as already-done
                'status' => count($links) === $existing->count() ? 'completed' : 'pending',
                'zip_requested' => $zip,
            ]);

            $items = [];
            foreach ($links as $link) {
                if ($existing->has($link)) {
                    // Attach the existing ready download to this batch, but don't re-charge.
                    /** @var DownloadRequest $reused */
                    $reused = $existing->get($link);
                    $reused->update(['batch_id' => $batch->id]);
                    $items[] = $reused;

                    continue;
                }

                $download = DownloadRequest::create([
                    'user_id' => $user->id,
                    'batch_id' => $batch->id,
                    'source_url' => $link,
                    'is_premium' => $isPremium,
                    'status' => DownloadRequest::STATUS_QUEUED,
                    'credits_charged' => $perItemEstimate,
                    'user_ip' => request()?->ip(),
                ]);

                $this->ledger->debit(
                    user: $user,
                    amount: $perItemEstimate,
                    type: CreditTransaction::TYPE_DOWNLOAD_CHARGE,
                    description: 'Hold for download',
                    reference: $download,
                );

                ProcessDownloadJob::dispatch($download->id);
                $items[] = $download;
            }

            return [
                'batch' => $batch->fresh(),
                'items' => $items,
                'reused' => $existing->count(),
            ];
        });
    }

    private function assertConcurrencyAvailable(User $user): void
    {
        $limit = max(1, $this->settings->max_concurrent_per_user);
        $active = DownloadRequest::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                DownloadRequest::STATUS_QUEUED,
                DownloadRequest::STATUS_RESOLVING,
                DownloadRequest::STATUS_REQUESTING,
                DownloadRequest::STATUS_DOWNLOADING,
            ])
            ->count();

        if ($active >= $limit) {
            throw new RuntimeException(sprintf(
                'Limite de downloads simultâneos atingido (%d). Aguarde finalizar antes de iniciar novos.',
                $limit
            ));
        }
    }
}
