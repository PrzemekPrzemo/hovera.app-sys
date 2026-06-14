<!DOCTYPE html>
<html lang="{{ $tenant->locale ?? 'pl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('public/box_inquiry.title', ['tenant' => $tenant->name]) }}</title>
    <meta name="robots" content="noindex">
    <style>
        :root { --primary: {{ $primary_color }}; }
        * { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: #fafafa; color: #1f2937; }
        body { display: flex; flex-direction: column; min-height: 100vh; }
        header.hero { background: var(--primary); color: #fff; padding: 1.5rem; text-align: center; }
        header.hero a { color: #fff; text-decoration: none; opacity: .85; font-size: .85rem; }
        header.hero h1 { margin: .25rem 0; font-size: 1.3rem; }
        header.hero .sub { font-size: 1rem; opacity: .9; }
        main { flex: 1; max-width: 480px; width: 100%; margin: 1.5rem auto; padding: 0 1rem; }
        form { background: #fff; border-radius: 12px; padding: 1.25rem; box-shadow: 0 4px 20px rgba(0,0,0,.06); }
        label { display: block; font-size: .85rem; color: #374151; margin: .75rem 0 .25rem; font-weight: 600; }
        input, textarea, select { width: 100%; padding: .65rem .8rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem; font-family: inherit; }
        input:focus, textarea:focus, select:focus { outline: 2px solid var(--primary); outline-offset: 1px; border-color: transparent; }
        button { margin-top: 1.25rem; width: 100%; padding: .85rem; background: var(--primary); color: #fff; border: 0; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; }
        .error { color: #dc2626; font-size: .85rem; margin-top: .25rem; }
        .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }
        .hp { position: absolute; left: -10000px; top: auto; width: 1px; height: 1px; overflow: hidden; }
        .alert { background: #fee2e2; color: #991b1b; padding: .75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: .9rem; }
        footer.site-footer { text-align: center; color: #9ca3af; font-size: .75rem; padding: 1rem; }
        @media (prefers-color-scheme: dark) {
            body { background: #0f172a; color: #e5e7eb; }
            form { background: #1e293b; }
            input, textarea, select { background: #0f172a; color: #e5e7eb; border-color: #475569; }
        }
    </style>
    <x-google-analytics />
</head>
<body>
    <header class="hero">
        <a href="{{ url('/s/' . $tenant->slug) }}">← {{ __('public/box_inquiry.back') }}</a>
        <h1>{{ $tenant->name }}</h1>
        <div class="sub">{{ __('public/box_inquiry.subtitle') }}</div>
    </header>

    <main>
        @if ($errors->any())
            <div class="alert">
                @foreach ($errors->all() as $err)
                    <div>{{ $err }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('public.box_inquiry.submit', ['slug' => $tenant->slug]) }}">
            @csrf
            <input type="hidden" name="source" value="{{ $source }}">

            <label for="name">{{ __('public/box_inquiry.field.name') }}</label>
            <input id="name" type="text" name="name" required maxlength="160" value="{{ old('name') }}">

            <div class="row-2">
                <div>
                    <label for="email">{{ __('public/box_inquiry.field.email') }}</label>
                    <input id="email" type="email" name="email" required maxlength="255" value="{{ old('email') }}">
                </div>
                <div>
                    <label for="phone">{{ __('public/box_inquiry.field.phone') }}</label>
                    <input id="phone" type="tel" name="phone" maxlength="40" value="{{ old('phone') }}">
                </div>
            </div>

            <div class="row-2">
                <div>
                    <label for="horse_count">{{ __('public/box_inquiry.field.horse_count') }}</label>
                    <input id="horse_count" type="number" name="horse_count" required min="1" max="50" value="{{ old('horse_count', 1) }}">
                </div>
                <div>
                    <label for="preferred_from">{{ __('public/box_inquiry.field.preferred_from') }}</label>
                    <input id="preferred_from" type="date" name="preferred_from" min="{{ now()->toDateString() }}" value="{{ old('preferred_from') }}">
                </div>
            </div>

            <label for="message">{{ __('public/box_inquiry.field.message') }}</label>
            <textarea id="message" name="message" rows="4" maxlength="2000" placeholder="{{ __('public/box_inquiry.field.message_placeholder') }}">{{ old('message') }}</textarea>

            {{-- Honeypot — hidden field. Boty wypełnią, human nie zobaczy. --}}
            <div class="hp" aria-hidden="true">
                <label for="company">Company (do not fill)</label>
                <input id="company" type="text" name="company" tabindex="-1" autocomplete="off">
            </div>

            <button type="submit">{{ __('public/box_inquiry.submit') }}</button>
        </form>
    </main>

    <footer class="site-footer">
        {{ __('public/box_inquiry.powered_by') }}
    </footer>
</body>
</html>
