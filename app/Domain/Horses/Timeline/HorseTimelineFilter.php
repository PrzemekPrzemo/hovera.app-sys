<?php

declare(strict_types=1);

namespace App\Domain\Horses\Timeline;

use Illuminate\Support\Carbon;

/**
 * Filtr dla HorseTimelineService::forHorse. Walidacja kinds + date range
 * + limit zrobiona w konstruktorze (defensive).
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 2".
 */
final readonly class HorseTimelineFilter
{
    /**
     * @param  list<string>  $kinds  Kinds do uwzględnienia. Pusta lista = wszystkie kinds.
     */
    public function __construct(
        public array $kinds = [],
        public ?Carbon $from = null,
        public ?Carbon $to = null,
        public int $limit = 200,
    ) {}

    /**
     * Helper: czy `kind` przechodzi filtr (pusty kinds = wszystkie pass).
     */
    public function allowsKind(string $kind): bool
    {
        if ($this->kinds === []) {
            return true;
        }

        return in_array($kind, $this->kinds, true);
    }

    /**
     * Helper: czy `occurredAt` mieści się w date range.
     */
    public function dateInRange(Carbon $occurredAt): bool
    {
        if ($this->from !== null && $occurredAt->lt($this->from)) {
            return false;
        }
        if ($this->to !== null && $occurredAt->gt($this->to)) {
            return false;
        }

        return true;
    }
}
