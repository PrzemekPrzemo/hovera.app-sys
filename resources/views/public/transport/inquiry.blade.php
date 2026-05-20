<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('public/transport_inquiry.title') }} — hovera</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}">
    <style>
        :root { --primary: #A8956B; --bg: #F7F4EF; --text: #1F1A17; --muted: #6b7280; --border: #d4cdb8; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: var(--bg); color: var(--text); }
        body { padding: 2rem 1rem; }
        .container { max-width: 640px; margin: 0 auto; }
        .logo { text-align: center; margin-bottom: 1.5rem; font-size: 1.4rem; font-weight: 700; color: #3D2E22; }
        .card { background: #fff; border-radius: 16px; padding: 2rem; box-shadow: 0 6px 24px rgba(0,0,0,.06); }
        h1 { margin: 0 0 .35rem; font-size: 1.5rem; color: #3D2E22; }
        .subtitle { color: var(--muted); margin-bottom: 1.5rem; font-size: .92rem; line-height: 1.5; }
        .form-row { display: flex; flex-direction: column; gap: .35rem; margin-bottom: 1rem; }
        .row-two { display: grid; gap: 1rem; grid-template-columns: 1fr 1fr; }
        label { font-weight: 600; font-size: .88rem; color: #3D2E22; }
        .helper { font-size: .78rem; color: var(--muted); }
        input[type=text], input[type=email], input[type=tel], input[type=number], input[type=date], input[type=time], textarea { padding: .65rem .85rem; border: 1px solid var(--border); border-radius: 8px; font: inherit; background: #fff; color: var(--text); width: 100%; }
        textarea { min-height: 80px; resize: vertical; }
        input:focus, textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 25%, transparent); }
        .checkbox { display: flex; gap: .5rem; align-items: flex-start; margin: 1rem 0; font-size: .85rem; }
        button[type=submit] { width: 100%; padding: .9rem 1.2rem; background: var(--primary); color: #fff; border: 0; border-radius: 8px; font-weight: 700; font-size: 1rem; cursor: pointer; }
        button[type=submit]:hover { filter: brightness(0.95); }
        .errors { background: #fef2f2; color: #991b1b; padding: .75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: .88rem; }
        .errors ul { margin: .25rem 0 0; padding-left: 1.25rem; }
        .direct-banner { background: color-mix(in srgb, var(--primary) 12%, #fff); border: 1px solid color-mix(in srgb, var(--primary) 35%, var(--border)); color: #3D2E22; padding: .75rem 1rem; border-radius: 8px; margin-bottom: 1.25rem; font-size: .9rem; line-height: 1.5; }
        .direct-banner a { color: var(--primary); text-decoration: underline; }
        .direct-banner strong { color: #3D2E22; }
                /* Mobile (≤600px) — pojedyncze kolumny, mniej paddingu, większe taps. */
        @media (max-width: 600px) {
            body { padding: 1rem .75rem; }
            .card { padding: 1.25rem 1rem; border-radius: 12px; }
            h1 { font-size: 1.25rem; }
            .row-two { grid-template-columns: 1fr; gap: 0; }
            .direct-banner { padding: .65rem .85rem; font-size: .85rem; }
            input[type=text], input[type=email], input[type=tel], input[type=number],
            input[type=date], input[type=time], textarea {
                font-size: 16px; /* zapobiega zoomowi iOS przy focus */
                padding: .75rem .85rem;
            }
            button[type=submit] { padding: 1rem; font-size: 1.05rem; }
        }
            /* Light mode only — wymog user spec. Brak prefers-color-scheme:dark override. */
        html { color-scheme: light; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">hovera</div>

        <div class="card">
            <h1>{{ __('public/transport_inquiry.heading') }}</h1>
            <div class="subtitle">{{ __('public/transport_inquiry.subtitle') }}</div>

            @if (! empty($targetTransporter))
                <div class="direct-banner">
                    {!! __('public/transport_inquiry.direct_target_banner', ['name' => '<strong>'.e($targetTransporter->name).'</strong>']) !!}
                    <br>
                    <a href="{{ route('public.transport.inquiry') }}">{{ __('public/transport_inquiry.direct_target_switch_to_broadcast') }}</a>
                </div>
            @endif

            @if (! empty($originatorStable))
                <div class="direct-banner">
                    {!! __('public/transport_inquiry.originator_banner.from_stable', ['name' => '<strong>'.e($originatorStable->name).'</strong>']) !!}
                    —
                    <a href="{{ url('/app/transport') }}">{{ __('public/transport_inquiry.originator_banner.back_to_app') }}</a>
                </div>
            @endif

            @include('public.transport._inquiry-form', [
                'old' => $old,
                'targetTransporter' => $targetTransporter,
                'formId' => 'inquiry-page',
            ])
        </div>
    </div>
</body>
</html>
