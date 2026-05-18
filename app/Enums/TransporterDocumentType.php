<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Typy dokumentów wymaganych do weryfikacji konta transportera.
 * Patrz docs/TRANSPORT.md (verification flow dorobiony po feedbacku produkcyjnym).
 *
 * isRequired() oznacza czy typ musi być wgrany przed flipem statusu na
 * under_review. 'other' jest opcjonalny — slot na custom dokumenty
 * (np. zaświadczenie z PIP, kopia licencji wspólnotowej).
 */
enum TransporterDocumentType: string
{
    case CompanyRegistration = 'company_registration';        // KRS / CEIDG
    case AnimalTransportCert = 'animal_transport_cert';        // Świadectwo z ustawy 1/2005
    case InsuranceOcp = 'insurance_ocp';                       // OC przewoźnika
    case InsuranceOcs = 'insurance_ocs';                       // OC cargo (ładunku)
    case VehicleRegistration = 'vehicle_registration';          // Dowód rejestracyjny pojazdu
    case Other = 'other';

    public function label(): string
    {
        return __('enums.transporter_document_type.'.$this->value);
    }

    public function description(): string
    {
        return __('enums.transporter_document_type.'.$this->value.'_description');
    }

    public function isRequired(): bool
    {
        return $this !== self::Other;
    }

    public function expiresByLaw(): bool
    {
        // Świadectwo transportu zwierząt ważne 5 lat; OC pojazdu rocznie;
        // certyfikat pojazdu okresowo — wszystkie wymagają expires_at.
        // Rejestracja firmy nie wygasa (zmienia się tylko gdy firma traci NIP).
        return match ($this) {
            self::AnimalTransportCert,
            self::InsuranceOcp,
            self::InsuranceOcs,
            self::VehicleRegistration => true,
            default => false,
        };
    }

    /** @return list<self> */
    public static function requiredCases(): array
    {
        return array_values(array_filter(self::cases(), fn (self $c) => $c->isRequired()));
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }
}
