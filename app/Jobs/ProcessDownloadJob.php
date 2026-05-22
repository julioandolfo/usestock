<?php

namespace App\Jobs;

use App\Events\DownloadStatusChanged;
use App\Models\DownloadRequest;
use App\Services\Downloads\CreditLedger;
use App\Services\GetStocks\GetStocksClient;
use App\Services\GetStocks\GetStocksException;
use App\Settings\GetStocksSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

/**
 * Orchestrates a single download from queued -> requesting.
 * Persists provider response and either:
 *   - Stops here if webhook is enabled (we wait for inbound callback), OR
 *   - Enqueues PollDownloadStatusJob to take over polling.
 */
class ProcessDownloadJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(public readonly int $downloadRequestId) {}

    public function uniqueId(): string
    {
        return 'download:' . $this->downloadRequestId;
    }

    public function handle(
        GetStocksClient $client,
        GetStocksSettings $settings,
        CreditLedger $ledger,
    ): void {
        $download = DownloadRequest::query()->find($this->downloadRequestId);
        if (! $download || $download->isFinalState()) {
            return;
        }

        $this->mark($download, DownloadRequest::STATUS_RESOLVING);

        try {
            // Resolve type if missing (best-effort, never blocks).
            if (empty($download->item_type)) {
                try {
                    $info = $client->getInfo($download->source_url, $download->is_premium, $download);
                    $support = $info['support'] ?? [];
                    $download->fill([
                        'provider_slug' => $support['slug'] ?? $download->provider_slug,
                        'upstream_item_id' => $support['id'] ?? $download->upstream_item_id,
                        'upstream_item_slug' => $support['itemslug'] ?? null,
                        'upstream_thumb_url' => $support['itemthumb'] ?? null,
                        'item_name' => $support['itemname'] ?? $download->item_name,
                    ]);
                    if (isset($support['type']) && is_array($support['type'])) {
                        $download->item_type = array_key_first($support['type']) ?: $download->item_type;
                    }
                    $download->save();
                } catch (GetStocksException $e) {
                    Log::warning('getinfo soft-fail: ' . $e->getMessage(), ['download' => $download->id]);
                }
            }

            $webhook = $settings->use_webhook
                ? URL::signedRoute('webhooks.getstocks', ['public_id' => $download->public_id])
                : null;

            $result = $client->getLink(
                link: $download->source_url,
                isPremium: $download->is_premium,
                type: $download->item_type,
                webhookUrl: $webhook,
                downloadRequest: $download,
            );

            $download->fill([
                'provider_slug' => $result['provSlug'] ?? $download->provider_slug,
                'upstream_item_id' => $result['itemID'] ?? $download->upstream_item_id,
                'item_type' => $result['itemType'] ?? $download->item_type,
                'is_premium' => (bool) ($result['isPremium'] ?? $download->is_premium),
                'status' => DownloadRequest::STATUS_REQUESTING,
                'upstream_response' => $result,
            ])->save();

            event(new DownloadStatusChanged($download));

            // Start polling fallback. Webhook may arrive sooner and finish the flow;
            // when it does, the poll job no-ops because state is already final.
            PollDownloadStatusJob::dispatch($download->id)->delay(now()->addSeconds(
                max(5, $settings->poll_interval_seconds)
            ));
        } catch (GetStocksException $e) {
            $this->fail($download, $e->getMessage(), $ledger);
            throw $e;
        }
    }

    private function mark(DownloadRequest $download, string $status): void
    {
        $download->status = $status;
        $download->save();
        event(new DownloadStatusChanged($download));
    }

    private function fail(DownloadRequest $download, string $reason, CreditLedger $ledger): void
    {
        $download->status = DownloadRequest::STATUS_FAILED;
        $download->failure_reason = $reason;
        $download->save();

        if ($download->credits_charged > 0) {
            $ledger->credit(
                user: $download->user,
                amount: $download->credits_charged,
                type: \App\Models\CreditTransaction::TYPE_REFUND,
                description: 'Auto refund: download failed',
                reference: $download,
            );
            $download->status = DownloadRequest::STATUS_REFUNDED;
            $download->save();
        }

        event(new DownloadStatusChanged($download));
    }
}
