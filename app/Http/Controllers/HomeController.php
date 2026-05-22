<?php

namespace App\Http\Controllers;

use App\Models\CreditPackage;
use App\Models\Provider;
use App\Settings\DownloadSettings;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(DownloadSettings $downloads): Response
    {
        // Group enabled providers by slug so each stock site shows up once,
        // even when it has both normal and premium variants in the table.
        $providers = Provider::query()
            ->where('enabled', true)
            ->orderBy('name')
            ->get(['slug', 'name', 'host', 'is_premium'])
            ->groupBy('slug')
            ->map(fn ($group) => [
                'slug' => $group->first()->slug,
                'name' => $group->first()->name,
                'host' => $group->first()->host,
                'has_premium' => $group->contains(fn ($p) => $p->is_premium),
            ])
            ->values();

        $packages = CreditPackage::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('price_cents')
            ->get(['id', 'name', 'description', 'credits', 'bonus_credits', 'price_cents', 'currency', 'featured']);

        return Inertia::render('welcome', [
            'providers' => $providers,
            'packages' => $packages,
            'limits' => [
                'bulk_max_items' => $downloads->bulk_max_items,
                'file_ttl_days' => $downloads->file_ttl_days,
            ],
        ]);
    }
}
