<?php

declare(strict_types=1);

namespace App\Domain\Transport\Verification;

use App\Enums\TransporterDocumentType;
use App\Models\Tenant\TransporterDocument;

/**
 * Wartość zwracana przez VerificationChecklistService — czysta DTO,
 * łatwa do renderowania w Blade i do asercji w testach.
 *
 *  - $items: każdy „slot" wymaganego dokumentu PWL z aktualnym statusem;
 *    klucz `type` to TransporterDocumentType ALBO null (dla alternatyw
 *    typu „T1 LUB T2" — wtedy `label` jest opisem zbiorczym).
 *  - $verifiedCount / $totalRequired: do paska postępu „X/Y zweryfikowanych".
 *  - $missingLabels: dokumenty brakujące lub nie-verified — do toastu
 *    blokady verify-tenant po stronie master admin'a.
 */
final readonly class VerificationChecklist
{
    /**
     * @param  list<VerificationChecklistItem>  $items
     * @param  list<string>  $missingLabels
     */
    public function __construct(
        public array $items,
        public int $verifiedCount,
        public int $totalRequired,
        public array $missingLabels,
    ) {}

    public function isComplete(): bool
    {
        return $this->verifiedCount === $this->totalRequired;
    }
}

/**
 * Pojedynczy wiersz checklisty. `documentType` może być null gdy slot
 * reprezentuje alternatywę (T1 LUB T2).
 */
final readonly class VerificationChecklistItem
{
    public function __construct(
        public ?TransporterDocumentType $documentType,
        public string $label,
        public string $status,            // 'verified' | 'pending' | 'rejected' | 'missing'
        public ?TransporterDocument $document = null,
    ) {}

    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }
}
