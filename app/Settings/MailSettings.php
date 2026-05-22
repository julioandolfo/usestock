<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class MailSettings extends Settings
{
    public string $driver;
    public ?string $resend_api_key;
    public string $from_address;
    public string $from_name;

    public static function group(): string
    {
        return 'mail';
    }

    public static function encrypted(): array
    {
        return ['resend_api_key'];
    }
}
