<!DOCTYPE html>
<html lang="{{ $tenant->locale ?? 'pl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('portal/login.invalid.title', ['tenant' => $tenant->name]) }}</title>
    <meta name="robots" content="noindex">
    <style>
        :root { --primary: {{ $primary_color }}; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: #fafafa; color: #3a2f25; }
        body { display: grid; place-items: center; padding: 1.5rem; }
        .card { background: #fff; border-radius: 16px; padding: 2rem; max-width: 460px; width: 100%; box-shadow: 0 8px 30px rgba(0,0,0,.08); text-align: center; }
        .icon { font-size: 3rem; margin-bottom: .5rem; }
        h1 { margin: 0 0 .5rem; font-size: 1.4rem; }
        p { color: #4b5563; line-height: 1.5; }
        a.btn { display: inline-block; margin-top: 1rem; padding: .65rem 1.2rem; background: var(--primary); color: #fff; border-radius: 8px; text-decoration: none; font-weight: 600; }
        @media (prefers-color-scheme: dark) {
            html:not(.is-demo) body { background: #2a2017; color: #f7f4ef; }
            html:not(.is-demo) .card { background: #3a2f25; }
            html:not(.is-demo) p { color: #e9e2d3; }
        }
    </style>
    <x-google-analytics />
</head>
<body>
    <x-demo-light-mode />
    <x-demo-banner />
    <div class="card">
        <div class="icon">⚠️</div>
        <h1>{{ __('portal/login.invalid.heading') }}</h1>
        <p>{{ __('portal/login.invalid.body') }}</p>
        <a class="btn" href="{{ route('client_portal.login.show', ['slug' => $tenant->slug]) }}">{{ __('portal/login.invalid.request_new') }}</a>
    </div>
</body>
</html>
