<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('transport/invoice_pdf.title', ['number' => $invoice->number]) }}</title>
    <style>
        @page { margin: 20mm 16mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #1F1A17; line-height: 1.4; }
        .header { width: 100%; margin-bottom: 18px; }
        .header td { vertical-align: top; }
        .brand { font-size: 16pt; font-weight: bold; color: #3D2E22; }
        .meta { text-align: right; }
        h1 { font-size: 18pt; margin: 0 0 4px; color: #3D2E22; }
        .number-big { font-size: 14pt; font-weight: bold; }
        .kind-label { font-size: 8pt; color: #6b7280; text-transform: uppercase; letter-spacing: .08em; }
        .parties { width: 100%; margin: 16px 0; border-collapse: collapse; }
        .parties td { vertical-align: top; width: 50%; padding: 12px; border: 1px solid #d4cdb8; }
        .parties .label { font-size: 8pt; color: #6b7280; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 4px; }
        .parties .name { font-weight: bold; font-size: 11pt; }
        .parties .line { margin-top: 2px; }
        .dates { width: 100%; margin: 16px 0; border-collapse: collapse; }
        .dates td { padding: 6px 10px; border: 1px solid #d4cdb8; font-size: 9pt; }
        .dates td.label { background: #F7F4EF; font-weight: bold; }
        .items { width: 100%; border-collapse: collapse; margin-top: 12px; }
        .items th, .items td { padding: 6px 8px; text-align: left; border-bottom: 1px solid #e5e7eb; font-size: 9pt; }
        .items th { background: #F7F4EF; font-weight: bold; color: #3D2E22; }
        .items td.num { text-align: right; }
        .items td.center { text-align: center; }
        .totals { width: 50%; margin-left: auto; margin-top: 8px; border-collapse: collapse; }
        .totals td { padding: 4px 8px; font-size: 10pt; }
        .totals tr.total td { font-size: 13pt; font-weight: bold; border-top: 2px solid #A8956B; padding-top: 10px; }
        .totals td.num { text-align: right; }
        .payment { margin-top: 16px; padding: 10px; background: #F7F4EF; font-size: 9pt; }
        .payment .label { font-weight: bold; color: #3D2E22; }
        .service-block { margin-top: 16px; padding: 10px; border-left: 3px solid #A8956B; font-size: 9pt; color: #3D2E22; }
        .footer { margin-top: 30px; font-size: 7.5pt; color: #6b7280; text-align: center; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <div class="brand">
                    @php $branding = (array) ($tenant?->branding ?? []); @endphp
                    @if (!empty($branding['logo_url']))
                        <img src="{{ $branding['logo_url'] }}" alt="" style="max-height: 32px;">
                    @else
                        {{ $invoice->seller_name }}
                    @endif
                </div>
            </td>
            <td class="meta">
                <div class="kind-label">{{ $invoice->kind->label() }}</div>
                <div class="number-big">{{ $invoice->number }}</div>
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td>
                <div class="label">{{ __('transport/invoice_pdf.label.seller') }}</div>
                <div class="name">{{ $invoice->seller_name }}</div>
                @if ($invoice->seller_nip)
                    <div class="line">NIP: <strong>{{ $invoice->seller_nip }}</strong></div>
                @endif
                @if ($invoice->seller_address)
                    <div class="line">{{ $invoice->seller_address }}</div>
                @endif
                @if ($invoice->seller_postal_code || $invoice->seller_city)
                    <div class="line">{{ trim($invoice->seller_postal_code.' '.$invoice->seller_city) }}</div>
                @endif
                <div class="line">{{ $invoice->seller_country }}</div>
            </td>
            <td>
                <div class="label">{{ __('transport/invoice_pdf.label.buyer') }}</div>
                <div class="name">{{ $invoice->buyer_name }}</div>
                @if ($invoice->buyer_nip)
                    <div class="line">NIP: <strong>{{ $invoice->buyer_nip }}</strong></div>
                @endif
                @if ($invoice->buyer_address)
                    <div class="line">{{ $invoice->buyer_address }}</div>
                @endif
                @if ($invoice->buyer_email)
                    <div class="line">{{ $invoice->buyer_email }}</div>
                @endif
                <div class="line">{{ $invoice->buyer_country }}</div>
            </td>
        </tr>
    </table>

    <table class="dates">
        <tr>
            <td class="label">{{ __('transport/invoice_pdf.label.issued_at') }}</td>
            <td>{{ $invoice->issued_at?->format('Y-m-d') }}</td>
            <td class="label">{{ __('transport/invoice_pdf.label.sale_date') }}</td>
            <td>{{ $invoice->sale_date?->format('Y-m-d') }}</td>
            <td class="label">{{ __('transport/invoice_pdf.label.due_at') }}</td>
            <td>{{ $invoice->due_at?->format('Y-m-d') }}</td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 30px;">#</th>
                <th>{{ __('transport/invoice_pdf.label.item_name') }}</th>
                <th class="center" style="width: 60px;">{{ __('transport/invoice_pdf.label.qty') }}</th>
                <th class="center" style="width: 40px;">{{ __('transport/invoice_pdf.label.unit') }}</th>
                <th class="num" style="width: 80px;">{{ __('transport/invoice_pdf.label.unit_price') }}</th>
                <th class="num" style="width: 80px;">{{ __('transport/invoice_pdf.label.net') }}</th>
                <th class="center" style="width: 40px;">{{ __('transport/invoice_pdf.label.vat_rate') }}</th>
                <th class="num" style="width: 80px;">{{ __('transport/invoice_pdf.label.vat') }}</th>
                <th class="num" style="width: 80px;">{{ __('transport/invoice_pdf.label.gross') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->position }}</td>
                    <td>
                        {{ $item->name }}
                        @if ($item->description)
                            <div style="color: #6b7280; font-size: 8pt; margin-top: 2px;">{{ $item->description }}</div>
                        @endif
                    </td>
                    <td class="center">{{ rtrim(rtrim(number_format((float) $item->quantity, 3, ',', ''), '0'), ',') }}</td>
                    <td class="center">{{ $item->unit }}</td>
                    <td class="num">{{ number_format($item->unit_price_cents / 100, 2, ',', ' ') }}</td>
                    <td class="num">{{ number_format($item->net_cents / 100, 2, ',', ' ') }}</td>
                    <td class="center">{{ is_numeric($item->vat_rate) ? $item->vat_rate.'%' : $item->vat_rate }}</td>
                    <td class="num">{{ number_format($item->vat_cents / 100, 2, ',', ' ') }}</td>
                    <td class="num">{{ number_format($item->total_cents / 100, 2, ',', ' ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>{{ __('transport/invoice_pdf.label.net_total') }}</td>
            <td class="num">{{ number_format($invoice->subtotal_cents / 100, 2, ',', ' ') }} {{ $invoice->currency }}</td>
        </tr>
        <tr>
            <td>{{ __('transport/invoice_pdf.label.vat_total') }}</td>
            <td class="num">{{ number_format($invoice->vat_cents / 100, 2, ',', ' ') }} {{ $invoice->currency }}</td>
        </tr>
        <tr class="total">
            <td>{{ __('transport/invoice_pdf.label.gross_total') }}</td>
            <td class="num">{{ number_format($invoice->total_cents / 100, 2, ',', ' ') }} {{ $invoice->currency }}</td>
        </tr>
    </table>

    @if ($invoice->seller_iban)
        <div class="payment">
            <span class="label">{{ __('transport/invoice_pdf.label.payment_to') }}:</span><br>
            {{ $invoice->seller_bank_name }} · IBAN <strong>{{ $invoice->seller_iban }}</strong><br>
            <span style="color: #6b7280;">{{ __('transport/invoice_pdf.label.payment_title', ['number' => $invoice->number]) }}</span>
        </div>
    @endif

    @if ($invoice->pickup_address && $invoice->dropoff_address)
        <div class="service-block">
            <strong>{{ __('transport/invoice_pdf.label.transport_details') }}:</strong>
            {{ $invoice->pickup_address }} → {{ $invoice->dropoff_address }}
            @if ($invoice->service_date) · {{ $invoice->service_date->format('Y-m-d') }} @endif
            @if ($invoice->distance_km) · {{ number_format((float) $invoice->distance_km, 2, ',', ' ') }} km @endif
        </div>
    @endif

    <div class="footer">
        {{ __('transport/invoice_pdf.footer', ['app' => config('app.name')]) }} · {{ now()->format('Y-m-d H:i') }}
    </div>
</body>
</html>
