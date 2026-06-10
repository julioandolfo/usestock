<?php

namespace App\Jobs;

use App\Events\DownloadStatusChanged;
use App\Models\CreditTransaction;
use App\Models\DownloadRequest;
use App\Services\Downloads\CreditLedger;
use App\Services\GetStocks\GetStocksClient;
use App\Services\GetStocks\GetStocksException;
use App\Settings\GetStocksSettings;
use App\Support\UpstreamErrorTranslator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Polls /api/v1/download-status on a delay loop until status=1 (ready),
 * then hands off to StreamDownloadFileJob to fetch the actual bytes.
 *
 * The job re-dispatches itself with a delay (via dispatch->delay) instead of
 * blocking the worker with sleep(), which keeps the queue responsive.
 */
class PollDownloadStatusJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $downloadRequestId) {}

    public function handle(
        GetStocksClient $client,
        GetStocksSettings $settings,
        CreditLedger $ledger,
    ): void {
        $download = DownloadRequest::query()->find($this->downloadRequestId);
        if (! $download || $download->isFinalState() || $download->status === DownloadRequest::STATUS_DOWNLOADING) {
            return;
        }

        $download->poll_attempts++;
        $download->save();

        if ($download->poll_attempts > max(5, $settings->poll_max_attempts)) {
            $this->fail($download, 'Polling timed out waiting for upstream to be ready.', $ledger);

            return;
        }

        try {
            $result = $client->downloadStatus(
                slug: $download->provider_slug ?? '',
                itemId: $download->upstream_item_id ?? '',
                isPremium: $download->is_premium,
                type: $download->item_type ?? '',
                downloadRequest: $download,
            );
        } catch (GetStocksException $e) {
            $this->fail($download, $e->getMessage(), $ledger);

            return;
        }

        $isReady = (int) ($result['status'] ?? 0) === 1
            && ! empty($result['itemDCode']);

        if ($isReady) {
            $download->fill([
                'item_d_code' => $result['itemDCode'],
                'upstream_download_link' => $result['itemDLink'] ?? null,
                'item_name' => $result['itemName'] ?? $download->item_name,
                // Upstream sometimes returns URL-encoded names ("Make%20You%20Sweat.zip");
                // decode at the boundary so Content-Disposition never breaks downstream.
                'file_name' => isset($result['itemFilename'])
                    ? rawurldecode((string) $result['itemFilename'])
                    : null,
                'file_extension' => $result['itemExt'] ?? null,
                'upstream_thumb_url' => $result['itemThumb'] ?? $download->upstream_thumb_url,
                'upstream_response' => $result,
                'status' => DownloadRequest::STATUS_DOWNLOADING,
            ])->save();
            event(new DownloadStatusChanged($download));

            StreamDownloadFileJob::dispatch($download->id);

            return;
        }

        // Not ready yet — re-poll after the configured delay.
        static::dispatch($download->id)->delay(now()->addSeconds(
            max(5, $settings->poll_interval_seconds)
        ));
    }

    private function fail(DownloadRequest $download, string $reason, CreditLedger $ledger): void
    {
        $translator = app(UpstreamErrorTranslator::class);
        Log::warning('Download polling failed', [
            'download_id' => $download->id,
            'public_id' => $download->public_id,
            'raw_reason' => $reason,
        ]);

        $download->status = DownloadRequest::STATUS_FAILED;
        $download->failure_reason = $translator->humanize($reason);
        $download->save();

        if ($download->credits_charged > 0) {
            $ledger->credit(
                user: $download->user,
                amount: $download->credits_charged,
                type: CreditTransaction::TYPE_REFUND,
                description: 'Auto refund: polling failed',
                reference: $download,
            );
            $download->status = DownloadRequest::STATUS_REFUNDED;
            $download->save();
        }

        event(new DownloadStatusChanged($download));
    }
}
