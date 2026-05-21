<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notyfikuje stable team o nowej wiadomości od ownera (cross-tenant
 * przez Hovera owner panel). W odróżnieniu od HorseMessageNotification
 * która leci do client portal users — ta idzie do operatorów stajni
 * (owner/admin/operator/manager z TenantMembership).
 *
 * Channels: database + mail. database notifications wymagają żeby
 * notifications table była w central DB (caller dispatch'uje POZA
 * TenantManager::execute żeby central connection był aktywny).
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 4 PR 4.4".
 */
class OwnerSentMessageToStableNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $ownerName,
        public readonly string $horseName,
        public readonly string $stableHorseId,
        public readonly ?string $subject,
        public readonly string $bodyPreview,
        public readonly int $attachmentCount,
        public readonly string $stableHorseUrl,
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
            'kind' => 'owner_message_to_stable',
            'owner_name' => $this->ownerName,
            'horse_name' => $this->horseName,
            'stable_horse_id' => $this->stableHorseId,
            'subject' => $this->subject,
            'body_preview' => $this->bodyPreview,
            'attachment_count' => $this->attachmentCount,
            'url' => $this->stableHorseUrl,
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $subject = $this->subject !== null && $this->subject !== ''
            ? __('notifications.owner_message_to_stable.subject_with_subject', [
                'subject' => $this->subject,
                'horse' => $this->horseName,
            ])
            : __('notifications.owner_message_to_stable.subject_default', [
                'horse' => $this->horseName,
                'owner' => $this->ownerName,
            ]);

        $msg = (new MailMessage)
            ->subject($subject)
            ->greeting(__('notifications.common.greeting'))
            ->line(__('notifications.owner_message_to_stable.line_intro', [
                'owner' => $this->ownerName,
                'horse' => $this->horseName,
            ]));

        if ($this->subject !== null && $this->subject !== '') {
            $msg->line('**'.__('notifications.common.field.subject').':** '.$this->subject);
        }
        $msg->line('> '.$this->bodyPreview);

        if ($this->attachmentCount > 0) {
            $msg->line(__('notifications.owner_message_to_stable.attachment_count', [
                'count' => $this->attachmentCount,
            ]));
        }

        return $msg->action(
            __('notifications.owner_message_to_stable.action'),
            $this->stableHorseUrl,
        );
    }
}
