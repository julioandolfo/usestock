<?php

namespace App\Services\Payments;

use App\Models\CreditPackage;
use App\Models\Payment;
use App\Models\User;
use App\Settings\MercadoPagoSettings;
use Illuminate\Support\Facades\Log;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;

/**
 * Thin wrapper around the MercadoPago SDK so the rest of the app can stay
 * unaware of provider specifics.
 *
 * Webhooks land in `Webhooks\MercadoPagoWebhookController` and are reconciled
 * against this `Payment` row via `provider_payment_id`.
 */
class MercadoPagoGateway
{
    public function __construct(private readonly MercadoPagoSettings $settings) {}

    public function createPayment(User $user, CreditPackage $package, string $method): Payment
    {
        $this->bootSdk();

        $payment = Payment::create([
            'user_id' => $user->id,
            'credit_package_id' => $package->id,
            'provider' => 'mercadopago',
            'method' => $method,
            'amount_cents' => $package->price_cents,
            'currency' => $package->currency,
            'credits_to_grant' => $package->totalCredits(),
            'status' => Payment::STATUS_PENDING,
            'payer_ip' => request()->ip(),
        ]);

        try {
            $client = new PreferenceClient();
            $preference = $client->create([
                'items' => [[
                    'id' => (string) $package->id,
                    'title' => $package->name,
                    'quantity' => 1,
                    'unit_price' => $package->price_cents / 100,
                    'currency_id' => $package->currency,
                ]],
                'external_reference' => $payment->public_id,
                'payment_methods' => [
                    'excluded_payment_types' => $this->excludedPaymentTypes($method),
                ],
                'notification_url' => route('webhooks.mercadopago'),
                'back_urls' => [
                    'success' => route('billing.index'),
                    'failure' => route('billing.index'),
                    'pending' => route('billing.index'),
                ],
                'metadata' => [
                    'user_id' => $user->id,
                    'package_id' => $package->id,
                ],
            ]);

            $payment->update([
                'provider_preference_id' => $preference->id ?? null,
                'provider_payload' => json_decode(json_encode($preference), true),
            ]);
        } catch (\Throwable $e) {
            Log::error('MercadoPago preference create failed: ' . $e->getMessage());
            $payment->update([
                'status' => Payment::STATUS_REJECTED,
                'failure_reason' => $e->getMessage(),
            ]);
        }

        return $payment->refresh();
    }

    private function bootSdk(): void
    {
        if (empty($this->settings->access_token)) {
            throw new \RuntimeException('MercadoPago access token not configured.');
        }

        MercadoPagoConfig::setAccessToken($this->settings->access_token);
        MercadoPagoConfig::setRuntimeEnviroment(
            $this->settings->sandbox
                ? MercadoPagoConfig::LOCAL
                : MercadoPagoConfig::SERVER
        );
    }

    private function excludedPaymentTypes(string $method): array
    {
        return match ($method) {
            'pix' => [
                ['id' => 'credit_card'],
                ['id' => 'debit_card'],
                ['id' => 'ticket'],
            ],
            'credit_card' => [
                ['id' => 'bank_transfer'],
                ['id' => 'ticket'],
            ],
            default => [],
        };
    }
}
