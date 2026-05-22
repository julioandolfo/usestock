<?php

namespace App\Http\Middleware;

use App\Settings\DownloadSettings;
use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ThrottleDownloads
{
    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly DownloadSettings $settings,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $key = 'downloads:'.$user->id;
        $perHour = max(1, $this->settings->rate_limit_per_hour);

        if ($this->limiter->tooManyAttempts($key, $perHour)) {
            $retryAfter = $this->limiter->availableIn($key);
            throw new ThrottleRequestsException(
                "Rate limit excedido. Tente novamente em {$retryAfter}s."
            );
        }

        $this->limiter->hit($key, 3600);

        return $next($request);
    }
}
