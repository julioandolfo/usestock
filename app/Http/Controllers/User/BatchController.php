<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\DownloadBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BatchController extends Controller
{
    public function show(Request $request, string $publicId): Response
    {
        $batch = DownloadBatch::query()
            ->where('user_id', $request->user()->id)
            ->where('public_id', $publicId)
            ->with(['downloadRequests' => fn ($q) => $q->latest('id')])
            ->firstOrFail();

        return Inertia::render('downloads/batch', [
            'batch' => $batch,
            'items' => $batch->downloadRequests,
        ]);
    }

    public function zip(Request $request, string $publicId): StreamedResponse
    {
        $batch = DownloadBatch::query()
            ->where('user_id', $request->user()->id)
            ->where('public_id', $publicId)
            ->firstOrFail();

        abort_unless($batch->zip_path, 425, 'ZIP ainda não está pronto.');

        $disk = Storage::disk('downloads');
        abort_unless($disk->exists($batch->zip_path), 410, 'ZIP expirado.');

        return $disk->download($batch->zip_path, "{$batch->public_id}.zip");
    }
}
