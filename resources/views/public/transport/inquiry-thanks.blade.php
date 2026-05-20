<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('public/transport_inquiry.thanks_title') }} — hovera</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}">
    <style>
        :root { --primary: #A8956B; --bg: #F7F4EF; --text: #1F1A17; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, system-ui, sans-serif; background: var(--bg); color: var(--text); }
        body { padding: 2rem 1rem; display: flex; align-items: center; justify-content: center; }
        .container { max-width: 520px; }
        .card { background: #fff; border-radius: 16px; padding: 2.5rem 2rem; box-shadow: 0 6px 24px rgba(0,0,0,.06); text-align: center; }
        .check { font-size: 3rem; color: var(--primary); margin-bottom: 1rem; }
        h1 { margin: 0 0 1rem; color: #3D2E22; }
        p { color: #6b7280; line-height: 1.6; }
        .lead-meta { background: #F7F4EF; padding: .75rem 1rem; border-radius: 8px; margin-top: 1.25rem; font-size: .85rem; color: #3D2E22; font-family: monospace; }
                    /* Light mode only — wymog user spec. Brak prefers-color-scheme:dark override. */
        html { color-scheme: light; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="check">✓</div>
            <h1>{{ __('public/transport_inquiry.thanks_heading') }}</h1>
            <p>{{ __('public/transport_inquiry.thanks_body', ['email' => $lead->originator_email]) }}</p>
            <div class="lead-meta">{{ __('public/transport_inquiry.thanks_reference') }}: {{ $lead->id }}</div>

            {{-- Przypomnienie: kontaktować się będzie WYBRANY przewoźnik (nie Hovera).
                 Hovera = tylko pośrednik technologiczny. --}}
            <p style="margin-top:1.25rem;font-size:.78rem;color:#6b7280;font-style:italic;line-height:1.5;">
                {!! __('public/transport_inquiry.disclaimer_intermediary_thanks') !!}
            </p>
        </div>
    </div>
</body>
</html>
