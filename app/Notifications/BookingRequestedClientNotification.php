<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Sent to the customer right after they submit a public booking
 * request. Confirms receipt and tells them what happens next.
 */
class BookingRequestedClientNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $tenantName,
        public readonly Carbon $startsAt,
        public readonly int $durationMinutes,
        public readonly string $instructorName,
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
        return (new MailMessage)
            ->subject("Otrzymaliśmy zgłoszenie — {$this->tenantName}")
            ->greeting('Cześć!')
            ->line("Dziękujemy za zgłoszenie rezerwacji w stajni **{$this->tenantName}**.")
            ->line("**Termin:** {$this->startsAt->format('Y-m-d H:i')} · {$this->durationMinutes} min")
            ->line("**Instruktor:** {$this->instructorName}")
            ->line('Stajnia potwierdzi rezerwację mailem (zwykle w ciągu kilku godzin) i przydzieli konia.')
            ->line('Jeśli musisz odwołać, kliknij poniżej. '
                .'Odwołanie minimum '.$this->cancellationPolicyHours.' godzin przed lekcją jest bez kosztu.')
            ->action('Odwołaj rezerwację', $this->cancelUrl)
            ->line('Jeśli nie odwołasz w terminie, karnet (jeśli używany) zostanie zużyty.')
            ->salutation("— {$this->tenantName}");
    }
}
