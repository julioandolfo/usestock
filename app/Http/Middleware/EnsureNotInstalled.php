<?php

namespace App\Http\Middleware;

use App\Settings\GeneralSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotInstalled
{
    public function __construct(private readonly GeneralSettings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->settings->installed) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
