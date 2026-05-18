<?php

declare(strict_types=1);

namespace App\Domain\Transport\Verification;

use App\Enums\TransporterDocumentType;
use App\Models\Tenant\TransporterDocument;

/**
 * Składa checklistę dokumentów PWL z aktualnymi statusami z `transporter_documents`
 * (tenant DB). Używana:
 *
 *  - Po stronie transportera (panel /transport): widget „X/Y wgranych" pokazuje
 *    użytkownikowi co jeszcze brakuje.
 *  - Po stronie master admin'a (/admin TransporterResource): blokada akcji
 *    verify-tenant + lista brakujących dokumentów w toaście.
 *
 * Wymaga że TenantManager::setCurrent() zostało już wywołane przed tą metodą
 * — tabela `transporter_documents` jest per-tenant (osobna DB).
 */
class VerificationChecklistService
{
    /**
     * Buduje checklistę PWL — items z deterministycznej kolejności:
     *
     *  1. KRS / CEIDG (legacy required)
     *  2. Zezwolenie na zawód przewoźnika
     *  3. PWL T1/T2 (jeden slot, alternatywa)
     *  4. Świadectwo kierowców
     *  5. Świadectwo pojazdu
     *  6. Książka mycia
     *  7. OC przewoźnika
     */
    public function build(): VerificationChecklist
    {
        $docs = $this->loadLatestByType();

        $items = [];
        $items[] = $this->slotForType(TransporterDocumentType::CompanyRegistration, $docs);
        $items[] = $this->slotForType(TransporterDocumentType::RoadCarrierLicense, $docs);

        // Alternatywa T1/T2 — bierzemy verified jeśli istnieje, w przeciwnym wypadku
        // ten z wyższym priorytetem statusu.
        $items[] = $this->slotForAuthorizationAlternative($docs);

        $items[] = $this->slotForType(TransporterDocumentType::PwlDriverHandlerCertificate, $docs);
        $items[] = $this->slotForType(TransporterDocumentType::PwlVehicleApprovalCertificate, $docs);
        $items[] = $this->slotForType(TransporterDocumentType::WashDisinfectionLog, $docs);
        $items[] = $this->slotForType(TransporterDocumentType::CarrierLiabilityInsurance, $docs);

        $total = count($items);
        $verified = count(array_filter($items, fn (VerificationChecklistItem $i) => $i->isVerified()));
        $missing = array_map(
            fn (VerificationChecklistItem $i) => $i->label,
            array_filter($items, fn (VerificationChecklistItem $i) => ! $i->isVerified()),
        );

        return new VerificationChecklist(
            items: array_values($items),
            verifiedCount: $verified,
            totalRequired: $total,
            missingLabels: array_values($missing),
        );
    }

    /**
     * Czy checklista jest kompletna — wszystkie wymagane dokumenty status=verified.
     * Wrapper dla wygody w master admin'ie (TransporterResource::verify pre-check).
     */
    public function isComplete(): bool
    {
        return $this->build()->isComplete();
    }

    private function slotForType(
        TransporterDocumentType $type,
        array $docs,
    ): VerificationChecklistItem {
        $doc = $docs[$type->value] ?? null;

        return new VerificationChecklistItem(
            documentType: $type,
            label: $type->label(),
            status: $this->resolveStatus($doc),
            document: $doc,
        );
    }

    /**
     * Specjalny slot — albo T1 albo T2 zalicza. Wybieramy ten ze „lepszym"
     * statusem: verified > pending > rejected > missing.
     *
     * @param  array<string, TransporterDocument|null>  $docs
     */
    private function slotForAuthorizationAlternative(array $docs): VerificationChecklistItem
    {
        $t1 = $docs[TransporterDocumentType::PwlAuthorizationT1->value] ?? null;
        $t2 = $docs[TransporterDocumentType::PwlAuthorizationT2->value] ?? null;

        $candidates = array_filter([$t1, $t2]);
        $best = null;
        $bestRank = -1;
        foreach ($candidates as $c) {
            $rank = $this->statusRank($this->resolveStatus($c));
            if ($rank > $bestRank) {
                $best = $c;
                $bestRank = $rank;
            }
        }

        $label = __('transport/documents.checklist.pwl_authorization_alternative');

        return new VerificationChecklistItem(
            documentType: $best?->document_type,
            label: $label,
            status: $best ? $this->resolveStatus($best) : 'missing',
            document: $best,
        );
    }

    private function resolveStatus(?TransporterDocument $doc): string
    {
        if (! $doc) {
            return 'missing';
        }

        return match ($doc->status) {
            TransporterDocument::STATUS_VERIFIED => 'verified',
            TransporterDocument::STATUS_REJECTED => 'rejected',
            default => 'pending',
        };
    }

    private function statusRank(string $status): int
    {
        return match ($status) {
            'verified' => 3,
            'pending' => 2,
            'rejected' => 1,
            default => 0,
        };
    }

    /**
     * @return array<string, TransporterDocument|null>
     */
    private function loadLatestByType(): array
    {
        $map = [];
        foreach (TransporterDocumentType::cases() as $type) {
            $map[$type->value] = TransporterDocument::query()
                ->where('document_type', $type->value)
                ->latest('id')
                ->first();
        }

        return $map;
    }
}
