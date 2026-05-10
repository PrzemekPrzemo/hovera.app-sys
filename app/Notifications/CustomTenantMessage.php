<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One-shot notification for ad-hoc messages a master admin sends from
 * /admin/tenants/{id}/mailer. Body is plain text (the page accepts
 * Markdown but Laravel's MailMessage already handles that for us).
 */
class CustomTenantMessage extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $subject,
        public readonly string $body,
        public readonly string $tenantName,
    ) {}

    /**
     * @return array<int,string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->subject)
            ->greeting(__('admin/back-office.mailer.email.greeting', ['name' => $notifiable->name ?? '']));

        // We split on blank lines so each paragraph survives Laravel's
        // MailMessage rendering — multi-paragraph Markdown otherwise
        // collapses into one big block.
        foreach (preg_split('/\R\R+/', trim($this->body)) ?: [trim($this->body)] as $paragraph) {
            $message->line($paragraph);
        }

        return $message
            ->salutation(__('admin/back-office.mailer.email.salutation', ['stable' => $this->tenantName]));
    }
}
