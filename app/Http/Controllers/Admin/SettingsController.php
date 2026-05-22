<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GetStocks\GetStocksClient;
use App\Services\GetStocks\GetStocksException;
use App\Settings\DownloadSettings;
use App\Settings\GeneralSettings;
use App\Settings\GetStocksSettings;
use App\Settings\MailSettings;
use App\Settings\MercadoPagoSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(
        GeneralSettings $general,
        GetStocksSettings $getstocks,
        MercadoPagoSettings $mp,
        MailSettings $mail,
        DownloadSettings $downloads,
    ): Response {
        return Inertia::render('admin/settings/index', [
            'general' => [
                'brand_name' => $general->brand_name,
                'support_email' => $general->support_email,
                'support_whatsapp' => $general->support_whatsapp,
                'primary_color' => $general->primary_color,
                'allow_registration' => $general->allow_registration,
                'require_email_verification' => $general->require_email_verification,
            ],
            'getstocks' => [
                'base_url' => $getstocks->base_url,
                'email' => $getstocks->email,
                // password & token never sent back to UI
                'poll_interval_seconds' => $getstocks->poll_interval_seconds,
                'poll_max_attempts' => $getstocks->poll_max_attempts,
                'request_timeout_seconds' => $getstocks->request_timeout_seconds,
                'use_webhook' => $getstocks->use_webhook,
                'low_balance_threshold' => $getstocks->low_balance_threshold,
                'has_token' => ! empty($getstocks->token),
            ],
            'mercadopago' => [
                'enabled' => $mp->enabled,
                'sandbox' => $mp->sandbox,
                'public_key' => $mp->public_key,
                'has_access_token' => ! empty($mp->access_token),
                'currency' => $mp->currency,
                'accepted_methods' => $mp->accepted_methods,
            ],
            'mail' => [
                'driver' => $mail->driver,
                'from_address' => $mail->from_address,
                'from_name' => $mail->from_name,
                'has_resend_key' => ! empty($mail->resend_api_key),
            ],
            'downloads' => [
                'file_ttl_days' => $downloads->file_ttl_days,
                'max_concurrent_per_user' => $downloads->max_concurrent_per_user,
                'rate_limit_per_hour' => $downloads->rate_limit_per_hour,
                'bulk_max_items' => $downloads->bulk_max_items,
                'signed_url_ttl_minutes' => $downloads->signed_url_ttl_minutes,
                'auto_refund_on_failure' => $downloads->auto_refund_on_failure,
            ],
        ]);
    }

    public function updateGeneral(Request $request, GeneralSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'brand_name' => ['required', 'string', 'max:120'],
            'support_email' => ['required', 'email'],
            'support_whatsapp' => ['nullable', 'string', 'max:20'],
            'primary_color' => ['required', 'string', 'max:9'],
            'allow_registration' => ['required', 'boolean'],
            'require_email_verification' => ['required', 'boolean'],
        ]);

        // Strip everything that isn't a digit so wa.me links work regardless
        // of how the admin formats the number ("(35) 99180-3209" → "5535991803209").
        if (! empty($data['support_whatsapp'])) {
            $digits = preg_replace('/\D+/', '', $data['support_whatsapp']);
            if ($digits && strlen($digits) <= 11) {
                $digits = '55'.$digits;
            }
            $data['support_whatsapp'] = $digits ?: null;
        }

        foreach ($data as $k => $v) {
            $settings->{$k} = $v;
        }
        $settings->save();

        return back()->with('status', 'Configurações gerais salvas.');
    }

    public function updateGetstocks(Request $request, GetStocksSettings $settings, GetStocksClient $client): RedirectResponse
    {
        $data = $request->validate([
            'base_url' => ['required', 'url'],
            'email' => ['required', 'email'],
            'password' => ['nullable', 'string'],
            'poll_interval_seconds' => ['required', 'integer', 'min:5', 'max:120'],
            'poll_max_attempts' => ['required', 'integer', 'min:1', 'max:100'],
            'request_timeout_seconds' => ['required', 'integer', 'min:5', 'max:300'],
            'use_webhook' => ['required', 'boolean'],
            'low_balance_threshold' => ['required', 'integer', 'min:0'],
        ]);

        $settings->base_url = $data['base_url'];
        $settings->email = $data['email'];
        if (! empty($data['password'])) {
            $settings->password = $data['password'];
            $settings->token = null; // force re-auth
        }
        $settings->poll_interval_seconds = $data['poll_interval_seconds'];
        $settings->poll_max_attempts = $data['poll_max_attempts'];
        $settings->request_timeout_seconds = $data['request_timeout_seconds'];
        $settings->use_webhook = $data['use_webhook'];
        $settings->low_balance_threshold = $data['low_balance_threshold'];
        $settings->save();

        if (! empty($data['password'])) {
            try {
                $client->refreshToken();
            } catch (GetStocksException $e) {
                throw ValidationException::withMessages(['password' => 'Falha ao autenticar: '.$e->getMessage()]);
            }
        }

        return back()->with('status', 'GetStocks configurado.');
    }

    public function updateMercadoPago(Request $request, MercadoPagoSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'sandbox' => ['required', 'boolean'],
            'access_token' => ['nullable', 'string'],
            'public_key' => ['nullable', 'string'],
            'webhook_secret' => ['nullable', 'string'],
            'currency' => ['required', 'string', 'size:3'],
            'accepted_methods' => ['required', 'array'],
            'accepted_methods.*' => ['in:pix,credit_card,debit_card,boleto'],
        ]);

        $settings->enabled = $data['enabled'];
        $settings->sandbox = $data['sandbox'];
        if (! empty($data['access_token'])) {
            $settings->access_token = $data['access_token'];
        }
        $settings->public_key = $data['public_key'] ?? null;
        if (! empty($data['webhook_secret'])) {
            $settings->webhook_secret = $data['webhook_secret'];
        }
        $settings->currency = $data['currency'];
        $settings->accepted_methods = $data['accepted_methods'];
        $settings->save();

        return back()->with('status', 'MercadoPago configurado.');
    }

    public function updateMail(Request $request, MailSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'driver' => ['required', 'in:log,resend'],
            'resend_api_key' => ['nullable', 'string'],
            'from_address' => ['required', 'email'],
            'from_name' => ['required', 'string', 'max:120'],
        ]);

        $settings->driver = $data['driver'];
        if (! empty($data['resend_api_key'])) {
            $settings->resend_api_key = $data['resend_api_key'];
        }
        $settings->from_address = $data['from_address'];
        $settings->from_name = $data['from_name'];
        $settings->save();

        return back()->with('status', 'Email configurado.');
    }

    public function updateDownloads(Request $request, DownloadSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'file_ttl_days' => ['required', 'integer', 'min:1', 'max:365'],
            'max_concurrent_per_user' => ['required', 'integer', 'min:1', 'max:50'],
            'rate_limit_per_hour' => ['required', 'integer', 'min:1', 'max:1000'],
            'bulk_max_items' => ['required', 'integer', 'min:1', 'max:500'],
            'signed_url_ttl_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'auto_refund_on_failure' => ['required', 'boolean'],
        ]);

        foreach ($data as $k => $v) {
            $settings->{$k} = $v;
        }
        $settings->save();

        return back()->with('status', 'Configurações de download salvas.');
    }
}
