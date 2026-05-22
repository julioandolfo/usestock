<?php

namespace App\Jobs;

use App\Models\DownloadRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class CleanExpiredDownloadsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): void
    {
        DownloadRequest::query()
            ->where('status', DownloadRequest::STATUS_READY)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->whereNotNull('storage_path')
            ->chunkById(200, function ($downloads) {
                foreach ($downloads as $download) {
                    try {
                        $disk = Storage::disk($download->storage_disk ?: 'downloads');
                        if ($disk->exists($download->storage_path)) {
                            $disk->delete($download->storage_path);
                        }
                    } catch (\Throwable $e) {
                        // log and continue — never let one bad file block cleanup
                    }

                    $download->update([
                        'status' => DownloadRequest::STATUS_EXPIRED,
                        'storage_path' => null,
                    ]);
                }
            });
    }
}
