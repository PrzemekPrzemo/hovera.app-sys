<?php

declare(strict_types=1);

namespace App\Mail\Customer;

use App\Models\Central\TransportLead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email do klienta po wypełnieniu publicznego formularza zapytania
 * o transport koni. Zawiera permanent link do portalu klienta gdzie
 * widzi swoje zapytanie + napływające oferty od przewoźników.
 *
 * Link działa bezterminowo (do momentu revoke ręcznego z admin'a).
 * Słownik tabel: transport_leads.access_slug (UUID).
 */
class TransportLeadAccessMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly TransportLead $lead,
        public readonly string $portalUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail/transport_lead_access.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.customer.transport-lead-access',
            with: [
                'lead' => $this->lead,
                'portalUrl' => $this->portalUrl,
            ],
        );
    }
}
