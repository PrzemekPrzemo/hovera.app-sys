<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Sent to the customer when their booking is cancelled — either by
 * the stable owner from /app, or by themselves through the cancel
 * link.
 */
class BookingCancelledClientNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $tenantName,
        public readonly Carbon $startsAt,
        public readonly string $instructorName,
        public readonly string $cancelledBy,   // 'stable' | 'client'
        public readonly bool $passRestored,
    ) {}

    /** @return array<int,string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("Rezerwacja odwołana — {$this->tenantName}")
            ->greeting('Cześć!');

        if ($this->cancelledBy === 'client') {
            $message->line("Twoja rezerwacja w **{$this->tenantName}** została odwołana zgodnie z Twoim wnioskiem.");
        } else {
            $message->line("Stajnia **{$this->tenantName}** odwołała Twoją rezerwację. Skontaktuj się ze stajnią po szczegóły.");
        }

        $message->line("**Anulowany termin:** {$this->startsAt->format('Y-m-d H:i')}")
            ->line("**Instruktor:** {$this->instructorName}");

        if ($this->passRestored) {
            $message->line('Karnet został zwrócony — możesz go wykorzystać przy kolejnej rezerwacji.');
        } else {
            $message->line('Karnet (jeśli był używany) nie został zwrócony — odwołanie po terminie polityki.');
        }

        return $message->salutation("— {$this->tenantName}");
    }
}
