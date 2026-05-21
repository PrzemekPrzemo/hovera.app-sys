<!DOCTYPE html>
<html lang="{{ $tenant->locale ?? 'pl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Faktura niedostępna — {{ $tenant->name }}</title>
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
    <x-google-analytics />
</head>
<body>
    <div class="card">
        <div class="icon">⚠️</div>
        <h1>Faktura niedostępna</h1>
        <p>
            @switch($reason)
                @case('expired')
                    Link do tej faktury wygasł. Skontaktuj się ze stajnią po nowy.
                    @break
                @case('not_found')
                    Faktura nie istnieje lub została usunięta.
                    @break
                @case('payment_error')
                    Nie udało się zainicjować płatności online.
                    @if (! empty($message ?? null))<br><small>{{ $message }}</small>@endif
                    @break
                @default
                    Faktura jest aktualnie niedostępna.
            @endswitch
        </p>
        <a class="btn" href="{{ url('/s/' . $tenant->slug) }}">Strona stajni</a>
    </div>
</body>
</html>
