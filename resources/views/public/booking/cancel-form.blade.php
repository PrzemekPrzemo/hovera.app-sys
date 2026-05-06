<!DOCTYPE html>
<html lang="{{ $tenant->locale ?? 'pl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Odwołaj rezerwację — {{ $tenant->name }}</title>
    <meta name="robots" content="noindex">
    <style>
        :root { --primary: {{ $primary_color }}; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: #fafafa; color: #1f2937; }
        body { display: grid; place-items: center; padding: 1.5rem; }
        .card { background: #fff; border-radius: 16px; padding: 2rem; max-width: 460px; width: 100%; box-shadow: 0 8px 30px rgba(0,0,0,.08); }
        h1 { margin: 0 0 .5rem; font-size: 1.4rem; }
        dl { display: grid; grid-template-columns: max-content 1fr; gap: .35rem 1rem; margin: 1rem 0; padding: 1rem; background: #f3f4f6; border-radius: 8px; font-size: .9rem; }
        dt { color: #6b7280; }
        dd { margin: 0; font-weight: 500; }
        .restore-info { padding: .75rem 1rem; border-radius: 8px; font-size: .9rem; margin: 1rem 0; }
        .restore-yes { background: #d1fae5; color: #065f46; }
        .restore-no { background: #fef3c7; color: #92400e; }
        button { width: 100%; padding: .8rem; background: #dc2626; color: #fff; border: 0; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; }
        button:hover { background: #b91c1c; }
        .secondary { display: block; text-align: center; margin-top: .75rem; padding: .5rem; color: #6b7280; text-decoration: none; font-size: .9rem; }
        @media (prefers-color-scheme: dark) {
            body { background: #0f172a; color: #e5e7eb; }
            .card { background: #1e293b; }
            dl { background: #0f172a; }
            dt { color: #94a3b8; }
            .secondary { color: #94a3b8; }
            .restore-yes { background: #064e3b; color: #a7f3d0; }
            .restore-no { background: #78350f; color: #fde68a; }
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Odwołaj rezerwację</h1>
        <p>Czy na pewno chcesz odwołać tę rezerwację w stajni <strong>{{ $tenant->name }}</strong>?</p>

        <dl>
            <dt>Termin</dt><dd>{{ $entry->starts_at->translatedFormat('l, d MMMM yyyy · H:i') }}</dd>
            <dt>Instruktor</dt><dd>{{ $entry->instructor?->name ?? '—' }}</dd>
            <dt>Status</dt><dd>{{ $entry->status->label() }}</dd>
        </dl>

        @if ($would_restore_pass)
            <div class="restore-info restore-yes">
                ✓ Odwołanie jest w terminie polityki — karnet zostanie zwrócony.
            </div>
        @else
            <div class="restore-info restore-no">
                ⚠ Odwołanie jest po terminie polityki — karnet (jeśli używany) NIE zostanie zwrócony.
            </div>
        @endif

        <form method="post" action="{{ url()->current() }}{{ request()->getQueryString() ? '?'.request()->getQueryString() : '' }}">
            @csrf
            <button type="submit">Tak, odwołaj rezerwację</button>
        </form>

        <a class="secondary" href="{{ url('/s/' . $tenant->slug) }}">← Wróć bez odwołania</a>
    </div>
</body>
</html>
