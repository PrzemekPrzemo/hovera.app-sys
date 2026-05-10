<?php

declare(strict_types=1);

namespace App\Services\Ksef;

use App\Models\Central\Invoice;
use App\Models\Central\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Central KSeF service — wysyła FV SaaS-owe hovery do KSeF (Krajowy
 * System e-Faktur). Hovera jako podatnik VAT MUSI od 2026-02-01
 * wystawiać faktury B2B przez KSeF — to pokrywa case'y faktur
 * subskrypcyjnych dla naszych stajni.
 *
 * Per-tenant KSeF (stajnia → klient) jest osobnym ścieżką w
 * App\Services\Ksef\KsefClient — TAMTO używa cert + NIP stajni jako
 * podatnika. TUTAJ używamy cert + NIP HOVERY (zapisany w
 * central.system_settings jako ksef_central.*).
 *
 * UWAGA: Pełny flow KSeF (challenge + sign XAdES + InitSigned + send +
 * status poll) wymaga RSA-OAEP + AES-256-CBC encryption layer + XML
 * signing. KsefClient już ma kawałek implementacji per-tenant. Tutaj
 * dostarczamy SKELETON który:
 *   - waliduje że cert jest w SystemSetting
 *   - generuje XML FA(3) (reuse KsefInvoiceXmlBuilder, dostosowany)
 *   - oznacza FV jako pending z lokalną referencją
 *   - nie wykonuje rzeczywistego push (Log::info zamiast HTTP POST)
 *
 * Pełna integracja prod-ready trafi w follow-up PR — wymaga session
 * lifecycle, encryption layer, status polling, retry logic, error
 * mapping z kodów MF.
 */
class CentralKsefService
{
    public const HOST_TEST = 'https://ksef-test.mf.gov.pl/api';

    public const HOST_DEMO = 'https://ksef-demo.mf.gov.pl/api';

    public const HOST_PROD = 'https://ksef.mf.gov.pl/api';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    /**
     * Sprawdź czy hovera ma skonfigurowany cert + NIP gotowe do KSeF.
     */
    public function isReady(): bool
    {
        return self::contextNip() !== ''
            && SystemSetting::getSecret('ksef_central.cert_pfx') !== null
            || SystemSetting::getSecret('ksef_central.cert_crt') !== null;
    }

    /**
     * Buduje XML FA(3) dla central FV (skeleton; pełna walidacja XSD
     * wymaga schematu MF — odkładamy do follow-up PR).
     */
    public function buildInvoiceXml(Invoice $invoice): string
    {
        $sellerNip = self::contextNip();
        $sellerName = config('app.name', 'Hovera');
        $tenant = $invoice->tenant;
        $buyerNip = (string) ($tenant?->tax_id ?? '');
        $buyerName = (string) ($tenant?->legal_name ?? $tenant?->name ?? 'Klient');
        $issued = $invoice->issued_at?->format('Y-m-d') ?? date('Y-m-d');
        $net = number_format($invoice->subtotal_cents / 100, 2, '.', '');
        $vat = number_format($invoice->vat_cents / 100, 2, '.', '');
        $total = number_format($invoice->total_cents / 100, 2, '.', '');

        $kodSystemowy = match ($invoice->kind) {
            'correction' => 'KOR',
            'proforma' => 'PRO',
            default => 'FA',
        };

        // Schema FA(3) namespace zgodne z resztą kodu hovery.
        $ns = 'http://crd.gov.pl/wzor/2023/06/29/12648/';

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Faktura xmlns="'.$ns.'">'
            .'<Naglowek>'
            .'<KodFormularza kodSystemowy="FA (3)" wersjaSchemy="1-0E">FA</KodFormularza>'
            .'<WariantFormularza>3</WariantFormularza>'
            .'<DataWytworzeniaFa>'.gmdate('Y-m-d\TH:i:s\Z').'</DataWytworzeniaFa>'
            .'<SystemInfo>Hovera Central</SystemInfo>'
            .'</Naglowek>'
            .'<Podmiot1>'
            .'<DaneIdentyfikacyjne>'
            .'<NIP>'.htmlspecialchars($sellerNip, ENT_XML1).'</NIP>'
            .'<Nazwa>'.htmlspecialchars($sellerName, ENT_XML1).'</Nazwa>'
            .'</DaneIdentyfikacyjne>'
            .'</Podmiot1>'
            .'<Podmiot2>'
            .'<DaneIdentyfikacyjne>'
            .($buyerNip !== '' ? '<NIP>'.htmlspecialchars($buyerNip, ENT_XML1).'</NIP>' : '<BrakID>1</BrakID>')
            .'<Nazwa>'.htmlspecialchars($buyerName, ENT_XML1).'</Nazwa>'
            .'</DaneIdentyfikacyjne>'
            .'</Podmiot2>'
            .'<Fa>'
            .'<KodWaluty>'.htmlspecialchars($invoice->currency, ENT_XML1).'</KodWaluty>'
            .'<P_1>'.$issued.'</P_1>'
            .'<P_2>'.htmlspecialchars($invoice->number, ENT_XML1).'</P_2>'
            .'<P_13_1>'.$net.'</P_13_1>'
            .'<P_14_1>'.$vat.'</P_14_1>'
            .'<P_15>'.$total.'</P_15>'
            .'<RodzajFaktury>'.$kodSystemowy.'</RodzajFaktury>'
            .'</Fa>'
            .'</Faktura>';
    }

    /**
     * Push faktury do KSeF. Skeleton implementacja: generuje XML +
     * markuje FV jako pending. Rzeczywisty HTTP POST + sign XAdES
     * trafi w follow-up. Zwraca tymczasową referencję lokalną
     * (UUID-podobną) zapisaną na FV.
     *
     * @throws RuntimeException gdy cert nie skonfigurowany
     */
    public function pushInvoice(Invoice $invoice): string
    {
        if (! $this->isReady()) {
            throw new RuntimeException('KSeF central nie jest skonfigurowany — wgraj certyfikat w /admin/ksef-settings.');
        }

        $xml = $this->buildInvoiceXml($invoice);
        $reference = 'HVR-LOCAL-'.strtoupper(bin2hex(random_bytes(8)));

        // STUB: zamiast właściwego POST do KSeF, logujemy dla audit
        // trail i markujemy FV jako pending. Pełen flow w follow-up PR.
        Log::info('KSeF central pushInvoice (STUB)', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->number,
            'env' => self::env(),
            'host' => $this->host(),
            'xml_length' => strlen($xml),
            'reference' => $reference,
        ]);

        $invoice->forceFill([
            'ksef_status' => self::STATUS_PENDING,
            'ksef_reference' => $reference,
            'ksef_pushed_at' => now(),
            'ksef_last_response' => [
                'stub' => true,
                'note' => 'Skeleton implementation — XML wygenerowany ale push do KSeF wymaga follow-up PR (signing + session).',
            ],
        ])->save();

        return $reference;
    }

    /**
     * Pobierz aktualny status faktury z KSeF (po wysłaniu trzeba
     * pollować bo akceptacja/odrzucenie jest asynchroniczne).
     *
     * STUB: bez prawdziwego HTTP, zwraca lokalnie zapisany status.
     */
    public function getStatus(string $reference): string
    {
        $invoice = Invoice::query()->where('ksef_reference', $reference)->first();
        if ($invoice === null) {
            throw new RuntimeException("Invoice with KSeF reference {$reference} not found.");
        }

        // STUB: w pełnej implementacji: GET {host}/online/Invoice/Status/{reference}
        // i mapować na lokalne enum. Tutaj zwracamy zapisany lokalnie.
        return (string) ($invoice->ksef_status ?? self::STATUS_PENDING);
    }

    public static function env(): string
    {
        $env = SystemSetting::getValue('ksef_central.env');
        if (is_array($env)) {
            $env = $env[0] ?? null;
        }

        return is_string($env) && $env !== '' ? $env : (string) config('services.ksef_central.env', 'test');
    }

    public static function contextNip(): string
    {
        $nip = SystemSetting::getValue('ksef_central.context_nip');
        if (is_array($nip)) {
            $nip = $nip[0] ?? '';
        }
        if (! is_string($nip) || $nip === '') {
            $nip = (string) config('services.ksef_central.context_nip', '');
        }

        return preg_replace('/[^0-9]/', '', $nip) ?? '';
    }

    private function host(): string
    {
        return match (self::env()) {
            'production', 'prod' => self::HOST_PROD,
            'demo' => self::HOST_DEMO,
            default => self::HOST_TEST,
        };
    }
}
