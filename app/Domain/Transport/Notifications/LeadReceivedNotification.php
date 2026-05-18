<?php

declare(strict_types=1);

namespace App\Domain\Transport\Notifications;

use App\Models\Central\Tenant;
use App\Models\Central\TransportLead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Powiadomienie do transportera o nowym zapytaniu (lead). Mailer 'transport'
 * (osobny SMTP). Patrz docs/TRANSPORT.md §6.
 *
 * Treść: krótkie podsumowanie (trasa, data, liczba koni) + przycisk
 * "Zobacz zapytanie" → /transport/leads (krok 5, inbox).
 */
class LeadReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly TransportLead $lead,
        public readonly Tenant $transporter,
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
            ->subject(__('transport/notify_lead_received.subject', [
                'horses' => $this->lead->horse_count,
            ]))
            ->greeting(__('transport/notify_lead_received.greeting'))
            ->line(__('transport/notify_lead_received.intro'))
            ->line(__('transport/notify_lead_received.summary', [
                'from' => $this->lead->pickup_address,
                'to' => $this->lead->dropoff_address,
                'date' => $this->lead->preferred_date->format('Y-m-d'),
                'horses' => $this->lead->horse_count,
            ]))
            ->action(
                __('transport/notify_lead_received.action'),
                url('/transport/leads/'.$this->lead->id),
            )
            ->line(__('transport/notify_lead_received.outro'))
            // Stopka prawna — Hovera = pośrednik marketplace; transporter wie
            // że to on (a nie Hovera) staje się stroną umowy po akceptacji oferty.
            ->line(__('transport/notify_lead_received.disclaimer_intermediary'));
    }
}
