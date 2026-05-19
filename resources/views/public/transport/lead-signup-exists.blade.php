<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('public/transport_lead_portal.signup_form.title') }} — hovera</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}">
    <style>
        :root { --primary: #A8956B; --primary-dark: #8F8576; --bg: #F7F4EF; --text: #1F1A17; --muted: #6b7280; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, system-ui, sans-serif; background: var(--bg); color: var(--text); color-scheme: light; }
        body { padding: 2rem 1rem; display: flex; align-items: center; justify-content: center; }
        .container { max-width: 480px; }
        .card { background: #fff; border-radius: 16px; padding: 2rem; box-shadow: 0 6px 24px rgba(0,0,0,.06); text-align: center; }
        h1 { margin: 0 0 .5rem; color: #3D2E22; }
        p { color: var(--muted); line-height: 1.55; }
        .cta { display: inline-block; margin-top: 1rem; padding: .85rem 1.5rem; background: var(--primary); color: #fff; border-radius: 10px; font-weight: 700; text-decoration: none; }
        .cta:hover { background: var(--primary-dark); }
        .back { display: block; margin-top: .85rem; color: var(--muted); text-decoration: none; font-size: .88rem; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h1>{{ __('public/transport_lead_portal.signup.account_exists') }}</h1>
        <p>{{ $lead->originator_email }}</p>
        <a href="{{ url('/app/login') }}" class="cta">{{ __('public/transport_lead_portal.signup.login_cta') }}</a>
        <a href="{{ route('public.transport.lead_portal', ['slug' => $lead->access_slug]) }}" class="back">← {{ __('public/transport_lead_portal.signup_form.cancel') }}</a>
    </div>
</div>
</body>
</html>
