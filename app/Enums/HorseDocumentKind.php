<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Kategorie dokumentów per koń. Free-form `Other` na koniec — nie blokujemy
 * stajni gdy chce trzymać coś nietypowego.
 */
enum HorseDocumentKind: string
{
    case Passport = 'passport';
    case Contract = 'contract';
    case Insurance = 'insurance';
    case VaccineBook = 'vaccine_book';
    case OwnershipProof = 'ownership_proof';
    case CompetitionLicence = 'competition_licence';
    case VetCertificate = 'vet_certificate';
    case Other = 'other';

    public function label(): string
    {
        return __('enums.horse_document_kind.'.$this->value);
    }

    public function icon(): string
    {
        return match ($this) {
            self::Passport => '📕',
            self::Contract => '📄',
            self::Insurance => '🛡️',
            self::VaccineBook => '💉',
            self::OwnershipProof => '📜',
            self::CompetitionLicence => '🏆',
            self::VetCertificate => '🩺',
            self::Other => '📁',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->icon().' '.$c->label()])
            ->all();
    }
}
