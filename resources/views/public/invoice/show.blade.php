<!DOCTYPE html>
<html lang="{{ $tenant->locale ?? 'pl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $invoice->kind->label() }} {{ $invoice->number }} — {{ $tenant->name }}</title>
    <meta name="robots" content="noindex">
    <style>
        :root { --primary: {{ $primary_color }}; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: #fafafa; color: #1f2937; }
        body { padding: 1rem; }
        .container { max-width: 720px; margin: 0 auto; }
        .card { background: #fff; border-radius: 14px; padding: 1.5rem; box-shadow: 0 4px 18px rgba(0,0,0,.05); margin-bottom: 1rem; }
        h1 { color: var(--primary); margin: 0 0 .25rem; font-size: 1.4rem; }
        .number { font-size: 1.6rem; font-weight: 700; }
        .meta { color: #6b7280; font-size: .9rem; margin-bottom: 1rem; }
        .parties { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .party { padding: 1rem; background: #f9fafb; border-radius: 8px; font-size: .9rem; }
        .party h3 { margin: 0 0 .35rem; font-size: .8rem; color: #6b7280; text-transform: uppercase; letter-spacing: .04em; }
        .items { width: 100%; border-collapse: collapse; margin-top: 1rem; font-size: .9rem; }
        .items th, .items td { padding: .5rem .65rem; border-bottom: 1px solid #f3f4f6; text-align: left; }
        .items th { background: #f9fafb; color: #374151; font-weight: 600; font-size: .8rem; text-transform: uppercase; }
        .items td.num { text-align: right; }
        .totals { margin-top: 1rem; text-align: right; }
        .totals dl { display: inline-grid; grid-template-columns: max-content max-content; gap: .35rem 1rem; }
        .totals dt { color: #6b7280; }
        .totals dd { margin: 0; font-weight: 500; }
        .totals .grand dt, .totals .grand dd { color: var(--primary); font-size: 1.1rem; font-weight: 700; }
        .pay-cta { padding: 1.5rem; background: color-mix(in srgb, var(--primary) 8%, white); border-radius: 14px; text-align: center; margin-bottom: 1rem; }
        .pay-cta .btn { display: inline-block; padding: .9rem 2rem; background: var(--primary); color: #fff; border-radius: 8px; font-weight: 700; text-decoration: none; font-size: 1.1rem; }
        .pay-cta .btn:hover { filter: brightness(0.95); }
        .paid { padding: 1rem; background: #d1fae5; color: #065f46; border-radius: 14px; text-align: center; margin-bottom: 1rem; font-weight: 600; }
        .pill { display: inline-block; padding: .15rem .55rem; border-radius: 999px; font-size: .7rem; font-weight: 600; }
        .pill.fv { background: color-mix(in srgb, var(--primary) 18%, white); color: var(--primary); }
        @media (prefers-color-scheme: dark) {
            body { background: #0f172a; color: #e5e7eb; }
            .card { background: #1e293b; }
            .meta { color: #94a3b8; }
            .party { background: #0f172a; }
            .party h3 { color: #94a3b8; }
            .items th { background: #0f172a; color: #cbd5e1; }
            .items th, .items td { border-color: #334155; }
            .totals dt { color: #94a3b8; }
            .pay-cta { background: #1e293b; }
        }
    </style>
</head>
<body>
    <div class="container">
        @if ($invoice->status === \App\Enums\InvoiceStatus::Paid)
            <div class="paid">✓ Faktura została opłacona {{ $invoice->paid_at?->format('d.m.Y') }}.</div>
        @elseif ($can_pay_online)
            <div class="pay-cta">
                <p style="margin:0 0 .8rem;color:#374151">Możesz zapłacić online bezpośrednio przez stajnię.</p>
                <a class="btn" href="{{ $pay_url }}">Zapłać teraz {{ $invoice->totalFormatted() }}</a>
            </div>
        @endif

        <div class="card">
            <span class="pill fv">{{ $invoice->kind->shortLabel() }}</span>
            <h1>{{ $invoice->kind->label() }}</h1>
            <div class="number">{{ $invoice->number }}</div>
            <div class="meta">
                Data wystawienia: {{ $invoice->issued_at?->format('Y-m-d') }}
                @if ($invoice->due_at) · Termin płatności: {{ $invoice->due_at->format('Y-m-d') }} @endif
            </div>

            <div class="parties">
                <div class="party">
                    <h3>Sprzedawca</h3>
                    <div><strong>{{ $invoice->seller_name }}</strong></div>
                    @if ($invoice->seller_nip)<div>NIP: {{ $invoice->seller_nip }}</div>@endif
                    @if ($invoice->seller_address)<div>{{ $invoice->seller_address }}</div>@endif
                    @if ($invoice->seller_postal_code || $invoice->seller_city)
                        <div>{{ trim(($invoice->seller_postal_code ?? '').' '.($invoice->seller_city ?? '')) }}</div>
                    @endif
                </div>
                <div class="party">
                    <h3>Nabywca</h3>
                    <div><strong>{{ $invoice->buyer_name }}</strong></div>
                    @if ($invoice->buyer_nip)<div>NIP: {{ $invoice->buyer_nip }}</div>@endif
                    @if ($invoice->buyer_address)<div>{{ $invoice->buyer_address }}</div>@endif
                    @if ($invoice->buyer_postal_code || $invoice->buyer_city)
                        <div>{{ trim(($invoice->buyer_postal_code ?? '').' '.($invoice->buyer_city ?? '')) }}</div>
                    @endif
                </div>
            </div>

            <table class="items">
                <thead>
                <tr>
                    <th style="width:1.5rem">#</th>
                    <th>Nazwa</th>
                    <th class="num">Ilość</th>
                    <th class="num">Cena netto</th>
                    <th class="num">VAT</th>
                    <th class="num">Brutto</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($invoice->items as $item)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $item->name }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 3, ',', ' '), '0'), ',') }} {{ $item->unit }}</td>
                        <td class="num">{{ number_format($item->unit_price_cents / 100, 2, ',', ' ') }}</td>
                        <td class="num">{{ is_numeric($item->vat_rate) ? $item->vat_rate.'%' : $item->vat_rate }}</td>
                        <td class="num">{{ number_format($item->total_cents / 100, 2, ',', ' ') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <div class="totals">
                <dl>
                    <dt>Suma netto</dt><dd>{{ number_format($invoice->subtotal_cents / 100, 2, ',', ' ') }} {{ $invoice->currency }}</dd>
                    <dt>VAT</dt><dd>{{ number_format($invoice->vat_cents / 100, 2, ',', ' ') }} {{ $invoice->currency }}</dd>
                </dl>
                <dl class="grand">
                    <dt>Do zapłaty</dt><dd>{{ $invoice->totalFormatted() }}</dd>
                </dl>
            </div>
        </div>
    </div>
</body>
</html>
