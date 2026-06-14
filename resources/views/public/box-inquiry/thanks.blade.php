<!DOCTYPE html>
<html lang="{{ $tenant->locale ?? 'pl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('public/box_inquiry.thanks.title') }} — {{ $tenant->name }}</title>
    <meta name="robots" content="noindex">
    <style>
        :root { --primary: {{ $primary_color }}; }
        html, body { margin: 0; height: 100%; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: #fafafa; color: #1f2937; display: flex; align-items: center; justify-content: center; }
        .card { background: #fff; border-radius: 16px; padding: 2rem; max-width: 480px; margin: 1rem; box-shadow: 0 4px 20px rgba(0,0,0,.06); text-align: center; }
        .ok { font-size: 3rem; margin-bottom: 1rem; }
        h1 { font-size: 1.4rem; margin: 0 0 .5rem; }
        p { color: #6b7280; }
        a { display: inline-block; margin-top: 1rem; background: var(--primary); color: #fff; padding: .65rem 1.25rem; text-decoration: none; border-radius: 8px; font-weight: 600; }
        @media (prefers-color-scheme: dark) {
            body { background: #0f172a; color: #e5e7eb; }
            .card { background: #1e293b; }
            p { color: #94a3b8; }
        }
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
