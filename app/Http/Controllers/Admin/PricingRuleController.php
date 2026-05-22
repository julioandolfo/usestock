<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PricingRule;
use App\Models\Provider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PricingRuleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/pricing/index', [
            'rules' => PricingRule::with('provider:id,name,slug')->orderByRaw('provider_id IS NULL')->orderBy('id')->get(),
            'providers' => Provider::orderBy('name')->get(['id', 'name', 'slug', 'is_premium']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'provider_id' => ['nullable', 'exists:providers,id'],
            'strategy' => ['required', 'in:fixed,multiplier'],
            'value' => ['required', 'numeric', 'min:0'],
            'min_credits' => ['required', 'integer', 'min:1'],
            'active' => ['sometimes', 'boolean'],
        ]);

        PricingRule::create($data + ['active' => $data['active'] ?? true]);

        return back()->with('status', 'Regra criada.');
    }

    public function update(Request $request, PricingRule $rule): RedirectResponse
    {
        $data = $request->validate([
            'strategy' => ['sometimes', 'in:fixed,multiplier'],
            'value' => ['sometimes', 'numeric', 'min:0'],
            'min_credits' => ['sometimes', 'integer', 'min:1'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $rule->update($data);

        return back()->with('status', 'Regra atualizada.');
    }

    public function destroy(PricingRule $rule): RedirectResponse
    {
        $rule->delete();

        return back()->with('status', 'Regra removida.');
    }
}
