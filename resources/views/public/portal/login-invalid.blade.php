<!DOCTYPE html>
<html lang="{{ $tenant->locale ?? 'pl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Link nieaktywny — {{ $tenant->name }}</title>
    <meta name="robots" content="noindex">
    <style>
        :root { --primary: {{ $primary_color }}; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: #fafafa; color: #1f2937; }
        body { display: grid; place-items: center; padding: 1.5rem; }
        .card { background: #fff; border-radius: 16px; padding: 2rem; max-width: 460px; width: 100%; box-shadow: 0 8px 30px rgba(0,0,0,.08); text-align: center; }
        .icon { font-size: 3rem; margin-bottom: .5rem; }
        h1 { margin: 0 0 .5rem; font-size: 1.4rem; }
        p { color: #4b5563; line-height: 1.5; }
        a.btn { display: inline-block; margin-top: 1rem; padding: .65rem 1.2rem; background: var(--primary); color: #fff; border-radius: 8px; text-decoration: none; font-weight: 600; }
        @media (prefers-color-scheme: dark) {
            body { background: #0f172a; color: #e5e7eb; }
            .card { background: #1e293b; }
            p { color: #cbd5e1; }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">⚠️</div>
        <h1>Link nieaktywny</h1>
        <p>Ten link logowania wygasł lub został już użyty. Linki są jednorazowe i ważne 30 minut.</p>
        <a class="btn" href="{{ route('client_portal.login.show', ['slug' => $tenant->slug]) }}">Wyślij nowy link</a>
    </div>
</body>
</html>
