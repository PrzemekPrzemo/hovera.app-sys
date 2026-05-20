<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('public/horse_owner_registration.thanks.heading') }} — hovera</title>
    <meta name="robots" content="noindex">
    <link rel="icon" href="{{ asset('favicon.svg') }}">
    <style>
        :root { --primary: #A8956B; --bg: #F7F4EF; --text: #1F1A17; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: var(--bg); color: var(--text); padding: 2rem 1rem; box-sizing: border-box; }
        .container { max-width: 520px; margin: 0 auto; text-align: center; }
        .logo { font-size: 1.4rem; font-weight: 700; letter-spacing: -.02em; color: #3D2E22; margin-bottom: 1.5rem; }
        .card { background: #fff; border-radius: 16px; padding: 2.5rem 2rem; box-shadow: 0 6px 24px rgba(0,0,0,.06); }
        .check { width: 64px; height: 64px; margin: 0 auto 1rem; border-radius: 50%; background: #10b981; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 2rem; }
        h1 { margin: 0 0 .75rem; font-size: 1.5rem; }
        p { color: #6b7280; line-height: 1.55; margin: 0 0 1.5rem; }
        .steps { text-align: left; background: var(--bg); border-radius: 10px; padding: 1rem 1.25rem; margin: 1.5rem 0; }
        .steps h3 { margin: 0 0 .5rem; font-size: .92rem; color: #3D2E22; }
        .steps ol { margin: 0; padding-left: 1.25rem; font-size: .88rem; color: #6b7280; line-height: 1.7; }
        .btn { display: inline-block; padding: .8rem 1.5rem; background: var(--primary); color: #fff; border: 0; border-radius: 8px; font-weight: 700; text-decoration: none; }
        .btn:hover { filter: brightness(0.95); }
        @media (prefers-color-scheme: dark) {
            html, body { background: #1F1A17; color: #F7F4EF; }
            .card { background: #2a221c; }
            .logo, h1, .steps h3 { color: #E9E2D3; }
            p, .steps ol { color: #C8B8A4; }
            .steps { background: #1F1A17; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">hovera</div>
        <div class="card">
            <div class="check">✓</div>
            <h1>{{ __('public/horse_owner_registration.thanks.heading') }}</h1>
            <p>{{ __('public/horse_owner_registration.thanks.body') }}</p>

            <div class="steps">
                <h3>{{ __('public/horse_owner_registration.thanks.next_steps') }}</h3>
                <ol>
                    <li>{{ __('public/horse_owner_registration.thanks.step_horses') }}</li>
                    <li>{{ __('public/horse_owner_registration.thanks.step_transport') }}</li>
                    <li>{{ __('public/horse_owner_registration.thanks.step_documents') }}</li>
                </ol>
            </div>

            <a href="/owner/login" class="btn">{{ __('public/horse_owner_registration.thanks.open_login') }}</a>
        </div>
    </div>
</body>
</html>
