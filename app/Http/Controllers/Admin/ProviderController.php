<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncProvidersJob;
use App\Models\PricingRule;
use App\Models\Provider;
use App\Services\Pricing\PricingResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProviderController extends Controller
{
    public function index(Request $request, PricingResolver $resolver): Response
    {
        $q = Provider::query()->orderBy('name');

        if ($search = $request->string('q')->trim()->toString()) {
            $q->where(function ($w) use ($search) {
                $w->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%");
            });
        }

        if ($request->filled('premium')) {
            $q->where('is_premium', $request->boolean('premium'));
        }

        $paginator = $q->paginate(40)->withQueryString();

        // Decorate each provider with its currently-effective credit cost
        // and the id of the provider-specific override rule (if any), so the
        // admin can edit costs directly from this page.
        $providers = $paginator->getCollection()->map(function (Provider $p) use ($resolver) {
            $override = PricingRule::query()
                ->where('provider_id', $p->id)
                ->where('active', true)
                ->latest('id')
                ->first();

            return array_merge($p->toArray(), [
                'effective_credits' => $resolver->creditsFor($p),
                'override_rule' => $override ? [
                    'id' => $override->id,
                    'strategy' => $override->strategy,
                    'value' => $override->value,
                    'min_credits' => $override->min_credits,
                ] : null,
            ]);
        });
        $paginator->setCollection($providers);

        return Inertia::render('admin/providers/index', [
            'providers' => $paginator,
            'filters' => $request->only(['q', 'premium']),
            'lastSyncAt' => Provider::max('synced_at'),
        ]);
    }

    public function update(Request $request, Provider $provider): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $provider->update($data);

        return back()->with('status', 'Provider atualizado.');
    }

    /**
     * Single-shot endpoint: set a fixed credit cost for this provider,
     * upserting the provider-scoped pricing rule. Pass `credits=null` to remove
     * the override (falls back to global rule).
     */
    public function setPrice(Request $request, Provider $provider): RedirectResponse
    {
        $data = $request->validate([
            'credits' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        if ($data['credits'] === null) {
            PricingRule::where('provider_id', $provider->id)->delete();

            return back()->with('status', 'Custo personalizado removido — usando regra global.');
        }

        PricingRule::updateOrCreate(
            ['provider_id' => $provider->id],
            [
                'strategy' => 'fixed',
                'value' => $data['credits'],
                'min_credits' => $data['credits'],
                'active' => true,
            ]
        );

        return back()->with('status', "Custo do {$provider->name} definido para {$data['credits']} créditos.");
    }

    public function sync(): RedirectResponse
    {
        SyncProvidersJob::dispatchSync();

        return back()->with('status', 'Sincronização concluída.');
    }
}
