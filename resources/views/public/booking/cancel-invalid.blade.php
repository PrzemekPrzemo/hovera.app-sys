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
        body { display: grid; place-items: center; padding: 1.5rem; text-align: center; }
        .card { background: #fff; border-radius: 16px; padding: 2.5rem 2rem; max-width: 460px; box-shadow: 0 8px 30px rgba(0,0,0,.08); }
        .icon { width: 64px; height: 64px; border-radius: 50%; background: #fef3c7; display: grid; place-items: center; margin: 0 auto 1rem; font-size: 2rem; }
        h1 { margin: 0 0 .5rem; font-size: 1.4rem; }
        p { color: #4b5563; line-height: 1.5; }
        a.btn { display: inline-block; margin-top: 1rem; padding: .65rem 1.5rem; background: var(--primary); color: #fff; text-decoration: none; border-radius: 8px; font-weight: 600; }
        @media (prefers-color-scheme: dark) {
            body { background: #0f172a; color: #e5e7eb; }
            .card { background: #1e293b; }
            p { color: #cbd5e1; }
            .icon { background: #78350f; }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">⚠</div>
        @switch($reason)
            @case('expired')
                <h1>Link wygasł</h1>
                <p>Link do odwołania jest już nieaktywny — termin lekcji minął lub link wygasł.</p>
                <p>Skontaktuj się ze stajnią telefonicznie.</p>
                @break

            @case('not_found')
                <h1>Rezerwacja nie istnieje</h1>
                <p>Nie znaleziono tej rezerwacji. Mogła zostać już usunięta.</p>
                @break

            @case('already_cancelled')
                <h1>Rezerwacja już odwołana</h1>
                <p>Ta rezerwacja została już odwołana. Nie ma potrzeby ponownego klikania.</p>
                @break

            @case('already_completed')
                <h1>Lekcja się odbyła</h1>
                <p>Tej rezerwacji nie da się już odwołać — została zaznaczona jako odbyta.</p>
                @break

            @default
                <h1>Link nieaktywny</h1>
                <p>Skontaktuj się ze stajnią po pomoc.</p>
        @endswitch

        <a class="btn" href="{{ url('/s/' . $tenant->slug) }}">Wróć do stajni</a>
    </div>
</body>
</html>
