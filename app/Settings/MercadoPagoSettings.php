<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class MercadoPagoSettings extends Settings
{
    public bool $enabled;

    public bool $sandbox;

    public ?string $access_token;

    public ?string $public_key;

    public ?string $webhook_secret;

    public string $currency;

    public array $accepted_methods;

    public static function group(): string
    {
        return 'mercadopago';
    }

    public static function encrypted(): array
    {
        return ['access_token', 'webhook_secret'];
    }
}
