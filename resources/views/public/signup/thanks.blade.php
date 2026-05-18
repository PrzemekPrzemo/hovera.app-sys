<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('public/signup.thanks_title') }} — hovera</title>
    <meta name="robots" content="noindex">
    <link rel="icon" href="{{ asset('favicon.svg') }}">
    <x-pwa-head />
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: #F7F4EF; color: #1F1A17; }
        body { padding: 3rem 1rem; }
        .container { max-width: 540px; margin: 0 auto; text-align: center; }
        .logo { font-size: 1.4rem; font-weight: 700; letter-spacing: -.02em; color: #3D2E22; margin-bottom: 2rem; }
        .card { background: #fff; border-radius: 16px; padding: 2.5rem 2rem; box-shadow: 0 6px 24px rgba(0,0,0,.06); }
        .icon { width: 64px; height: 64px; margin: 0 auto 1rem; background: #d1fae5; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        h1 { margin: 0 0 .8rem; font-size: 1.6rem; }
        .subtitle { color: #4b5563; margin-bottom: 1.5rem; line-height: 1.6; }
        .email-pill { display: inline-block; padding: .35rem .85rem; background: #F7F4EF; border-radius: 999px; font-family: ui-monospace, monospace; font-size: .85rem; color: #3D2E22; margin: .35rem 0; }
        .step-list { text-align: left; background: #F7F4EF; padding: 1rem 1.25rem 1rem 2rem; border-radius: 10px; margin: 1.5rem 0; line-height: 1.7; color: #3D2E22; font-size: .9rem; }
        .step-list li { margin: .25rem 0; }
        .footer-links { margin-top: 2rem; font-size: .85rem; color: #6b7280; }
        .footer-links a { color: #A8956B; text-decoration: none; margin: 0 .35rem; }
        .footer-links a:hover { text-decoration: underline; }
        @media (prefers-color-scheme: dark) {
            html, body { background: #1F1A17; color: #F7F4EF; }
            .card { background: #2a221c; }
            .logo { color: #E9E2D3; }
            .subtitle { color: #C8B8A4; }
            .step-list, .email-pill { background: #1F1A17; color: #E9E2D3; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">hovera</div>

        <div class="card">
            <div class="icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#065f46" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 12.5l5 5L20 6.5"/>
                </svg>
            </div>

            <h1>{{ __('public/signup.thanks_heading') }}</h1>
            <div class="subtitle">
                {!! __('public/signup.thanks_subtitle', ['tenant' => '<strong>'.e($tenant->name).'</strong>']) !!}
            </div>

            <ol class="step-list">
                <li>{{ __('public/signup.thanks_step_1') }}</li>
                <li>{{ __('public/signup.thanks_step_2') }}</li>
                <li>{{ __('public/signup.thanks_step_3') }}</li>
                <li>{{ __('public/signup.thanks_step_4') }}</li>
            </ol>

            <div class="subtitle">
                <strong>{{ __('public/signup.thanks_no_email') }}</strong><br>
                <span style="font-size: .88rem;">{{ __('public/signup.thanks_no_email_help') }}</span>
            </div>
        </div>

        <div class="footer-links">
            <a href="{{ url('/demo') }}">{{ __('public/signup.footer.demo') }}</a>
            ·
            <a href="mailto:office@hovera.app">office@hovera.app</a>
        </div>
    </div>

    <x-pwa-register />
</body>
</html>
