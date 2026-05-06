<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class NewBookingRequestNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $tenantName,
        public readonly string $tenantSlug,
        public readonly string $entryId,
        public readonly Carbon $startsAt,
        public readonly string $instructorName,
        public readonly string $clientName,
        public readonly string $clientEmail,
        public readonly ?string $clientPhone,
        public readonly ?string $notes,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $url = url('/app/calendar-entries/'.$this->entryId.'/edit');

        $message = (new MailMessage)
            ->subject("Nowe zgłoszenie online — {$this->tenantName}")
            ->greeting('Cześć!')
            ->line("Klient zgłosił prośbę o lekcję w stajni **{$this->tenantName}**:")
            ->line("**Termin:** {$this->startsAt->format('Y-m-d H:i')}")
            ->line("**Instruktor:** {$this->instructorName}")
            ->line("**Klient:** {$this->clientName} ({$this->clientEmail}"
                .($this->clientPhone ? ", tel. {$this->clientPhone}" : '').')');

        if ($this->notes) {
            $message->line("**Notatka klienta:** {$this->notes}");
        }

        return $message
            ->line('Aby zatwierdzić, przejdź do edycji rezerwacji, przypisz konia i zmień status na „Potwierdzone".')
            ->action('Otwórz rezerwację', $url)
            ->line('Konia można przypisać dopiero w momencie potwierdzania — system wymaga tego przed zmianą statusu.')
            ->salutation('— Hovera');
    }
}
