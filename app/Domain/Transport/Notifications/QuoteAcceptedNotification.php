<?php

declare(strict_types=1);

namespace App\Domain\Transport\Notifications;

use App\Models\Tenant\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Powiadomienie właściciela transportera, gdy klient zaakceptował ofertę.
 * Idzie przez ten sam mailer co QuoteSentNotification (`transport`).
 */
class QuoteAcceptedNotification extends Notification
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
        return (new MailMessage)
            ->mailer('transport')
            ->success()
            ->subject(__('transport/notify_quote_accepted.subject', ['number' => $this->quote->number]))
            ->greeting(__('transport/notify_quote_accepted.greeting'))
            ->line(__('transport/notify_quote_accepted.line', [
                'number' => $this->quote->number,
                'customer' => $this->quote->customer_name,
                'from' => $this->quote->pickup_address,
                'to' => $this->quote->dropoff_address,
                'date' => $this->quote->preferred_date->format('Y-m-d'),
                'gross' => number_format((float) $this->quote->gross_total, 2, ',', ' '),
                'currency' => $this->quote->currency,
            ]))
            ->line(__('transport/notify_quote_accepted.outro'));
    }
}
