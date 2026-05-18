<?php

declare(strict_types=1);

namespace App\Domain\Transport\Notifications;

use App\Models\Central\TransportReview;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Notyfikacja "nowa recenzja" dla transportera. Idzie do ownera po
 * submit'cie przez klienta — z linkiem do panelu, gdzie transporter
 * może odpowiedzieć publicznie albo zgłosić do moderacji.
 *
 * Mailer = 'transport' (osobny SMTP — patrz docs/TRANSPORT.md §6).
 */
class TransportReviewSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly TransportReview $review,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $stars = str_repeat('★', (int) ($this->review->rating ?? 0)).
                 str_repeat('☆', 5 - (int) ($this->review->rating ?? 0));

        $message = (new MailMessage)
            ->mailer('transport')
            ->subject(__('transport/notify_review_submitted.subject', [
                'rating' => (int) ($this->review->rating ?? 0),
            ]))
            ->greeting(__('transport/notify_review_submitted.greeting'))
            ->line(__('transport/notify_review_submitted.intro', ['stars' => $stars]));

        if (! empty($this->review->comment)) {
            $message->line('"'.Str::limit((string) $this->review->comment, 280).'"');
        }

        $message->action(
            __('transport/notify_review_submitted.action'),
            url('/transport/transport-reviews'),
        );

        $message->line(__('transport/notify_review_submitted.outro'));

        return $message;
    }
}
