<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\CreditPackage;
use App\Models\Payment;
use App\Services\Payments\MercadoPagoGateway;
use App\Settings\MercadoPagoSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(Request $request, MercadoPagoSettings $mpSettings): Response
    {
        return Inertia::render('billing/index', [
            'packages' => CreditPackage::where('active', true)->orderBy('sort_order')->get(),
            'recentPayments' => Payment::where('user_id', $request->user()->id)
                ->latest()
                ->limit(10)
                ->get(),
            'mercadopago' => [
                'enabled' => $mpSettings->enabled,
                'public_key' => $mpSettings->public_key,
                'sandbox' => $mpSettings->sandbox,
            ],
        ]);
    }

    public function checkout(
        Request $request,
        MercadoPagoGateway $gateway,
        MercadoPagoSettings $mpSettings,
    ): RedirectResponse {
        if (! $mpSettings->enabled) {
            throw ValidationException::withMessages([
                'payment' => 'Pagamentos automáticos estão desabilitados.',
            ]);
        }

        $data = $request->validate([
            'package_id' => ['required', 'integer', 'exists:credit_packages,id'],
            'method' => ['required', 'in:pix,credit_card'],
        ]);

        $package = CreditPackage::where('active', true)->findOrFail($data['package_id']);

        $payment = $gateway->createPayment(
            user: $request->user(),
            package: $package,
            method: $data['method'],
        );

        return back()->with('checkout', [
            'payment_id' => $payment->public_id,
            'preference_id' => $payment->provider_preference_id,
        ]);
    }
}
