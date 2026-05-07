<!DOCTYPE html>
<html lang="{{ $tenant->locale ?? 'pl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Przesuń rezerwację — {{ $tenant->name }}</title>
    <meta name="robots" content="noindex">
    <style>
        :root { --primary: {{ $primary_color }}; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: #fafafa; color: #1f2937; }
        body { padding: 1rem; }
        .container { max-width: 720px; margin: 0 auto; }
        .card { background: #fff; border-radius: 14px; padding: 1.25rem; box-shadow: 0 4px 18px rgba(0,0,0,.05); margin-bottom: 1rem; }
        .card h1 { margin: 0 0 .5rem; font-size: 1.2rem; color: var(--primary); }
        .meta { color: #6b7280; font-size: .9rem; margin-bottom: 1rem; }
        .errors { background: #fee2e2; color: #991b1b; padding: .65rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: .9rem; }
        .step-label { font-weight: 600; margin: .8rem 0 .4rem; font-size: .9rem; color: #374151; }
        .dates { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: .5rem; }
        .dates a { display: block; text-align: center; padding: .55rem; border: 1px solid #e5e7eb; border-radius: 8px; text-decoration: none; color: #374151; font-size: .85rem; }
        .dates a.active { border-color: var(--primary); color: var(--primary); font-weight: 600; }
        .dates a:hover { background: #f9fafb; }
        .empty { color: #9ca3af; font-style: italic; padding: .6rem 0; }
        .slots { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: .5rem; margin-top: .5rem; }
        .slot { display: contents; }
        .slot button { width: 100%; padding: .55rem; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; cursor: pointer; font-size: .9rem; }
        .slot button:hover { border-color: var(--primary); color: var(--primary); }
        .back { display: inline-block; margin-top: 1rem; color: #6b7280; text-decoration: none; font-size: .9rem; }
        @media (prefers-color-scheme: dark) {
            body { background: #0f172a; color: #e5e7eb; }
            .card { background: #1e293b; }
            .meta, .step-label { color: #cbd5e1; }
            .dates a, .slot button { background: #0f172a; border-color: #334155; color: #e2e8f0; }
            .dates a:hover, .slot button:hover { background: #1f2937; }
            .back { color: #94a3b8; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>Przesuń rezerwację</h1>
            <div class="meta">
                Obecny termin: <strong>{{ $entry->starts_at->translatedFormat('l, d MMMM yyyy · H:i') }}</strong><br>
                Instruktor: {{ $entry->instructor?->name ?? '—' }}
            </div>

            @if ($errors->any())
                <div class="errors">
                    @foreach ($errors->all() as $error)
                        {{ $error }}<br>
                    @endforeach
                </div>
            @endif

            <div class="step-label">1. Wybierz dzień</div>
            @if ($dates_with_slots->isEmpty())
                <div class="empty">Brak wolnych terminów dla tego instruktora.</div>
            @else
                <div class="dates">
                    @foreach ($dates_with_slots as $date)
                        @php $d = \Illuminate\Support\Carbon::parse($date); @endphp
                        <a href="{{ route('client_portal.reschedule.show', ['slug' => $tenant->slug, 'entry' => $entry->id]) }}?date={{ $date }}"
                           class="{{ $selected_date === $date ? 'active' : '' }}">
                            {{ $d->translatedFormat('d MMM') }}
                            <small style="display:block; opacity:.6">{{ $d->translatedFormat('EEE') }}</small>
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($selected_date)
                <div class="step-label">2. Wybierz godzinę — {{ \Illuminate\Support\Carbon::parse($selected_date)->translatedFormat('d MMMM yyyy') }}</div>
                @if ($slots->isEmpty())
                    <div class="empty">Brak wolnych godzin tego dnia.</div>
                @else
                    <div class="slots">
                        @foreach ($slots as $slot)
                            <form method="post" class="slot"
                                  action="{{ route('client_portal.reschedule.submit', ['slug' => $tenant->slug, 'entry' => $entry->id]) }}">
                                @csrf
                                <input type="hidden" name="starts_at" value="{{ $slot->toDateTimeString() }}">
                                <button type="submit">{{ $slot->format('H:i') }}</button>
                            </form>
                        @endforeach
                    </div>
                @endif
            @endif

            <a class="back" href="{{ route('client_portal.dashboard', ['slug' => $tenant->slug]) }}">← Wróć do panelu</a>
        </div>
    </div>
</body>
</html>
