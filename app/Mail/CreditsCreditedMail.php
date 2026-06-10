<?php

namespace App\Mail;

use App\Models\User;
use App\Settings\GeneralSettings;
use App\Settings\MailSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CreditsCreditedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly int $amount,
        public readonly int $balanceAfter,
        public readonly string $reason,
    ) {}

    public function envelope(): Envelope
    {
        $mailSettings = app(MailSettings::class);
        $general = app(GeneralSettings::class);

        return new Envelope(
            from: new Address($mailSettings->from_address, $mailSettings->from_name ?: ($general->brand_name ?? 'UseStock')),
            to: [new Address($this->user->email, $this->user->name)],
            subject: '+'.$this->amount.' créditos na sua conta — '.($general->brand_name ?? 'UseStock'),
        );
    }

    public function content(): Content
    {
        $general = app(GeneralSettings::class);

        return new Content(
            view: 'emails.credits-credited',
            with: [
                'user' => $this->user,
                'amount' => $this->amount,
                'balanceAfter' => $this->balanceAfter,
                'reason' => $this->reason,
                'brand' => $general->brand_name ?? 'UseStock',
                'dashboardUrl' => url(route('dashboard', absolute: false)),
            ],
        );
    }
}
