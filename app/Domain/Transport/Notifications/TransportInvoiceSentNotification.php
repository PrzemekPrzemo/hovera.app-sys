<?php

declare(strict_types=1);

namespace App\Domain\Transport\Notifications;

use App\Domain\Transport\Invoices\TransportInvoicePdfGenerator;
use App\Models\Tenant\TransportInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Mail z fakturą do klienta (po stronie transportera). PDF jako attachment;
 * leci przez mailer 'transport' (osobny SMTP — docs/TRANSPORT.md §6).
 */
class TransportInvoiceSentNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly TransportInvoice $invoice,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $pdf = app(TransportInvoicePdfGenerator::class)->generate($this->invoice);

        return (new MailMessage)
            ->mailer('transport')
            ->subject(__('transport/notify_invoice_sent.subject', ['number' => $this->invoice->number]))
            ->greeting(__('transport/notify_invoice_sent.greeting', ['name' => $this->invoice->buyer_name]))
            ->line(__('transport/notify_invoice_sent.intro', [
                'number' => $this->invoice->number,
                'gross' => number_format($this->invoice->total_cents / 100, 2, ',', ' '),
                'currency' => $this->invoice->currency,
            ]))
            ->line(__('transport/notify_invoice_sent.due', [
                'date' => $this->invoice->due_at?->format('Y-m-d'),
            ]))
            ->line(__('transport/notify_invoice_sent.outro'))
            ->attachData(
                data: $pdf,
                name: $this->invoice->number.'.pdf',
                options: ['mime' => 'application/pdf'],
            );
    }
}
