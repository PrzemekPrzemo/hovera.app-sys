<?php

declare(strict_types=1);

namespace App\Services\Ksef;

use App\Enums\InvoiceKind;
use App\Models\Tenant\Invoice;

/**
 * Faktura VAT RR (faktura rolnicza, art. 116 ustawy o VAT) — KSeF
 * FA_VAT_RR struktura logiczna v1-0E (2024/04/04/13150).
 *
 * Inaczej niż FA(3): NABYWCA (VAT-owiec) wystawia fakturę dla ROLNIKA
 * RYCZAŁTOWEGO. Stawka VAT zryczałtowana (7.00%), oświadczenie dostawcy
 * art. 116 obligatoryjne, RachunekBankowy do wypłaty wymagany.
 *
 * Spec verified against Billu-System reference (KsefVatRrInvoiceSendService).
 *
 * Use case Hovera: stable kupuje paszę / siano od rolnika ryczałtowego
 * (bez działalności VAT) — żeby móc odliczyć VAT, wystawia FV RR.
 *
 * Routing: `TenantKsefSubmissionService::buildXmlForKind` kieruje FvRr
 * tutaj, reszta InvoiceKind do `KsefInvoiceXmlBuilder` (FA(3)).
 */
class KsefRrInvoiceXmlBuilder
{
    private const SCHEMA_NS = 'http://crd.gov.pl/wzor/2024/04/04/13150/';

    /**
     * Mapping rolnik-specific fields w Invoice::metadata:
     *   - rolnik_pesel (string)             — fallback gdy brak NIP
     *   - rolnik_dok_tozsamosci_numer       — numer dokumentu tożsamości
     *   - rolnik_dok_tozsamosci_wydany_przez
     *   - rolnik_dok_tozsamosci_data_wyd    — YYYY-MM-DD
     *   - rolnik_rachunek_bankowy           — IBAN dla Platnosc
     *   - rolnik_oswiadczenie               — opcjonalny custom text;
     *     domyślnie używamy standardowego art. 116
     */
    public function build(Invoice $invoice): string
    {
        $this->assertIsRr($invoice);
        $invoice->loadMissing(['items']);

        $metadata = (array) ($invoice->metadata ?? []);

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Faktura xmlns="'.self::SCHEMA_NS.'" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .$this->headerXml()
            .$this->podmiot1Xml($invoice) // NABYWCA — VAT-owiec
            .$this->podmiot2Xml($invoice, $metadata) // DOSTAWCA — rolnik ryczałtowy
            .$this->faXml($invoice, $metadata)
            .'</Faktura>';
    }

    private function assertIsRr(Invoice $invoice): void
    {
        $kind = $invoice->kind;
        $isRr = $kind === InvoiceKind::FvRr || (is_string($kind) && $kind === 'fv_rr');
        if (! $isRr) {
            throw new \InvalidArgumentException(
                'KsefRrInvoiceXmlBuilder wymaga InvoiceKind::FvRr, dostał: '
                .((string) ($kind?->value ?? $kind))
            );
        }
    }

    private function headerXml(): string
    {
        return '<Naglowek>'
            .'<KodFormularza kodSystemowy="FA_VAT_RR (1)" wersjaSchemy="1-0E">FA_VAT_RR</KodFormularza>'
            .'<WariantFormularza>1</WariantFormularza>'
            .'<DataWytworzeniaFa>'.gmdate('Y-m-d\TH:i:s\Z').'</DataWytworzeniaFa>'
            .'<SystemInfo>Hovera</SystemInfo>'
            .'</Naglowek>';
    }

    /**
     * Podmiot1 = NABYWCA (VAT-owiec). W naszym modelu = `seller_*` fields,
     * bo to my (stable) jako VAT-payer wystawiamy fakturę.
     */
    private function podmiot1Xml(Invoice $invoice): string
    {
        return '<Podmiot1>'
            .'<DaneIdentyfikacyjne>'
            .'<NIP>'.htmlspecialchars((string) ($invoice->seller_nip ?? ''), ENT_XML1).'</NIP>'
            .'<Nazwa>'.htmlspecialchars((string) $invoice->seller_name, ENT_XML1).'</Nazwa>'
            .'</DaneIdentyfikacyjne>'
            .$this->addressXml(
                $invoice->seller_address,
                $invoice->seller_postal_code,
                $invoice->seller_city,
                $invoice->seller_country,
            )
            .'</Podmiot1>';
    }

    /**
     * Podmiot2 = ROLNIK RYCZAŁTOWY. W naszym modelu = `buyer_*` fields.
     * Identyfikacja w priority order: NIP → PESEL → BrakID.
     * Conditional DokumentTozsamosci block gdy metadata zawiera dane
     * dokumentu rolnika.
     *
     * @param  array<string,mixed>  $metadata
     */
    private function podmiot2Xml(Invoice $invoice, array $metadata): string
    {
        $buyerNip = (string) ($invoice->buyer_nip ?? '');
        $pesel = (string) ($metadata['rolnik_pesel'] ?? '');

        $identyfikacja = '<DaneIdentyfikacyjne>';
        if ($buyerNip !== '') {
            $identyfikacja .= '<NIP>'.htmlspecialchars($buyerNip, ENT_XML1).'</NIP>';
        } elseif ($pesel !== '') {
            $identyfikacja .= '<PESEL>'.htmlspecialchars($pesel, ENT_XML1).'</PESEL>';
        } else {
            $identyfikacja .= '<BrakID>1</BrakID>';
        }
        $identyfikacja .= '<Nazwa>'.htmlspecialchars((string) ($invoice->buyer_name ?: 'Rolnik Ryczałtowy'), ENT_XML1).'</Nazwa>';
        $identyfikacja .= '</DaneIdentyfikacyjne>';

        $dokTozNumer = (string) ($metadata['rolnik_dok_tozsamosci_numer'] ?? '');
        $dokTozWydanyPrzez = (string) ($metadata['rolnik_dok_tozsamosci_wydany_przez'] ?? '');
        $dokTozDataWyd = (string) ($metadata['rolnik_dok_tozsamosci_data_wyd'] ?? '');
        $dokTozsamosci = '';
        if ($dokTozNumer !== '') {
            $dokTozsamosci = '<DokumentTozsamosci>'
                .'<NumerDokumentu>'.htmlspecialchars($dokTozNumer, ENT_XML1).'</NumerDokumentu>'
                .($dokTozWydanyPrzez !== '' ? '<WydanyPrzez>'.htmlspecialchars($dokTozWydanyPrzez, ENT_XML1).'</WydanyPrzez>' : '')
                .($dokTozDataWyd !== '' ? '<DataWydania>'.$dokTozDataWyd.'</DataWydania>' : '')
                .'</DokumentTozsamosci>';
        }

        return '<Podmiot2>'
            .$identyfikacja
            .$this->addressXml(
                $invoice->buyer_address,
                $invoice->buyer_postal_code,
                $invoice->buyer_city,
                $invoice->buyer_country,
            )
            .$dokTozsamosci
            .'</Podmiot2>';
    }

    private function addressXml(?string $street, ?string $postal, ?string $city, ?string $country): string
    {
        return '<Adres>'
            .'<KodKraju>'.htmlspecialchars($country ?: 'PL', ENT_XML1).'</KodKraju>'
            .($street ? '<AdresL1>'.htmlspecialchars($street, ENT_XML1).'</AdresL1>' : '')
            .($postal && $city
                ? '<AdresL2>'.htmlspecialchars(trim($postal.' '.$city), ENT_XML1).'</AdresL2>'
                : ($city ? '<AdresL2>'.htmlspecialchars($city, ENT_XML1).'</AdresL2>' : '')
            )
            .'</Adres>';
    }

    /**
     * @param  array<string,mixed>  $metadata
     */
    private function faXml(Invoice $invoice, array $metadata): string
    {
        $rachunek = (string) ($metadata['rolnik_rachunek_bankowy'] ?? '');
        $oswiadczenie = (string) ($metadata['rolnik_oswiadczenie'] ?? $this->defaultOswiadczenie());

        $net = $this->cents((int) $invoice->subtotal_cents);
        $vat = $this->cents((int) $invoice->vat_cents);
        $total = $this->cents((int) $invoice->total_cents);

        // FA_VAT_RR zawsze PLN — rolnik ryczałtowy musi dostać wypłatę
        // w polskiej walucie (art. 116).
        return '<Fa>'
            .'<KodWaluty>PLN</KodWaluty>'
            .'<P_1>'.($invoice->issued_at?->format('Y-m-d') ?? date('Y-m-d')).'</P_1>'
            .'<P_2>'.htmlspecialchars((string) $invoice->number, ENT_XML1).'</P_2>'
            .'<P_6>'.($invoice->sale_date?->format('Y-m-d') ?? $invoice->issued_at?->format('Y-m-d') ?? date('Y-m-d')).'</P_6>'
            .$this->positionsXml($invoice)
            .'<P_11>'.$net.'</P_11>'         // suma netto
            .'<P_13_7>'.$net.'</P_13_7>'     // suma netto stawki 7%
            .'<P_14_7>'.$vat.'</P_14_7>'     // suma VAT 7% (ryczałt)
            .'<P_15>'.$total.'</P_15>'        // suma brutto
            .($rachunek !== ''
                ? '<Platnosc><RachunekBankowy><NrRB>'.htmlspecialchars($rachunek, ENT_XML1).'</NrRB></RachunekBankowy></Platnosc>'
                : '')
            .'<OswiadczenieDostawcy>'.htmlspecialchars($oswiadczenie, ENT_XML1).'</OswiadczenieDostawcy>'
            .'</Fa>';
    }

    private function positionsXml(Invoice $invoice): string
    {
        $xml = '';
        foreach ($invoice->items as $i => $item) {
            $position = $i + 1;
            // FA_VAT_RR: stawka VAT zryczałtowana 7.00% (zmienna ustawowo
            // ale per faktura zawsze ta sama). P_12 zapisujemy z .00.
            $xml .= '<FaWiersz>'
                .'<NrWierszaFa>'.$position.'</NrWierszaFa>'
                .'<P_7>'.htmlspecialchars((string) $item->name, ENT_XML1).'</P_7>'
                .'<P_8A>'.htmlspecialchars((string) $item->unit, ENT_XML1).'</P_8A>'
                .'<P_8B>'.number_format((float) $item->quantity, 4, '.', '').'</P_8B>'
                .'<P_9A>'.$this->cents((int) $item->unit_price_cents).'</P_9A>'
                .'<P_11Nett>'.$this->cents((int) $item->net_cents).'</P_11Nett>'
                .'<P_12>7.00</P_12>'
                .'</FaWiersz>';
        }

        return $xml;
    }

    private function defaultOswiadczenie(): string
    {
        return 'Oświadczam, że jestem rolnikiem ryczałtowym w rozumieniu art. 2 pkt 19 ustawy o VAT, '
            .'zwolnionym z podatku VAT na podstawie art. 43 ust. 1 pkt 3 ustawy.';
    }

    private function cents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
