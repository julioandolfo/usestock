<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\CreditTransaction;
use App\Models\Payment;
use App\Services\Downloads\CreditLedger;
use App\Settings\MercadoPagoSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\MercadoPagoConfig;

/**
 * Receives MercadoPago Webhook notifications and reconciles the matching
 * Payment row. Idempotent: re-deliveries of the same payment do not double-credit.
 */
class MercadoPagoWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        MercadoPagoSettings $settings,
        CreditLedger $ledger,
    ): Response {
        if (! $settings->enabled) {
            return response('disabled', 200);
        }

        $providerPaymentId = $request->input('data.id') ?? $request->input('id');
        $topic = $request->input('type') ?? $request->input('topic');

        if ($topic !== 'payment' || ! $providerPaymentId) {
            return response('ignored', 200);
        }

        // TODO: validate `x-signature` header per MercadoPago spec using $settings->webhook_secret.

        try {
            MercadoPagoConfig::setAccessToken($settings->access_token);
            $providerPayment = (new PaymentClient)->get((int) $providerPaymentId);
        } catch (\Throwable $e) {
            Log::warning('MP webhook fetch failed: '.$e->getMessage());

            return response('lookup-failed', 200);
        }

        $externalRef = $providerPayment->external_reference ?? null;
        $payment = $externalRef
            ? Payment::where('public_id', $externalRef)->first()
            : null;

        if (! $payment) {
            return response('not-found', 200);
        }

        $newStatus = $this->mapStatus($providerPayment->status ?? 'pending');
        $alreadyApproved = $payment->status === Payment::STATUS_APPROVED;

        $payment->update([
            'provider_payment_id' => (string) $providerPayment->id,
            'status' => $newStatus,
            'provider_payload' => json_decode(json_encode($providerPayment), true),
            'method' => $providerPayment->payment_type_id ?? $payment->method,
            'paid_at' => ($newStatus === Payment::STATUS_APPROVED) ? now() : $payment->paid_at,
        ]);

        if ($newStatus === Payment::STATUS_APPROVED && ! $alreadyApproved) {
            $ledger->credit(
                user: $payment->user,
                amount: $payment->credits_to_grant,
                type: CreditTransaction::TYPE_PURCHASE,
                description: "Pagamento MercadoPago {$payment->provider_payment_id}",
                reference: $payment,
                metadata: ['method' => $payment->method],
            );
        }

        return response('ok', 200);
    }

    private function mapStatus(string $providerStatus): string
    {
        return match ($providerStatus) {
            'approved' => Payment::STATUS_APPROVED,
            'in_process', 'authorized' => Payment::STATUS_IN_PROCESS,
            'rejected' => Payment::STATUS_REJECTED,
            'cancelled' => Payment::STATUS_CANCELLED,
            'refunded', 'charged_back' => Payment::STATUS_REFUNDED,
            default => Payment::STATUS_PENDING,
        };
    }
}
