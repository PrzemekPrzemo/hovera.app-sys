<?php

declare(strict_types=1);

namespace App\Domain\Transport\Notifications;

use App\Models\Central\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Mail do owner'a firmy transportowej po odrzuceniu konta przez master admin'a.
 * Idzie przez mailer 'transport'.
 */
class TransporterRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $reason,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->mailer('transport')
            ->error()
            ->subject(__('transport/notify_rejected.subject'))
            ->greeting(__('transport/notify_rejected.greeting'))
            ->line(__('transport/notify_rejected.intro', ['name' => $this->tenant->name]))
            ->line('"'.$this->reason.'"')
            ->line(__('transport/notify_rejected.next_steps'))
            ->action(
                __('transport/notify_rejected.action'),
                url('/transport/transporter-documents'),
            );
    }
}
