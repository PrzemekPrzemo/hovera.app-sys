<?php

declare(strict_types=1);

namespace App\Domain\Transport\Notifications;

use App\Models\Central\TransportLead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Powiadomienie do transportera, który NIE wygrał lead'a — klient wybrał
 * innego dostawcę. Mailer 'transport'.
 *
 * Treść celowo grzeczna i krótka — nie chcemy zniechęcać do dalszego
 * uczestnictwa w marketplace. Jeden lead przegrany ≠ koniec relacji.
 */
class LeadClosedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly TransportLead $lead,
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
            ->subject(__('transport/notify_lead_closed.subject'))
            ->greeting(__('transport/notify_lead_closed.greeting'))
            ->line(__('transport/notify_lead_closed.intro', [
                'from' => $this->lead->pickup_address,
                'to' => $this->lead->dropoff_address,
                'date' => $this->lead->preferred_date->format('Y-m-d'),
            ]))
            ->line(__('transport/notify_lead_closed.thanks'))
            ->action(
                __('transport/notify_lead_closed.action'),
                url('/transport/leads'),
            );
    }
}
