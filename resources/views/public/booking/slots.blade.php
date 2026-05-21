<!DOCTYPE html>
<html lang="{{ $tenant->locale ?? 'pl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wybierz termin — {{ $tenant->name }}</title>
    <meta name="robots" content="noindex">
    <style>
        :root { --primary: {{ $primary_color }}; }
        * { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: #fafafa; color: #1f2937; }
        body { display: flex; flex-direction: column; min-height: 100vh; }
        header.hero { background: var(--primary); color: #fff; padding: 1.5rem; text-align: center; }
        header.hero a { color: #fff; text-decoration: none; opacity: .85; }
        header.hero h1 { margin: .25rem 0 0; font-size: 1.3rem; }
        header.hero .sub { font-size: .85rem; opacity: .9; }
        main { flex: 1; max-width: 720px; width: 100%; margin: 1.5rem auto; padding: 0 1rem; }
        .day-strip { display: flex; gap: .5rem; overflow-x: auto; padding-bottom: .5rem; margin-bottom: 1rem; -webkit-overflow-scrolling: touch; }
        .day-strip a { flex-shrink: 0; padding: .6rem 1rem; border-radius: 10px; background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,.05); color: inherit; text-decoration: none; text-align: center; min-width: 80px; }
        .day-strip a.active { background: var(--primary); color: #fff; }
        .day-strip a.has-slots .dot { display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: var(--primary); margin-bottom: 4px; }
        .day-strip a.active .dot { background: #fff; }
        .day-strip .day-name { font-size: .75rem; }
        .day-strip .day-num { font-weight: 700; font-size: 1.05rem; }
        .slot-grid { display: grid; gap: .5rem; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); }
        .slot { padding: .8rem; border-radius: 8px; background: #fff; text-align: center; text-decoration: none; color: inherit; font-weight: 600; box-shadow: 0 2px 8px rgba(0,0,0,.05); }
        .slot:hover { background: var(--primary); color: #fff; }
        .empty { text-align: center; color: #6b7280; padding: 2rem; }
        footer.site-footer { text-align: center; color: #9ca3af; font-size: .75rem; padding: 1rem; }
        @media (prefers-color-scheme: dark) {
            body { background: #0f172a; color: #e5e7eb; }
            .day-strip a, .slot { background: #1e293b; }
        }
    </style>
    <x-google-analytics />
</head>
<body>
    <header class="hero">
        <a href="{{ url('/s/' . $tenant->slug . '/book') }}">← Zmień instruktora</a>
        <h1>{{ $instructor->name }}</h1>
        <div class="sub">Lekcje {{ $config['lesson_duration_minutes'] }} min</div>
    </header>

    <main>
        @php
            $daysSet = $dates_with_slots->flip();
            $strip = collect();
            $cursor = \Illuminate\Support\Carbon::now()->startOfDay();
            for ($i = 0; $i < $config['advance_max_days']; $i++) {
                $strip->push($cursor->copy());
                $cursor->addDay();
            }
        @endphp

        <div class="day-strip">
            @foreach ($strip as $day)
                @php $iso = $day->toDateString(); @endphp
                <a href="{{ url('/s/' . $tenant->slug . '/book/' . $instructor->id . '?date=' . $iso) }}"
                   class="{{ $iso === $date->toDateString() ? 'active' : '' }} {{ $daysSet->has($iso) ? 'has-slots' : '' }}">
                    @if ($daysSet->has($iso))<span class="dot"></span>@endif
                    <div class="day-name">{{ $day->translatedFormat('D') }}</div>
                    <div class="day-num">{{ $day->format('d.m') }}</div>
                </a>
            @endforeach
        </div>

        @if ($slots->isEmpty())
            <div class="empty">Brak wolnych slotów {{ $date->translatedFormat('d MMMM') }}. Wybierz inny dzień.</div>
        @else
            <div class="slot-grid">
                @foreach ($slots as $slot)
                    <a class="slot" href="{{ url('/s/' . $tenant->slug . '/book/' . $instructor->id . '/confirm?starts_at=' . urlencode($slot->toDateTimeString())) }}">
                        {{ $slot->format('H:i') }}
                    </a>
                @endforeach
            </div>
        @endif
    </main>

    <footer class="site-footer">powered by <a href="https://hovera.app">Hovera</a></footer>
</body>
</html>
