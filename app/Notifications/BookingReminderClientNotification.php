<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Sent ~24h before a confirmed booking. Goal is to nudge the client
 * to actually show up (no-show is the #1 pain point for stables) and
 * to surface a cancel link in case they need to back out — better
 * to lose a slot than to host an empty horse.
 */
class BookingReminderClientNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $tenantName,
        public readonly Carbon $startsAt,
        public readonly int $durationMinutes,
        public readonly string $instructorName,
        public readonly ?string $horseName,
        public readonly ?string $arenaName,
        public readonly ?string $stableAddress,
        public readonly ?string $stablePhone,
        public readonly string $cancelUrl,
        public readonly int $cancellationPolicyHours,
    ) {}

    /** @return array<int,string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("Przypomnienie: jutro {$this->startsAt->format('H:i')} — {$this->tenantName}")
            ->greeting('Cześć!')
            ->line('Przypominamy o jutrzejszej rezerwacji.')
            ->line("**Termin:** {$this->startsAt->format('Y-m-d H:i')} · {$this->durationMinutes} min")
            ->line("**Instruktor:** {$this->instructorName}");

        if ($this->horseName) {
            $message->line("**Koń:** {$this->horseName}");
        }
        if ($this->arenaName) {
            $message->line("**Ujeżdżalnia:** {$this->arenaName}");
        }
        if ($this->stableAddress) {
            $message->line("**Adres:** {$this->stableAddress}");
        }
        if ($this->stablePhone) {
            $message->line("**Telefon do stajni:** {$this->stablePhone}");
        }

        return $message
            ->line('Jeśli musisz odwołać, zrób to jak najszybciej — '
                .'odwołanie do '.$this->cancellationPolicyHours.' godzin przed lekcją '
                .'jest bez kosztu.')
            ->action('Odwołaj rezerwację', $this->cancelUrl)
            ->line('Do zobaczenia jutro!')
            ->salutation("— {$this->tenantName}");
    }
}
