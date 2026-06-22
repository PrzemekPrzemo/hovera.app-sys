<?php

declare(strict_types=1);

namespace App\Services\Ksef;

use App\Enums\InvoiceStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Invoice;
use App\Tenancy\TenantManager;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * JPK_FA(3) exporter — Jednolity Plik Kontrolny "Faktura" w wersji 3.
 *
 * JPK_FA to **agregat** wszystkich wystawionych faktur sprzedażowych za
 * okres (najczęściej kwartał lub rok), wysyłany do MF na żądanie
 * urzędu skarbowego. NIE jest to to samo co FA(3) — tamta jest per-FV
 * przez KSeF. JPK_FA jest raportem kontrolnym.
 *
 * Spec: https://www.podatki.gov.pl/jednolity-plik-kontrolny/struktury-jpk/
 * Schema namespace: http://crd.gov.pl/wzor/2022/01/05/11148/
 *
 * Struktura:
 *   <JPK>
 *     <Naglowek>             — kod formularza JPK_FA, period, NIP, cel złożenia
 *     <Podmiot1>             — dane wystawcy (NIP, nazwa, adres)
 *     <Faktura typ="G">      — jedna na każdą FV (header)
 *     <FakturaCtrl>          — kontrolne: liczba FV + suma brutto
 *     <FakturaWiersz typ="G"> — jedna na każdą pozycję FV (line item)
 *     <FakturaWierszCtrl>    — kontrolne: liczba wierszy + suma netto
 *   </JPK>
 *
 * Cel złożenia (CelZlozenia):
 *   - 1 = pierwotny (regular submission)
 *   - 2 = korekta wcześniejszego JPK
 *
 * Skip Draft + Void + Cancelled — JPK zawiera tylko realne faktury
 * sprzedażowe, czyli Issued/Paid/Overdue/Sent.
 */
class JpkFa3Exporter
{
    private const SCHEMA_NS = 'http://crd.gov.pl/wzor/2022/01/05/11148/';

    public function __construct(
        private readonly TenantManager $tenants,
    ) {}

    /**
     * Eksport JPK_FA(3) dla kwartału. Wrapper dla exportRange().
     *
     * @param  int  $quarter  1-4
     */
    public function exportQuarter(Tenant $tenant, int $year, int $quarter): string
    {
        if ($quarter < 1 || $quarter > 4) {
            throw new InvalidArgumentException('Quarter must be 1-4, got: '.$quarter);
        }

        $startMonth = ($quarter - 1) * 3 + 1;
        $from = Carbon::create($year, $startMonth, 1)->startOfMonth();
        $to = $from->copy()->addMonths(2)->endOfMonth();

        return $this->exportRange($tenant, $from, $to);
    }

    /**
     * Eksport JPK_FA(3) dla roku. Wrapper dla exportRange().
     */
    public function exportYear(Tenant $tenant, int $year): string
    {
        $from = Carbon::create($year, 1, 1)->startOfYear();
        $to = $from->copy()->endOfYear();

        return $this->exportRange($tenant, $from, $to);
    }

    /**
     * Główny entry point — eksport za dowolny zakres. Wykonuje switch
     * na DB stajni żeby query'ować jej faktury.
     */
    public function exportRange(Tenant $tenant, Carbon $from, Carbon $to): string
    {
        return (string) $this->tenants->execute($tenant, function () use ($tenant, $from, $to): string {
            $invoices = $this->loadInvoices($from, $to);

            return $this->buildXml($tenant, $from, $to, $invoices);
        });
    }

    /**
     * @return iterable<Invoice>
     */
    private function loadInvoices(Carbon $from, Carbon $to): iterable
    {
        // JPK uwzględnia tylko faktury wystawione (issued_at w okresie),
        // bez draftów / voidów / cancelled. Status Sent/Paid/Overdue
        // wszystkie kwalifikują się.
        return Invoice::query()
            ->whereNotNull('issued_at')
            ->whereBetween('issued_at', [$from->toDateString(), $to->toDateString()])
            ->whereNotIn('status', [
                InvoiceStatus::Draft->value,
                InvoiceStatus::Void->value,
                InvoiceStatus::Cancelled->value,
            ])
            ->with('items')
            ->orderBy('issued_at')
            ->orderBy('number')
            ->get();
    }

    /**
     * @param  iterable<Invoice>  $invoices
     */
    private function buildXml(Tenant $tenant, Carbon $from, Carbon $to, iterable $invoices): string
    {
        $headerXml = $this->headerXml($tenant, $from, $to);
        $subjectXml = $this->subjectXml($tenant);

        $invoiceXml = '';
        $invoiceCount = 0;
        $invoiceGrossTotalCents = 0;

        $rowXml = '';
        $rowCount = 0;
        $rowNetTotalCents = 0;

        foreach ($invoices as $invoice) {
            $invoiceCount++;
            $invoiceGrossTotalCents += (int) $invoice->total_cents;
            $invoiceXml .= $this->invoiceHeaderXml($invoice);

            foreach ($invoice->items as $item) {
                $rowCount++;
                $rowNetTotalCents += (int) $item->net_cents;
                $rowXml .= $this->invoiceRowXml($invoice, $item);
            }
        }

        $invoiceCtrlXml = '<FakturaCtrl>'
            .'<LiczbaFaktur>'.$invoiceCount.'</LiczbaFaktur>'
            .'<WartoscFaktur>'.$this->cents($invoiceGrossTotalCents).'</WartoscFaktur>'
            .'</FakturaCtrl>';

        $rowCtrlXml = '<FakturaWierszCtrl>'
            .'<LiczbaWierszyFaktur>'.$rowCount.'</LiczbaWierszyFaktur>'
            .'<WartoscWierszyFaktur>'.$this->cents($rowNetTotalCents).'</WartoscWierszyFaktur>'
            .'</FakturaWierszCtrl>';

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<JPK xmlns="'.self::SCHEMA_NS.'">'
            .$headerXml
            .$subjectXml
            .$invoiceXml
            .$invoiceCtrlXml
            .$rowXml
            .$rowCtrlXml
            .'</JPK>';
    }

    private function headerXml(Tenant $tenant, Carbon $from, Carbon $to): string
    {
        // CelZlozenia=1 = regular submission. Korekta JPK (=2) wymaga
        // ręcznego oznaczenia — zostaje na bardziej zaawansowane UI.
        return '<Naglowek>'
            .'<KodFormularza kodSystemowy="JPK_FA (3)" wersjaSchemy="1-0">JPK_FA</KodFormularza>'
            .'<WariantFormularza>3</WariantFormularza>'
            .'<CelZlozenia>1</CelZlozenia>'
            .'<DataWytworzeniaJPK>'.gmdate('Y-m-d\TH:i:s\Z').'</DataWytworzeniaJPK>'
            .'<DataOd>'.$from->toDateString().'</DataOd>'
            .'<DataDo>'.$to->toDateString().'</DataDo>'
            .'<NIPPodmiotu>'.htmlspecialchars($this->normalizeNip((string) ($tenant->tax_id ?? '')), ENT_XML1).'</NIPPodmiotu>'
            .'<KodUrzedu>'.htmlspecialchars((string) (data_get($tenant->settings, 'jpk.kod_urzedu') ?? '0000'), ENT_XML1).'</KodUrzedu>'
            .'</Naglowek>';
    }

    private function subjectXml(Tenant $tenant): string
    {
        $name = (string) ($tenant->legal_name ?: $tenant->name);
        $nip = $this->normalizeNip((string) ($tenant->tax_id ?? ''));
        $profile = (array) (data_get($tenant->settings, 'public_profile') ?? []);
        $address = (string) ($profile['address'] ?? '');
        $postal = (string) ($profile['postal_code'] ?? '');
        $city = (string) ($profile['city'] ?? '');
        $country = (string) ($tenant->country ?? 'PL');

        return '<Podmiot1>'
            .'<IdentyfikatorPodmiotu>'
            .'<NIP>'.htmlspecialchars($nip, ENT_XML1).'</NIP>'
            .'<PelnaNazwa>'.htmlspecialchars($name, ENT_XML1).'</PelnaNazwa>'
            .'</IdentyfikatorPodmiotu>'
            .'<AdresPodmiotu>'
            .'<KodKraju>'.htmlspecialchars($country, ENT_XML1).'</KodKraju>'
            .'<Wojewodztwo>'.htmlspecialchars((string) ($profile['voivodeship'] ?? ''), ENT_XML1).'</Wojewodztwo>'
            .'<Powiat>'.htmlspecialchars((string) ($profile['county'] ?? ''), ENT_XML1).'</Powiat>'
            .'<Gmina>'.htmlspecialchars((string) ($profile['commune'] ?? $city), ENT_XML1).'</Gmina>'
            .'<Ulica>'.htmlspecialchars($address, ENT_XML1).'</Ulica>'
            .'<NrDomu>'.htmlspecialchars((string) ($profile['house_number'] ?? ''), ENT_XML1).'</NrDomu>'
            .'<Miejscowosc>'.htmlspecialchars($city, ENT_XML1).'</Miejscowosc>'
            .'<KodPocztowy>'.htmlspecialchars($postal, ENT_XML1).'</KodPocztowy>'
            .'<Poczta>'.htmlspecialchars($city, ENT_XML1).'</Poczta>'
            .'</AdresPodmiotu>'
            .'</Podmiot1>';
    }

    private function invoiceHeaderXml(Invoice $invoice): string
    {
        $kind = (string) ($invoice->kind?->value ?? 'fv');
        $rodzajFaktury = match ($kind) {
            'fv_korekta' => 'KOREKTA',
            'fv_proforma' => 'POZ',
            'fv_uproszczona' => 'UPROSZCZONA',
            'fv_zaliczkowa' => 'ZAL',
            'fv_rr' => 'VAT_RR',
            default => 'VAT',
        };

        $issued = $invoice->issued_at?->toDateString() ?? date('Y-m-d');
        $saleDate = $invoice->sale_date?->toDateString() ?? $issued;

        return '<Faktura typ="G">'
            .'<KodWaluty>'.htmlspecialchars((string) ($invoice->currency ?? 'PLN'), ENT_XML1).'</KodWaluty>'
            .'<P_1>'.$issued.'</P_1>'                                                          // data wystawienia
            .'<P_2A>'.htmlspecialchars((string) $invoice->number, ENT_XML1).'</P_2A>'          // nr FV
            .'<P_3A>'.htmlspecialchars((string) $invoice->buyer_name, ENT_XML1).'</P_3A>'      // nabywca nazwa
            .'<P_3B>'.htmlspecialchars((string) ($invoice->buyer_address ?? ''), ENT_XML1).'</P_3B>'
            .'<P_3C>'.htmlspecialchars((string) $invoice->seller_name, ENT_XML1).'</P_3C>'     // sprzedawca nazwa
            .'<P_3D>'.htmlspecialchars((string) ($invoice->seller_address ?? ''), ENT_XML1).'</P_3D>'
            .'<P_4B>'.htmlspecialchars($this->normalizeNip((string) ($invoice->seller_nip ?? '')), ENT_XML1).'</P_4B>'
            .($invoice->buyer_nip !== null && $invoice->buyer_nip !== ''
                ? '<P_5B>'.htmlspecialchars($this->normalizeNip((string) $invoice->buyer_nip), ENT_XML1).'</P_5B>'
                : '')
            .'<P_6>'.$saleDate.'</P_6>'                                                        // data sprzedaży
            .'<P_13_1>'.$this->cents((int) $invoice->subtotal_cents).'</P_13_1>'               // suma netto 23%
            .'<P_14_1>'.$this->cents((int) $invoice->vat_cents).'</P_14_1>'                    // suma VAT 23%
            .'<P_15>'.$this->cents((int) $invoice->total_cents).'</P_15>'                      // razem brutto
            .'<RodzajFaktury>'.$rodzajFaktury.'</RodzajFaktury>'
            .'</Faktura>';
    }

    private function invoiceRowXml(Invoice $invoice, $item): string
    {
        return '<FakturaWiersz typ="G">'
            .'<P_2B>'.htmlspecialchars((string) $invoice->number, ENT_XML1).'</P_2B>'    // nr FV (klucz do <Faktura>)
            .'<P_7>'.htmlspecialchars((string) $item->name, ENT_XML1).'</P_7>'            // nazwa towaru/usługi
            .'<P_8A>'.htmlspecialchars((string) $item->unit, ENT_XML1).'</P_8A>'         // jednostka
            .'<P_8B>'.number_format((float) $item->quantity, 4, '.', '').'</P_8B>'        // ilość
            .'<P_9A>'.$this->cents((int) $item->unit_price_cents).'</P_9A>'              // cena jedn. netto
            .'<P_11>'.$this->cents((int) $item->net_cents).'</P_11>'                     // wartość netto
            .'<P_12>'.htmlspecialchars((string) $item->vat_rate, ENT_XML1).'</P_12>'      // stawka VAT
            .'</FakturaWiersz>';
    }

    private function cents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function normalizeNip(string $nip): string
    {
        return preg_replace('/[^0-9]/', '', $nip) ?? '';
    }
}
