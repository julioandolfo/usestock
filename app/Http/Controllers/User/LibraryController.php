<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\DownloadRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LibraryController extends Controller
{
    public function index(Request $request): Response
    {
        $query = DownloadRequest::query()
            ->where('user_id', $request->user()->id)
            ->where('status', DownloadRequest::STATUS_READY)
            ->whereNotNull('storage_path')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });

        if ($search = $request->string('q')->trim()->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('item_name', 'ilike', "%{$search}%")
                    ->orWhere('file_name', 'ilike', "%{$search}%");
            });
        }

        if ($provider = $request->string('provider')->toString()) {
            $query->where('provider_slug', $provider);
        }

        $items = $query->latest('ready_at')->paginate(24)->withQueryString();

        return Inertia::render('library/index', [
            'items' => $items,
            'filters' => $request->only(['q', 'provider']),
        ]);
    }
}
