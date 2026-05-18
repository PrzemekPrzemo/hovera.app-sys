<?php

declare(strict_types=1);

namespace App\Domain\Transport\Notifications;

use App\Domain\Transport\Quotes\QuotePdfGenerator;
use App\Models\Tenant\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notyfikacja "oferta wysłana" dla klienta. PDF dołączony jako attachment;
 * mail idzie przez dedykowany mailer `transport` (osobny SMTP — patrz
 * docs/TRANSPORT.md §6).
 */
class QuoteSentNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Quote $quote,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $pdf = app(QuotePdfGenerator::class)->generate($this->quote);

        $message = (new MailMessage)
            ->mailer('transport')
            ->subject(__('transport/notify_quote_sent.subject', ['number' => $this->quote->number]))
            ->greeting(__('transport/notify_quote_sent.greeting', ['name' => $this->quote->customer_name]))
            ->line(__('transport/notify_quote_sent.intro'))
            ->line(__('transport/notify_quote_sent.summary', [
                'from' => $this->quote->pickup_address,
                'to' => $this->quote->dropoff_address,
                'date' => $this->quote->preferred_date->format('Y-m-d'),
                'gross' => number_format((float) $this->quote->gross_total, 2, ',', ' '),
                'currency' => $this->quote->currency,
            ]));

        if ($this->quote->valid_until) {
            $message->line(__('transport/notify_quote_sent.valid_until', [
                'date' => $this->quote->valid_until->format('Y-m-d'),
            ]));
        }

        // Link do publicznej landing page (akceptacja / odrzucenie). Wymaga
        // accept_token (gwarantowany przez QuoteResource::sendQuote) i slug
        // tenant'a z aktualnego kontekstu (TenantManager).
        $acceptUrl = $this->buildAcceptUrl();
        if ($acceptUrl !== null) {
            $message->action(__('transport/notify_quote_sent.action_accept'), $acceptUrl);
        }

        $message->line(__('transport/notify_quote_sent.outro'));
        // Stopka prawna — Hovera = pośrednik marketplace, nie przewoźnik.
        // Wymagane przez §2 Regulaminu marketplace transportowego.
        $message->line(__('transport/notify_quote_sent.disclaimer_intermediary'));

        return $message->attachData(
            data: $pdf,
            name: $this->quote->number.'.pdf',
            options: ['mime' => 'application/pdf'],
        );
    }

    private function buildAcceptUrl(): ?string
    {
        $tenant = app(\App\Tenancy\TenantManager::class)->current();
        if (! $tenant || ! $this->quote->accept_token) {
            return null;
        }

        return route('public.transport.quote', [
            'slug' => $tenant->slug,
            'token' => $this->quote->accept_token,
        ]);
    }
}
