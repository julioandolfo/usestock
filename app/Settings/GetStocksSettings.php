<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GetStocksSettings extends Settings
{
    public string $base_url;
    public ?string $email;
    public ?string $password;
    public ?string $token;
    public ?string $webhook_secret;
    public int $poll_interval_seconds;
    public int $poll_max_attempts;
    public int $request_timeout_seconds;
    public bool $use_webhook;
    public int $low_balance_threshold;

    public static function group(): string
    {
        return 'getstocks';
    }

    public static function encrypted(): array
    {
        return ['password', 'token', 'webhook_secret'];
    }
}
