@php
    $ochra = '#A8956B';
    $brown = '#3D2E22';
    $cream = '#F7F4EF';
    $line = '#E9E2D3';
@endphp
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Faktura Hovera {{ $invoice->number }}</title>
    <style>
        @page { margin: 28mm 18mm 22mm 18mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: {{ $brown }}; line-height: 1.4; margin: 0; }
        .brand-strip { background: {{ $ochra }}; color: #fff; padding: 14px 18px; margin: -28mm -18mm 16px -18mm; }
        .brand-strip .logo-text { font-size: 22pt; font-weight: 700; letter-spacing: 0.02em; }
        .brand-strip .tagline { font-size: 9pt; opacity: 0.9; margin-top: 2px; }
        .header-row { width: 100%; margin-bottom: 18px; }
        .header-row td { vertical-align: top; }
        .title { font-size: 18pt; font-weight: 700; color: {{ $ochra }}; margin: 0; }
        .doc-number { font-size: 14pt; color: {{ $brown }}; margin: 4px 0 0 0; }
        .doc-dates { font-size: 9pt; color: #6b7280; }
        .parties { width: 100%; margin: 18px 0; }
        .parties td { vertical-align: top; width: 50%; padding: 12px; background: {{ $cream }}; border-radius: 6px; }
        .parties h3 { margin: 0 0 6px 0; font-size: 9pt; color: {{ $ochra }}; text-transform: uppercase; letter-spacing: .05em; }
        .party-name { font-weight: 700; font-size: 11pt; color: {{ $brown }}; margin-bottom: 4px; }
        .party-detail { color: #4b5563; font-size: 9pt; }
        table.items { width: 100%; border-collapse: collapse; margin: 14px 0; }
        table.items th { background: {{ $ochra }}; color: #fff; padding: 7px 6px; font-size: 9pt; text-align: left; }
        table.items td { padding: 7px 6px; border-bottom: 1px solid {{ $line }}; font-size: 9pt; }
        table.items td.num { text-align: right; }
        .totals { width: 100%; margin-top: 14px; }
        .totals td { padding: 5px 10px; font-size: 10pt; }
        .totals .label { text-align: right; color: #4b5563; }
        .totals .value { text-align: right; width: 25%; font-weight: 700; }
        .totals .grand { font-size: 14pt; color: {{ $ochra }}; border-top: 2px solid {{ $ochra }}; padding-top: 8px; }
        .payment-info { margin-top: 18px; padding: 12px; background: {{ $cream }}; border-radius: 6px; }
        .payment-info h3 { margin: 0 0 6px 0; font-size: 9pt; color: {{ $ochra }}; text-transform: uppercase; letter-spacing: .05em; }
        .payment-info .iban { font-family: 'DejaVu Sans Mono', monospace; font-size: 11pt; color: {{ $brown }}; letter-spacing: 0.05em; }
        .footer-meta { margin-top: 24px; padding-top: 12px; border-top: 1px solid {{ $line }}; font-size: 8pt; color: #6b7280; text-align: center; }
        .status-badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 8pt; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; }
        .status-paid { background: #d1fae5; color: #065f46; }
    </style>
</head>
<body>
    <div class="brand-strip">
        <div class="logo-text">hovera.app</div>
        <div class="tagline">Platforma SaaS dla stajni i firm transportowych</div>
    </div>

    <table class="header-row">
        <tr>
            <td style="width: 60%;">
                <p class="title">FAKTURA VAT</p>
                <p class="doc-number">Nr {{ $invoice->number }}</p>
            </td>
            <td style="width: 40%; text-align: right;">
                <div class="doc-dates">
                    <strong>Data wystawienia:</strong> {{ $invoice->issued_at?->format('d.m.Y') ?? '—' }}<br>
                    @if ($invoice->due_at)
                        <strong>Termin płatności:</strong> {{ $invoice->due_at->format('d.m.Y') }}<br>
                    @endif
                    @if ($invoice->period)
                        <strong>Okres rozliczeniowy:</strong> {{ $invoice->period }}<br>
                    @endif
                </div>
                @if ($invoice->paid_at)
                    <span class="status-badge status-paid">Opłacona — {{ $invoice->paid_at->format('d.m.Y') }}</span>
                @endif
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td>
                <h3>Sprzedawca</h3>
                <div class="party-name">{{ $seller['company_name'] ?? 'Sendormeco Holding sp. z o.o.' }}</div>
                <div class="party-detail">
                    @if (! empty($seller['nip']))NIP: {{ $seller['nip'] }}<br>@endif
                    @if (! empty($seller['regon']))REGON: {{ $seller['regon'] }}<br>@endif
                    @if (! empty($seller['krs']))KRS: {{ $seller['krs'] }}<br>@endif
                    @if (! empty($seller['address'])){{ $seller['address'] }}<br>@endif
                    @if (! empty($seller['support_email'])){{ $seller['support_email'] }}@endif
                </div>
            </td>
            <td>
                <h3>Nabywca</h3>
                <div class="party-name">{{ $buyer->legal_name ?? $buyer->name ?? '—' }}</div>
                <div class="party-detail">
                    @if (! empty($buyer->tax_id))NIP: {{ $buyer->tax_id }}<br>@endif
                    @php $companyAddress = (array) (data_get($buyer->settings, 'company.address') ?? []); @endphp
                    @if (! empty($companyAddress)){{ is_string($companyAddress) ? $companyAddress : implode(', ', $companyAddress) }}<br>@endif
                </div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 4%;">Lp.</th>
                <th style="width: 50%;">Nazwa</th>
                <th style="width: 10%;" class="num">Netto</th>
                <th style="width: 8%;" class="num">VAT</th>
                <th style="width: 12%;" class="num">Wartość VAT</th>
                <th style="width: 16%;" class="num">Brutto</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1.</td>
                <td>
                    Abonament Hovera.app — plan <strong>{{ $invoice->plan_code ?? '—' }}</strong>
                    @if ($invoice->period)<br><span style="font-size: 8pt; color: #6b7280;">Okres: {{ $invoice->period }}</span>@endif
                </td>
                <td class="num">{{ number_format($invoice->amount_cents / 100, 2, ',', ' ') }}</td>
                <td class="num">{{ $invoice->vat_rate ?? 23 }}%</td>
                <td class="num">{{ number_format($invoice->vat_cents / 100, 2, ',', ' ') }}</td>
                <td class="num">{{ number_format($invoice->total_cents / 100, 2, ',', ' ') }}</td>
            </tr>
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="label">Razem netto:</td>
            <td class="value">{{ number_format($invoice->amount_cents / 100, 2, ',', ' ') }} {{ $invoice->currency }}</td>
        </tr>
        <tr>
            <td class="label">Razem VAT:</td>
            <td class="value">{{ number_format($invoice->vat_cents / 100, 2, ',', ' ') }} {{ $invoice->currency }}</td>
        </tr>
        <tr>
            <td class="label grand">RAZEM DO ZAPŁATY:</td>
            <td class="value grand">{{ number_format($invoice->total_cents / 100, 2, ',', ' ') }} {{ $invoice->currency }}</td>
        </tr>
    </table>

    @if (! empty($seller['iban']))
        <div class="payment-info">
            <h3>Dane do przelewu</h3>
            @if (! empty($seller['bank_name']))<div style="font-size: 9pt; margin-bottom: 4px;">{{ $seller['bank_name'] }}</div>@endif
            <div class="iban">{{ $seller['iban'] }}</div>
            <div style="font-size: 8pt; color: #6b7280; margin-top: 6px;">
                W tytule prosimy podać numer faktury: <strong>{{ $invoice->number }}</strong>
            </div>
        </div>
    @endif

    <div class="footer-meta">
        Dokument wygenerowany elektronicznie — nie wymaga podpisu.<br>
        {{ $seller['company_name'] ?? 'Sendormeco Holding sp. z o.o.' }}
        @if (! empty($seller['court'])) · {{ $seller['court'] }}@endif
        @if ($invoice->ksef_reference)
            <br>Numer KSeF: <code>{{ $invoice->ksef_reference }}</code>
        @endif
    </div>
</body>
</html>
