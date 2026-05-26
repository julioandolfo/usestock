<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\DownloadRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Serves a previously-downloaded file to its owner.
 *
 * Error paths intentionally return text/plain (not HTML) because this route
 * is invoked through <a download>, and the browser saves whatever the
 * response body is using the link's download attribute. Returning HTML on
 * abort() would land in the user's Downloads folder as "file.html", which is
 * a horrible UX. text/plain at least gets saved as "file.txt" with the
 * actual error message inside.
 */
class FileServeController extends Controller
{
    public function __invoke(Request $request, string $publicId): BinaryFileResponse|Response
    {
        $download = DownloadRequest::query()
            ->where('user_id', $request->user()->id)
            ->where('public_id', $publicId)
            ->firstOrFail();

        if (! $download->isReady()) {
            return $this->errorResponse(410, 'Arquivo não está mais disponível.');
        }

        $disk = Storage::disk($download->storage_disk ?: 'downloads');
        $relativePath = $download->storage_path;

        if (! $relativePath || ! $disk->exists($relativePath)) {
            // Rich diagnostic so we can tell a cross-container volume issue
            // (worker wrote the file, web can't see it) apart from a genuine
            // deletion. Logs the host, the absolute path we looked at, whether
            // the parent dir exists, and a sample of what IS in that dir.
            $absolute = $relativePath ? $disk->path($relativePath) : null;
            $dir = $absolute ? dirname($absolute) : null;
            Log::warning('FileServe: file not found on this container', [
                'download_id' => $download->id,
                'public_id' => $download->public_id,
                'hostname' => gethostname(),
                'relative_path' => $relativePath,
                'absolute_path' => $absolute,
                'dir_exists' => $dir ? is_dir($dir) : null,
                'dir_sample' => ($dir && is_dir($dir)) ? array_slice(array_values(array_diff(scandir($dir) ?: [], ['.', '..'])), 0, 10) : [],
            ]);

            // IMPORTANT: do NOT mutate the row to expired here. If the cause is
            // a volume that isn't shared between worker and web (or a not-yet
            // persistent volume), the file may actually still exist elsewhere
            // and destroying the row would lose it permanently. Genuine TTL
            // expiry is handled by CleanExpiredDownloadsJob. We just report.
            return $this->errorResponse(404, 'Arquivo temporariamente indisponível. Se persistir, refaça o download.');
        }

        $absolutePath = $disk->path($relativePath);
        $size = is_file($absolutePath) ? filesize($absolutePath) : 0;

        if ($size === 0) {
            Log::warning('FileServe: zero-byte file', [
                'download_id' => $download->id,
                'path' => $relativePath,
            ]);

            return $this->errorResponse(410, 'Arquivo corrompido. Tente baixar novamente.');
        }

        $filename = $download->file_name
            ?: ($download->public_id.($download->file_extension ? '.'.$download->file_extension : ''));

        $download->forceFill([
            'served_count' => $download->served_count + 1,
            'last_served_at' => now(),
        ])->saveQuietly();

        $response = new BinaryFileResponse($absolutePath, 200, [
            'Content-Type' => $this->guessContentType($download->file_extension, $absolutePath),
            'Content-Length' => (string) $size,
            'X-Accel-Buffering' => 'no',
            'Cache-Control' => 'private, no-store',
        ]);

        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename,
            // Sanitised ASCII fallback for old browsers that can't parse RFC 5987.
            $this->asciiFallback($filename),
        );

        return $response;
    }

    private function errorResponse(int $status, string $message): Response
    {
        return response($message, $status, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-store',
            // Tell the browser to display, not save — so the user sees the
            // message instead of getting a mysterious "file.txt" in Downloads.
            'Content-Disposition' => 'inline',
        ]);
    }

    private function asciiFallback(string $filename): string
    {
        $sanitised = preg_replace('/[^\x20-\x7E]+/', '_', $filename) ?: 'download';

        return trim($sanitised, '_') ?: 'download';
    }

    private function guessContentType(?string $ext, ?string $path = null): string
    {
        $mime = match (strtolower((string) $ext)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'tif', 'tiff' => 'image/tiff',
            'bmp' => 'image/bmp',
            'mp4', 'm4v' => 'video/mp4',
            'webm' => 'video/webm',
            'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo',
            'mkv' => 'video/x-matroska',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'flac' => 'audio/flac',
            'aac' => 'audio/aac',
            'zip' => 'application/zip',
            'rar' => 'application/vnd.rar',
            '7z' => 'application/x-7z-compressed',
            'tar' => 'application/x-tar',
            'gz' => 'application/gzip',
            'pdf' => 'application/pdf',
            'eps' => 'application/postscript',
            'ai' => 'application/postscript',
            'ps' => 'application/postscript',
            'psd' => 'image/vnd.adobe.photoshop',
            'indd' => 'application/x-indesign',
            'sketch' => 'application/octet-stream',
            'fig' => 'application/octet-stream',
            'xd' => 'application/octet-stream',
            default => null,
        };

        if ($mime !== null) {
            return $mime;
        }

        if ($path !== null && function_exists('mime_content_type')) {
            $detected = @mime_content_type($path);
            if ($detected) {
                return $detected;
            }
        }

        return 'application/octet-stream';
    }
}
