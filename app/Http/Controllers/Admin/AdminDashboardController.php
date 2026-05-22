<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CreditTransaction;
use App\Models\DownloadRequest;
use App\Models\Payment;
use App\Models\User;
use App\Services\GetStocks\GetStocksClient;
use App\Services\GetStocks\GetStocksException;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __invoke(GetStocksClient $client): Response
    {
        $today = now()->startOfDay();
        $thisMonth = now()->startOfMonth();

        $balance = null;
        $balanceError = null;
        try {
            $balance = $client->balance();
        } catch (GetStocksException $e) {
            $balanceError = $e->getMessage();
        }

        return Inertia::render('admin/dashboard', [
            'metrics' => [
                'users_total' => User::count(),
                'users_active_7d' => User::where('last_seen_at', '>=', now()->subDays(7))->count(),
                'downloads_today' => DownloadRequest::where('created_at', '>=', $today)->count(),
                'downloads_month' => DownloadRequest::where('created_at', '>=', $thisMonth)->count(),
                'downloads_failed_today' => DownloadRequest::where('created_at', '>=', $today)
                    ->whereIn('status', [DownloadRequest::STATUS_FAILED, DownloadRequest::STATUS_REFUNDED])
                    ->count(),
                'revenue_month_cents' => (int) Payment::where('status', Payment::STATUS_APPROVED)
                    ->where('paid_at', '>=', $thisMonth)
                    ->sum('amount_cents'),
                'credits_spent_today' => (int) abs((int) CreditTransaction::where('type', CreditTransaction::TYPE_DOWNLOAD_CHARGE)
                    ->where('created_at', '>=', $today)
                    ->sum('amount')),
            ],
            'upstream' => [
                'balance' => $balance,
                'error' => $balanceError,
            ],
            'topProviders' => DownloadRequest::query()
                ->select('provider_slug', DB::raw('count(*) as total'))
                ->where('created_at', '>=', now()->subDays(30))
                ->whereNotNull('provider_slug')
                ->groupBy('provider_slug')
                ->orderByDesc('total')
                ->limit(8)
                ->get(),
            'recentDownloads' => DownloadRequest::with('user:id,name,email')
                ->latest()
                ->limit(15)
                ->get(),
        ]);
    }
}
