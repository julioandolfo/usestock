<?php

namespace App\Providers;

use App\Settings\MailSettings;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    /**
     * Apply runtime configuration that lives in the database-backed
     * settings table — primarily the mail driver / from-address pair so
     * the admin can switch between log / sendmail / smtp / resend from
     * the admin panel without touching env files.
     */
    public function boot(): void
    {
        try {
            $mail = app(MailSettings::class);

            $driver = $mail->driver ?: 'log';
            Config::set('mail.default', $driver);

            if ($mail->from_address) {
                Config::set('mail.from.address', $mail->from_address);
            }
            if ($mail->from_name) {
                Config::set('mail.from.name', $mail->from_name);
            }
            if ($driver === 'resend' && $mail->resend_api_key) {
                Config::set('services.resend.key', $mail->resend_api_key);
            }
        } catch (\Throwable $e) {
            // Settings table may not exist yet (initial migrations, tests
            // before the seed); ignore and keep the default mail config.
        }
    }
}
