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

            // Upstream sometimes returns 200 with an HTML error page or a JSON
            // error envelope (captcha, link unsupported, expired token, etc.).
            // Detecting that here prevents writing an HTML page to disk and
            // serving it later as a bogus "file.html" download.
            $upstreamType = strtolower((string) $response->header('Content-Type'));
            if (
                str_contains($upstreamType, 'text/html')
                || str_contains($upstreamType, 'application/json')
                || str_contains($upstreamType, 'text/plain')
            ) {
                $this->fail($download, 'Upstream returned a non-file response ('.$upstreamType.').', $ledger);

                return;
            }

            $body = $response->toPsrResponse()->getBody();
            $bytes = 0;

            // Make sure the parent directory exists, then truncate the destination.
            $disk->put($relativePath, '');
            $absolutePath = $disk->path($relativePath);
            $handle = fopen($absolutePath, 'wb');
            if ($handle === false) {
                $this->fail($download, 'Unable to open destination file for writing.', $ledger);

                return;
            }

            $firstChunkChecked = false;
            try {
                while (! $body->eof()) {
                    $chunk = $body->read(1024 * 1024);
                    if ($chunk === '') {
                        continue;
                    }
                    // Fallback magic-byte sniff: some providers don't set a sane
                    // Content-Type, so peek at the first bytes for HTML/JSON.
                    if (! $firstChunkChecked) {
                        $firstChunkChecked = true;
                        $head = ltrim(substr($chunk, 0, 256));
                        $lower = strtolower($head);
                        if (
                            str_starts_with($lower, '<!doctype html')
                            || str_starts_with($lower, '<html')
                            || str_starts_with($head, '{"')
                            || str_starts_with($head, '{ "')
                        ) {
                            fclose($handle);
                            @unlink($absolutePath);
                            $this->fail($download, 'Upstream returned an HTML/JSON body instead of a file.', $ledger);

                            return;
                        }
                    }
                    $written = fwrite($handle, $chunk);
                    if ($written === false) {
                        throw new \RuntimeException('fwrite failed mid-stream');
                    }
                    $bytes += $written;
                }
                fflush($handle);
            } finally {
                if (is_resource($handle)) {
                    fclose($handle);
                }
            }

            // Sanity check: if upstream gave us nothing, treat it as a failure
            // instead of marking READY with a 0-byte file (which causes Chrome's
            // "Falha - Arquivo incompleto" on the user side).
            if ($bytes === 0 || ! file_exists($absolutePath) || filesize($absolutePath) === 0) {
                @unlink($absolutePath);
                $this->fail($download, 'Upstream returned an empty file.', $ledger);

                return;
            }

            // Video formats are always wrapped in a ZIP so the browser never
            // tries to preview them inline and the user always gets a clean
            // archive instead of a raw video file.
            if ($this->shouldWrapInZip($download)) {
                [$relativePath, $filename, $bytes] = $this->wrapInZip(
                    $disk,
                    $absolutePath,
                    $relativePath,
                    $filename,
                );
                $download->file_extension = 'zip';
            }

            // The worker may run as root while PHP-FPM serves as www-data.
            // Force the final file to 0644 + every dir up to the downloads
            // root to 0755 so the web process can traverse + read regardless
            // of the writer's umask/owner. Without this the file is 0600/0700
            // root-only and the serve route reports "file not found".
            $this->makeReadable($disk, $disk->path($relativePath));

            $download->fill([
                'storage_path' => $relativePath,
                'file_name' => $filename,
                'file_size_bytes' => $bytes,
                'status' => DownloadRequest::STATUS_READY,
                'ready_at' => now(),
                'completed_at' => now(),
                'expires_at' => now()->addDays(max(1, $settings->file_ttl_days)),
            ])->save();

            // Diagnostic: record where (host + absolute path) the worker wrote
            // the file. If FileServeController later logs a "not found" for the
            // same relative path on a different host, that's a smoking gun for
            // a volume that isn't shared between worker and web containers.
            \Illuminate\Support\Facades\Log::info('Download stored', [
                'download_id' => $download->id,
                'public_id' => $download->public_id,
                'hostname' => gethostname(),
                'relative_path' => $relativePath,
                'absolute_path' => $disk->path($relativePath),
                'bytes' => $bytes,
            ]);

            event(new DownloadStatusChanged($download));

            $this->maybeFinalizeBatch($download);
        } catch (\Throwable $e) {
            $this->fail($download, 'Stream failed: '.$e->getMessage(), $ledger);
            throw $e;
        }
    }

    private function fail(DownloadRequest $download, string $reason, CreditLedger $ledger): void
    {
        $translator = app(\App\Support\UpstreamErrorTranslator::class);
        \Illuminate\Support\Facades\Log::warning('Download stream failed', [
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

    /**
     * Make the stored file world-readable and every ancestor directory up to
     * the disk root traversable, so PHP-FPM (www-data) can serve a file that
     * the queue worker may have written as root.
     */
    private function makeReadable(\Illuminate\Contracts\Filesystem\Filesystem $disk, string $absoluteFile): void
    {
        @chmod($absoluteFile, 0644);

        $root = rtrim($disk->path(''), '/');
        $dir = dirname($absoluteFile);
        // Walk up from the file's directory to the downloads root.
        while ($dir && str_starts_with($dir, $root) && strlen($dir) >= strlen($root)) {
            @chmod($dir, 0755);
            if ($dir === $root) {
                break;
            }
            $dir = dirname($dir);
        }
    }

    /**
     * Whether the just-downloaded file is a video that we want to ship
     * wrapped in a ZIP instead of raw. Triggers off the file extension —
     * the upstream provider always names video downloads with the right
     * suffix (`.mp4`, `.mov`).
     */
    private function shouldWrapInZip(DownloadRequest $download): bool
    {
        $ext = strtolower((string) $download->file_extension);

        return in_array($ext, ['mp4', 'mov', 'm4v', 'avi', 'mkv', 'webm', 'mpg', 'mpeg'], true);
    }

    /**
     * Repack the file on disk as a ZIP, swap the storage_path / file_name
     * with the archive, and return the new (path, name, size) tuple. The
     * original raw video is removed.
     */
    private function wrapInZip(
        \Illuminate\Contracts\Filesystem\Filesystem $disk,
        string $sourceAbsolute,
        string $sourceRelative,
        string $originalName,
    ): array {
        $base = pathinfo($originalName, PATHINFO_FILENAME) ?: 'video';
        $relativeZip = pathinfo($sourceRelative, PATHINFO_DIRNAME).'/'.$base.'.zip';

        // Ensure the directory exists and the zip path is fresh.
        $disk->put($relativeZip, '');
        $absoluteZip = $disk->path($relativeZip);
        @unlink($absoluteZip);

        $zip = new \ZipArchive;
        if ($zip->open($absoluteZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            \Illuminate\Support\Facades\Log::warning('Failed to open ZipArchive for video wrap', [
                'source' => $sourceRelative,
                'target' => $relativeZip,
            ]);

            // Fall back to serving the raw file if zipping fails — better
            // than blocking the download entirely.
            return [$sourceRelative, $originalName, filesize($sourceAbsolute) ?: 0];
        }

        // STORE method: don't recompress (videos are already compressed and
        // recompression would burn CPU for ~0% gain).
        $zip->addFile($sourceAbsolute, $originalName);
        $zip->setCompressionName($originalName, \ZipArchive::CM_STORE);
        $zip->close();

        // Verify the zip was written before deleting the original.
        if (! file_exists($absoluteZip) || filesize($absoluteZip) === 0) {
            \Illuminate\Support\Facades\Log::warning('Generated zip is empty, keeping raw file', [
                'source' => $sourceRelative,
            ]);

            return [$sourceRelative, $originalName, filesize($sourceAbsolute) ?: 0];
        }

        @unlink($sourceAbsolute);

        return [$relativeZip, $base.'.zip', filesize($absoluteZip)];
    }
}
