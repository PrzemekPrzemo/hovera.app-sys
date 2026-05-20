<?php

declare(strict_types=1);

namespace App\Domain\Transport\Verification;

/**
 * Wartość zwracana przez VerificationChecklistService — czysta DTO,
 * łatwa do renderowania w Blade i do asercji w testach.
 *
 *  - $items: każdy „slot" wymaganego dokumentu PLW z aktualnym statusem;
 *    klucz `type` to TransporterDocumentType ALBO null (dla alternatyw
 *    typu „T1 LUB T2" — wtedy `label` jest opisem zbiorczym).
 *  - $verifiedCount / $totalRequired: do paska postępu „X/Y zweryfikowanych".
 *  - $missingLabels: dokumenty brakujące lub nie-verified — do toastu
 *    blokady verify-tenant po stronie master admin'a.
 *
 * `VerificationChecklistItem` mieszka w osobnym pliku (PSR-4 wymaga
 * jednej klasy per plik) — patrz VerificationChecklistItem.php.
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
