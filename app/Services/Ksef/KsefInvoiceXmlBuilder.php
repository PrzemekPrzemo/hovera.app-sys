<?php

declare(strict_types=1);

namespace App\Services\Ksef;

use App\Models\Tenant\Invoice;

/**
 * Buduje FA(3) XML — krajowy format faktury ustrukturyzowanej.
 *
 * To jest ZAWĘŻONA implementacja: pokrywa najpopularniejsze przypadki
 * (FV/Korekta/Proforma z 1+ pozycjami, jeden VAT rate per pozycja, PLN)
 * — czyli to czego potrzebują stajnie. Pełna spec FA(3) jest w
 * /CIRFMF/ksef-docs i ma kilkadziesiąt elementów których nie używamy.
 *
 * Zwracany XML jest jeszcze NIE-podpisany; KsefSigningService dodaje
 * XAdES-BES dopiero przy wysyłce.
 */
class KsefInvoiceXmlBuilder
{
    private const SCHEMA_NS = 'http://crd.gov.pl/wzor/2023/06/29/12648/';

    public function build(Invoice $invoice): string
    {
        $invoice->loadMissing('items');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Faktura xmlns="'.self::SCHEMA_NS.'" xmlns:etd="http://crd.gov.pl/xml/schematy/dziedzinowe/mf/2022/01/05/eD/DefinicjeTypy/">'
            .$this->headerXml($invoice)
            .$this->subjectsXml($invoice)
            .$this->positionsXml($invoice)
            .$this->summaryXml($invoice)
            .'</Faktura>';

        return $xml;
    }

    private function headerXml(Invoice $invoice): string
    {
        $issueDate = $invoice->issued_at?->format('Y-m-d') ?? date('Y-m-d');
        $sysCode = match ($invoice->kind?->value) {
            'fv_korekta' => 'KOR',
            'fv_proforma' => 'PRO',
            default => 'FA',
        };

        return '<Naglowek>'
            .'<KodFormularza kodSystemowy="FA (3)" wersjaSchemy="1-0E">FA</KodFormularza>'
            .'<WariantFormularza>3</WariantFormularza>'
            .'<DataWytworzeniaFa>'.gmdate('Y-m-d\TH:i:s\Z').'</DataWytworzeniaFa>'
            .'<SystemInfo>Hovera</SystemInfo>'
            .'</Naglowek>';
    }

    private function subjectsXml(Invoice $invoice): string
    {
        return '<Podmiot1>'
            .$this->dataIdentyfikacyjne($invoice->seller_name, $invoice->seller_nip, isSeller: true)
            .$this->dataAdresowe($invoice->seller_address, $invoice->seller_postal_code, $invoice->seller_city, $invoice->seller_country)
            .'</Podmiot1>'
            .'<Podmiot2>'
            .$this->dataIdentyfikacyjne($invoice->buyer_name, $invoice->buyer_nip, isSeller: false)
            .$this->dataAdresowe($invoice->buyer_address, $invoice->buyer_postal_code, $invoice->buyer_city, $invoice->buyer_country)
            .'</Podmiot2>';
    }

    private function dataIdentyfikacyjne(string $name, ?string $nip, bool $isSeller): string
    {
        if ($nip !== null && $nip !== '') {
            return '<DaneIdentyfikacyjne>'
                .'<NIP>'.htmlspecialchars($nip, ENT_XML1).'</NIP>'
                .'<Nazwa>'.htmlspecialchars($name, ENT_XML1).'</Nazwa>'
                .'</DaneIdentyfikacyjne>';
        }

        // Bez NIP — osoba fizyczna (only buyer side; seller MUST have NIP).
        if ($isSeller) {
            return '<DaneIdentyfikacyjne><BrakID>1</BrakID><Nazwa>'.htmlspecialchars($name, ENT_XML1).'</Nazwa></DaneIdentyfikacyjne>';
        }

        return '<DaneIdentyfikacyjne><BrakID>1</BrakID><Nazwa>'.htmlspecialchars($name, ENT_XML1).'</Nazwa></DaneIdentyfikacyjne>';
    }

    private function dataAdresowe(?string $street, ?string $postal, ?string $city, ?string $country): string
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

    private function positionsXml(Invoice $invoice): string
    {
        $xml = '';
        foreach ($invoice->items as $i => $item) {
            $position = $i + 1;
            $xml .= '<FaWiersz>'
                .'<NrWierszaFa>'.$position.'</NrWierszaFa>'
                .'<P_7>'.htmlspecialchars($item->name, ENT_XML1).'</P_7>' // nazwa towaru
                .'<P_8A>'.htmlspecialchars($item->unit, ENT_XML1).'</P_8A>'
                .'<P_8B>'.number_format((float) $item->quantity, 4, '.', '').'</P_8B>'
                .'<P_9A>'.$this->cents($item->unit_price_cents).'</P_9A>'    // cena jednostkowa netto
                .'<P_11>'.$this->cents($item->net_cents).'</P_11>'           // wartość netto
                .'<P_12>'.$this->vatRateXml($item->vat_rate).'</P_12>'        // stawka VAT
                .'</FaWiersz>';
        }

        return $xml;
    }

    private function vatRateXml(string $vatRate): string
    {
        return match ($vatRate) {
            'zw' => 'zw',
            'np' => 'np',
            'oo' => 'oo',
            default => $vatRate,
        };
    }

    private function summaryXml(Invoice $invoice): string
    {
        return '<Fa>'
            .'<KodWaluty>'.htmlspecialchars($invoice->currency ?? 'PLN', ENT_XML1).'</KodWaluty>'
            .'<P_1>'.($invoice->issued_at?->format('Y-m-d') ?? date('Y-m-d')).'</P_1>'      // data wystawienia
            .'<P_2>'.htmlspecialchars((string) $invoice->number, ENT_XML1).'</P_2>'        // numer FV
            .'<P_6>'.($invoice->sale_date?->format('Y-m-d') ?? $invoice->issued_at?->format('Y-m-d') ?? date('Y-m-d')).'</P_6>' // data sprzedaży
            .'<P_13_1>'.$this->cents($invoice->subtotal_cents).'</P_13_1>'  // suma netto 23%
            .'<P_14_1>'.$this->cents($invoice->vat_cents).'</P_14_1>'      // VAT
            .'<P_15>'.$this->cents($invoice->total_cents).'</P_15>'        // suma brutto
            .'</Fa>';
    }

    private function cents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
