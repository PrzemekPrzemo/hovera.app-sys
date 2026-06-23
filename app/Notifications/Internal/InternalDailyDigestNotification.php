<?php

declare(strict_types=1);

namespace App\Notifications\Internal;

use App\Models\Central\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Dzienny digest nieprzeczytanych wiadomości w kanałach wewnętrznych
 * (PR O5 Channel C, epic 2).
 *
 * Wysyłany codziennie o 08:00 (Europe/Warsaw) tylko do userów z >0 unread
 * (skip empty — per captured decisions §4). Grupowanie po kanale.
 */
class InternalDailyDigestNotification extends Notification
{
    use Queueable;

    /**
     * @param list<array{name:string,count:int}> $groups
     */
    public function __construct(
        public readonly Tenant $tenant,
        public readonly array $groups,
        public readonly int $total,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('internal/digest.mail.subject', ['count' => $this->total, 'tenant' => $this->tenant->name]))
            ->greeting(__('internal/digest.mail.greeting'))
            ->line(__('internal/digest.mail.intro', ['tenant' => $this->tenant->name]));

        foreach ($this->groups as $group) {
            $mail->line('#'.$group['name'].' — '.__('internal/digest.mail.channel_count', ['count' => $group['count']]));
        }

        return $mail
            ->action(__('internal/digest.mail.cta'), url('/app'))
            ->line(__('internal/digest.mail.footer'));
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'internal_daily_digest',
            'tenant_id' => $this->tenant->id,
            'total' => $this->total,
            'groups' => $this->groups,
        ];
    }
}
