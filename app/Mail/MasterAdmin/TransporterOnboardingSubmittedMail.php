<?php

declare(strict_types=1);

namespace App\Mail\MasterAdmin;

use App\Models\Central\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notyfikacja do master admin'ów po publicznej rejestracji transportera —
 * sygnał że nowa firma czeka na weryfikację dokumentów. Patrz
 * docs/TRANSPORT.md §15 + `TransporterOnboardingController`.
 */
class TransporterOnboardingSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly int $documentsUploaded,
        public readonly int $documentsRequired,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail/transporter_onboarding.subject', ['name' => $this->tenant->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.master-admin.transporter-onboarding-submitted',
            with: [
                'tenant' => $this->tenant,
                'documentsUploaded' => $this->documentsUploaded,
                'documentsRequired' => $this->documentsRequired,
                'adminUrl' => url('/'.config('hovera.admin.path', 'admin').'/transporters/'.$this->tenant->id.'/edit'),
            ],
        );
    }
}
