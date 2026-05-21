<?php

declare(strict_types=1);

namespace App\Notifications\Owner;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notyfikuje właściciela gdy stajnia zarejestruje wizytę zdrowotną
 * (HealthRecord) dla konia. Dispatch z HealthRecord Observer (created
 * event) gdy type ∈ {vet_visit, vaccination, deworming, dentist,
 * farrier, check_up, medication}. Inne types (np. inne) pomijamy żeby
 * nie spamować.
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 6 PR 6.1".
 */
class VetVisitRecordedForOwner extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $stableTenantId,
        public readonly string $stableName,
        public readonly string $centralHorseId,
        public readonly string $horseName,
        public readonly string $recordType,         // HealthRecordType enum value
        public readonly ?string $summary,
        public readonly ?string $details,
        public readonly ?int $costCents,
        public readonly ?string $performedAt,        // ISO datetime
        public readonly ?string $nextDueAt,           // ISO date if scheduled follow-up
        public readonly string $ownerPanelUrl,
    ) {}

    /** @return array<int,string> */
    public function via(mixed $notifiable): array
    {
        return ['database', 'mail'];
    }

    /** @return array<string,mixed> */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'kind' => 'owner.vet_visit_recorded',
            'stable_tenant_id' => $this->stableTenantId,
            'stable_name' => $this->stableName,
            'central_horse_id' => $this->centralHorseId,
            'horse_name' => $this->horseName,
            'record_type' => $this->recordType,
            'summary' => $this->summary,
            'cost_cents' => $this->costCents,
            'performed_at' => $this->performedAt,
            'next_due_at' => $this->nextDueAt,
            'url' => $this->ownerPanelUrl,
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $subject = __('notifications.owner_vet_visit.subject', [
            'horse' => $this->horseName,
            'type' => __('enums.health_record_type.'.$this->recordType),
        ]);

        $msg = (new MailMessage)
            ->subject($subject)
            ->greeting(__('notifications.common.greeting'))
            ->line(__('notifications.owner_vet_visit.line_intro', [
                'stable' => $this->stableName,
                'horse' => $this->horseName,
                'type' => __('enums.health_record_type.'.$this->recordType),
            ]));

        if ($this->summary !== null && $this->summary !== '') {
            $msg->line('> '.$this->summary);
        }
        if ($this->costCents !== null && $this->costCents > 0) {
            $totalFormatted = number_format($this->costCents / 100, 2, ',', ' ').' PLN';
            $msg->line('**'.__('notifications.owner_vet_visit.field.cost').':** '.$totalFormatted);
        }
        if ($this->nextDueAt !== null) {
            $msg->line('**'.__('notifications.owner_vet_visit.field.next_due').':** '.$this->nextDueAt);
        }

        return $msg->action(__('notifications.owner_vet_visit.action'), $this->ownerPanelUrl);
    }
}
