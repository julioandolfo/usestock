<?php

namespace App\Http\Middleware;

use App\Settings\GeneralSettings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $user = $request->user();
        $general = app(GeneralSettings::class);

        return array_merge(parent::share($request), [
            'name' => $general->brand_name ?: config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar_path' => $user->avatar_path,
                    'credits_balance' => $user->credits_balance,
                    'is_admin' => $user->hasRole('admin'),
                ] : null,
            ],
            'brand' => [
                'name' => $general->brand_name,
                'primary_color' => $general->primary_color,
                'support_email' => $general->support_email,
            ],
            'reverb' => [
                'key' => config('reverb.apps.apps.0.key'),
                'host' => config('reverb.apps.apps.0.options.host'),
                'port' => (int) config('reverb.apps.apps.0.options.port'),
                'scheme' => config('reverb.apps.apps.0.options.scheme'),
            ],
            'flash' => [
                'status' => $request->session()->get('status'),
                'error' => $request->session()->get('error'),
                'lastSubmit' => $request->session()->get('lastSubmit'),
            ],
        ]);
    }
}
