<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('public/signup.choose.title') }} — hovera</title>
    <meta name="robots" content="noindex">
    <link rel="icon" href="{{ asset('favicon.svg') }}">
    <x-pwa-head />
    <style>
        :root { --primary: #A8956B; --primary-dark: #8F8576; --bg: #F7F4EF; --text: #1F1A17; --card: #fff; --muted: #6b7280; --border: #d4cdb8; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: var(--bg); color: var(--text); }
        body { padding: 2rem 1rem; }
        .container { max-width: 920px; margin: 0 auto; }
        .logo { text-align: center; margin-bottom: 1.5rem; font-size: 1.4rem; font-weight: 700; letter-spacing: -.02em; color: #3D2E22; }
        .intro { text-align: center; margin-bottom: 2rem; }
        h1 { margin: 0 0 .5rem; font-size: 1.7rem; }
        .subtitle { color: var(--muted); font-size: .95rem; line-height: 1.5; max-width: 540px; margin: 0 auto; }
        .grid { display: grid; gap: 1.25rem; grid-template-columns: 1fr; }
        @media (min-width: 720px) { .grid { grid-template-columns: 1fr 1fr; } }
        .card { background: var(--card); border-radius: 16px; padding: 2rem; box-shadow: 0 6px 24px rgba(0,0,0,.06); display: flex; flex-direction: column; gap: 1rem; transition: transform .15s ease, box-shadow .15s ease; }
        .card:hover { transform: translateY(-2px); box-shadow: 0 12px 32px rgba(0,0,0,.08); }
        .card h2 { margin: 0; font-size: 1.25rem; color: #3D2E22; }
        .card .icon { font-size: 2.2rem; line-height: 1; }
        .card ul { margin: 0; padding-left: 1.1rem; color: var(--text); font-size: .9rem; line-height: 1.6; }
        .card li { margin-bottom: .15rem; }
        .card .cta { display: inline-block; background: var(--primary); color: #fff; padding: .75rem 1.25rem; border-radius: 8px; text-decoration: none; font-weight: 700; text-align: center; margin-top: auto; }
        .card .cta:hover { filter: brightness(.95); }
        .card .price { color: var(--muted); font-size: .85rem; }
        .footer-links { text-align: center; margin-top: 1.5rem; font-size: .85rem; color: var(--muted); }
        .footer-links a { color: var(--primary); text-decoration: none; margin: 0 .35rem; }
        .footer-links a:hover { text-decoration: underline; }
        @media (prefers-color-scheme: dark) {
            html, body { background: #1F1A17; color: #F7F4EF; }
            .card { background: #2a221c; }
            .logo, .card h2 { color: #E9E2D3; }
            .subtitle, .footer-links, .card .price { color: #C8B8A4; }
            .card ul { color: #F7F4EF; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">hovera</div>

        <div class="intro">
            <h1>{{ __('public/signup.choose.heading') }}</h1>
            <p class="subtitle">{{ __('public/signup.choose.subtitle') }}</p>
        </div>

        <div class="grid">
            <div class="card">
                <div class="icon">🐴</div>
                <h2>{{ __('public/signup.choose.stable.title') }}</h2>
                <p class="price">{{ __('public/signup.choose.stable.price') }}</p>
                <ul>
                    <li>{{ __('public/signup.choose.stable.bullet_1') }}</li>
                    <li>{{ __('public/signup.choose.stable.bullet_2') }}</li>
                    <li>{{ __('public/signup.choose.stable.bullet_3') }}</li>
                    <li>{{ __('public/signup.choose.stable.bullet_4') }}</li>
                </ul>
                <a class="cta" href="{{ route('signup.show', ['type' => 'stable']) }}">{{ __('public/signup.choose.stable.cta') }}</a>
            </div>

            <div class="card">
                <div class="icon">🚚</div>
                <h2>{{ __('public/signup.choose.transporter.title') }}</h2>
                <p class="price">{{ __('public/signup.choose.transporter.price') }}</p>
                <ul>
                    <li>{{ __('public/signup.choose.transporter.bullet_1') }}</li>
                    <li>{{ __('public/signup.choose.transporter.bullet_2') }}</li>
                    <li>{{ __('public/signup.choose.transporter.bullet_3') }}</li>
                    <li>{{ __('public/signup.choose.transporter.bullet_4') }}</li>
                </ul>
                <a class="cta" href="{{ route('signup.show', ['type' => 'transporter']) }}">{{ __('public/signup.choose.transporter.cta') }}</a>
            </div>
        </div>

        <div class="footer-links">
            <a href="{{ url('/demo') }}">{{ __('public/signup.footer.demo') }}</a>
            ·
            <a href="{{ route('pricing.show') }}">{{ __('public/signup.footer.pricing') }}</a>
            ·
            <a href="/app/login">{{ __('public/signup.footer.login') }}</a>
        </div>
    </div>

    <x-pwa-register />
</body>
</html>
