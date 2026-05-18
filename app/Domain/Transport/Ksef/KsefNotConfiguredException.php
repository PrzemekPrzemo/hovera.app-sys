<?php

declare(strict_types=1);

namespace App\Domain\Transport\Ksef;

use RuntimeException;

/**
 * Rzucany, gdy `TransporterKsefService` jest wywołany dla tenanta, który
 * nie ma kompletu danych KSeF (token + NIP + zweryfikowane konto).
 *
 * Treść wyjątku CELOWO nie zawiera tokenu ani jego fragmentu — patrz
 * `TransporterKsefService` (redactedTokenPreview() dla bezpiecznego
 * logowania ops). UI łapie ten wyjątek i pokazuje notification z
 * instrukcją "skonfiguruj KSeF w ustawieniach".
 */
class KsefNotConfiguredException extends RuntimeException
{
    public static function missingToken(): self
    {
        return new self('KSeF token is not configured for the current transporter.');
    }

    public static function missingNip(): self
    {
        return new self('KSeF NIP is not configured for the current transporter.');
    }

    public static function notEnabled(): self
    {
        return new self('KSeF integration is disabled for the current transporter.');
    }

    public static function tenantNotVerified(): self
    {
        return new self('Transporter account is not verified — KSeF submissions are blocked.');
    }
}
