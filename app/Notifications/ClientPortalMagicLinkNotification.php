<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Click here to log in" mail — single-use, ~30 minute TTL.
 *
 * Intentionally vague when the email is unknown: the controller still
 * shows the same "if your email is registered, we sent a link" page
 * either way, so this notification is only dispatched for matched
 * clients. Don't add hints that would let an attacker enumerate.
 */
class ClientPortalMagicLinkNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $tenantName,
        public readonly string $magicLinkUrl,
        public readonly int $ttlMinutes,
    ) {}

    /** @return array<int,string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Logowanie do panelu — {$this->tenantName}")
            ->greeting('Cześć!')
            ->line("Klikinij poniżej, aby zalogować się do panelu klienta **{$this->tenantName}**.")
            ->action('Zaloguj się', $this->magicLinkUrl)
            ->line("Link działa przez {$this->ttlMinutes} minut i można użyć go tylko raz.")
            ->line('Jeśli to nie Ty próbujesz się zalogować — zignoruj tę wiadomość.')
            ->salutation("— {$this->tenantName}");
    }
}
