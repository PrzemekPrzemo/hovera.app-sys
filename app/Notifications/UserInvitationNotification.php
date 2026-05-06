<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Central\UserInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly UserInvitation $invitation,
        public readonly string $plaintextToken,
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
        $tenantName = $this->invitation->tenant?->name;
        $url = url('/invite/'.$this->plaintextToken);

        $message = (new MailMessage)
            ->subject($tenantName
                ? "Zaproszenie do stajni {$tenantName} — Hovera"
                : 'Zaproszenie do Hovera')
            ->greeting($this->invitation->name
                ? "Cześć {$this->invitation->name}!"
                : 'Cześć!');

        if ($tenantName) {
            $message->line("Zostałeś dodany do stajni **{$tenantName}** w systemie Hovera"
                .($this->invitation->role
                    ? " z rolą *{$this->invitation->role}*."
                    : '.'));
        } else {
            $message->line('Otrzymałeś zaproszenie do systemu Hovera.');
        }

        return $message
            ->line('Aby aktywować konto i ustawić hasło, kliknij poniżej.')
            ->action('Ustaw hasło i zaloguj się', $url)
            ->line('Link wygasa '.$this->invitation->expires_at->format('Y-m-d H:i').' (UTC).')
            ->line('Jeśli to nie Ty, możesz zignorować tę wiadomość — bez kliknięcia konto nie zostanie aktywowane.')
            ->salutation('— Hovera');
    }
}
