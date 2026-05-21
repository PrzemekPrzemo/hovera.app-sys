<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('public/transport_lead_portal.title') }} — hovera</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}">
    <style>
        :root { --primary: #A8956B; --primary-dark: #8F8576; --bg: #F7F4EF; --text: #1F1A17; --success: #166534; --muted: #6b7280; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, system-ui, sans-serif; background: var(--bg); color: var(--text); color-scheme: light; }
        body { padding: 2rem 1rem; }
        .container { max-width: 880px; margin: 0 auto; }
        .logo { text-align: center; margin-bottom: 1.5rem; font-size: 1.4rem; font-weight: 700; letter-spacing: -.02em; color: #3D2E22; }
        .card { background: #fff; border-radius: 16px; padding: 1.75rem 1.5rem; box-shadow: 0 6px 24px rgba(0,0,0,.06); margin-bottom: 1.25rem; }
        h1 { margin: 0 0 .5rem; font-size: 1.5rem; color: #3D2E22; }
        h2 { margin: 0 0 1rem; font-size: 1.05rem; color: #3D2E22; border-bottom: 2px solid var(--bg); padding-bottom: .5rem; }
        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem 1.5rem; font-size: .9rem; }
        @media (max-width: 600px) { .meta-grid { grid-template-columns: 1fr; } }
        .meta-grid .label { color: var(--muted); font-size: .78rem; text-transform: uppercase; letter-spacing: .05em; margin-bottom: .15rem; }
        .meta-grid .value { color: var(--text); font-weight: 500; }
        .badge-status { display: inline-block; padding: .15rem .5rem; border-radius: 12px; background: #ecfdf5; color: var(--success); font-size: .78rem; font-weight: 600; }
        .response-row { padding: 1rem; background: #faf7f1; border-radius: 10px; border: 1px solid #e8e0cc; margin-bottom: .75rem; display: grid; grid-template-columns: 1fr auto; gap: 1rem; align-items: center; }
        @media (max-width: 600px) { .response-row { grid-template-columns: 1fr; } }
        .response-row .name { font-weight: 600; color: #3D2E22; }
        .response-row .price { font-weight: 700; color: var(--primary-dark); font-size: 1.15rem; }
        .response-row .terms { color: var(--muted); font-size: .82rem; margin-top: .35rem; line-height: 1.5; }
        .response-row .accepted { color: var(--success); font-weight: 700; }
        .no-responses { padding: 1.25rem; text-align: center; color: var(--muted); background: #faf7f1; border-radius: 10px; }
        .signup-cta { background: linear-gradient(135deg, #fff8e1 0%, #fef3c7 100%); border: 2px solid #d4b95c; border-radius: 12px; padding: 1.25rem; color: #5d4d22; }
        .signup-cta h3 { margin: 0 0 .35rem; font-size: 1rem; color: #3D2E22; }
        .signup-cta p { margin: 0 0 .75rem; font-size: .88rem; line-height: 1.55; }
        .signup-cta .signup-disabled { font-style: italic; color: var(--muted); font-size: .82rem; }
        .footer { text-align: center; color: var(--muted); font-size: .78rem; margin-top: 1.5rem; line-height: 1.5; }
    </style>
    <x-google-analytics />
</head>
<body>
<div class="container">
    <div class="logo">hovera · transport</div>

    <div class="card">
        <h1>{{ __('public/transport_lead_portal.heading') }}</h1>
        <p style="color:var(--muted);margin:.25rem 0 1.25rem 0;line-height:1.55;">{{ __('public/transport_lead_portal.subtitle') }}</p>

        <h2>{{ __('public/transport_lead_portal.section.summary') }}</h2>
        <div class="meta-grid">
            <div>
                <div class="label">{{ __('public/transport_lead_portal.label.pickup') }}</div>
                <div class="value">{{ $lead->pickup_address }}</div>
            </div>
            <div>
                <div class="label">{{ __('public/transport_lead_portal.label.dropoff') }}</div>
                <div class="value">{{ $lead->dropoff_address }}</div>
            </div>
            <div>
                <div class="label">{{ __('public/transport_lead_portal.label.date') }}</div>
                <div class="value">{{ optional($lead->preferred_date)->toDateString() ?: '—' }}{{ $lead->preferred_time ? ' '.\Illuminate\Support\Str::substr((string) $lead->preferred_time, 0, 5) : '' }}</div>
            </div>
            <div>
                <div class="label">{{ __('public/transport_lead_portal.label.horses') }}</div>
                <div class="value">{{ $lead->horse_count }}</div>
            </div>
            <div>
                <div class="label">{{ __('public/transport_lead_portal.label.status') }}</div>
                <div class="value"><span class="badge-status">{{ __('public/transport_lead_portal.status.'.$lead->status) }}</span></div>
            </div>
            @if ($lead->notes)
                <div style="grid-column: 1 / -1;">
                    <div class="label">{{ __('public/transport_lead_portal.label.notes') }}</div>
                    <div class="value" style="white-space:pre-line;">{{ $lead->notes }}</div>
                </div>
            @endif
        </div>
    </div>

    <div class="card">
        <h2>{{ __('public/transport_lead_portal.section.offers', ['count' => $responses->count()]) }}</h2>

        @forelse ($responses as $response)
            @php($transporter = $transporters->get($response->transporter_tenant_id))
            <div class="response-row">
                <div>
                    <div class="name">{{ optional($transporter)->name ?: __('public/transport_lead_portal.transporter_unknown') }}</div>
                    @if ($response->status === 'accepted')
                        <div class="accepted">✓ {{ __('public/transport_lead_portal.response.accepted') }}</div>
                    @endif
                    @if ($response->terms)
                        <div class="terms">{{ $response->terms }}</div>
                    @endif
                    @if ($response->proposed_date)
                        <div class="terms">
                            {{ __('public/transport_lead_portal.response.proposed_date') }}:
                            {{ optional($response->proposed_date)->toDateString() }}{{ $response->proposed_time ? ' '.\Illuminate\Support\Str::substr((string) $response->proposed_time, 0, 5) : '' }}
                        </div>
                    @endif
                </div>
                <div style="text-align:right;">
                    <div class="price">{{ number_format((float) $response->price_gross, 2, ',', ' ') }} {{ $response->currency }}</div>
                    @if ($response->distance_km)
                        <div class="terms">{{ number_format((float) $response->distance_km, 0, ',', ' ') }} km</div>
                    @endif
                </div>
            </div>
        @empty
            <div class="no-responses">{{ __('public/transport_lead_portal.no_responses') }}</div>
        @endforelse
    </div>

    <div class="card signup-cta">
        <h3>{{ __('public/transport_lead_portal.signup.heading') }}</h3>
        <p>{{ __('public/transport_lead_portal.signup.body') }}</p>
        <p class="signup-disabled">{{ __('public/transport_lead_portal.signup.coming_soon') }}</p>
    </div>

    <div class="footer">
        {{ __('public/transport_lead_portal.footer.permanent_link') }}<br>
        {{ __('public/transport_lead_portal.footer.disclaimer_intermediary') }}
    </div>
</div>
</body>
</html>
