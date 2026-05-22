<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class DownloadSettings extends Settings
{
    public int $file_ttl_days;

    public int $max_concurrent_per_user;

    public int $rate_limit_per_hour;

    public int $bulk_max_items;

    public int $signed_url_ttl_minutes;

    public bool $auto_refund_on_failure;

    public static function group(): string
    {
        return 'downloads';
    }

    public static function encrypted(): array
    {
        return [];
    }
}
