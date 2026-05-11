<!DOCTYPE html>
<html lang="{{ $tenant->locale ?? 'pl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('portal/login.login.title', ['tenant' => $tenant->name]) }}</title>
    <meta name="robots" content="noindex">
    <style>
        :root { --primary: {{ $primary_color }}; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: #fafafa; color: #3a2f25; }
        body { display: grid; place-items: center; padding: 1.5rem; }
        .card { background: #fff; border-radius: 16px; padding: 2rem; max-width: 420px; width: 100%; box-shadow: 0 8px 30px rgba(0,0,0,.08); }
        h1 { margin: 0 0 .5rem; font-size: 1.4rem; }
        p { color: #4b5563; line-height: 1.5; margin: .5rem 0 1.5rem; }
        label { display: block; font-size: .85rem; font-weight: 500; margin-bottom: .35rem; color: #374151; }
        input[type=email] { width: 100%; padding: .65rem .8rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem; }
        input[type=email]:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 25%, transparent); }
        button { margin-top: 1rem; width: 100%; padding: .8rem; background: var(--primary); color: #fff; border: 0; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; }
        button:hover { filter: brightness(0.95); }
        .error { color: #b91c1c; font-size: .85rem; margin-top: .35rem; }
        .secondary { display: block; text-align: center; margin-top: 1rem; color: #6b7280; text-decoration: none; font-size: .9rem; }
        @media (prefers-color-scheme: dark) {
            html:not(.is-demo) body { background: #2a2017; color: #f7f4ef; }
            html:not(.is-demo) .card { background: #3a2f25; }
            html:not(.is-demo) input[type=email] { background: #2a2017; border-color: #5a4d44; color: #f7f4ef; }
            html:not(.is-demo) label, p { color: #e9e2d3; }
            html:not(.is-demo) .secondary { color: #c8b8a4; }
        }
    </style>
</head>
<body>
    <x-demo-light-mode />
    <x-demo-banner />
    <div class="card">
        <h1>{{ __('portal/login.login.heading', ['tenant' => $tenant->name]) }}</h1>
        <p>{{ __('portal/login.login.intro') }}</p>

        <form method="post" action="{{ route('client_portal.login.submit', ['slug' => $tenant->slug]) }}">
            @csrf
            <label for="email">{{ __('portal/login.login.email') }}</label>
            <input id="email" type="email" name="email" required autofocus placeholder="ty@example.com" value="{{ old('email') }}">
            @error('email')<div class="error">{{ $message }}</div>@enderror
            <button type="submit">{{ __('portal/login.login.submit') }}</button>
        </form>

        <a class="secondary" href="{{ url('/s/' . $tenant->slug) }}">{{ __('portal/login.login.back') }}</a>
    </div>
</body>
</html>
