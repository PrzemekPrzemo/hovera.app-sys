<?php

declare(strict_types=1);

namespace App\Domain\Transport\Notifications;

use App\Models\Tenant\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Powiadomienie właściciela transportera, gdy klient odrzucił ofertę.
 * Idzie przez mailer 'transport'.
 */
class QuoteRejectedNotification extends Notification
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
            ->error()
            ->subject(__('transport/notify_quote_rejected.subject', ['number' => $this->quote->number]))
            ->greeting(__('transport/notify_quote_rejected.greeting'))
            ->line(__('transport/notify_quote_rejected.line', [
                'number' => $this->quote->number,
                'customer' => $this->quote->customer_name,
            ]))
            ->line(__('transport/notify_quote_rejected.outro'));
    }
}
