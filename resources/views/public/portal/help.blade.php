<!DOCTYPE html>
<html lang="{{ $tenant->locale ?? 'pl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('portal/help.title') }} — {{ $tenant->name }}</title>
    <meta name="robots" content="noindex">
    <style>
        :root { --primary: {{ $primary_color }}; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: #fafafa; color: #1f2937; }
        body { padding: 1rem; }
        .container { max-width: 760px; margin: 0 auto; }
        .back { display: inline-block; margin-bottom: .8rem; color: #6b7280; text-decoration: none; font-size: .9rem; }
        .card { background: #fff; border-radius: 14px; padding: 1.75rem 2rem; box-shadow: 0 4px 18px rgba(0,0,0,.05); }
        .help h1 { color: var(--primary); margin: 0 0 1rem; font-size: 1.6rem; }
        .help h2 { color: #111827; margin: 1.5rem 0 .5rem; font-size: 1.2rem; }
        .help h3 { margin: 1rem 0 .35rem; font-size: 1rem; }
        .help p { line-height: 1.6; margin: .5rem 0; color: #374151; }
        .help ul, .help ol { line-height: 1.6; margin: .5rem 0; padding-left: 1.5rem; color: #374151; }
        .help li { margin: .25rem 0; }
        .help table { width: 100%; border-collapse: collapse; margin: 1rem 0; font-size: .95rem; }
        .help th { background: #f9fafb; padding: .55rem .75rem; text-align: left; font-weight: 600; }
        .help td { padding: .55rem .75rem; border-top: 1px solid #f3f4f6; }
        .help code { background: #f3f4f6; padding: .15rem .4rem; border-radius: 4px; font-family: ui-monospace, monospace; font-size: .9em; }
        .help pre { background: #1f2937; color: #f3f4f6; padding: 1rem; border-radius: 8px; overflow-x: auto; }
        .help blockquote { border-left: 4px solid var(--primary); background: color-mix(in srgb, var(--primary) 8%, white); padding: .65rem 1rem; margin: 1rem 0; border-radius: 0 6px 6px 0; }
        .help strong { color: #111827; }
        .help a { color: var(--primary); text-decoration: underline; }
        @media (prefers-color-scheme: dark) {
            body { background: #0f172a; color: #e5e7eb; }
            .card { background: #1e293b; }
            .help h2, .help strong { color: #f3f4f6; }
            .help p, .help ul, .help ol { color: #cbd5e1; }
            .help th { background: #0f172a; }
            .help td { border-color: #334155; }
            .help code { background: #0f172a; color: #f3f4f6; }
            .back { color: #94a3b8; }
        }
    </style>
</head>
<body>
    <div class="container">
        <a class="back" href="{{ route('client_portal.dashboard', ['slug' => $tenant->slug]) }}">{{ __('portal/help.back') }}</a>

        <div class="card help">
            {!! $help_html !!}
        </div>
    </div>
</body>
</html>
