<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('transport/landing.title', ['number' => $quote->number]) }}</title>
    <meta name="robots" content="noindex">
    <link rel="icon" href="{{ asset('favicon.svg') }}">
    <style>
        :root { --primary: #A8956B; --bg: #F7F4EF; --text: #1F1A17; --muted: #6b7280; --border: #d4cdb8; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: var(--bg); color: var(--text); }
        body { padding: 2rem 1rem; }
        .container { max-width: 640px; margin: 0 auto; }
        .seller { text-align: center; margin-bottom: 1.5rem; color: #3D2E22; font-weight: 700; font-size: 1.1rem; }
        .card { background: #fff; border-radius: 16px; padding: 2rem; box-shadow: 0 6px 24px rgba(0,0,0,.06); margin-bottom: 1rem; }
        .number { font-size: .85rem; color: var(--muted); letter-spacing: .04em; }
        h1 { margin: .25rem 0 1.25rem; font-size: 1.5rem; color: #3D2E22; }
        .grid { display: grid; gap: .35rem; font-size: .92rem; margin-bottom: 1.25rem; }
        .grid .row { display: flex; }
        .grid .row .l { color: var(--muted); flex: 0 0 9rem; }
        .grid .row .v { color: var(--text); font-weight: 500; }
        .pricing { margin-top: 1rem; border-top: 1px solid var(--border); padding-top: .75rem; }
        .pricing-row { display: flex; justify-content: space-between; padding: .25rem 0; font-size: .92rem; }
        .pricing-row.total { border-top: 2px solid var(--primary); padding-top: .75rem; margin-top: .5rem; font-weight: 700; font-size: 1.15rem; }
        .actions { display: flex; gap: .75rem; margin-top: 1.5rem; }
        .btn { flex: 1; padding: .9rem; border-radius: 10px; font-weight: 700; cursor: pointer; border: 0; font-size: 1rem; }
        .btn-accept { background: var(--primary); color: #fff; }
        .btn-accept:hover { filter: brightness(.95); }
        .btn-reject { background: #fff; color: #b91c1c; border: 1px solid #fecaca; }
        .btn-reject:hover { background: #fef2f2; }
        .banner { padding: 1.25rem; border-radius: 12px; text-align: center; font-weight: 600; margin-bottom: 1rem; }
        .banner.accepted { background: #ecfdf5; color: #047857; border: 1px solid #6ee7b7; }
        .banner.rejected { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .banner.final { background: #f3f4f6; color: var(--muted); border: 1px solid #e5e7eb; font-weight: 400; }
        .terms { margin-top: 1.25rem; padding: 1rem; background: #f7f4ef; border-radius: 10px; font-size: .85rem; line-height: 1.6; white-space: pre-wrap; }
        .footer { text-align: center; margin-top: 1.5rem; font-size: .75rem; color: var(--muted); }
        @media (prefers-color-scheme: dark) {
            html, body { background: #1F1A17; color: #F7F4EF; }
            .card { background: #2a221c; }
            .seller, h1 { color: #E9E2D3; }
            .grid .row .v { color: #F7F4EF; }
            .grid .row .l, .number, .footer { color: #C8B8A4; }
            .terms { background: #1F1A17; color: #E9E2D3; }
            .btn-reject { background: #2a221c; color: #fca5a5; border-color: #57342f; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="seller">{{ $tenant->legal_name ?? $tenant->name }}</div>

        @if (session('accepted'))
            <div class="banner accepted">✓ {{ __('transport/landing.accepted_banner') }}</div>
        @elseif (session('rejected'))
            <div class="banner rejected">✕ {{ __('transport/landing.rejected_banner') }}</div>
        @elseif ($quote->status->value === 'accepted')
            <div class="banner final">{{ __('transport/landing.already_accepted') }} ({{ $quote->accepted_at?->format('Y-m-d H:i') }})</div>
        @elseif ($quote->status->value === 'rejected')
            <div class="banner final">{{ __('transport/landing.already_rejected') }} ({{ $quote->rejected_at?->format('Y-m-d H:i') }})</div>
        @endif

        <div class="card">
            <div class="number">{{ __('transport/landing.quote_number') }}</div>
            <h1>{{ $quote->number }}</h1>

            <div class="grid">
                <div class="row"><span class="l">{{ __('transport/landing.label.from') }}</span><span class="v">{{ $quote->pickup_address }}</span></div>
                <div class="row"><span class="l">{{ __('transport/landing.label.to') }}</span><span class="v">{{ $quote->dropoff_address }}</span></div>
                <div class="row"><span class="l">{{ __('transport/landing.label.date') }}</span><span class="v">{{ $quote->preferred_date->format('Y-m-d') }}@if ($quote->preferred_time) · {{ $quote->preferred_time }}@endif</span></div>
                <div class="row"><span class="l">{{ __('transport/landing.label.distance') }}</span><span class="v">{{ number_format((float) $quote->distance_km, 2, ',', ' ') }} km</span></div>
                @if ($quote->valid_until)
                    <div class="row"><span class="l">{{ __('transport/landing.label.valid_until') }}</span><span class="v">{{ $quote->valid_until->format('Y-m-d') }}</span></div>
                @endif
            </div>

            <div class="pricing">
                <div class="pricing-row"><span>{{ __('transport/landing.label.net') }}</span><span>{{ number_format((float) $quote->net_total, 2, ',', ' ') }} {{ $quote->currency }}</span></div>
                <div class="pricing-row"><span>{{ __('transport/landing.label.vat', ['rate' => number_format((float) $quote->vat_rate, 0)]) }}</span><span>{{ number_format((float) $quote->vat_amount, 2, ',', ' ') }} {{ $quote->currency }}</span></div>
                <div class="pricing-row total"><span>{{ __('transport/landing.label.gross') }}</span><span>{{ number_format((float) $quote->gross_total, 2, ',', ' ') }} {{ $quote->currency }}</span></div>
            </div>

            @if ($quote->terms)
                <div class="terms">{{ $quote->terms }}</div>
            @endif

            @if ($quote->status->value === 'sent')
                <div class="actions">
                    <form method="post" action="{{ route('public.transport.quote.accept', ['slug' => $slug, 'token' => $token]) }}">
                        @csrf
                        <button type="submit" class="btn btn-accept">✓ {{ __('transport/landing.action.accept') }}</button>
                    </form>
                    <form method="post" action="{{ route('public.transport.quote.reject', ['slug' => $slug, 'token' => $token]) }}">
                        @csrf
                        <button type="submit" class="btn btn-reject">✕ {{ __('transport/landing.action.reject') }}</button>
                    </form>
                </div>
            @endif
        </div>

        <div class="footer">{{ __('transport/landing.footer', ['app' => config('app.name')]) }}</div>
    </div>
</body>
</html>
