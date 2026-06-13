<?php

declare(strict_types=1);

namespace App\Notifications\Health;

use App\Models\Tenant\HealthRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Apn\ApnMessage;

/**
 * Powiadomienie o zbliżającym się terminie ekspiracji health record
 * (szczepienie, odrobaczenie, wizyta dentysty itd). Wysyłane przez
 * `HealthRecordsRemindDueCommand` w 3 fazach (30/14/7 dni).
 *
 * Audience-aware:
 *   - `vet` → mail only (Specialist trzymany jako external email, brak User)
 *   - `owner` → mail only (external owner / public visitor)
 *   - `staff` → mail + apn + fcm (zalogowany do app, ma device tokens)
 */
class HealthRecordExpiryReminderNotification extends Notification
{
    use Queueable;

    public const AUDIENCE_VET = 'vet';

    public const AUDIENCE_OWNER = 'owner';

    public const AUDIENCE_STAFF = 'staff';

    public function __construct(
        public readonly HealthRecord $record,
        public readonly int $daysUntilDue,
        public readonly string $audience,
        public readonly string $horseName,
        public readonly string $stableName,
    ) {}

    /** @return array<int,string> */
    public function via(mixed $notifiable): array
    {
        return $this->audience === self::AUDIENCE_STAFF
            ? ['mail', 'apn', 'fcm']
            : ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $base = 'notifications.health_reminder.'.$this->audience;
        $typeLabel = $this->record->type->label();

        return (new MailMessage)
            ->subject(__($base.'.subject', [
                'type' => $typeLabel,
                'horse' => $this->horseName,
                'days' => $this->daysUntilDue,
            ]))
            ->greeting(__($base.'.greeting'))
            ->line(__($base.'.intro', [
                'type' => $typeLabel,
                'horse' => $this->horseName,
                'stable' => $this->stableName,
                'days' => $this->daysUntilDue,
                'due' => $this->record->next_due_at?->format('Y-m-d') ?? '—',
            ]))
            ->line(__($base.'.cta_line'))
            ->action(
                __($base.'.cta'),
                $this->ctaUrl(),
            );
    }

    public function toApn(mixed $notifiable): ApnMessage
    {
        return ApnMessage::create()
            ->title(__('notifications.health_reminder.push_title', [
                'type' => $this->record->type->label(),
            ]))
            ->body(__('notifications.health_reminder.push_body', [
                'horse' => $this->horseName,
                'days' => $this->daysUntilDue,
            ]))
            ->custom('kind', 'health_reminder')
            ->custom('record_id', (string) $this->record->id)
            ->custom('horse_id', (string) $this->record->horse_id);
    }

    /** @return array<string,mixed> */
    public function toFcm(mixed $notifiable): array
    {
        return [
            'notification' => [
                'title' => __('notifications.health_reminder.push_title', [
                    'type' => $this->record->type->label(),
                ]),
                'body' => __('notifications.health_reminder.push_body', [
                    'horse' => $this->horseName,
                    'days' => $this->daysUntilDue,
                ]),
            ],
            'data' => [
                'kind' => 'health_reminder',
                'record_id' => (string) $this->record->id,
                'horse_id' => (string) $this->record->horse_id,
            ],
        ];
    }

    private function ctaUrl(): string
    {
        return match ($this->audience) {
            self::AUDIENCE_OWNER => url('/owner/horses/'.$this->record->horse_id.'/timeline'),
            default => url('/app/health-records'),
        };
    }
}
