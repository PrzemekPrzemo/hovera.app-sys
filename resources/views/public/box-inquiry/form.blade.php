<!DOCTYPE html>
<html lang="{{ $tenant->locale ?? 'pl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('public/box_inquiry.title', ['tenant' => $tenant->name]) }}</title>
    <meta name="robots" content="noindex">
    <style>
        :root {
            --ochre: #A8956B;
            --ochre-dark: #8a7a55;
            --brown: #3D2E22;
            --brown-soft: #6b5b4a;
            --bg: #F7F4EF;
            --line: #E9E2D3;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: var(--bg); color: var(--brown); }
        body { display: flex; flex-direction: column; min-height: 100vh; }
        header.hero {
            background: #fff;
            border-bottom: 3px solid var(--ochre);
            color: var(--brown);
            padding: 1.5rem; text-align: center;
        }
        header.hero a {
            color: var(--brown-soft); text-decoration: none;
            font-size: .85rem;
            display: inline-block; margin-bottom: .5rem;
        }
        header.hero a:hover { color: var(--ochre); }
        header.hero h1 { margin: .25rem 0; font-size: 1.4rem; color: var(--brown); font-weight: 700; }
        header.hero .sub { font-size: .95rem; color: var(--brown-soft); }
        main { flex: 1; max-width: 520px; width: 100%; margin: 1.5rem auto; padding: 0 1rem; }
        form {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 12px; padding: 1.5rem;
            box-shadow: 0 4px 18px rgba(168, 149, 107, 0.08);
        }
        label { display: block; font-size: .85rem; color: var(--brown); margin: .85rem 0 .3rem; font-weight: 600; }
        input, textarea, select {
            width: 100%; padding: .7rem .85rem;
            background: #fff; color: var(--brown);
            border: 1px solid var(--line);
            border-radius: 8px; font-size: 1rem; font-family: inherit;
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: var(--ochre);
            box-shadow: 0 0 0 3px rgba(168, 149, 107, 0.18);
        }
        button {
            margin-top: 1.5rem; width: 100%; padding: .9rem;
            background: var(--ochre); color: #fff;
            border: 0; border-radius: 8px;
            font-size: 1rem; font-weight: 600; cursor: pointer;
            transition: background .15s ease;
        }
        button:hover { background: var(--ochre-dark); }
        .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: .85rem; }
        .hp { position: absolute; left: -10000px; top: auto; width: 1px; height: 1px; overflow: hidden; }
        .alert {
            background: #fef3f2; border: 1px solid #fda4af; color: #991b1b;
            padding: .8rem 1rem; border-radius: 8px;
            margin-bottom: 1rem; font-size: .9rem;
        }
        footer.site-footer { text-align: center; color: var(--brown-soft); font-size: .75rem; padding: 1rem; opacity: .8; }
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
