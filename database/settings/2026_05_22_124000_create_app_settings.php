<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // General brand/UX
        $this->migrator->add('general.brand_name', 'UseStock');
        $this->migrator->add('general.brand_logo_path', null);
        $this->migrator->add('general.primary_color', '#0f172a');
        $this->migrator->add('general.support_email', 'support@example.com');
        $this->migrator->add('general.installed', false);
        $this->migrator->add('general.allow_registration', true);
        $this->migrator->add('general.require_email_verification', false);

        // GetStocks upstream API
        $this->migrator->add('getstocks.base_url', 'https://getstocks.net');
        $this->migrator->addEncrypted('getstocks.email', null);
        $this->migrator->addEncrypted('getstocks.password', null);
        $this->migrator->addEncrypted('getstocks.token', null);
        $this->migrator->addEncrypted('getstocks.webhook_secret', null);
        $this->migrator->add('getstocks.poll_interval_seconds', 10);
        $this->migrator->add('getstocks.poll_max_attempts', 30);
        $this->migrator->add('getstocks.request_timeout_seconds', 30);
        $this->migrator->add('getstocks.use_webhook', true);
        $this->migrator->add('getstocks.low_balance_threshold', 10);

        // MercadoPago
        $this->migrator->add('mercadopago.enabled', false);
        $this->migrator->add('mercadopago.sandbox', true);
        $this->migrator->addEncrypted('mercadopago.access_token', null);
        $this->migrator->add('mercadopago.public_key', null);
        $this->migrator->addEncrypted('mercadopago.webhook_secret', null);
        $this->migrator->add('mercadopago.currency', 'BRL');
        $this->migrator->add('mercadopago.accepted_methods', ['pix', 'credit_card']);

        // Mail (Resend by default)
        $this->migrator->add('mail.driver', 'log');
        $this->migrator->addEncrypted('mail.resend_api_key', null);
        $this->migrator->add('mail.from_address', 'no-reply@example.com');
        $this->migrator->add('mail.from_name', 'UseStock');

        // Download policy
        $this->migrator->add('downloads.file_ttl_days', 30);
        $this->migrator->add('downloads.max_concurrent_per_user', 3);
        $this->migrator->add('downloads.rate_limit_per_hour', 60);
        $this->migrator->add('downloads.bulk_max_items', 50);
        $this->migrator->add('downloads.signed_url_ttl_minutes', 5);
        $this->migrator->add('downloads.auto_refund_on_failure', true);
    }
};
