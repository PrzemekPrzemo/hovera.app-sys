<?php

declare(strict_types=1);

namespace App\Domain\Horses\Timeline;

use App\Models\Central\Tenant;
use App\Models\Tenant\BoxAssignment;
use App\Models\Tenant\HealthRecord;
use App\Models\Tenant\Horse;
use App\Models\Tenant\HorseDocument;
use App\Models\Tenant\HorsePhoto;
use App\Models\Tenant\HorseWeightMeasurement;
use App\Models\Tenant\StableActivity;
use App\Tenancy\TenantManager;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cross-tenant aggregator dla osi czasu konia. Łączy w jeden DESC feed
 * eventy z 7 źródeł:
 *   - HealthRecord (vet/dentist/farrier/vaccination/...)
 *   - BoxAssignment (assigned + vacated)
 *   - HorseWeightMeasurement
 *   - StableActivity (paddock/longe/grooming/...)
 *   - HorsePhoto upload
 *   - HorseDocument upload
 *
 * Każde wywołanie używa TenantManager::execute żeby tymczasowo
 * przepiąć connection 'tenant' na stable DB; po wyjściu wszystko jest
 * w DTO (Eloquent nie zna już stable schema).
 *
 * Nie używamy paginacji kursor-based dla MVP — date range filter +
 * limit (default 200) wystarczają. Realnie owner timeline rzadko
 * przekracza ~200 events / rok.
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 2 — Timeline".
 */
class HorseTimelineService
{
    public function __construct(
        private readonly TenantManager $tenants,
    ) {}

    /**
     * @return list<HorseTimelineEntry>  Posortowane DESC po occurred_at, limit applied
     */
    public function forHorse(string $centralHorseId, Tenant $stableTenant, ?HorseTimelineFilter $filter = null): array
    {
        $filter ??= new HorseTimelineFilter;

        return $this->tenants->execute($stableTenant, function () use ($centralHorseId, $filter): array {
            // Znajdź horse'a w stable DB. Brak = pusty feed (per Q3 caller
            // powinien był wcześniej zweryfikować dostęp przez gate).
            $horse = Horse::query()
                ->where('central_horse_id', $centralHorseId)
                ->first();

            if ($horse === null) {
                return [];
            }

            $entries = [];

            if ($filter->allowsKind(HorseTimelineEntry::KIND_HEALTH)) {
                $entries = array_merge($entries, $this->collectHealth($horse, $filter));
            }
            if ($filter->allowsKind(HorseTimelineEntry::KIND_BOX)) {
                $entries = array_merge($entries, $this->collectBoxAssignments($horse, $filter));
            }
            if ($filter->allowsKind(HorseTimelineEntry::KIND_WEIGHT)) {
                $entries = array_merge($entries, $this->collectWeights($horse, $filter));
            }
            if ($filter->allowsKind(HorseTimelineEntry::KIND_ACTIVITY)) {
                $entries = array_merge($entries, $this->collectActivities($horse, $filter));
            }
            if ($filter->allowsKind(HorseTimelineEntry::KIND_PHOTO)) {
                $entries = array_merge($entries, $this->collectPhotos($horse, $filter));
            }
            if ($filter->allowsKind(HorseTimelineEntry::KIND_DOCUMENT)) {
                $entries = array_merge($entries, $this->collectDocuments($horse, $filter));
            }

            // Sort DESC po occurred_at; przy remisie po source_id (stabilny).
            usort($entries, function (HorseTimelineEntry $a, HorseTimelineEntry $b): int {
                $diff = $b->occurredAt->getTimestamp() <=> $a->occurredAt->getTimestamp();
                if ($diff !== 0) {
                    return $diff;
                }

                return $b->sourceId <=> $a->sourceId;
            });

            return array_slice($entries, 0, $filter->limit);
        });
    }

    /**
     * @return list<HorseTimelineEntry>
     */
    private function collectHealth(Horse $horse, HorseTimelineFilter $filter): array
    {
        $query = HealthRecord::query()
            ->with('specialist')
            ->where('horse_id', $horse->id);
        $this->applyDateRange($query, 'performed_at', $filter);

        $out = [];
        foreach ($query->get() as $record) {
            $out[] = new HorseTimelineEntry(
                kind: HorseTimelineEntry::KIND_HEALTH,
                subkind: $record->type->value,
                occurredAt: $record->performed_at,
                sourceId: (string) $record->id,
                actorRole: HorseTimelineEntry::ACTOR_STABLE,
                actorName: $record->performedByLabel(),
                title: (string) ($record->summary ?? $record->type->value),
                description: $record->details !== null ? (string) $record->details : null,
                costCents: $record->cost_cents !== null ? (int) $record->cost_cents : null,
                payload: [
                    'next_due_at' => $record->next_due_at?->toDateString(),
                ],
            );
        }

        return $out;
    }

    /**
     * BoxAssignment generuje 2 eventy: jeden na `assigned_at` (kind=box,
     * subkind=assigned) i opcjonalnie drugi na `vacated_at` jeśli koń
     * został przeniesiony (subkind=vacated).
     *
     * @return list<HorseTimelineEntry>
     */
    private function collectBoxAssignments(Horse $horse, HorseTimelineFilter $filter): array
    {
        $assignments = BoxAssignment::query()
            ->with('box.building')
            ->where('horse_id', $horse->id)
            ->get();

        $out = [];
        foreach ($assignments as $assignment) {
            $boxName = $assignment->box?->name ?? '—';
            $buildingName = $assignment->box?->building?->name;

            // Event "wprowadzony do boksu"
            if ($filter->dateInRange($assignment->assigned_at)) {
                $out[] = new HorseTimelineEntry(
                    kind: HorseTimelineEntry::KIND_BOX,
                    subkind: 'assigned',
                    occurredAt: $assignment->assigned_at,
                    sourceId: (string) $assignment->id.':assigned',
                    actorRole: HorseTimelineEntry::ACTOR_STABLE,
                    actorName: null,
                    title: $boxName,
                    description: $buildingName,
                    costCents: null,
                    payload: [
                        'box_id' => $assignment->box_id,
                        'reason' => $assignment->reason,
                    ],
                );
            }

            // Event "opuszczony boks" — jeśli vacated_at ustawione
            if ($assignment->vacated_at !== null && $filter->dateInRange($assignment->vacated_at)) {
                $out[] = new HorseTimelineEntry(
                    kind: HorseTimelineEntry::KIND_BOX,
                    subkind: 'vacated',
                    occurredAt: $assignment->vacated_at,
                    sourceId: (string) $assignment->id.':vacated',
                    actorRole: HorseTimelineEntry::ACTOR_STABLE,
                    actorName: null,
                    title: $boxName,
                    description: $buildingName,
                    costCents: null,
                    payload: [
                        'box_id' => $assignment->box_id,
                        'duration_days' => $assignment->durationDays(),
                    ],
                );
            }
        }

        return $out;
    }

    /**
     * @return list<HorseTimelineEntry>
     */
    private function collectWeights(Horse $horse, HorseTimelineFilter $filter): array
    {
        $query = HorseWeightMeasurement::query()->where('horse_id', $horse->id);
        $this->applyDateRange($query, 'measured_at', $filter);

        $out = [];
        foreach ($query->get() as $measurement) {
            $out[] = new HorseTimelineEntry(
                kind: HorseTimelineEntry::KIND_WEIGHT,
                subkind: 'measured',
                occurredAt: $measurement->measured_at,
                sourceId: (string) $measurement->id,
                actorRole: HorseTimelineEntry::ACTOR_STABLE,
                actorName: null,
                title: (string) $measurement->weight_kg.' kg',
                description: $measurement->notes !== null ? (string) $measurement->notes : null,
                costCents: null,
                payload: [
                    'weight_kg' => (float) $measurement->weight_kg,
                    'girth_cm' => $measurement->girth_cm !== null ? (float) $measurement->girth_cm : null,
                ],
            );
        }

        return $out;
    }

    /**
     * @return list<HorseTimelineEntry>
     */
    private function collectActivities(Horse $horse, HorseTimelineFilter $filter): array
    {
        $query = StableActivity::query()
            ->with('specialist')
            ->where('horse_id', $horse->id);
        $this->applyDateRange($query, 'performed_at', $filter);

        $out = [];
        foreach ($query->get() as $activity) {
            $out[] = new HorseTimelineEntry(
                kind: HorseTimelineEntry::KIND_ACTIVITY,
                subkind: $activity->type->value,
                occurredAt: $activity->performed_at,
                sourceId: (string) $activity->id,
                actorRole: HorseTimelineEntry::ACTOR_STABLE,
                actorName: $activity->performedByLabel(),
                title: (string) ($activity->summary ?? $activity->type->value),
                description: $activity->details !== null ? (string) $activity->details : null,
                costCents: $activity->cost_cents !== null ? (int) $activity->cost_cents : null,
                payload: [],
            );
        }

        return $out;
    }

    /**
     * Zdjęcia używają `created_at` jako occurred_at — moment uploadu.
     *
     * @return list<HorseTimelineEntry>
     */
    private function collectPhotos(Horse $horse, HorseTimelineFilter $filter): array
    {
        $query = HorsePhoto::query()->where('horse_id', $horse->id);
        $this->applyDateRange($query, 'created_at', $filter);

        $out = [];
        foreach ($query->get() as $photo) {
            $actorRole = $photo->uploaded_by_role === 'client'
                ? HorseTimelineEntry::ACTOR_OWNER
                : HorseTimelineEntry::ACTOR_STABLE;

            $out[] = new HorseTimelineEntry(
                kind: HorseTimelineEntry::KIND_PHOTO,
                subkind: 'added',
                occurredAt: $photo->created_at,
                sourceId: (string) $photo->id,
                actorRole: $actorRole,
                actorName: null,
                title: $photo->caption !== null && $photo->caption !== ''
                    ? (string) $photo->caption
                    : (string) $photo->original_name,
                description: null,
                costCents: null,
                payload: [
                    'mime' => $photo->mime,
                    'size_bytes' => (int) $photo->size_bytes,
                ],
            );
        }

        return $out;
    }

    /**
     * @return list<HorseTimelineEntry>
     */
    private function collectDocuments(Horse $horse, HorseTimelineFilter $filter): array
    {
        $query = HorseDocument::query()->where('horse_id', $horse->id);
        $this->applyDateRange($query, 'created_at', $filter);

        $out = [];
        foreach ($query->get() as $doc) {
            $actorRole = $doc->uploaded_by_role === 'client'
                ? HorseTimelineEntry::ACTOR_OWNER
                : HorseTimelineEntry::ACTOR_STABLE;

            $out[] = new HorseTimelineEntry(
                kind: HorseTimelineEntry::KIND_DOCUMENT,
                subkind: $doc->kind->value,
                occurredAt: $doc->created_at,
                sourceId: (string) $doc->id,
                actorRole: $actorRole,
                actorName: null,
                title: (string) $doc->name,
                description: $doc->description !== null ? (string) $doc->description : null,
                costCents: null,
                payload: [
                    'valid_until' => $doc->valid_until?->toDateString(),
                    'mime' => $doc->mime,
                    'size_bytes' => (int) $doc->size_bytes,
                ],
            );
        }

        return $out;
    }

    /**
     * Applies from/to date range na query.
     */
    private function applyDateRange(Builder $query, string $column, HorseTimelineFilter $filter): void
    {
        if ($filter->from !== null) {
            $query->where($column, '>=', $filter->from);
        }
        if ($filter->to !== null) {
            $query->where($column, '<=', $filter->to);
        }
    }
}
