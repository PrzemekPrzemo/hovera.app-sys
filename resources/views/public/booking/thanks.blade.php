<!DOCTYPE html>
<html lang="{{ $tenant->locale ?? 'pl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Zgłoszenie wysłane — {{ $tenant->name }}</title>
    <meta name="robots" content="noindex">
    <style>
        :root { --primary: {{ $primary_color }}; }
        * { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: #fafafa; color: #1f2937; }
        body { display: grid; place-items: center; min-height: 100vh; padding: 1.5rem; }
        .card { background: #fff; border-radius: 16px; padding: 2.5rem 2rem; max-width: 460px; text-align: center; box-shadow: 0 8px 30px rgba(0,0,0,.1); }
        .check { width: 64px; height: 64px; border-radius: 50%; background: var(--primary); display: grid; place-items: center; margin: 0 auto 1rem; color: #fff; font-size: 2rem; }
        h1 { margin: 0 0 .5rem; font-size: 1.4rem; }
        p { color: #4b5563; line-height: 1.5; }
        .when { display: inline-block; margin: 1rem 0; padding: .5rem 1rem; background: #f3f4f6; border-radius: 8px; font-weight: 600; }
        a.btn { display: inline-block; margin-top: 1.5rem; padding: .65rem 1.5rem; background: var(--primary); color: #fff; text-decoration: none; border-radius: 8px; font-weight: 600; }
        @media (prefers-color-scheme: dark) {
            body { background: #0f172a; color: #e5e7eb; }
            .card { background: #1e293b; }
            p { color: #cbd5e1; }
            .when { background: #0f172a; }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="check">✓</div>
        <h1>Zgłoszenie wysłane!</h1>
        <p>Dziękujemy. Rezerwacja w <strong>{{ $tenant->name }}</strong> czeka na potwierdzenie przez stajnię.</p>
        <div class="when">{{ $starts_at->translatedFormat('l, d MMMM yyyy · H:i') }}</div>
        <p style="font-size: .85rem;">Skontaktujemy się z Tobą mailem kiedy stajnia potwierdzi termin i przydzieli konia.</p>
        <a class="btn" href="{{ url('/s/' . $tenant->slug) }}">Wróć do strony stajni</a>
    </div>
</body>
</html>
