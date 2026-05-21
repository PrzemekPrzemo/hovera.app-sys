<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('public/transporter_onboarding.thanks.title') }} — hovera</title>
    <meta name="robots" content="noindex">
    <link rel="icon" href="{{ asset('favicon.svg') }}">
    <style>
        :root { --primary: #A8956B; --primary-dark: #8F8576; --bg: #F7F4EF; --text: #1F1A17; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: var(--bg); color: var(--text); }
        body { padding: 3rem 1rem; }
        .container { max-width: 560px; margin: 0 auto; }
        .card { background: #fff; border-radius: 16px; padding: 2.5rem 2rem; text-align: center; box-shadow: 0 6px 24px rgba(0,0,0,.06); }
        .icon { font-size: 3rem; margin-bottom: 1rem; }
        h1 { margin: 0 0 .5rem; font-size: 1.6rem; }
        p { color: #6b7280; line-height: 1.6; margin-bottom: 1.25rem; }
        ol { text-align: left; background: var(--bg); padding: 1.25rem 1.25rem 1.25rem 2.5rem; border-radius: 10px; line-height: 1.7; font-size: .92rem; color: #3D2E22; }
        .cta { display: inline-block; padding: .75rem 1.5rem; background: var(--primary); color: #fff; border-radius: 10px; text-decoration: none; font-weight: 700; margin-top: 1rem; }
        .cta:hover { background: var(--primary-dark); }
            /* Light mode only — wymog user spec. Brak prefers-color-scheme:dark override. */
        html { color-scheme: light; }
    </style>
    <x-google-analytics />
</head>
<body>
<div class="container">
    <div class="card">
        <div class="icon">✓</div>
        <h1>{{ __('public/transporter_onboarding.thanks.heading') }}</h1>
        <p>{{ __('public/transporter_onboarding.thanks.intro', ['name' => $tenant->name]) }}</p>

        <ol>
            <li>{{ __('public/transporter_onboarding.thanks.step_1') }}</li>
            <li>{{ __('public/transporter_onboarding.thanks.step_2') }}</li>
            <li>{{ __('public/transporter_onboarding.thanks.step_3') }}</li>
        </ol>

        <p style="margin-top: 1.25rem; font-size: .85rem;">
            {{ __('public/transporter_onboarding.thanks.contact_hint', ['email' => config('hovera.legal.contact_email', 'office@hovera.app')]) }}
        </p>

        <a href="{{ route('public.transporters.directory') }}" class="cta">{{ __('public/transporter_onboarding.thanks.cta_directory') }}</a>
    </div>
</div>
</body>
</html>
