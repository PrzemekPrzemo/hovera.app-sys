<?php

declare(strict_types=1);

namespace App\Domain\Transport\Verification;

use App\Enums\TransporterDocumentType;
use App\Models\Tenant\TransporterDocument;

/**
 * Pojedynczy wiersz checklisty. `documentType` może być null gdy slot
 * reprezentuje alternatywę (PLW T1 LUB T2).
 *
 * Wyodrębnione z VerificationChecklist.php — PSR-4 autoload wymaga jednej
 * klasy per plik. Wcześniej Composer optimized autoload (--optimize) na
 * prodzie nie zawsze widział drugą klasę → fatal „Class not found".
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
