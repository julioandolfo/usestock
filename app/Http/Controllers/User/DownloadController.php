<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessDownloadJob;
use App\Models\CreditTransaction;
use App\Models\DownloadBatch;
use App\Models\DownloadRequest;
use App\Models\Provider;
use App\Services\Downloads\CreditLedger;
use App\Services\Pricing\PricingResolver;
use App\Settings\DownloadSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    /**
     * Accepts one or more links (bulk supported up to the configured cap).
     * Reserves credits up-front so the user can't queue more than they can pay for.
     */
    public function store(
        Request $request,
        PricingResolver $pricing,
        CreditLedger $ledger,
        DownloadSettings $settings,
    ): RedirectResponse {
        $data = $request->validate([
            'links' => ['required', 'array', 'min:1', 'max:' . $settings->bulk_max_items],
            'links.*' => ['required', 'url'],
            'is_premium' => ['sometimes', 'boolean'],
            'zip' => ['sometimes', 'boolean'],
        ]);

        $isPremium = (bool) ($data['is_premium'] ?? true);
        $user = $request->user();

        // Provider-less initial cost estimate: we'll refine it after getinfo,
        // but to lock funds we use the default rule via PricingResolver against
        // a synthetic Provider with upstream_price=0 (= min_credits).
        $perItemEstimate = $pricing->creditsFor(new Provider(['upstream_price' => 0]));
        $totalEstimate = $perItemEstimate * count($data['links']);

        if ($user->credits_balance < $totalEstimate) {
            throw ValidationException::withMessages([
                'links' => sprintf('Créditos insuficientes (estimado %d, disponível %d).', $totalEstimate, $user->credits_balance),
            ]);
        }

        $batch = DB::transaction(function () use ($data, $user, $perItemEstimate, $ledger, $isPremium) {
            $batch = DownloadBatch::create([
                'user_id' => $user->id,
                'total_items' => count($data['links']),
                'status' => 'pending',
                'zip_requested' => (bool) ($data['zip'] ?? false),
            ]);

            foreach ($data['links'] as $link) {
                $download = DownloadRequest::create([
                    'user_id' => $user->id,
                    'batch_id' => $batch->id,
                    'source_url' => $link,
                    'is_premium' => $isPremium,
                    'status' => DownloadRequest::STATUS_QUEUED,
                    'credits_charged' => $perItemEstimate,
                    'user_ip' => request()->ip(),
                ]);

                $ledger->debit(
                    user: $user,
                    amount: $perItemEstimate,
                    type: CreditTransaction::TYPE_DOWNLOAD_CHARGE,
                    description: 'Hold for download',
                    reference: $download,
                );

                ProcessDownloadJob::dispatch($download->id);
            }

            return $batch;
        });

        return back()->with('status', "Lote #{$batch->public_id} enfileirado.");
    }
}
