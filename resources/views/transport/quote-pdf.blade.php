<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('transport/pdf.title', ['number' => $quote->number]) }}</title>
    <style>
        @page { margin: 24mm 18mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5pt; color: #1F1A17; line-height: 1.45; }
        .header { width: 100%; margin-bottom: 24px; }
        .header td { vertical-align: top; }
        .brand { font-size: 18pt; font-weight: bold; color: #3D2E22; }
        .meta { text-align: right; font-size: 9pt; color: #6b7280; }
        .meta strong { color: #1F1A17; }
        h1 { font-size: 16pt; margin: 0 0 4px; color: #3D2E22; }
        .subtitle { color: #6b7280; font-size: 9pt; margin-bottom: 14px; }
        .section { margin-bottom: 18px; }
        .section h2 { font-size: 11pt; margin: 0 0 6px; padding-bottom: 4px; border-bottom: 1px solid #A8956B; color: #3D2E22; }
        .grid { width: 100%; }
        .grid td { vertical-align: top; padding: 2px 0; }
        .grid td.label { color: #6b7280; width: 38%; }
        .pricing { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .pricing th, .pricing td { padding: 6px 8px; text-align: left; }
        .pricing th { background: #F7F4EF; font-size: 9pt; color: #6b7280; }
        .pricing td.amount { text-align: right; }
        .pricing tr.total td { font-weight: bold; font-size: 12pt; border-top: 2px solid #A8956B; padding-top: 10px; }
        .pricing tr.subtotal td { font-weight: bold; }
        .terms { font-size: 9pt; color: #3D2E22; line-height: 1.5; white-space: pre-wrap; padding: 10px; background: #F7F4EF; border-left: 3px solid #A8956B; }
        .footer { margin-top: 30px; font-size: 8pt; color: #6b7280; text-align: center; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <div class="brand">
                    @if ($sellerLogoUrl)
                        <img src="{{ $sellerLogoUrl }}" alt="" style="max-height: 36px;">
                    @else
                        {{ $sellerName }}
                    @endif
                </div>
                <div style="font-size: 9pt; color: #6b7280; margin-top: 4px;">
                    {{ $sellerName }}
                    @if ($sellerTaxId) · NIP {{ $sellerTaxId }} @endif
                </div>
                @if ($sellerAddress)
                    <div style="font-size: 9pt; color: #6b7280;">{{ $sellerAddress }}</div>
                @endif
                @if ($sellerEmail || $sellerPhone)
                    <div style="font-size: 9pt; color: #6b7280;">
                        @if ($sellerEmail){{ $sellerEmail }}@endif
                        @if ($sellerEmail && $sellerPhone) · @endif
                        @if ($sellerPhone){{ $sellerPhone }}@endif
                    </div>
                @endif
            </td>
            <td class="meta">
                <strong>{{ __('transport/pdf.number_label') }}</strong><br>
                <span style="font-size: 14pt; font-weight: bold; color: #1F1A17;">{{ $quote->number }}</span><br>
                <br>
                <strong>{{ __('transport/pdf.issued') }}:</strong> {{ $quote->created_at?->format('Y-m-d') }}<br>
                @if ($quote->valid_until)
                    <strong>{{ __('transport/pdf.valid_until') }}:</strong> {{ $quote->valid_until->format('Y-m-d') }}<br>
                @endif
            </td>
        </tr>
    </table>

    <h1>{{ __('transport/pdf.heading') }}</h1>
    <div class="subtitle">{{ __('transport/pdf.subtitle') }}</div>

    <div class="section">
        <h2>{{ __('transport/pdf.section.customer') }}</h2>
        <table class="grid">
            <tr><td class="label">{{ __('transport/pdf.label.name') }}</td><td>{{ $quote->customer_name }}</td></tr>
            @if ($quote->customer_company)
                <tr><td class="label">{{ __('transport/pdf.label.company') }}</td><td>{{ $quote->customer_company }}</td></tr>
            @endif
            @if ($quote->customer_tax_id)
                <tr><td class="label">{{ __('transport/pdf.label.tax_id') }}</td><td>{{ $quote->customer_tax_id }}</td></tr>
            @endif
            @if ($quote->customer_email)
                <tr><td class="label">{{ __('transport/pdf.label.email') }}</td><td>{{ $quote->customer_email }}</td></tr>
            @endif
            @if ($quote->customer_phone)
                <tr><td class="label">{{ __('transport/pdf.label.phone') }}</td><td>{{ $quote->customer_phone }}</td></tr>
            @endif
            @if ($quote->customer_address)
                <tr><td class="label">{{ __('transport/pdf.label.address') }}</td><td>{{ $quote->customer_address }}</td></tr>
            @endif
        </table>
    </div>

    <div class="section">
        <h2>{{ __('transport/pdf.section.route') }}</h2>
        <table class="grid">
            <tr><td class="label">{{ __('transport/pdf.label.from') }}</td><td>{{ $quote->pickup_address }}</td></tr>
            <tr><td class="label">{{ __('transport/pdf.label.to') }}</td><td>{{ $quote->dropoff_address }}</td></tr>
            <tr><td class="label">{{ __('transport/pdf.label.date') }}</td><td>{{ $quote->preferred_date->format('Y-m-d') }}@if ($quote->preferred_time) {{ $quote->preferred_time }} @endif</td></tr>
            <tr><td class="label">{{ __('transport/pdf.label.distance') }}</td><td>{{ number_format((float) $quote->distance_km, 2, ',', ' ') }} km</td></tr>
            <tr><td class="label">{{ __('transport/pdf.label.duration') }}</td><td>{{ floor($quote->duration_seconds / 3600) }}h {{ floor(($quote->duration_seconds % 3600) / 60) }}min</td></tr>
            @if ($quote->round_trip)
                <tr><td class="label">{{ __('transport/pdf.label.round_trip') }}</td><td>{{ __('transport/pdf.value.yes') }}</td></tr>
            @endif
        </table>
    </div>

    <div class="section">
        <h2>{{ __('transport/pdf.section.pricing') }}</h2>
        <table class="pricing">
            <thead>
                <tr>
                    <th>{{ __('transport/pdf.label.component') }}</th>
                    <th style="text-align: right;">{{ __('transport/pdf.label.amount') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ __('transport/pdf.label.base_cost') }} ({{ number_format((float) $quote->distance_km, 2, ',', ' ') }} km × {{ number_format((float) $quote->rate_per_km, 2, ',', ' ') }} {{ $quote->currency }}/km)</td>
                    <td class="amount">{{ number_format((float) $quote->base_cost, 2, ',', ' ') }} {{ $quote->currency }}</td>
                </tr>
                @if ((float) $quote->fuel_surcharge > 0)
                    <tr>
                        <td>{{ __('transport/pdf.label.fuel_surcharge') }}</td>
                        <td class="amount">{{ number_format((float) $quote->fuel_surcharge, 2, ',', ' ') }} {{ $quote->currency }}</td>
                    </tr>
                @endif
                @if ((int) ($quote->horses_count ?? 1) > 1 && (float) ($quote->extra_horse_fee_snapshot ?? 0) > 0)
                    @php
                        $extraHorses = (int) $quote->horses_count - 1;
                        $extraTotal = $extraHorses * (float) $quote->extra_horse_fee_snapshot;
                    @endphp
                    <tr>
                        <td>{{ __('transport/pdf.label.extra_horse_fee', ['count' => $extraHorses, 'rate' => number_format((float) $quote->extra_horse_fee_snapshot, 2, ',', ' '), 'currency' => $quote->currency]) }}</td>
                        <td class="amount">{{ number_format($extraTotal, 2, ',', ' ') }} {{ $quote->currency }}</td>
                    </tr>
                @endif
                @if ((float) $quote->minimum_adjustment > 0)
                    <tr>
                        <td>{{ __('transport/pdf.label.minimum_adjustment') }}</td>
                        <td class="amount">{{ number_format((float) $quote->minimum_adjustment, 2, ',', ' ') }} {{ $quote->currency }}</td>
                    </tr>
                @endif
                <tr class="subtotal">
                    <td>{{ __('transport/pdf.label.net_total') }}</td>
                    <td class="amount">{{ number_format((float) $quote->net_total, 2, ',', ' ') }} {{ $quote->currency }}</td>
                </tr>
                <tr>
                    <td>{{ __('transport/pdf.label.vat', ['rate' => number_format((float) $quote->vat_rate, 0)]) }}</td>
                    <td class="amount">{{ number_format((float) $quote->vat_amount, 2, ',', ' ') }} {{ $quote->currency }}</td>
                </tr>
                <tr class="total">
                    <td>{{ __('transport/pdf.label.gross_total') }}</td>
                    <td class="amount">{{ number_format((float) $quote->gross_total, 2, ',', ' ') }} {{ $quote->currency }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    @if ($quote->terms)
        <div class="section">
            <h2>{{ __('transport/pdf.section.terms') }}</h2>
            <div class="terms">{{ $quote->terms }}</div>
        </div>
    @endif

    {{--
        Direct-charge payments MVP — patrz docs/TRANSPORT.md §13.
        W PDF'ie pokazujemy info o płatności (URL / metoda / instrukcje + disclaimer),
        żeby klient miał wszystko w jednym pliku.
    --}}
    @php
        $paymentInstructions = trim((string) ($settings?->payment_instructions ?? ''));
        $hasPaymentBlock = $quote->payment_url || $paymentInstructions !== '';
    @endphp
    @if ($hasPaymentBlock)
        <div class="section">
            <h2>{{ __('transport/pdf.section.payment') }}</h2>
            <table class="grid">
                @if ($quote->payment_url)
                    <tr>
                        <td class="label">{{ __('transport/pdf.label.payment_url') }}</td>
                        <td><a href="{{ $quote->payment_url }}">{{ $quote->payment_url }}</a></td>
                    </tr>
                @endif
                @if ($quote->payment_method_label)
                    <tr>
                        <td class="label">{{ __('transport/pdf.label.payment_method_label') }}</td>
                        <td>{{ $quote->payment_method_label }}</td>
                    </tr>
                @endif
                @if (! $quote->payment_url && $paymentInstructions !== '')
                    <tr>
                        <td class="label">{{ __('transport/pdf.label.payment_instructions') }}</td>
                        <td style="white-space: pre-wrap;">{{ $paymentInstructions }}</td>
                    </tr>
                @endif
            </table>
            <div class="terms" style="margin-top: 8px; background: #fffbeb; border-left-color: #d97706; color: #92400e; font-size: 8.5pt;">
                {{ __('transport/pdf.payment_disclaimer', ['transporter' => $sellerName]) }}
            </div>
        </div>
    @endif

    <div class="footer">
        {{ __('transport/pdf.footer.generated', ['app' => config('app.name')]) }} · {{ now()->format('Y-m-d H:i') }}
    </div>
</body>
</html>
