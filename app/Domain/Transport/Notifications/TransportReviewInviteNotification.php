<?php

declare(strict_types=1);

namespace App\Domain\Transport\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Zaproszenie do recenzji — 14 dni po preferred_date dla zaakceptowanej
 * oferty. Magic link, klient nie ma konta, nie musi się rejestrować.
 *
 * Mailer = 'transport' (osobny SMTP — patrz docs/TRANSPORT.md §6).
 */
class TransportReviewInviteNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $transporterName,
        public readonly string $transporterSlug,
        public readonly ?string $customerName,
        public readonly string $reviewLink,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $greeting = $this->customerName
            ? __('transport/notify_review_invite.greeting', ['name' => $this->customerName])
            : __('transport/notify_review_invite.greeting_anonymous');

        return (new MailMessage)
            ->mailer('transport')
            ->subject(__('transport/notify_review_invite.subject', ['transporter' => $this->transporterName]))
            ->greeting($greeting)
            ->line(__('transport/notify_review_invite.intro', ['transporter' => $this->transporterName]))
            ->line(__('transport/notify_review_invite.encouragement'))
            ->action(__('transport/notify_review_invite.action'), $this->reviewLink)
            ->line(__('transport/notify_review_invite.outro'))
            ->line(__('transport/notify_review_invite.disclaimer_intermediary'));
    }
}
