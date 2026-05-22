<?php

namespace App\Jobs;

use App\Events\DownloadStatusChanged;
use App\Models\CreditTransaction;
use App\Models\DownloadRequest;
use App\Services\Downloads\CreditLedger;
use App\Services\GetStocks\GetStocksClient;
use App\Settings\DownloadSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Streams the final file from GetStocks into our local storage disk.
 * Runs out-of-band so the user request never holds open a long HTTP fetch.
 */
class StreamDownloadFileJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 600; // 10 min, large videos

    public function __construct(public readonly int $downloadRequestId) {}

    public function handle(
        GetStocksClient $client,
        DownloadSettings $settings,
        CreditLedger $ledger,
    ): void {
        $download = DownloadRequest::query()->find($this->downloadRequestId);
        if (! $download || $download->status !== DownloadRequest::STATUS_DOWNLOADING) {
            return;
        }
        if (empty($download->item_d_code)) {
            $this->fail($download, 'Missing itemDCode for stream', $ledger);

            return;
        }

        $disk = Storage::disk($download->storage_disk ?: 'downloads');

        $filename = $download->file_name ?: ($download->upstream_item_slug ?: Str::uuid()->toString()).($download->file_extension ? '.'.$download->file_extension : '');
        $relativePath = sprintf(
            '%d/%s/%s',
            $download->user_id,
            now()->format('Y/m'),
            $download->public_id.'-'.$filename
        );

        try {
            $response = $client->streamDownload($download->item_d_code);

            if ($response->failed()) {
                $this->fail($download, 'Upstream download failed: HTTP '.$response->status(), $ledger);

                return;
            }

            $body = $response->toPsrResponse()->getBody();
            $bytes = 0;

            // Write in chunks so memory stays flat for large files.
            $disk->put($relativePath, ''); // ensure directory exists / file truncated
            $absolutePath = $disk->path($relativePath);
            $handle = fopen($absolutePath, 'wb');
            try {
                while (! $body->eof()) {
                    $chunk = $body->read(1024 * 1024);
                    if ($chunk === '') {
                        continue;
                    }
                    fwrite($handle, $chunk);
                    $bytes += strlen($chunk);
                }
            } finally {
                fclose($handle);
            }

            $download->fill([
                'storage_path' => $relativePath,
                'file_name' => $filename,
                'file_size_bytes' => $bytes,
                'status' => DownloadRequest::STATUS_READY,
                'ready_at' => now(),
                'completed_at' => now(),
                'expires_at' => now()->addDays(max(1, $settings->file_ttl_days)),
            ])->save();

            event(new DownloadStatusChanged($download));

            $this->maybeFinalizeBatch($download);
        } catch (\Throwable $e) {
            $this->fail($download, 'Stream failed: '.$e->getMessage(), $ledger);
            throw $e;
        }
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
                type: CreditTransaction::TYPE_REFUND,
                description: 'Auto refund: stream failed',
                reference: $download,
            );
            $download->status = DownloadRequest::STATUS_REFUNDED;
            $download->save();
        }

        event(new DownloadStatusChanged($download));

        $this->maybeFinalizeBatch($download);
    }

    /**
     * Aggregate the parent batch counts and, when every item is in a final state,
     * mark the batch + optionally trigger the ZIP build.
     */
    private function maybeFinalizeBatch(DownloadRequest $download): void
    {
        $batch = $download->batch;
        if (! $batch) {
            return;
        }

        $items = $batch->downloadRequests()->get();
        $allFinal = $items->every(fn ($d) => $d->isFinalState());
        if (! $allFinal) {
            $batch->update([
                'status' => 'processing',
                'completed_items' => $items->where('status', DownloadRequest::STATUS_READY)->count(),
                'failed_items' => $items->whereIn('status', [
                    DownloadRequest::STATUS_FAILED,
                    DownloadRequest::STATUS_REFUNDED,
                ])->count(),
            ]);

            return;
        }

        $ok = $items->where('status', DownloadRequest::STATUS_READY)->count();
        $failed = $items->whereIn('status', [
            DownloadRequest::STATUS_FAILED,
            DownloadRequest::STATUS_REFUNDED,
        ])->count();

        $status = match (true) {
            $ok === 0 => 'failed',
            $failed === 0 => 'completed',
            default => 'partial',
        };

        $batch->update([
            'status' => $status,
            'completed_items' => $ok,
            'failed_items' => $failed,
            'total_credits_charged' => (int) $items->sum('credits_charged'),
        ]);

        if ($batch->zip_requested && $ok > 0 && ! $batch->zip_path) {
            BuildBatchZipJob::dispatch($batch->id);
        }
    }
}
