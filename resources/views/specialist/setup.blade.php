<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('specialist/setup.page.title') }}</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #f4f4f8; margin: 0; padding: 2rem 1rem; }
        .container { max-width: 480px; margin: 0 auto; background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); padding: 2rem; }
        h1 { margin: 0 0 .5rem; font-size: 1.5rem; }
        p.muted { color: #6b7280; margin: 0 0 1.5rem; font-size: .9rem; }
        label { display: block; font-size: .85rem; color: #374151; margin: 1rem 0 .25rem; }
        input[type=password] { width: 100%; padding: .65rem .75rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 1rem; box-sizing: border-box; }
        input[type=password]:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.1); }
        button { background: #4f46e5; color: white; border: 0; padding: .75rem 1rem; border-radius: 6px; font-size: 1rem; cursor: pointer; width: 100%; margin-top: 1.5rem; }
        button:hover { background: #4338ca; }
        .errors { background: #fee2e2; border-left: 3px solid #ef4444; padding: .75rem; margin: 1rem 0; border-radius: 4px; color: #991b1b; font-size: .85rem; }
        .errors li { margin: .25rem 0 .25rem 1rem; }
    </style>
</head>
<body>
    <div class="container">
        <h1>{{ __('specialist/setup.heading') }}</h1>
        <p class="muted">{{ __('specialist/setup.intro', ['email' => $specialist->email]) }}</p>

        @if ($errors->any())
            <div class="errors">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ url('/specialist/setup/'.$token) }}">
            @csrf

            <label for="password">{{ __('specialist/setup.field.password') }}</label>
            <input type="password" id="password" name="password" required autocomplete="new-password">

            <label for="password_confirmation">{{ __('specialist/setup.field.password_confirmation') }}</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">

            <button type="submit">{{ __('specialist/setup.button.submit') }}</button>
        </form>
    </div>
</body>
</html>
