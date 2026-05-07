<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Sent to the customer when stable owner moves the booking from
 * `requested` to `confirmed` — the actual "yes, you have a lesson"
 * email. Includes the assigned horse and stable address if set.
 */
class BookingConfirmedClientNotification extends Notification
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
            ->subject("Rezerwacja potwierdzona — {$this->tenantName}")
            ->greeting('Cześć!')
            ->line("Twoja rezerwacja w **{$this->tenantName}** została potwierdzona.")
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

        $message
            ->line('Jeśli musisz odwołać, kliknij poniżej. '
                .'Odwołanie minimum '.$this->cancellationPolicyHours.' godzin przed lekcją jest bez kosztu.')
            ->action('Odwołaj rezerwację', $this->cancelUrl);

        if ($this->portalUrl) {
            $message->line("Wszystkie rezerwacje znajdziesz w panelu klienta: [{$this->portalUrl}]({$this->portalUrl})");
        }

        return $message
            ->line('Do zobaczenia!')
            ->salutation("— {$this->tenantName}");
    }
}
