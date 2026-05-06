<!DOCTYPE html>
<html lang="{{ $tenant->locale ?? 'pl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Zarezerwuj lekcję — {{ $tenant->name }}</title>
    <meta name="robots" content="noindex">

    <style>
        :root { --primary: {{ $primary_color }}; }
        * { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: #fafafa; color: #1f2937; }
        body { display: flex; flex-direction: column; min-height: 100vh; }
        header.hero { background: var(--primary); color: #fff; padding: 1.5rem; text-align: center; }
        header.hero a { color: #fff; text-decoration: none; opacity: .85; }
        header.hero h1 { margin: .25rem 0 0; font-size: 1.4rem; }
        main { flex: 1; max-width: 720px; width: 100%; margin: 1.5rem auto; padding: 0 1rem; }
        h2 { font-size: 1.1rem; margin: .5rem 0 1rem; }
        .grid { display: grid; gap: 1rem; grid-template-columns: 1fr; }
        @media (min-width: 600px) { .grid { grid-template-columns: 1fr 1fr; } }
        .card { background: #fff; border-radius: 12px; padding: 1.25rem; box-shadow: 0 4px 20px rgba(0,0,0,.06); display: flex; align-items: center; gap: 1rem; text-decoration: none; color: inherit; transition: transform .1s; }
        .card:hover { transform: translateY(-2px); }
        .card .avatar { width: 48px; height: 48px; border-radius: 50%; background: #e5e7eb; display: grid; place-items: center; font-weight: 700; color: #6b7280; font-size: 1.1rem; flex-shrink: 0; }
        .card .name { font-weight: 600; }
        .empty { text-align: center; color: #6b7280; padding: 2rem; }
        footer.site-footer { text-align: center; color: #9ca3af; font-size: .75rem; padding: 1rem; }
        @media (prefers-color-scheme: dark) {
            body { background: #0f172a; color: #e5e7eb; }
            .card { background: #1e293b; }
            .card .avatar { background: #334155; color: #cbd5e1; }
        }
    </style>
</head>
<body>
    <header class="hero">
        <a href="{{ url('/s/' . $tenant->slug) }}">← {{ $tenant->name }}</a>
        <h1>Wybierz instruktora</h1>
    </header>

    <main>
        @if ($instructors->isEmpty())
            <div class="empty">Aktualnie brak dostępnych instruktorów. Skontaktuj się ze stajnią telefonicznie.</div>
        @else
            <div class="grid">
                @foreach ($instructors as $instructor)
                    <a class="card" href="{{ url('/s/' . $tenant->slug . '/book/' . $instructor->id) }}">
                        <div class="avatar" @if ($instructor->color) style="background: {{ $instructor->color }}; color: #fff;" @endif>
                            {{ mb_substr($instructor->name, 0, 1) }}
                        </div>
                        <div>
                            <div class="name">{{ $instructor->name }}</div>
                            @if ($instructor->hourly_rate_cents)
                                <div style="font-size: .85rem; color: #6b7280;">
                                    {{ number_format($instructor->hourly_rate_cents / 100, 2, ',', ' ') }} zł / h
                                </div>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </main>

    <footer class="site-footer">powered by <a href="https://hovera.app">Hovera</a></footer>
</body>
</html>
