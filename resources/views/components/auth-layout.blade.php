<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Hovera' }}</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; }
        body { display: grid; place-items: center; background: #0f172a; color: #e2e8f0; padding: 1.5rem; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 2rem; max-width: 28rem; width: 100%; box-shadow: 0 10px 40px rgba(0,0,0,.3); }
        h1 { margin: 0 0 .5rem; font-size: 1.25rem; }
        p.muted { margin: 0 0 1.25rem; color: #94a3b8; font-size: .9rem; line-height: 1.5; }
        label { display: block; margin: 0 0 .35rem; font-size: .85rem; color: #cbd5e1; }
        input[type=text], input[type=password] {
            width: 100%; padding: .65rem .8rem; border-radius: 8px;
            border: 1px solid #475569; background: #0f172a; color: #e2e8f0;
            font-size: 1rem; font-family: ui-monospace, SFMono-Regular, monospace;
        }
        input:focus { outline: 2px solid #6366f1; outline-offset: 1px; }
        button {
            margin-top: 1rem; width: 100%; padding: .7rem 1rem;
            background: #6366f1; color: #fff; border: 0; border-radius: 8px;
            font-size: 1rem; cursor: pointer;
        }
        button:hover { background: #4f46e5; }
        .error { color: #fca5a5; font-size: .85rem; margin-top: .25rem; }
        .qr { background: #fff; border-radius: 8px; padding: 1rem; display: grid; place-items: center; margin: 1rem 0; }
        .qr svg { width: 220px; height: 220px; }
        .secret { font-family: ui-monospace, SFMono-Regular, monospace; word-break: break-all; background: #0f172a; padding: .75rem; border-radius: 6px; font-size: .8rem; }
        .codes { font-family: ui-monospace, SFMono-Regular, monospace; background: #0f172a; padding: 1rem; border-radius: 8px; line-height: 2; }
        .codes span { display: inline-block; margin-right: 1rem; }
    </style>
</head>
<body>
    <div class="card">
        {{ $slot }}
    </div>
</body>
</html>
