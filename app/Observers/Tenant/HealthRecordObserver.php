<?php

declare(strict_types=1);

namespace App\Observers\Tenant;

use App\Domain\Notifications\Owner\OwnerNotificationDispatcher;
use App\Enums\HealthRecordType;
use App\Models\Tenant\HealthRecord;
use App\Notifications\Owner\VetVisitRecordedForOwner;
use App\Tenancy\TenantManager;

/**
 * Faza 6 PR 6.1 — dispatch VetVisitRecordedForOwner gdy stable doda
 * HealthRecord dla konia. Pomijamy `Other` type żeby nie spamować
 * (np. krótkie notatki "podałem witaminę" nie wymagają emaila).
 *
 * Owner widzi w panel'u + dostaje mail z linkiem do timeline.
 */
class HealthRecordObserver
{
    /** @var array<string, HealthRecordType> */
    private const NOTIFY_TYPES = [
        'vet_visit' => HealthRecordType::VetVisit,
        'vaccination' => HealthRecordType::Vaccination,
        'deworming' => HealthRecordType::Deworming,
        'dentist' => HealthRecordType::Dentist,
        'farrier' => HealthRecordType::Farrier,
        'check_up' => HealthRecordType::CheckUp,
        'medication' => HealthRecordType::Medication,
    ];

    public function created(HealthRecord $record): void
    {
        $typeValue = $record->type instanceof HealthRecordType
            ? $record->type->value
            : (string) $record->type;

        if (! isset(self::NOTIFY_TYPES[$typeValue])) {
            return; // 'other' lub nieznany — silent skip
        }

        $record->loadMissing('horse');
        $horse = $record->horse;
        if ($horse === null || $horse->central_horse_id === null) {
            return;
        }

        $tenant = app(TenantManager::class)->current();
        $stableName = $tenant?->name ?? '';
        $stableId = $tenant !== null ? (string) $tenant->id : '';

        $ownerPanelUrl = url(sprintf('/owner/horses/%s/timeline', $horse->central_horse_id));

        app(OwnerNotificationDispatcher::class)->forCentralHorse(
            (string) $horse->central_horse_id,
            new VetVisitRecordedForOwner(
                stableTenantId: $stableId,
                stableName: (string) $stableName,
                centralHorseId: (string) $horse->central_horse_id,
                horseName: (string) $horse->name,
                recordType: $typeValue,
                summary: $record->summary !== null ? (string) $record->summary : null,
                details: $record->details !== null ? (string) $record->details : null,
                costCents: $record->cost_cents !== null ? (int) $record->cost_cents : null,
                performedAt: $record->performed_at?->toIso8601String(),
                nextDueAt: $record->next_due_at?->toDateString(),
                ownerPanelUrl: $ownerPanelUrl,
            ),
        );
    }
}
