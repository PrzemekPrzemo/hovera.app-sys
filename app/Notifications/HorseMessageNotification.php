<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Mail-out gdy nowa wiadomość pojawi się w threadzie konia. Wysyłany
 * w obie strony (stajnia → właściciel, właściciel → stajnia).
 */
class HorseMessageNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $tenantName,
        public readonly string $horseName,
        public readonly string $portalUrl,
        public readonly string $fromLabel,
        public readonly ?string $subject,
        public readonly string $bodyPreview,
        public readonly int $attachmentCount,
    ) {}

    /** @return array<int,string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $subject = $this->subject
            ? "{$this->subject} ({$this->horseName})"
            : "Nowa wiadomość — {$this->horseName} — {$this->tenantName}";

        $msg = (new MailMessage)
            ->subject($subject)
            ->greeting('Cześć!')
            ->line("Otrzymałeś nową wiadomość dotyczącą konia **{$this->horseName}** ({$this->tenantName}).")
            ->line('**Od:** '.$this->fromLabel);

        if ($this->subject) {
            $msg->line('**Temat:** '.$this->subject);
        }
        $msg->line('> '.$this->bodyPreview);

        if ($this->attachmentCount > 0) {
            $msg->line($this->attachmentCount === 1
                ? '📎 1 załącznik'
                : "📎 {$this->attachmentCount} załączniki");
        }

        return $msg
            ->action('Otwórz wiadomość', $this->portalUrl)
            ->salutation("— {$this->tenantName}");
    }
}
