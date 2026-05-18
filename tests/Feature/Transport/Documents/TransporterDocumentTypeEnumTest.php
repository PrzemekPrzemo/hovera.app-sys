<?php

declare(strict_types=1);

namespace Tests\Feature\Transport\Documents;

use App\Enums\TransporterDocumentType;
use Tests\TestCase;

/**
 * Sanity testy enum'a — pilnujemy że lista PWL pozostaje pełna i że legacy
 * cases nie pojawiają się w nowym UI ani w PWL required.
 */
class TransporterDocumentTypeEnumTest extends TestCase
{
    public function test_all_pwl_required_cases_defined(): void
    {
        $pwl = collect(TransporterDocumentType::pwlRequiredCases())
            ->map(fn (TransporterDocumentType $c) => $c->value)
            ->all();

        $expected = [
            'road_carrier_license',
            'pwl_authorization_type1',
            'pwl_authorization_type2',
            'pwl_driver_handler_certificate',
            'pwl_vehicle_approval_certificate',
            'wash_disinfection_log',
            'carrier_liability_insurance',
        ];

        foreach ($expected as $value) {
            $this->assertContains($value, $pwl, "Missing PWL required case: {$value}");
        }
        $this->assertSame(count($expected), count($pwl));
    }

    public function test_legacy_cases_marked_deprecated(): void
    {
        $this->assertTrue(TransporterDocumentType::InsuranceOcp->isDeprecated());
        $this->assertTrue(TransporterDocumentType::AnimalTransportCert->isDeprecated());
        $this->assertTrue(TransporterDocumentType::VehicleRegistration->isDeprecated());

        // Niektóre legacy są utrzymane jako opcjonalne (OCS) — NIE deprecated.
        $this->assertFalse(TransporterDocumentType::InsuranceOcs->isDeprecated());
        $this->assertFalse(TransporterDocumentType::CompanyRegistration->isDeprecated());
    }

    public function test_deprecated_excluded_from_ui_cases(): void
    {
        $uiValues = collect(TransporterDocumentType::uiCases())
            ->map(fn ($c) => $c->value)->all();

        $this->assertNotContains('insurance_ocp', $uiValues);
        $this->assertNotContains('animal_transport_cert', $uiValues);
        $this->assertNotContains('vehicle_registration', $uiValues);

        $this->assertContains('road_carrier_license', $uiValues);
        $this->assertContains('carrier_liability_insurance', $uiValues);
    }

    public function test_options_uses_ui_cases_only(): void
    {
        $opts = TransporterDocumentType::options();
        $this->assertArrayHasKey('road_carrier_license', $opts);
        $this->assertArrayNotHasKey('insurance_ocp', $opts);
    }

    public function test_is_required_for_pwl_verification(): void
    {
        // KRS — wymagane przez isRequired() (legacy + nowy reżim), ale NIE
        // w pwlRequiredCases() (które trzyma tylko ścisłe PWL).
        $this->assertFalse(TransporterDocumentType::CompanyRegistration->isRequiredForPwlVerification());
        $this->assertTrue(TransporterDocumentType::CompanyRegistration->isRequired());

        // PWL → true
        $this->assertTrue(TransporterDocumentType::RoadCarrierLicense->isRequiredForPwlVerification());
        $this->assertTrue(TransporterDocumentType::PwlAuthorizationT1->isRequiredForPwlVerification());
        $this->assertTrue(TransporterDocumentType::PwlAuthorizationT2->isRequiredForPwlVerification());
        $this->assertTrue(TransporterDocumentType::PwlDriverHandlerCertificate->isRequiredForPwlVerification());
        $this->assertTrue(TransporterDocumentType::PwlVehicleApprovalCertificate->isRequiredForPwlVerification());
        $this->assertTrue(TransporterDocumentType::WashDisinfectionLog->isRequiredForPwlVerification());
        $this->assertTrue(TransporterDocumentType::CarrierLiabilityInsurance->isRequiredForPwlVerification());

        // Inne / legacy → false
        $this->assertFalse(TransporterDocumentType::InsuranceOcp->isRequiredForPwlVerification());
        $this->assertFalse(TransporterDocumentType::InsuranceOcs->isRequiredForPwlVerification());
        $this->assertFalse(TransporterDocumentType::Other->isRequiredForPwlVerification());
    }

    public function test_label_mapping_for_pwl_cases(): void
    {
        // PL locale jest defaultem testów. Etykieta dla T1 musi zawierać "Typ 1".
        // Nie sprawdzamy całej stringi (różni się między locale'ami) — tylko że
        // klucz translacji się rozwija (nie zwraca raw klucza).
        $label = TransporterDocumentType::PwlAuthorizationT1->label();
        $this->assertNotSame('enums.transporter_document_type.pwl_authorization_type1', $label);
        $this->assertStringContainsString('Typ 1', $label);
    }

    public function test_expires_by_law_for_pwl_cases(): void
    {
        $this->assertTrue(TransporterDocumentType::RoadCarrierLicense->expiresByLaw());
        $this->assertTrue(TransporterDocumentType::CarrierLiabilityInsurance->expiresByLaw());
        $this->assertTrue(TransporterDocumentType::WashDisinfectionLog->expiresByLaw());

        // KRS / CEIDG nie wygasa — to też utrzymujemy.
        $this->assertFalse(TransporterDocumentType::CompanyRegistration->expiresByLaw());
        $this->assertFalse(TransporterDocumentType::Other->expiresByLaw());
    }
}
