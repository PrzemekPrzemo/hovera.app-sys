<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Confirmation that a self-service reschedule went through. Includes
 * the new time and a portal link so the client can re-verify or undo.
 */
class BookingRescheduledClientNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $tenantName,
        public readonly Carbon $oldStartsAt,
        public readonly Carbon $newStartsAt,
        public readonly int $durationMinutes,
        public readonly string $instructorName,
        public readonly string $cancelUrl,
        public readonly ?string $portalUrl = null,
    ) {}

    /** @return array<int,string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("Rezerwacja przesunięta — {$this->tenantName}")
            ->greeting('Cześć!')
            ->line("Twoja rezerwacja w **{$this->tenantName}** została przesunięta.")
            ->line('**Stara data:** '.$this->oldStartsAt->format('Y-m-d H:i'))
            ->line('**Nowa data:** '.$this->newStartsAt->format('Y-m-d H:i').' · '.$this->durationMinutes.' min')
            ->line("**Instruktor:** {$this->instructorName}")
            ->line('Jeśli to przesunięcie było pomyłką, możesz odwołać i zarezerwować nowy termin.')
            ->action('Odwołaj rezerwację', $this->cancelUrl);

        if ($this->portalUrl) {
            $message->line("Zarządzaj rezerwacjami w panelu klienta: [{$this->portalUrl}]({$this->portalUrl})");
        }

        return $message->salutation("— {$this->tenantName}");
    }
}
