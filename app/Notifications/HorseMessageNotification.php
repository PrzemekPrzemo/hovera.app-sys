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
            ? __('notifications.horse_message.subject_with_subject', [
                'subject' => $this->subject,
                'horse' => $this->horseName,
            ])
            : __('notifications.horse_message.subject_default', [
                'horse' => $this->horseName,
                'tenant' => $this->tenantName,
            ]);

        $msg = (new MailMessage)
            ->subject($subject)
            ->greeting(__('notifications.common.greeting'))
            ->line(__('notifications.horse_message.line_intro', [
                'horse' => $this->horseName,
                'tenant' => $this->tenantName,
            ]))
            ->line('**'.__('notifications.common.field.from').':** '.$this->fromLabel);

        if ($this->subject) {
            $msg->line('**'.__('notifications.common.field.subject').':** '.$this->subject);
        }
        $msg->line('> '.$this->bodyPreview);

        if ($this->attachmentCount > 0) {
            $msg->line($this->attachmentCount === 1
                ? __('notifications.horse_message.attachments_one')
                : __('notifications.horse_message.attachments_many', ['count' => $this->attachmentCount]));
        }

        return $msg
            ->action(__('notifications.horse_message.action'), $this->portalUrl)
            ->salutation(__('notifications.common.salutation_prefix').$this->tenantName);
    }
}
