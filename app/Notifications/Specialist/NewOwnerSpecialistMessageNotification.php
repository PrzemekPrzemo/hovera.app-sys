<?php

declare(strict_types=1);

namespace App\Notifications\Specialist;

use App\Models\Central\OwnerSpecialistMessage;
use App\Models\Central\OwnerSpecialistThread;
use App\Models\Central\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Powiadomienie o nowej wiadomości w wątku Channel D — właściciel ↔
 * specjalista (PR O5 epic 3). Central users (owner) dostają mail+database,
 * ExternalSpecialist tylko mail.
 */
class NewOwnerSpecialistMessageNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly OwnerSpecialistThread $thread,
        public readonly OwnerSpecialistMessage $message,
        public readonly string $senderName,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? ['mail', 'database'] : ['mail'];
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
            'type' => 'owner_specialist_message',
            'thread_id' => $this->thread->id,
            'message_id' => $this->message->id,
            'subject' => $this->thread->subject,
            'sender' => $this->senderName,
            'preview' => (string) str($this->message->body)->limit(120),
        ];
    }
}
