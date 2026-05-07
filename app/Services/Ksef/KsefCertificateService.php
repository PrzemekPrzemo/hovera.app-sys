<?php

declare(strict_types=1);

namespace App\Services\Ksef;

/**
 * Parsuje i waliduje certyfikaty PFX/P12 oraz pary PEM (.crt + .key).
 *
 * Adapted from Billu-System patterns (KsefCertificateService v4.0).
 * Skupiamy się na trzech rodzajach certyfikatów akceptowanych przez KSeF:
 *   1. Podpis kwalifikowany (PESEL na osobę fizyczną w PFX)
 *   2. Pieczęć elektroniczna (NIP na firmę w PFX)
 *   3. Certyfikat KSeF (wystawiany przez KSeF — separate .crt + .key)
 *
 * Wszystkie metody są statyczne — żadnego stanu, można wołać z dowolnego
 * miejsca (settings page upload, signing service, validate command).
 */
class KsefCertificateService
{
    /**
     * Parse PFX/P12 file. Zwraca info-array z subject CN, NIP, PESEL,
     * fingerprint, daty ważności, typ certyfikatu.
     *
     * @return array{
     *   subject_cn:string, subject_nip:?string, subject_pesel:?string,
     *   issuer:string, serial_number:string, fingerprint:string,
     *   valid_from:string, valid_to:string, is_expired:bool,
     *   days_until_expiry:int, cert_type:string,
     * }
     *
     * @throws \RuntimeException gdy hasło niepoprawne lub plik niepoprawny
     */
    public static function parsePfx(string $pfxData, string $password): array
    {
        $certs = [];
        if (! openssl_pkcs12_read($pfxData, $certs, $password)) {
            throw new \RuntimeException(
                'Nie można odczytać certyfikatu PFX. Sprawdź hasło. OpenSSL: '
                .(openssl_error_string() ?: 'unknown'),
            );
        }
        if (empty($certs['cert'])) {
            throw new \RuntimeException('Plik PFX nie zawiera certyfikatu publicznego.');
        }
        if (empty($certs['pkey'])) {
            throw new \RuntimeException('Plik PFX nie zawiera klucza prywatnego.');
        }

        return self::parsePemCert($certs['cert'], hasPrivateKey: true);
    }

    /**
     * Parse separate .crt (PEM) + .key (PEM) — używane dla KSeF-issued
     * certyfikatów lub electronic seals nieopakowanych w PFX.
     *
     * @return array<string,mixed>
     *
     * @throws \RuntimeException
     */
    public static function parsePemPair(string $certPem, string $keyPem, ?string $password = null): array
    {
        $certResource = openssl_x509_read($certPem);
        if (! $certResource) {
            throw new \RuntimeException('Nie można odczytać certyfikatu .crt. Sprawdź format pliku.');
        }
        $privateKey = openssl_pkey_get_private($keyPem, $password ?? '');
        if (! $privateKey) {
            throw new \RuntimeException('Nie można odczytać klucza prywatnego .key. Sprawdź hasło lub format.');
        }
        if (! openssl_x509_check_private_key($certResource, $privateKey)) {
            throw new \RuntimeException('Certyfikat i klucz prywatny nie tworzą pary.');
        }

        return self::parsePemCert($certPem, hasPrivateKey: true);
    }

    /**
     * Parse pojedynczy X.509 cert w PEM format.
     *
     * @return array<string,mixed>
     */
    public static function parsePemCert(string $certPem, bool $hasPrivateKey = false): array
    {
        $certResource = openssl_x509_read($certPem);
        if (! $certResource) {
            throw new \RuntimeException('Nie można odczytać certyfikatu X.509.');
        }
        $info = openssl_x509_parse($certResource);
        if (! $info) {
            throw new \RuntimeException('Nie można sparsować certyfikatu X.509.');
        }

        $nip = self::extractNip($info);
        $pesel = self::extractPesel($info);
        $fingerprint = openssl_x509_fingerprint($certResource, 'sha256') ?: '';

        $certType = match (true) {
            self::looksLikeKsefIssued($info) => 'ksef',
            $nip !== null && $pesel === null => 'seal',
            default => 'personal',
        };

        return [
            'subject_cn' => $info['subject']['CN'] ?? $info['subject']['O'] ?? 'Unknown',
            'subject_nip' => $nip,
            'subject_pesel' => $pesel,
            'issuer' => trim(($info['issuer']['O'] ?? '').' '.($info['issuer']['CN'] ?? '')),
            'serial_number' => (string) ($info['serialNumber'] ?? $info['serialNumberHex'] ?? ''),
            'fingerprint' => $fingerprint,
            'valid_from' => date('Y-m-d H:i:s', $info['validFrom_time_t']),
            'valid_to' => date('Y-m-d H:i:s', $info['validTo_time_t']),
            'is_expired' => $info['validTo_time_t'] < time(),
            'days_until_expiry' => max(0, (int) (($info['validTo_time_t'] - time()) / 86400)),
            'cert_type' => $certType,
            'has_private_key' => $hasPrivateKey,
        ];
    }

    /**
     * Sprawdź sumę kontrolną NIP (Polska 10-cyfrowa).
     */
    public static function isValidNip(string $nip): bool
    {
        $nip = preg_replace('/[^0-9]/', '', $nip);
        if (strlen((string) $nip) !== 10) {
            return false;
        }
        $weights = [6, 5, 7, 2, 3, 4, 5, 6, 7];
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += $weights[$i] * (int) $nip[$i];
        }

        return ($sum % 11) === (int) $nip[9];
    }

    /**
     * Wyciąga NIP z subject pól (kilka konwencji w wydawanych certyfikatach).
     */
    private static function extractNip(array $info): ?string
    {
        $serial = (string) ($info['subject']['serialNumber'] ?? '');
        if (preg_match('/NIP[:\-]?\s*(\d{10})/', $serial, $m)) {
            return $m[1];
        }
        // organizationIdentifier może mieć "VATPL-1234567890" lub goły NIP
        $orgId = (string) (
            $info['subject']['organizationIdentifier']
            ?? $info['subject']['2.5.4.97']
            ?? ''
        );
        if (preg_match('/VATPL[:\-]?\s*(\d{10})/', $orgId, $m)) {
            return $m[1];
        }
        if (preg_match('/^\d{10}$/', $orgId) && self::isValidNip($orgId)) {
            return $orgId;
        }
        // O field często zawiera "Firma X NIP: 1234567890"
        $org = (string) ($info['subject']['O'] ?? '');
        if (preg_match('/NIP[:\s\-]*(\d{10})/', $org, $m)) {
            return $m[1];
        }
        // Fallback: szukaj 10 cyfr w dowolnym subject string z poprawnym checksum
        foreach ((array) $info['subject'] as $value) {
            if (is_string($value) && preg_match('/\b(\d{10})\b/', $value, $m)) {
                if (self::isValidNip($m[1])) {
                    return $m[1];
                }
            }
        }

        return null;
    }

    private static function extractPesel(array $info): ?string
    {
        $serial = (string) ($info['subject']['serialNumber'] ?? '');
        if (preg_match('/PESEL[:\-]?\s*(\d{11})/', $serial, $m)) {
            return $m[1];
        }
        if (preg_match('/^\d{11}$/', $serial)) {
            return $serial;
        }

        return null;
    }

    private static function looksLikeKsefIssued(array $info): bool
    {
        $issuer = (string) ($info['issuer']['O'] ?? '');
        if (stripos($issuer, 'KSeF') !== false || stripos($issuer, 'Ministerstwo Finans') !== false) {
            return true;
        }

        return false;
    }
}
