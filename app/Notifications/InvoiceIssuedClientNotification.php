<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Email do klienta po wystawieniu faktury (FV / Korekta / Proforma).
 * Zawiera link do publicznego widoku z signed URL — klient widzi
 * fakturę i może kliknąć "Zapłać teraz" (jeśli online payments
 * skonfigurowane w stajni).
 */
class InvoiceIssuedClientNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $tenantName,
        public readonly string $invoiceNumber,
        public readonly string $kindLabel,
        public readonly string $totalFormatted,
        public readonly Carbon $issuedAt,
        public readonly ?Carbon $dueAt,
        public readonly string $publicUrl,
        public readonly bool $canPayOnline,
    ) {}

    /** @return array<int,string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $msg = (new MailMessage)
            ->subject("{$this->kindLabel} {$this->invoiceNumber} — {$this->tenantName}")
            ->greeting('Cześć!')
            ->line("Wystawiliśmy {$this->kindLabel} **{$this->invoiceNumber}** ze stajni **{$this->tenantName}**.")
            ->line('**Data wystawienia:** '.$this->issuedAt->format('Y-m-d'))
            ->line('**Kwota brutto:** '.$this->totalFormatted);

        if ($this->dueAt) {
            $msg->line('**Termin płatności:** '.$this->dueAt->format('Y-m-d'));
        }

        $msg->action(
            $this->canPayOnline ? 'Zobacz fakturę i zapłać' : 'Zobacz fakturę',
            $this->publicUrl,
        );

        if (! $this->canPayOnline) {
            $msg->line('Płatność prosimy uregulować przelewem na konto stajni — szczegóły w panelu klienta.');
        }

        return $msg
            ->line('Dziękujemy!')
            ->salutation("— {$this->tenantName}");
    }
}
