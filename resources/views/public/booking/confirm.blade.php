<!DOCTYPE html>
<html lang="{{ $tenant->locale ?? 'pl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Potwierdź rezerwację — {{ $tenant->name }}</title>
    <meta name="robots" content="noindex">
    <style>
        :root { --primary: {{ $primary_color }}; }
        * { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: #fafafa; color: #1f2937; }
        body { display: flex; flex-direction: column; min-height: 100vh; }
        header.hero { background: var(--primary); color: #fff; padding: 1.5rem; text-align: center; }
        header.hero a { color: #fff; text-decoration: none; opacity: .85; }
        header.hero h1 { margin: .25rem 0; font-size: 1.3rem; }
        header.hero .sub { font-size: 1rem; }
        main { flex: 1; max-width: 480px; width: 100%; margin: 1.5rem auto; padding: 0 1rem; }
        .summary { background: #fff; border-radius: 12px; padding: 1.25rem; box-shadow: 0 4px 20px rgba(0,0,0,.06); margin-bottom: 1rem; }
        .summary dl { margin: 0; display: grid; grid-template-columns: max-content 1fr; gap: .25rem 1rem; font-size: .9rem; }
        .summary dt { color: #6b7280; }
        form { background: #fff; border-radius: 12px; padding: 1.25rem; box-shadow: 0 4px 20px rgba(0,0,0,.06); }
        label { display: block; font-size: .85rem; color: #374151; margin: .75rem 0 .25rem; }
        input, textarea { width: 100%; padding: .65rem .8rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem; font-family: inherit; }
        input:focus, textarea:focus { outline: 2px solid var(--primary); outline-offset: 1px; border-color: transparent; }
        button { margin-top: 1rem; width: 100%; padding: .8rem; background: var(--primary); color: #fff; border: 0; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; }
        .error { color: #dc2626; font-size: .85rem; margin-top: .25rem; }
        footer.site-footer { text-align: center; color: #9ca3af; font-size: .75rem; padding: 1rem; }
        @media (prefers-color-scheme: dark) {
            body { background: #0f172a; color: #e5e7eb; }
            .summary, form { background: #1e293b; }
            input, textarea { background: #0f172a; color: #e5e7eb; border-color: #475569; }
            .summary dt { color: #94a3b8; }
        }
    </style>
    <x-google-analytics />
</head>
<body>
    <header class="hero">
        <a href="{{ url('/s/' . $tenant->slug . '/book/' . $instructor->id . '?date=' . $starts_at->toDateString()) }}">← Zmień termin</a>
        <h1>{{ $tenant->name }}</h1>
        <div class="sub">{{ $starts_at->translatedFormat('l, d MMMM yyyy') }} · {{ $starts_at->format('H:i') }}</div>
    </header>

    <main>
        <div class="summary">
            <dl>
                <dt>Instruktor</dt><dd>{{ $instructor->name }}</dd>
                <dt>Termin</dt><dd>{{ $starts_at->format('d.m.Y H:i') }}</dd>
                <dt>Czas</dt><dd>{{ $config['lesson_duration_minutes'] }} min</dd>
            </dl>
        </div>

        <form method="post" action="{{ url('/s/' . $tenant->slug . '/book/' . $instructor->id) }}">
            @csrf
            <input type="hidden" name="starts_at" value="{{ $starts_at->toDateTimeString() }}">

            <label for="name">Imię i nazwisko *</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus>
            @error('name')<div class="error">{{ $message }}</div>@enderror

            <label for="email">Email *</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required>
            @error('email')<div class="error">{{ $message }}</div>@enderror

            <label for="phone">Telefon</label>
            <input id="phone" name="phone" type="tel" value="{{ old('phone') }}">

            <label for="notes">Uwagi (opcjonalne)</label>
            <textarea id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>

            @if ($errors->has('starts_at'))
                <div class="error">{{ $errors->first('starts_at') }}</div>
            @endif
            @if ($errors->has('public_booking'))
                <div class="error">{{ $errors->first('public_booking') }}</div>
            @endif

            <button type="submit">Zarezerwuj</button>
        </form>

        <p style="font-size: .8rem; color: #6b7280; margin-top: 1rem; text-align: center;">
            Stajnia potwierdzi rezerwację mailem. Konia przydzielimy w stajni.
        </p>
    </main>

    <footer class="site-footer">powered by <a href="https://hovera.app">Hovera</a></footer>
</body>
</html>
