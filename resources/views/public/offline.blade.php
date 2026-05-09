<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('public/offline.title') }} — hovera</title>
    <meta name="robots" content="noindex">
    <link rel="icon" href="{{ asset('favicon.svg') }}">
    <x-pwa-head />
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: #F7F4EF; color: #1F1A17; }
        body { padding: 3rem 1rem; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .container { max-width: 480px; margin: 0 auto; text-align: center; }
        .logo { font-size: 1.4rem; font-weight: 700; letter-spacing: -.02em; color: #3D2E22; margin-bottom: 2rem; }
        .card { background: #fff; border-radius: 16px; padding: 2.5rem 2rem; box-shadow: 0 6px 24px rgba(0,0,0,.06); }
        .icon { width: 64px; height: 64px; margin: 0 auto 1rem; background: #F7F4EF; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #A8956B; }
        h1 { margin: 0 0 .8rem; font-size: 1.6rem; color: #3D2E22; }
        .subtitle { color: #4b5563; margin-bottom: 1.5rem; line-height: 1.6; }
        button { padding: .85rem 1.5rem; background: #A8956B; color: #fff; border: 0; border-radius: 8px; font-weight: 700; font-size: 1rem; cursor: pointer; font-family: inherit; }
        button:hover { filter: brightness(0.95); }
        .footer-links { margin-top: 1.25rem; font-size: .85rem; color: #6b7280; }
        .footer-links a { color: #A8956B; text-decoration: none; }
        .footer-links a:hover { text-decoration: underline; }
        @media (prefers-color-scheme: dark) {
            html, body { background: #1F1A17; color: #F7F4EF; }
            .card { background: #2a221c; }
            .logo, h1 { color: #E9E2D3; }
            .subtitle, .footer-links { color: #C8B8A4; }
            .icon { background: #1F1A17; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">hovera</div>

        <div class="card">
            <div class="icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 1l22 22"/>
                    <path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55"/>
                    <path d="M5 12.55a10.94 10.94 0 0 1 5.17-2.39"/>
                    <path d="M10.71 5.05A16 16 0 0 1 22.58 9"/>
                    <path d="M1.42 9a15.91 15.91 0 0 1 4.7-2.88"/>
                    <path d="M8.53 16.11a6 6 0 0 1 6.95 0"/>
                    <line x1="12" y1="20" x2="12.01" y2="20"/>
                </svg>
            </div>

            <h1>{{ __('public/offline.heading') }}</h1>
            <div class="subtitle">{{ __('public/offline.subtitle') }}</div>

            <button type="button" onclick="window.location.reload()">{{ __('public/offline.reload') }}</button>
        </div>

        <div class="footer-links">
            <a href="/">{{ __('public/offline.home') }}</a>
        </div>
    </div>

    <x-pwa-register />
</body>
</html>
