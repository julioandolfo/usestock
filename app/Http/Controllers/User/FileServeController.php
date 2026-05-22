<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\DownloadRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a previously-downloaded file to the owner via a signed URL.
 * The actual storage path is never exposed; we stream from the private disk.
 */
class FileServeController extends Controller
{
    public function __invoke(Request $request, string $publicId): StreamedResponse
    {
        $download = DownloadRequest::query()
            ->where('user_id', $request->user()->id)
            ->where('public_id', $publicId)
            ->firstOrFail();

        abort_unless($download->isReady(), 410, 'File is no longer available.');

        $disk = Storage::disk($download->storage_disk ?: 'downloads');
        abort_unless($disk->exists($download->storage_path), 404);

        return $disk->download($download->storage_path, $download->file_name ?? $publicId);
    }
}
