<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\DownloadRequest;
use App\Models\Provider;
use App\Services\Pricing\PricingResolver;
use App\Settings\DownloadSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, PricingResolver $resolver, DownloadSettings $settings): Response
    {
        $user = $request->user();

        $recentDownloads = DownloadRequest::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit(8)
            ->get([
                'public_id', 'source_url', 'item_name', 'provider_slug', 'status',
                'file_name', 'file_size_bytes', 'failure_reason', 'ready_at',
                'created_at', 'upstream_thumb_url',
            ]);

        $libraryCount = DownloadRequest::query()
            ->where('user_id', $user->id)
            ->where('status', DownloadRequest::STATUS_READY)
            ->whereNotNull('storage_path')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->count();

        $monthDownloads = DownloadRequest::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        $providers = Provider::query()
            ->where('enabled', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Provider $p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'name' => $p->name,
                'host' => $p->host,
                'type' => $p->type,
                'resolution' => $p->resolution,
                'is_premium' => $p->is_premium,
                'credits' => $resolver->creditsFor($p),
            ])
            ->groupBy('slug')
            ->map(fn ($group) => [
                'slug' => $group->first()['slug'],
                'name' => $group->first()['name'],
                'host' => $group->first()['host'],
                'normal_credits' => $group->firstWhere('is_premium', false)['credits'] ?? null,
                'premium_credits' => $group->firstWhere('is_premium', true)['credits'] ?? null,
                'resolutions' => $group->pluck('resolution')->filter()->unique()->values(),
            ])
            ->values();

        return Inertia::render('dashboard', [
            'stats' => [
                'credits_balance' => $user->credits_balance,
                'library_count' => $libraryCount,
                'month_downloads' => $monthDownloads,
                'total_downloads' => $user->downloads_count,
            ],
            'recentDownloads' => $recentDownloads,
            'providers' => $providers,
            'limits' => [
                'bulk_max_items' => $settings->bulk_max_items,
                'file_ttl_days' => $settings->file_ttl_days,
                'max_concurrent_per_user' => $settings->max_concurrent_per_user,
            ],
        ]);
    }
}
