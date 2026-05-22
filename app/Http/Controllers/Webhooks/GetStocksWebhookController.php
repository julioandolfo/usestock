<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\PollDownloadStatusJob;
use App\Models\DownloadRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Inbound webhook for GetStocks `getlink(...webhook=...)` notifications.
 *
 * The route is protected by Laravel's `signed` middleware (HMAC over query),
 * so we don't need extra shared-secret validation — the URL itself is the proof.
 */
class GetStocksWebhookController extends Controller
{
    public function __invoke(Request $request, string $public_id): Response
    {
        $download = DownloadRequest::query()->where('public_id', $public_id)->first();
        if (! $download || $download->isFinalState()) {
            return response('ok', 200);
        }

        // We don't trust the body; kick off a single status poll which will
        // fetch the final itemDCode + sizes and progress the state machine.
        PollDownloadStatusJob::dispatch($download->id);

        return response('ok', 200);
    }
}
