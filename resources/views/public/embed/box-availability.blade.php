@php /** @var \App\Models\Central\Tenant $tenant */ @endphp
<!DOCTYPE html>
<html lang="{{ $tenant->locale ?? 'pl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tenant->name }} — wolne boksy</title>
    <style>
        :root { --primary: {{ $primary_color }}; }
        html, body { margin: 0; padding: 0; font-family: -apple-system, "Segoe UI", system-ui, sans-serif; }
        .wrap {
            display: flex; align-items: center; gap: 1rem;
            padding: 1.5rem; background: #fff;
            border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,.06);
        }
        .pill {
            display: grid; place-items: center;
            min-width: 84px; min-height: 84px;
            border-radius: 999px;
            font-weight: 800; font-size: 2rem;
            color: #fff;
        }
        .pill.free { background: var(--primary); }
        .pill.full { background: #6b7280; }
        .body strong { display: block; font-size: 1.1rem; color: #111827; margin-bottom: .25rem; }
        .body span { display: block; color: #6b7280; font-size: .9rem; }
        .cta {
            display: inline-block; margin-top: .5rem;
            padding: .5rem 1rem; background: var(--primary); color: #fff;
            text-decoration: none; border-radius: 8px; font-weight: 600; font-size: .9rem;
        }
        @media (prefers-color-scheme: dark) {
            .wrap { background: #1e293b; }
            .body strong { color: #f3f4f6; }
            .body span { color: #94a3b8; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        @if ($box_availability && $box_availability['free'] > 0)
            <div class="pill free">{{ $box_availability['free'] }}</div>
            <div class="body">
                <strong>Mamy {{ $box_availability['free'] }} {{ $box_availability['free'] === 1 ? 'wolny box' : ($box_availability['free'] < 5 ? 'wolne boksy' : 'wolnych boksów') }}</strong>
                <span>{{ $tenant->name }} · czeka na Ciebie</span>
                <a class="cta" href="{{ url('/' . config('hovera.public_site.prefix', 's') . '/' . $tenant->slug) }}" target="_blank" rel="noopener">Zobacz szczegóły →</a>
            </div>
        @elseif ($box_availability)
            <div class="pill full">0</div>
            <div class="body">
                <strong>Wszystkie boksy zajęte</strong>
                <span>{{ $tenant->name }} · zostaw kontakt — wpiszemy na listę</span>
                <a class="cta" href="{{ url('/' . config('hovera.public_site.prefix', 's') . '/' . $tenant->slug) }}" target="_blank" rel="noopener">Skontaktuj się →</a>
            </div>
        @else
            <div class="body">
                <strong>{{ $tenant->name }}</strong>
                <a class="cta" href="{{ url('/' . config('hovera.public_site.prefix', 's') . '/' . $tenant->slug) }}" target="_blank" rel="noopener">Otwórz stronę stajni →</a>
            </div>
        @endif
    </div>
</body>
</html>
