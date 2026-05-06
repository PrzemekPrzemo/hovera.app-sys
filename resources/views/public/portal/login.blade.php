<!DOCTYPE html>
<html lang="{{ $tenant->locale ?? 'pl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel klienta — {{ $tenant->name }}</title>
    <meta name="robots" content="noindex">
    <style>
        :root { --primary: {{ $primary_color }}; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: #fafafa; color: #1f2937; }
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
            body { background: #0f172a; color: #e5e7eb; }
            .card { background: #1e293b; }
            input[type=email] { background: #0f172a; border-color: #334155; color: #e5e7eb; }
            label, p { color: #cbd5e1; }
            .secondary { color: #94a3b8; }
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Panel klienta — {{ $tenant->name }}</h1>
        <p>Wpisz adres e-mail, na który zostały wysyłane potwierdzenia rezerwacji. Otrzymasz link do logowania.</p>

        <form method="post" action="{{ route('client_portal.login.submit', ['slug' => $tenant->slug]) }}">
            @csrf
            <label for="email">E-mail</label>
            <input id="email" type="email" name="email" required autofocus placeholder="ty@example.com" value="{{ old('email') }}">
            @error('email')<div class="error">{{ $message }}</div>@enderror
            <button type="submit">Wyślij link logowania</button>
        </form>

        <a class="secondary" href="{{ url('/s/' . $tenant->slug) }}">← Wróć do strony stajni</a>
    </div>
</body>
</html>
