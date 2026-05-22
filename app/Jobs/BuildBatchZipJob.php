<?php

namespace App\Jobs;

use App\Models\DownloadBatch;
use App\Models\DownloadRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class BuildBatchZipJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1200;

    public function __construct(public readonly int $batchId) {}

    public function handle(): void
    {
        $batch = DownloadBatch::query()->find($this->batchId);
        if (! $batch || ! $batch->zip_requested || $batch->zip_path) {
            return;
        }

        $items = $batch->downloadRequests()
            ->where('status', DownloadRequest::STATUS_READY)
            ->whereNotNull('storage_path')
            ->get();

        if ($items->isEmpty()) {
            return;
        }

        $disk = Storage::disk('downloads');
        $relativeZip = sprintf('%d/batches/%s.zip', $batch->user_id, $batch->public_id);
        $disk->put($relativeZip, '');
        $absolute = $disk->path($relativeZip);

        $zip = new ZipArchive;
        if ($zip->open($absolute, ZipArchive::OVERWRITE) !== true) {
            return;
        }

        foreach ($items as $item) {
            $disk2 = Storage::disk($item->storage_disk ?: 'downloads');
            $source = $disk2->path($item->storage_path);
            if (is_file($source)) {
                $zip->addFile($source, $item->file_name ?: basename($item->storage_path));
            }
        }

        $zip->close();

        $batch->update([
            'zip_path' => $relativeZip,
            'zip_ready_at' => now(),
        ]);
    }
}
