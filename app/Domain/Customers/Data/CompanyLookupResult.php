<?php

declare(strict_types=1);

namespace App\Domain\Customers\Data;

/**
 * Znormalizowany wynik lookup'u danych firmy w publicznych rejestrach PL
 * (MF Biała Lista, KRS, w przyszłości CEIDG).
 */
final readonly class CompanyLookupResult
{
    /** @param array<string,mixed> $raw */
    public function __construct(
        public string $source,                     // 'mf' | 'krs' | 'ceidg'
        public ?string $name = null,                // pełna nazwa
        public ?string $taxId = null,               // NIP (znormalizowany — same cyfry)
        public ?string $regon = null,
        public ?string $krsNumber = null,
        public ?string $address = null,             // pełny adres jednym stringiem
        public ?string $status = null,              // 'czynny' / 'wykreślony' / ...
        public array $raw = [],
    ) {}
}
