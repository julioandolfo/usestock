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

class WelcomeUserMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly ?string $plainPassword = null,
    ) {}

    public function envelope(): Envelope
    {
        $mailSettings = app(MailSettings::class);
        $general = app(GeneralSettings::class);

        return new Envelope(
            from: new Address($mailSettings->from_address, $mailSettings->from_name ?: ($general->brand_name ?? 'UseStock')),
            to: [new Address($this->user->email, $this->user->name)],
            subject: 'Bem-vindo ao '.($general->brand_name ?? 'UseStock'),
        );
    }

    public function content(): Content
    {
        $general = app(GeneralSettings::class);

        return new Content(
            view: 'emails.welcome',
            with: [
                'user' => $this->user,
                'plainPassword' => $this->plainPassword,
                'brand' => $general->brand_name ?? 'UseStock',
                'loginUrl' => url(route('login', absolute: false)),
                'supportEmail' => $general->support_email,
                'supportWhatsapp' => $general->support_whatsapp,
            ],
        );
    }
}
