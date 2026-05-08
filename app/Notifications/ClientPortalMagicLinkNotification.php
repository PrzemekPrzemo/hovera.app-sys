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
            ->subject(__('notifications.client_portal_magic_link.subject', ['tenant' => $this->tenantName]))
            ->greeting(__('notifications.common.greeting'))
            ->line(__('notifications.client_portal_magic_link.line_intro', ['tenant' => $this->tenantName]))
            ->action(__('notifications.client_portal_magic_link.action'), $this->magicLinkUrl)
            ->line(__('notifications.client_portal_magic_link.line_ttl', ['minutes' => $this->ttlMinutes]))
            ->line(__('notifications.client_portal_magic_link.line_security'))
            ->salutation(__('notifications.common.salutation_prefix').$this->tenantName);
    }
}
