<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\DownloadRequest;
use App\Services\Downloads\DownloadOrchestrator;
use App\Settings\DownloadSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DownloadController extends Controller
{
    public function index(Request $request): Response
    {
        $downloads = DownloadRequest::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return Inertia::render('downloads/index', [
            'downloads' => $downloads,
        ]);
    }

    public function show(string $publicId, Request $request): Response
    {
        $download = DownloadRequest::query()
            ->where('user_id', $request->user()->id)
            ->where('public_id', $publicId)
            ->firstOrFail();

        return Inertia::render('downloads/show', [
            'download' => $download,
        ]);
    }

    public function store(
        Request $request,
        DownloadOrchestrator $orchestrator,
        DownloadSettings $settings,
    ): RedirectResponse {
        $data = $request->validate([
            'links' => ['required', 'array', 'min:1', 'max:'.$settings->bulk_max_items],
            'links.*' => ['required', 'url'],
            'is_premium' => ['sometimes', 'boolean'],
            'zip' => ['sometimes', 'boolean'],
        ]);

        try {
            $result = $orchestrator->submit(
                user: $request->user(),
                links: $data['links'],
                isPremium: (bool) ($data['is_premium'] ?? true),
                zip: (bool) ($data['zip'] ?? false),
            );
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['links' => $e->getMessage()]);
        }

        $msg = "Lote #{$result['batch']->public_id} enfileirado com ".count($result['items']).' item(s)';
        if ($result['reused'] > 0) {
            $msg .= " ({$result['reused']} reaproveitado(s) sem cobrança)";
        }

        return back()->with('status', $msg);
    }
}
