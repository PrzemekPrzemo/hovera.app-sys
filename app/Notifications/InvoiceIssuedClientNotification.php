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
            ->subject(__('notifications.invoice_issued.subject', [
                'kind' => $this->kindLabel,
                'number' => $this->invoiceNumber,
                'tenant' => $this->tenantName,
            ]))
            ->greeting(__('notifications.common.greeting'))
            ->line(__('notifications.invoice_issued.line_intro', [
                'kind' => $this->kindLabel,
                'number' => $this->invoiceNumber,
                'tenant' => $this->tenantName,
            ]))
            ->line('**'.__('notifications.common.field.issued_at').':** '.$this->issuedAt->format('Y-m-d'))
            ->line('**'.__('notifications.common.field.gross_amount').':** '.$this->totalFormatted);

        if ($this->dueAt) {
            $msg->line('**'.__('notifications.common.field.due_date').':** '.$this->dueAt->format('Y-m-d'));
        }

        $msg->action(
            $this->canPayOnline
                ? __('notifications.invoice_issued.action_pay')
                : __('notifications.invoice_issued.action_view'),
            $this->publicUrl,
        );

        if (! $this->canPayOnline) {
            $msg->line(__('notifications.invoice_issued.line_offline_payment'));
        }

        return $msg
            ->line(__('notifications.invoice_issued.line_thanks'))
            ->salutation(__('notifications.common.salutation_prefix').$this->tenantName);
    }
}
