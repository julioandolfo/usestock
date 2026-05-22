<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\DownloadRequest;
use App\Models\Provider;
use App\Services\Downloads\DownloadOrchestrator;
use App\Services\GetStocks\GetStocksClient;
use App\Services\GetStocks\GetStocksException;
use App\Services\Pricing\PricingResolver;
use App\Settings\DownloadSettings;
use App\Support\ProviderType;
use App\Support\UpstreamErrorTranslator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

        $total = count($result['items']);
        $reused = $result['reused'];
        $queued = $total - $reused;

        return back()->with('lastSubmit', [
            'batch_id' => $result['batch']->public_id,
            'total' => $total,
            'queued' => $queued,
            'reused' => $reused,
        ]);
    }

    /**
     * Resolve thumbnails + metadata for a list of links without charging credits
     * and without queueing any download. Results are cached per (link, is_premium)
     * for 1h so previewing the same link repeatedly doesn't hammer upstream.
     */
    public function preview(
        Request $request,
        GetStocksClient $client,
        PricingResolver $resolver,
        DownloadSettings $settings,
        UpstreamErrorTranslator $translator,
    ): JsonResponse {
        $data = $request->validate([
            'links' => ['required', 'array', 'min:1', 'max:'.$settings->bulk_max_items],
            'links.*' => ['required', 'url'],
            'is_premium' => ['sometimes', 'boolean'],
        ]);

        $isPremium = (bool) ($data['is_premium'] ?? true);
        $items = [];

        foreach ($data['links'] as $link) {
            $cacheKey = 'preview:'.($isPremium ? 'pre:' : 'nor:').sha1($link);

            $items[] = Cache::remember($cacheKey, now()->addHour(), function () use ($link, $isPremium, $client, $resolver, $translator) {
                try {
                    $info = $client->getInfo($link, $isPremium);
                    $support = $info['support'] ?? [];

                    $providerSlug = $support['slug'] ?? null;
                    $typeKey = is_array($support['type'] ?? null) ? array_key_first($support['type']) : null;
                    $typeLabel = is_array($support['type'] ?? null) ? array_values($support['type'])[0] ?? null : null;

                    $provider = null;
                    if ($providerSlug && $typeKey) {
                        $provider = Provider::where('slug', $providerSlug)
                            ->where('type', $typeKey)
                            ->where('is_premium', $isPremium)
                            ->first();
                    }

                    $kind = ProviderType::describe($typeKey);

                    return [
                        'link' => $link,
                        'ok' => true,
                        'enabled' => $provider?->enabled ?? false,
                        'name' => $support['itemname'] ?? null,
                        'thumb' => $support['itemthumb'] ?? null,
                        'item_id' => $support['id'] ?? null,
                        'provider' => $providerSlug,
                        'provider_name' => $provider?->name ?? ($providerSlug ? ucfirst($providerSlug) : null),
                        'type' => $typeKey,
                        'type_label' => $kind['label'] ?? $typeLabel,
                        'is_premium' => $isPremium,
                        'credits' => $provider ? $resolver->creditsFor($provider) : null,
                    ];
                } catch (GetStocksException $e) {
                    \Illuminate\Support\Facades\Log::warning('Preview failed', [
                        'link' => $link,
                        'raw' => $e->getMessage(),
                    ]);

                    return [
                        'link' => $link,
                        'ok' => false,
                        'error' => $translator->humanize($e->getMessage()),
                    ];
                }
            });
        }

        return response()->json(['items' => $items]);
    }
}

