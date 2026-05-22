<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncProvidersJob;
use App\Models\PricingRule;
use App\Models\Provider;
use App\Services\Pricing\PricingResolver;
use App\Support\ProviderType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProviderController extends Controller
{
    public function index(Request $request, PricingResolver $resolver): Response
    {
        $q = Provider::query()->orderBy('name')->orderBy('is_premium')->orderBy('type');

        if ($search = $request->string('q')->trim()->toString()) {
            $q->where(function ($w) use ($search) {
                $w->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%");
            });
        }

        // Fetch ALL matching rows (no pagination) — rendered grouped by provider.
        $rows = $q->get();

        $decorated = $rows->map(function (Provider $p) use ($resolver) {
            $override = PricingRule::query()
                ->where('provider_id', $p->id)
                ->where('active', true)
                ->latest('id')
                ->first();

            $kind = ProviderType::describe($p->type);

            return array_merge($p->toArray(), [
                'kind' => $kind['kind'],
                'kind_label' => $kind['label'],
                'effective_credits' => $resolver->creditsFor($p),
                'override_rule' => $override ? [
                    'id' => $override->id,
                    'strategy' => $override->strategy,
                    'value' => (float) $override->value,
                    'min_credits' => $override->min_credits,
                ] : null,
            ]);
        });

        $groups = $decorated
            ->groupBy('slug')
            ->map(fn ($groupRows) => [
                'slug' => $groupRows->first()['slug'],
                'name' => $groupRows->first()['name'],
                'host' => $groupRows->first()['host'],
                'logo' => $groupRows->first()['logo'],
                'total_rows' => $groupRows->count(),
                'enabled_rows' => $groupRows->where('enabled', true)->count(),
                'rows' => $groupRows->values(),
            ])
            ->values();

        return Inertia::render('admin/providers/index', [
            'groups' => $groups,
            'filters' => $request->only(['q']),
            'lastSyncAt' => Provider::max('synced_at'),
            'totalProviders' => $rows->count(),
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
     * Toggle enable/disable for ALL types belonging to a provider slug, in one shot.
     * Useful when the admin wants to disable Shutterstock entirely instead of clicking
     * a switch per content type.
     */
    public function bulkUpdate(Request $request, string $slug): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $count = Provider::where('slug', $slug)->update(['enabled' => $data['enabled']]);

        return back()->with('status', "{$count} tipo(s) de {$slug} ".($data['enabled'] ? 'habilitado(s)' : 'desabilitado(s)').'.');
    }

    /**
     * Single-shot endpoint: set a fixed credit cost for this provider row,
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

        $kind = ProviderType::describe($provider->type);

        return back()->with('status', "Custo de {$provider->name} ({$kind['label']}) definido para {$data['credits']} créditos.");
    }

    public function sync(): RedirectResponse
    {
        SyncProvidersJob::dispatchSync();

        return back()->with('status', 'Sincronização concluída.');
    }
}
