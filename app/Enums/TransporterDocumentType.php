<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Typy dokumentów wymaganych do weryfikacji konta transportera koni
 * w Polsce (Przewóz Wewnątrzwspólnotowy Zwierząt Żywych — PWL).
 *
 * Lista jest zgodna z polskim reżimem prawnym przewoźnika konia:
 *
 *  - Rozporządzenie Rady (WE) nr 1/2005 z 22 grudnia 2004 r. w sprawie
 *    ochrony zwierząt podczas transportu — art. 6 (kompetencje kierowców
 *    i obsługujących), art. 18 (zatwierdzenie środka transportu < 8h),
 *    art. 19 (zatwierdzenie środka transportu > 8h).
 *  - Ustawa z 21 sierpnia 1997 r. o ochronie zwierząt + Ustawa
 *    z 11 marca 2004 r. o ochronie zdrowia zwierząt oraz zwalczaniu
 *    chorób zakaźnych zwierząt — książka mycia i dezynfekcji.
 *  - Ustawa z 6 września 2001 r. o transporcie drogowym + Rozp. WE
 *    1071/2009 — zezwolenie na wykonywanie zawodu przewoźnika drogowego
 *    (GITD / starosta).
 *
 * Verify flow: TransporterDocument::status per dokument; Tenant.verification_status
 * agreguje stan. Master admin nie może zatwierdzić tenanta zanim wszystkie
 * `isRequiredForPwlVerification()` typy nie mają statusu `verified`.
 *
 * Patrz docs/TRANSPORT.md (§1.x onboarding, §13 master admin verification).
 *
 * Legacy: `insurance_ocp` (OC przewoźnika) zostało zastąpione przez bardziej
 * jednoznaczny `carrier_liability_insurance` — istnieje migration data fix
 * mapping starych rekordów. `insurance_ocp` jako case TYMCZASOWO zostaje
 * dla wstecznej kompatybilności kodowej, ale jest oznaczony jako deprecated
 * i wyłączony z PWL required set.
 */
enum TransporterDocumentType: string
{
    // === Identyfikacja firmy (legacy, nie-PWL) ===
    case CompanyRegistration = 'company_registration';        // KRS / CEIDG

    // === Legacy — zachowane dla wstecznej kompatybilności, NIE wymagane do PWL ===
    /** @deprecated Zastąpione przez CarrierLiabilityInsurance. */
    case InsuranceOcp = 'insurance_ocp';
    case InsuranceOcs = 'insurance_ocs';                       // OC cargo (opcjonalne)
    /** @deprecated Zastąpione przez PwlVehicleApprovalCertificate. */
    case AnimalTransportCert = 'animal_transport_cert';
    /** @deprecated Zostawione jako opcjonalna metadana — nie zastępuje świadectwa PWL. */
    case VehicleRegistration = 'vehicle_registration';

    // === PWL — wymagane do weryfikacji konta przewoźnika koni w PL ===
    case RoadCarrierLicense = 'road_carrier_license';
    case PwlAuthorizationT1 = 'pwl_authorization_type1';
    case PwlAuthorizationT2 = 'pwl_authorization_type2';
    case PwlDriverHandlerCertificate = 'pwl_driver_handler_certificate';
    case PwlVehicleApprovalCertificate = 'pwl_vehicle_approval_certificate';
    case WashDisinfectionLog = 'wash_disinfection_log';
    case CarrierLiabilityInsurance = 'carrier_liability_insurance';

    case Other = 'other';

    public function label(): string
    {
        return __('enums.transporter_document_type.'.$this->value);
    }

    public function description(): string
    {
        return __('enums.transporter_document_type.'.$this->value.'_description');
    }

    /**
     * Czy dokument musi być wgrany przed `verification_status = under_review`.
     * Stara reguła — utrzymujemy dla wstecznej kompatybilności kodu, który
     * iteruje po `requiredCases()`. Nowy, ściślejszy zestaw to `pwlRequiredCases()`.
     */
    public function isRequired(): bool
    {
        return $this->isRequiredForPwlVerification() || $this === self::CompanyRegistration;
    }

    /**
     * Czy dokument jest wymagany do weryfikacji konta PWL (przewóz koni).
     *
     * Reguła PWL: KRS/CEIDG + zezwolenie na zawód + (T1 LUB T2) + świadectwo
     * kompetencji kierowców + świadectwo pojazdu + książka mycia + OC.
     *
     * UWAGA: T1 i T2 są opcją alternatywną — transporter wybiera dokładnie
     * jeden zależnie od długości transportów (< 8h vs > 8h). `hasPwlAuthorization()`
     * w DocumentUploadService sprawdza obecność co najmniej jednego z dwóch.
     */
    public function isRequiredForPwlVerification(): bool
    {
        return match ($this) {
            self::RoadCarrierLicense,
            self::PwlAuthorizationT1,
            self::PwlAuthorizationT2,
            self::PwlDriverHandlerCertificate,
            self::PwlVehicleApprovalCertificate,
            self::WashDisinfectionLog,
            self::CarrierLiabilityInsurance => true,
            default => false,
        };
    }

    /**
     * Czy dokument jest oznaczony jako deprecated (nie pokazujemy w nowym
     * UI, ale stare rekordy w DB wciąż mogą istnieć).
     */
    public function isDeprecated(): bool
    {
        return match ($this) {
            self::InsuranceOcp,
            self::AnimalTransportCert,
            self::VehicleRegistration => true,
            default => false,
        };
    }

    public function expiresByLaw(): bool
    {
        // Wszystkie dokumenty PWL mają termin ważności. KRS/CEIDG nie wygasa
        // (zmienia się tylko gdy firma traci NIP). Książka mycia w sensie
        // dokumentu fizycznego się nie odnawia, ale wpisy muszą być bieżące
        // — traktujemy expires_at jako "data ostatniego wpisu + 12 mies."
        return match ($this) {
            self::RoadCarrierLicense,
            self::PwlAuthorizationT1,
            self::PwlAuthorizationT2,
            self::PwlDriverHandlerCertificate,
            self::PwlVehicleApprovalCertificate,
            self::WashDisinfectionLog,
            self::CarrierLiabilityInsurance,
            self::AnimalTransportCert,
            self::InsuranceOcp,
            self::InsuranceOcs,
            self::VehicleRegistration => true,
            default => false,
        };
    }

    /**
     * Typy widoczne w UI uploadu (deprecated ukrywamy).
     *
     * @return list<self>
     */
    public static function uiCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $c) => ! $c->isDeprecated(),
        ));
    }

    /**
     * Stary alias — wszystkie required typy (PWL + KRS).
     *
     * @return list<self>
     */
    public static function requiredCases(): array
    {
        return array_values(array_filter(self::cases(), fn (self $c) => $c->isRequired()));
    }

    /**
     * Ścisły zestaw PWL bez T1/T2 jako pojedynczych slotów — bo te dwa
     * traktujemy jako alternatywę "co najmniej jeden". Reguła weryfikacji
     * obsługiwana w DocumentUploadService::hasAllRequired().
     *
     * @return list<self>
     */
    public static function pwlRequiredCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $c) => $c->isRequiredForPwlVerification(),
        ));
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::uiCases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }
}
