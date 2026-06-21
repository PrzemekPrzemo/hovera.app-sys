@php
    use App\Enums\InvoiceKind;
    $primaryColor = $primary_color ?? '#A8956B';
    $kindLabel = match (true) {
        $invoice->kind === InvoiceKind::FvKorekta => 'FAKTURA KORYGUJĄCA',
        $invoice->kind === InvoiceKind::FvProforma => 'FAKTURA PROFORMA',
        default => 'FAKTURA',
    };
@endphp
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>{{ $kindLabel }} {{ $invoice->number }}</title>
    <style>
        @page { margin: 28mm 18mm 22mm 18mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #1F1A17; line-height: 1.4; margin: 0; }
        .header { border-bottom: 2px solid {{ $primaryColor }}; padding-bottom: 12px; margin-bottom: 18px; }
        .header-row { width: 100%; }
        .header-row td { vertical-align: top; }
        .logo { max-height: 56px; max-width: 200px; }
        .title { font-size: 18pt; font-weight: 700; color: {{ $primaryColor }}; margin: 0; }
        .doc-number { font-size: 14pt; color: #3D2E22; margin: 4px 0 0 0; }
        .doc-dates { font-size: 9pt; color: #6b7280; margin-top: 6px; }
        .parties { width: 100%; margin: 18px 0; }
        .parties td { vertical-align: top; width: 50%; padding: 10px; background: #F7F4EF; border-radius: 6px; }
        .parties h3 { margin: 0 0 6px 0; font-size: 9pt; color: {{ $primaryColor }}; text-transform: uppercase; letter-spacing: .05em; }
        .party-name { font-weight: 700; font-size: 11pt; color: #3D2E22; margin-bottom: 4px; }
        .party-detail { color: #4b5563; font-size: 9pt; }
        table.items { width: 100%; border-collapse: collapse; margin: 14px 0; }
        table.items th { background: {{ $primaryColor }}; color: #fff; padding: 7px 6px; font-size: 9pt; text-align: left; }
        table.items td { padding: 6px; border-bottom: 1px solid #E9E2D3; font-size: 9pt; }
        table.items td.num { text-align: right; }
        .totals { width: 100%; margin-top: 14px; }
        .totals td { padding: 5px 10px; font-size: 10pt; }
        .totals .label { text-align: right; color: #4b5563; }
        .totals .value { text-align: right; width: 25%; font-weight: 700; }
        .totals .grand { font-size: 13pt; color: {{ $primaryColor }}; border-top: 2px solid {{ $primaryColor }}; padding-top: 8px; }
        .footer-meta { margin-top: 24px; padding-top: 12px; border-top: 1px solid #E9E2D3; font-size: 8pt; color: #6b7280; }
        .notes { margin-top: 14px; padding: 10px; background: #FEFEFC; border-left: 3px solid {{ $primaryColor }}; font-size: 9pt; color: #3D2E22; }
        .status-badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 8pt; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; }
        .status-paid { background: #d1fae5; color: #065f46; }
        .status-overdue { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-row">
            <tr>
                <td style="width: 60%;">
                    @if ($logo_url)
                        <img src="{{ $logo_url }}" alt="Logo" class="logo">
                    @endif
                    <p class="title">{{ $kindLabel }}</p>
                    <p class="doc-number">Nr {{ $invoice->number }}</p>
                </td>
                <td style="width: 40%; text-align: right;">
                    <div class="doc-dates">
                        <strong>Data wystawienia:</strong> {{ $invoice->issued_at?->format('d.m.Y') ?? '—' }}<br>
                        <strong>Data sprzedaży:</strong> {{ $invoice->sale_date?->format('d.m.Y') ?? '—' }}<br>
                        @if ($invoice->due_at)
                            <strong>Termin płatności:</strong> {{ $invoice->due_at->format('d.m.Y') }}<br>
                        @endif
                    </div>
                    @if ($invoice->paid_at)
                        <span class="status-badge status-paid">Opłacona</span>
                    @elseif ($invoice->due_at && $invoice->due_at->isPast())
                        <span class="status-badge status-overdue">Przeterminowana</span>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <table class="parties">
        <tr>
            <td>
                <h3>Sprzedawca</h3>
                <div class="party-name">{{ $invoice->seller_name }}</div>
                <div class="party-detail">
                    @if ($invoice->seller_nip)NIP: {{ $invoice->seller_nip }}<br>@endif
                    @if ($invoice->seller_address){{ $invoice->seller_address }}<br>@endif
                    @if ($invoice->seller_postal_code || $invoice->seller_city){{ $invoice->seller_postal_code }} {{ $invoice->seller_city }}<br>@endif
                    @if ($invoice->seller_country && $invoice->seller_country !== 'PL'){{ $invoice->seller_country }}@endif
                </div>
            </td>
            <td>
                <h3>Nabywca</h3>
                <div class="party-name">{{ $invoice->buyer_name }}</div>
                <div class="party-detail">
                    @if ($invoice->buyer_nip)NIP: {{ $invoice->buyer_nip }}<br>@endif
                    @if ($invoice->buyer_address){{ $invoice->buyer_address }}<br>@endif
                    @if ($invoice->buyer_postal_code || $invoice->buyer_city){{ $invoice->buyer_postal_code }} {{ $invoice->buyer_city }}<br>@endif
                </div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 4%;">Lp.</th>
                <th style="width: 36%;">Nazwa</th>
                <th style="width: 6%;">Ilość</th>
                <th style="width: 8%;">jm</th>
                <th style="width: 11%;" class="num">Netto</th>
                <th style="width: 7%;" class="num">VAT</th>
                <th style="width: 13%;" class="num">Wartość VAT</th>
                <th style="width: 15%;" class="num">Brutto</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}.</td>
                    <td>{{ $item->name }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', ' '), '0'), ',') }}</td>
                    <td>{{ $item->unit ?? 'szt.' }}</td>
                    <td class="num">{{ number_format($item->net_cents / 100, 2, ',', ' ') }}</td>
                    <td class="num">{{ $item->vat_rate ? $item->vat_rate.'%' : '—' }}</td>
                    <td class="num">{{ number_format($item->vat_cents / 100, 2, ',', ' ') }}</td>
                    <td class="num">{{ number_format($item->total_cents / 100, 2, ',', ' ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="label">Razem netto:</td>
            <td class="value">{{ number_format($invoice->subtotal_cents / 100, 2, ',', ' ') }} {{ $invoice->currency }}</td>
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

    @if ($invoice->notes)
        <div class="notes"><strong>Uwagi:</strong> {{ $invoice->notes }}</div>
    @endif

    <div class="footer-meta">
        Dokument wygenerowany elektronicznie przez Hovera.app — nie wymaga podpisu.
        @if ($invoice->ksef_reference)
            <br>Numer KSeF: <code>{{ $invoice->ksef_reference }}</code>
        @endif
    </div>
</body>
</html>
