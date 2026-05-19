<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('public/transport_lead_portal.signup_form.title') }} — hovera</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}">
    <style>
        :root { --primary: #A8956B; --primary-dark: #8F8576; --bg: #F7F4EF; --text: #1F1A17; --danger: #b91c1c; --muted: #6b7280; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, system-ui, sans-serif; background: var(--bg); color: var(--text); color-scheme: light; }
        body { padding: 2rem 1rem; }
        .container { max-width: 480px; margin: 0 auto; }
        .logo { text-align: center; margin-bottom: 1.25rem; font-size: 1.3rem; font-weight: 700; color: #3D2E22; }
        .card { background: #fff; border-radius: 16px; padding: 1.75rem; box-shadow: 0 6px 24px rgba(0,0,0,.06); }
        h1 { margin: 0 0 .5rem; font-size: 1.4rem; color: #3D2E22; }
        .intro { color: var(--muted); margin: 0 0 1.25rem; line-height: 1.55; font-size: .92rem; }
        .form-row { display: flex; flex-direction: column; gap: .35rem; margin-bottom: 1rem; }
        label { font-weight: 600; font-size: .88rem; color: #3D2E22; }
        input[type=email], input[type=password] { padding: .65rem .85rem; border: 1px solid #d4cdb8; border-radius: 8px; font: inherit; background: #fff; color: var(--text); width: 100%; }
        input[disabled] { background: #faf7f1; color: var(--muted); }
        input:focus { outline: 2px solid var(--primary); outline-offset: 2px; border-color: var(--primary); }
        .hint { font-size: .78rem; color: var(--muted); line-height: 1.4; }
        .terms-row { display: flex; gap: .5rem; align-items: flex-start; padding: .75rem; background: var(--bg); border-radius: 8px; margin: .75rem 0; }
        .terms-row label { font-weight: 400; font-size: .85rem; line-height: 1.5; }
        button.submit { width: 100%; padding: .85rem 1.2rem; background: var(--primary); color: #fff; border: 0; border-radius: 10px; font-weight: 700; font-size: 1rem; cursor: pointer; margin-top: .75rem; }
        button.submit:hover { background: var(--primary-dark); }
        .cancel-link { display: block; text-align: center; margin-top: .85rem; color: var(--muted); text-decoration: none; font-size: .88rem; }
        .alert { padding: .85rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: .9rem; background: #fef2f2; color: var(--danger); border-left: 4px solid var(--danger); }
        .honeypot { position: absolute; left: -9999px; }
    </style>
</head>
<body>
<div class="container">
    <div class="logo">hovera · transport</div>
    <div class="card">
        <h1>{{ __('public/transport_lead_portal.signup_form.heading') }}</h1>
        <p class="intro">{{ __('public/transport_lead_portal.signup_form.intro', ['email' => $lead->originator_email]) }}</p>

        @if ($errors->any())
            <div class="alert">
                <ul style="margin:0;padding-left:1.2rem;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('public.transport.lead_portal.signup.submit', ['slug' => $lead->access_slug]) }}" novalidate>
            @csrf

            <div class="honeypot" aria-hidden="true">
                <label>Website (leave blank)<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>

            <div class="form-row">
                <label for="email">{{ __('public/transport_lead_portal.signup_form.label.email') }}</label>
                <input id="email" type="email" value="{{ $lead->originator_email }}" disabled>
                <span class="hint">{{ __('public/transport_lead_portal.signup_form.hint.email_locked') }}</span>
            </div>

            <div class="form-row">
                <label for="password">{{ __('public/transport_lead_portal.signup_form.label.password') }}</label>
                <input id="password" type="password" name="password" required minlength="8" maxlength="128" autocomplete="new-password">
                <span class="hint">{{ __('public/transport_lead_portal.signup_form.hint.password') }}</span>
            </div>

            <div class="form-row">
                <label for="password_confirmation">{{ __('public/transport_lead_portal.signup_form.label.password_confirmation') }}</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required minlength="8" maxlength="128" autocomplete="new-password">
            </div>

            <div class="terms-row">
                <input type="checkbox" id="terms" name="terms" required value="1">
                <label for="terms">{{ __('public/transport_lead_portal.signup_form.label.terms') }}</label>
            </div>

            <button type="submit" class="submit">{{ __('public/transport_lead_portal.signup_form.submit') }}</button>
            <a href="{{ route('public.transport.lead_portal', ['slug' => $lead->access_slug]) }}" class="cancel-link">{{ __('public/transport_lead_portal.signup_form.cancel') }}</a>
        </form>
    </div>
</div>
</body>
</html>
