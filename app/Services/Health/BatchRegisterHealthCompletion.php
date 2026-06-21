<?php

declare(strict_types=1);

namespace App\Services\Health;

use App\Enums\HealthRecordType;
use App\Models\Tenant\HealthRecord;
use App\Services\TenantAuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bulk-rejestracja wykonania zabiegu na grupie HealthRecord. Wzorzec
 * typowego dnia weterynarii: vet przyjeżdża, szczepi 8 koni tym samym
 * preparatem. Pojedyncze klikanie „nowy wpis" dla każdego z 8 = 8 min.
 *
 * Selektujesz 8 nadchodzących (due) wpisów na liście → bulk action
 * „Zarejestruj wykonanie" → wpisujesz wspólny `performed_at`, `summary`,
 * `specialist_id`, `next_due_at` → service tworzy 8 nowych follow-up
 * HealthRecord wpisów (po jednym per original — używając jego horse_id
 * i type), nie modyfikuje original'ów (historia zachowana).
 *
 * Transakcyjne na tenant connection — jeśli 1 z 8 failuje (np. invalid
 * fk), wszystkie 8 cofnięte (atomicity).
 */
class BatchRegisterHealthCompletion
{
    public function __construct(
        private readonly TenantAuditLogger $audit,
    ) {}

    /**
     * @param  Collection<int,HealthRecord>  $originals
     * @param  array{performed_at:string|\DateTimeInterface, summary:string,
     *               specialist_id?:?string, performed_by?:?string,
     *               next_due_at?:?string, cost_cents?:?int}  $data
     * @return array{created_count:int, created_ids:list<string>}
     */
    public function execute(Collection $originals, array $data): array
    {
        $createdIds = [];

        DB::connection('tenant')->transaction(function () use ($originals, $data, &$createdIds) {
            foreach ($originals as $original) {
                /** @var HealthRecord $original */
                $new = HealthRecord::create([
                    'horse_id' => $original->horse_id,
                    'type' => $this->resolveType($original->type),
                    'performed_at' => $data['performed_at'],
                    'specialist_id' => $data['specialist_id'] ?? null,
                    'performed_by' => $data['performed_by'] ?? null,
                    'summary' => $data['summary'],
                    'details' => null, // bulk = tylko summary, details wymagałby per-record
                    'next_due_at' => $data['next_due_at'] ?? null,
                    'cost_cents' => $data['cost_cents'] ?? null,
                ]);

                $createdIds[] = (string) $new->id;

                $this->audit->record(
                    'health.batch_completed',
                    'HealthRecord',
                    (string) $new->id,
                    [
                        'horse_id' => $original->horse_id,
                        'type' => $new->type instanceof HealthRecordType ? $new->type->value : (string) $new->type,
                        'follow_up_to_id' => (string) $original->id,
                    ],
                );
            }
        });

        return [
            'created_count' => count($createdIds),
            'created_ids' => $createdIds,
        ];
    }

    /**
     * HealthRecord.type może być enum'em lub stringiem (legacy data).
     * HealthRecord::create() przyjmuje string|enum, tu normalizujemy
     * żeby DB zawsze dostała string zgodny z fillable casts.
     */
    private function resolveType(HealthRecordType|string|null $type): string
    {
        if ($type instanceof HealthRecordType) {
            return $type->value;
        }

        return (string) $type;
    }
}
