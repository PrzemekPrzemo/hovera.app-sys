<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Central\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email do owner'a stajni po opłaceniu subskrypcji hovera (Stripe
 * webhook → onCheckoutCompleted). KSeF push i PDF-ka idą osobnym
 * jobem; tu jedynie potwierdzenie + numer faktury.
 */
class InvoicePaidNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly string $tenantName,
        public readonly string $totalFormatted,
    ) {}

    /** @return array<int,string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('billing.email.invoice_paid.subject', [
                'number' => $this->invoice->number,
            ]))
            ->view('emails.invoice-paid', [
                'invoice' => $this->invoice,
                'tenantName' => $this->tenantName,
                'totalFormatted' => $this->totalFormatted,
            ]);
    }
}
