<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('public/transporter_onboarding.rate_limited.title') }} — hovera</title>
    <meta name="robots" content="noindex">
    <link rel="icon" href="{{ asset('favicon.svg') }}">
    <style>
        :root { --primary: #A8956B; --primary-dark: #8F8576; --bg: #F7F4EF; --text: #1F1A17; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: var(--bg); color: var(--text); }
        body { padding: 3rem 1rem; }
        .container { max-width: 560px; margin: 0 auto; }
        .card { background: #fff; border-radius: 16px; padding: 2.5rem 2rem; text-align: center; box-shadow: 0 6px 24px rgba(0,0,0,.06); }
        .icon { font-size: 3rem; margin-bottom: 1rem; }
        h1 { margin: 0 0 .5rem; font-size: 1.55rem; }
        p { color: #6b7280; line-height: 1.6; margin-bottom: 1rem; }
        .info-box { background: #FFFBEB; border: 1px solid #FCD34D; border-radius: 10px; padding: 1.1rem 1.25rem; margin: 1.25rem 0; text-align: left; }
        .info-box strong { color: #92400E; }
        .info-box p { color: #78350F; margin: .35rem 0 0; font-size: .92rem; }
        .cta-secondary { display: inline-block; padding: .65rem 1.25rem; background: transparent; color: var(--primary); border: 1.5px solid var(--primary); border-radius: 10px; text-decoration: none; font-weight: 600; margin-top: 1rem; font-size: .92rem; }
        .cta-secondary:hover { background: var(--bg); }
        .contact { margin-top: 1.5rem; font-size: .85rem; color: #6b7280; }
        .contact a { color: var(--primary); text-decoration: none; }
        /* Light mode only — wymog user spec. */
        html { color-scheme: light; }
    </style>
    <x-google-analytics />
</head>
<body>
<div class="container">
    <div class="card">
        <div class="icon">⏳</div>
        <h1>{{ __('public/transporter_onboarding.rate_limited.heading') }}</h1>

        <p>{{ __('public/transporter_onboarding.rate_limited.intro') }}</p>

        <div class="info-box">
            <strong>{{ __('public/transporter_onboarding.rate_limited.already_submitted_heading') }}</strong>
            <p>{{ __('public/transporter_onboarding.rate_limited.already_submitted_body') }}</p>
        </div>

        <p>
            {{ __('public/transporter_onboarding.rate_limited.retry_after', ['minutes' => $retry_after_minutes]) }}
        </p>

        <a href="{{ url('/przewoznicy') }}" class="cta-secondary">
            ← {{ __('public/transporter_onboarding.rate_limited.back_to_landing') }}
        </a>

        <p class="contact">
            {!! __('public/transporter_onboarding.rate_limited.contact_hint', [
                'email' => '<a href="mailto:'.e(config('hovera.legal.contact_email', 'office@hovera.app')).'">'.e(config('hovera.legal.contact_email', 'office@hovera.app')).'</a>',
            ]) !!}
        </p>
    </div>
</div>
</body>
</html>
