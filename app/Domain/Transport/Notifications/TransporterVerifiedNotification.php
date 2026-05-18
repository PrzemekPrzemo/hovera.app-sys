<?php

declare(strict_types=1);

namespace App\Domain\Transport\Notifications;

use App\Models\Central\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Mail do owner'a firmy transportowej po zatwierdzeniu konta przez master admin'a.
 * Idzie przez mailer 'transport'.
 */
class TransporterVerifiedNotification extends Notification
{
    use Queueable;

    /**
     * @param  list<string>  $verifiedDocuments  Lista etykiet zweryfikowanych
     *                                           dokumentów (PL/EN/...). Wstrzykiwane
     *                                           przez TransporterResource::verify.
     */
    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $notes = '',
        public readonly array $verifiedDocuments = [],
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->mailer('transport')
            ->success()
            ->subject(__('transport/notify_verified.subject'))
            ->greeting(__('transport/notify_verified.greeting'))
            ->line(__('transport/notify_verified.intro', ['name' => $this->tenant->name]))
            ->line(__('transport/notify_verified.now_can'))
            ->line(__('transport/notify_verified.trial_started'));

        if (! empty($this->verifiedDocuments)) {
            $message->line(__('transport/notify_verified.verified_list_heading'));
            foreach ($this->verifiedDocuments as $label) {
                $message->line('• '.$label);
            }
        }

        if ($this->notes !== '') {
            $message->line('"'.$this->notes.'"');
        }

        return $message->action(
            __('transport/notify_verified.action'),
            url('/transport'),
        );
    }
}
