<?php

declare(strict_types=1);

namespace App\Enums;

enum HealthRecordType: string
{
    case Vaccination = 'vaccination';
    case Deworming = 'deworming';
    case VetVisit = 'vet_visit';
    case Farrier = 'farrier';
    case Dentist = 'dentist';
    case CheckUp = 'check_up';
    case Medication = 'medication';
    case Other = 'other';

    public function label(): string
    {
        return __('enums.health_record_type.'.$this->value);
    }

    public function icon(): string
    {
        return match ($this) {
            self::Vaccination => 'heroicon-o-shield-check',
            self::Deworming => 'heroicon-o-bug-ant',
            self::VetVisit => 'heroicon-o-heart',
            self::Farrier => 'heroicon-o-wrench',
            self::Dentist => 'heroicon-o-mouth-open',
            self::CheckUp => 'heroicon-o-clipboard-document-check',
            self::Medication => 'heroicon-o-beaker',
            self::Other => 'heroicon-o-document',
        };
    }

    /**
     * Default suggestion for "next due" gap, in months. UI uses this to
     * pre-fill `next_due_at` based on `performed_at`. Caller can always
     * override; this is just a sensible default.
     */
    public function defaultFollowUpMonths(): ?int
    {
        return match ($this) {
            self::Vaccination => 12,   // most equine vaccines yearly
            self::Deworming => 3,      // standard dewormer cadence
            self::Farrier => 2,        // typical kucie
            self::Dentist => 12,
            self::CheckUp => 6,
            // No default for medication / vet_visit / other — too varied.
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }
}
