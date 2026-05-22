<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CreditPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreditPackageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/packages/index', [
            'packages' => CreditPackage::orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        CreditPackage::create($data);

        return back()->with('status', 'Pacote criado.');
    }

    public function update(Request $request, CreditPackage $package): RedirectResponse
    {
        $package->update($this->validated($request, $package));

        return back()->with('status', 'Pacote atualizado.');
    }

    public function destroy(CreditPackage $package): RedirectResponse
    {
        $package->delete();

        return back()->with('status', 'Pacote removido.');
    }

    private function validated(Request $request, ?CreditPackage $package = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'credits' => ['required', 'integer', 'min:1'],
            'bonus_credits' => ['required', 'integer', 'min:0'],
            'price_cents' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'featured' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer'],
        ]);
    }
}
