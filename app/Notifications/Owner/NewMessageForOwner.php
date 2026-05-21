<?php

declare(strict_types=1);

namespace App\Notifications\Owner;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notyfikuje właściciela konia o nowej wiadomości od stajni. Dispatch
 * z `SendHorseMessage::fromStable` (stable side action). Target = User
 * (central) resolveowany z Client.central_user_id.
 *
 * Channels: database (Owner Dashboard widget z PR 6.2 będzie czytał),
 * mail (uzupełnienie istniejącego HorseMessageNotification która jedzie
 * do portal'u klienta na email — tu mamy też ścieżkę dla owner panel'u
 * Hovera).
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 6 PR 6.1".
 */
class NewMessageForOwner extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $stableTenantId,
        public readonly string $stableName,
        public readonly string $centralHorseId,
        public readonly string $horseName,
        public readonly string $messageId,
        public readonly ?string $subject,
        public readonly string $bodyPreview,
        public readonly int $attachmentCount,
        public readonly string $ownerPanelUrl,
    ) {}

    /** @return array<int,string> */
    public function via(mixed $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'kind' => 'owner.new_message',
            'stable_tenant_id' => $this->stableTenantId,
            'stable_name' => $this->stableName,
            'central_horse_id' => $this->centralHorseId,
            'horse_name' => $this->horseName,
            'message_id' => $this->messageId,
            'subject' => $this->subject,
            'body_preview' => $this->bodyPreview,
            'attachment_count' => $this->attachmentCount,
            'url' => $this->ownerPanelUrl,
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $subject = $this->subject !== null && $this->subject !== ''
            ? __('notifications.owner_new_message.subject_with_subject', [
                'subject' => $this->subject,
                'horse' => $this->horseName,
            ])
            : __('notifications.owner_new_message.subject_default', [
                'horse' => $this->horseName,
                'stable' => $this->stableName,
            ]);

        $msg = (new MailMessage)
            ->subject($subject)
            ->greeting(__('notifications.common.greeting'))
            ->line(__('notifications.owner_new_message.line_intro', [
                'stable' => $this->stableName,
                'horse' => $this->horseName,
            ]));

        if ($this->subject !== null && $this->subject !== '') {
            $msg->line('**'.__('notifications.common.field.subject').':** '.$this->subject);
        }
        $msg->line('> '.$this->bodyPreview);
        if ($this->attachmentCount > 0) {
            $msg->line(__('notifications.owner_new_message.attachment_count', ['count' => $this->attachmentCount]));
        }

        return $msg->action(__('notifications.owner_new_message.action'), $this->ownerPanelUrl);
    }
}
