<!DOCTYPE html>
<html lang="{{ $tenant->locale ?? 'pl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('public/box_inquiry.thanks.title') }} — {{ $tenant->name }}</title>
    <meta name="robots" content="noindex">
    <style>
        :root {
            --ochre: #A8956B;
            --ochre-dark: #8a7a55;
            --brown: #3D2E22;
            --brown-soft: #6b5b4a;
            --bg: #F7F4EF;
            --line: #E9E2D3;
        }
        * { box-sizing: border-box; }
        html, body {
            margin: 0; height: 100%;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif;
            background: var(--bg); color: var(--brown);
            display: flex; align-items: center; justify-content: center;
        }
        .card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px; padding: 2.25rem;
            max-width: 480px; margin: 1rem;
            box-shadow: 0 4px 18px rgba(168, 149, 107, 0.08);
            text-align: center;
        }
        .ok {
            display: inline-grid; place-items: center;
            width: 64px; height: 64px;
            border-radius: 999px;
            background: var(--bg);
            border: 3px solid var(--ochre);
            color: var(--ochre);
            font-size: 2rem; font-weight: 700;
            margin-bottom: 1rem;
        }
        h1 { font-size: 1.4rem; margin: 0 0 .5rem; color: var(--brown); font-weight: 700; }
        p { color: var(--brown-soft); line-height: 1.5; margin: .5rem 0 0; }
        a {
            display: inline-block; margin-top: 1.25rem;
            background: var(--ochre); color: #fff;
            padding: .7rem 1.4rem; text-decoration: none;
            border-radius: 8px; font-weight: 600;
            transition: background .15s ease;
        }
        a:hover { background: var(--ochre-dark); }
    </style>
</head>
<body>
    <div class="card">
        <div class="ok">✓</div>
        <h1>{{ __('public/box_inquiry.thanks.title') }}</h1>
        <p>{{ __('public/box_inquiry.thanks.body', ['tenant' => $tenant->name]) }}</p>
        <a href="{{ url('/s/' . $tenant->slug) }}">{{ __('public/box_inquiry.thanks.back') }}</a>
    </div>
</body>
</html>
