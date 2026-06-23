<?php

declare(strict_types=1);

namespace App\Notifications\Specialist;

use App\Models\Central\SpecialistMessage;
use App\Models\Central\SpecialistThread;
use App\Models\Central\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Powiadomienie o nowej wiadomości w wątku Channel B (PR O5 epic 1.4/1.5).
 *
 * Wysyłane do drugiej strony konwersacji:
 *   - gdy odpisał specjalista → do pracowników stajni (mail + database)
 *   - gdy odpisała stajnia    → do specjalisty (mail)
 *
 * `channels` ustalany dynamicznie — ExternalSpecialist nie ma tabeli
 * notifications, więc dostaje tylko mail.
 */
class NewSpecialistMessageNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly SpecialistThread $thread,
        public readonly SpecialistMessage $message,
        public readonly string $senderName,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        // Database channel tylko dla central users (mają tabelę notifications).
        return method_exists($notifiable, 'routeNotificationForDatabase') || $notifiable instanceof User
            ? ['mail', 'database']
            : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('specialist/message.mail.subject', ['subject' => $this->thread->subject]))
            ->greeting(__('specialist/message.mail.greeting'))
            ->line(__('specialist/message.mail.intro', [
                'sender' => $this->senderName,
                'subject' => $this->thread->subject,
            ]))
            ->line('„'.str($this->message->body)->limit(200).'"')
            ->action(__('specialist/message.mail.cta'), url('/'))
            ->line(__('specialist/message.mail.footer'));
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'specialist_message',
            'thread_id' => $this->thread->id,
            'message_id' => $this->message->id,
            'subject' => $this->thread->subject,
            'sender' => $this->senderName,
            'preview' => (string) str($this->message->body)->limit(120),
        ];
    }
}
