<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\DownloadRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Serves a previously-downloaded file to its owner.
 *
 * The session auth guard handles "is this user allowed at all", and the query
 * scope handles "is this row theirs". No signed URL is required — accessing a
 * stranger's UUID requires guessing it and being logged in.
 */
class FileServeController extends Controller
{
    public function __invoke(Request $request, string $publicId): BinaryFileResponse
    {
        $download = DownloadRequest::query()
            ->where('user_id', $request->user()->id)
            ->where('public_id', $publicId)
            ->firstOrFail();

        abort_unless($download->isReady(), 410, 'Arquivo não está mais disponível.');

        $disk = Storage::disk($download->storage_disk ?: 'downloads');
        $relativePath = $download->storage_path;

        if (! $relativePath || ! $disk->exists($relativePath)) {
            Log::warning('FileServe: missing path on disk', [
                'download_id' => $download->id,
                'path' => $relativePath,
                'disk' => $download->storage_disk,
            ]);
            abort(404, 'Arquivo não encontrado.');
        }

        $absolutePath = $disk->path($relativePath);
        $size = is_file($absolutePath) ? filesize($absolutePath) : 0;

        if ($size === 0) {
            // The stream job died mid-write or produced a 0-byte file.
            Log::warning('FileServe: zero-byte file', [
                'download_id' => $download->id,
                'path' => $relativePath,
                'absolute' => $absolutePath,
            ]);
            abort(410, 'Arquivo corrompido. Tente baixar novamente.');
        }

        $filename = $download->file_name ?: ($download->public_id.($download->file_extension ? '.'.$download->file_extension : ''));

        $response = new BinaryFileResponse($absolutePath, 200, [
            'Content-Type' => $this->guessContentType($download->file_extension),
            'Content-Length' => (string) $size,
            // Disable nginx XSendFile so we control the stream end-to-end.
            'X-Accel-Buffering' => 'no',
        ]);

        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename,
        );

        return $response;
    }

    private function guessContentType(?string $ext): string
    {
        return match (strtolower((string) $ext)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'mov' => 'video/quicktime',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'zip' => 'application/zip',
            'rar' => 'application/vnd.rar',
            '7z' => 'application/x-7z-compressed',
            'pdf' => 'application/pdf',
            'eps' => 'application/postscript',
            'ai' => 'application/postscript',
            'psd' => 'image/vnd.adobe.photoshop',
            default => 'application/octet-stream',
        };
    }
}
