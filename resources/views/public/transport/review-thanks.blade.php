<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('public/transport_review.thanks.title') }}</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="{{ asset('favicon.svg') }}">
    <style>
        :root { --primary: #A8956B; --bg: #F7F4EF; --text: #1F1A17; --muted: #6b7280; --card: #fff; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: var(--bg); color: var(--text); }
        .wrap { max-width: 540px; margin: 0 auto; padding: 3rem 1.25rem; text-align: center; }
        .card { background: var(--card); border-radius: 14px; padding: 2rem 1.75rem; box-shadow: 0 3px 14px rgba(0,0,0,.06); }
        h1 { margin: 0 0 .75rem; color: #3D2E22; }
        p { color: var(--muted); line-height: 1.55; }
        .disclaimer { font-size: .8rem; color: var(--muted); margin-top: 1.5rem; }
        .disclaimer a { color: var(--primary); }
        @media (prefers-color-scheme: dark) {
            html, body { background: #1F1A17; color: #F7F4EF; }
            .card { background: #2a221c; }
            h1 { color: #E9E2D3; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1>{{ __('public/transport_review.thanks.heading') }}</h1>
            <p>{{ __('public/transport_review.thanks.body') }}</p>
            <p class="disclaimer">
                {!! __('public/transport_review.thanks.disclaimer_intermediary') !!}
            </p>
        </div>
    </div>
</body>
</html>
