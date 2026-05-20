<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('public/transport_review.already.title') }}</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="{{ asset('favicon.svg') }}">
    <style>
        :root { --primary: #A8956B; --bg: #F7F4EF; --text: #1F1A17; --muted: #6b7280; --card: #fff; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: var(--bg); color: var(--text); }
        .wrap { max-width: 540px; margin: 0 auto; padding: 3rem 1.25rem; text-align: center; }
        .card { background: var(--card); border-radius: 14px; padding: 2rem 1.75rem; box-shadow: 0 3px 14px rgba(0,0,0,.06); }
        h1 { margin: 0 0 .75rem; color: #3D2E22; }
        p { color: var(--muted); line-height: 1.55; }
        a.btn { display: inline-block; margin-top: 1rem; padding: .65rem 1.1rem; background: var(--primary); color: #fff; border-radius: 10px; text-decoration: none; font-weight: 600; }
                    /* Light mode only — wymog user spec. Brak prefers-color-scheme:dark override. */
        html { color-scheme: light; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1>{{ __('public/transport_review.already.heading') }}</h1>
            <p>{{ __('public/transport_review.already.body') }}</p>
            @if ($transporter && $transporter->slug)
                <a class="btn" href="{{ route('public.transporter', ['slug' => $transporter->slug]) }}">
                    {{ __('public/transport_review.already.see_profile') }}
                </a>
            @endif
        </div>
    </div>
</body>
</html>
