<?php

declare(strict_types=1);

namespace App\Domain\Transport\Payments;

use App\Models\Tenant\Quote;

/**
 * Direct-charge payments MVP — patrz docs/TRANSPORT.md §13.
 *
 * Rozwija placeholdery w template'cie URL'a płatności. Hovera NIE pośredniczy
 * w płatnościach — to tylko ułatwienie dla transportera, żeby nie wklejał
 * URL'a ręcznie na każdej ofercie.
 *
 * Wspierane placeholdery (URL-encoded):
 *   - {quote_number}     numer oferty (np. OF/2026/05/0001)
 *   - {gross_total_pln}  kwota brutto bez separatorów (np. 1694.66)
 *   - {customer_name}    nazwa klienta
 */
final class PaymentUrlTemplate
{
    /**
     * @param  array<string,string>  $extra  dodatkowe placeholdery (np. z atrybutów Quote'u)
     */
    public static function expand(string $template, Quote $quote, array $extra = []): string
    {
        $replacements = array_merge([
            '{quote_number}' => rawurlencode((string) $quote->number),
            '{gross_total_pln}' => rawurlencode(number_format((float) $quote->gross_total, 2, '.', '')),
            '{customer_name}' => rawurlencode((string) $quote->customer_name),
        ], $extra);

        return strtr($template, $replacements);
    }
}
