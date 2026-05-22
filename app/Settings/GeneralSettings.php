<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $brand_name;
    public ?string $brand_logo_path;
    public string $primary_color;
    public string $support_email;
    public bool $installed;
    public bool $allow_registration;
    public bool $require_email_verification;

    public static function group(): string
    {
        return 'general';
    }

    public static function encrypted(): array
    {
        return [];
    }
}
