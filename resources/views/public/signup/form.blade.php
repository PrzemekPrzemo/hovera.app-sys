<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('public/signup.title') }} — hovera</title>
    <meta name="robots" content="noindex">
    <link rel="icon" href="{{ asset('favicon.svg') }}">
    <style>
        :root { --primary: #A8956B; --primary-dark: #8F8576; --bg: #F7F4EF; --text: #1F1A17; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: var(--bg); color: var(--text); }
        body { padding: 2rem 1rem; }
        .container { max-width: 520px; margin: 0 auto; }
        .logo { text-align: center; margin-bottom: 1.5rem; font-size: 1.4rem; font-weight: 700; letter-spacing: -.02em; color: #3D2E22; }
        .card { background: #fff; border-radius: 16px; padding: 2rem; box-shadow: 0 6px 24px rgba(0,0,0,.06); }
        h1 { margin: 0 0 .35rem; font-size: 1.5rem; }
        .subtitle { color: #6b7280; margin-bottom: 1.5rem; font-size: .92rem; line-height: 1.5; }
        .perks { background: var(--bg); padding: .9rem 1rem; border-radius: 10px; margin-bottom: 1.5rem; font-size: .85rem; color: #3D2E22; line-height: 1.6; }
        .perks strong { display: block; margin-bottom: .25rem; }
        .form-row { display: flex; flex-direction: column; gap: .35rem; margin-bottom: 1rem; }
        label { font-weight: 600; font-size: .88rem; color: #3D2E22; }
        .helper { font-size: .78rem; color: #6b7280; }
        input[type=text], input[type=email] { padding: .65rem .85rem; border: 1px solid #d4cdb8; border-radius: 8px; font: inherit; background: #fff; color: var(--text); }
        input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 25%, transparent); }
        .slug-prefix { display: flex; align-items: stretch; }
        .slug-prefix span { padding: .65rem .75rem; border: 1px solid #d4cdb8; border-right: 0; border-radius: 8px 0 0 8px; background: var(--bg); color: #6b7280; font-size: .85rem; white-space: nowrap; }
        .slug-prefix input { flex: 1; border-radius: 0 8px 8px 0; }
        .terms { display: flex; gap: .5rem; align-items: flex-start; margin: 1rem 0; font-size: .85rem; color: #3D2E22; }
        .terms input { margin-top: .15rem; }
        .terms a { color: var(--primary); }
        button[type=submit] { width: 100%; padding: .9rem 1.2rem; background: var(--primary); color: #fff; border: 0; border-radius: 8px; font-weight: 700; font-size: 1rem; cursor: pointer; }
        button[type=submit]:hover { filter: brightness(0.95); }
        .errors { background: #fef2f2; color: #991b1b; padding: .75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: .88rem; }
        .errors ul { margin: .25rem 0 0; padding-left: 1.25rem; }
        .footer-links { text-align: center; margin-top: 1rem; font-size: .85rem; color: #6b7280; }
        .footer-links a { color: var(--primary); text-decoration: none; margin: 0 .35rem; }
        .footer-links a:hover { text-decoration: underline; }
        @media (prefers-color-scheme: dark) {
            html, body { background: #1F1A17; color: #F7F4EF; }
            .card { background: #2a221c; }
            .logo, label, .terms { color: #E9E2D3; }
            .subtitle, .helper, .footer-links { color: #C8B8A4; }
            input[type=text], input[type=email] { background: #1F1A17; border-color: #4a3d31; color: #F7F4EF; }
            .slug-prefix span { background: #1F1A17; border-color: #4a3d31; color: #C8B8A4; }
            .perks { background: #1F1A17; color: #E9E2D3; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">hovera</div>

        <div class="card">
            <h1>{{ __('public/signup.heading') }}</h1>
            <div class="subtitle">{{ __('public/signup.subtitle') }}</div>

            <div class="perks">
                <strong>{{ __('public/signup.trial_strong') }}</strong>
                {{ __('public/signup.trial_text') }}
            </div>

            @if ($errors->any())
                <div class="errors">
                    <strong>{{ __('public/signup.errors.heading') }}</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="post" action="{{ route('signup.submit') }}">
                @csrf

                <div class="form-row">
                    <label for="name">{{ __('public/signup.label.name') }}</label>
                    <input type="text" name="name" id="name" required maxlength="120" autofocus
                           value="{{ $old['name'] }}"
                           placeholder="{{ __('public/signup.placeholder.name') }}"
                           oninput="suggestSlug(this.value)">
                    <div class="helper">{{ __('public/signup.helper.name') }}</div>
                </div>

                <div class="form-row">
                    <label for="slug">{{ __('public/signup.label.slug') }}</label>
                    <div class="slug-prefix">
                        <span>app.hovera.app/s/</span>
                        <input type="text" name="slug" id="slug" required minlength="3" maxlength="62"
                               value="{{ $old['slug'] }}"
                               pattern="[a-z0-9](?:[a-z0-9-]{1,60}[a-z0-9])?"
                               placeholder="moja-stajnia">
                    </div>
                    <div class="helper">{{ __('public/signup.helper.slug') }}</div>
                </div>

                <div class="form-row">
                    <label for="owner_name">{{ __('public/signup.label.owner_name') }}</label>
                    <input type="text" name="owner_name" id="owner_name" required maxlength="120"
                           value="{{ $old['owner_name'] }}"
                           placeholder="{{ __('public/signup.placeholder.owner_name') }}">
                </div>

                <div class="form-row">
                    <label for="owner_email">{{ __('public/signup.label.owner_email') }}</label>
                    <input type="email" name="owner_email" id="owner_email" required maxlength="255"
                           value="{{ $old['owner_email'] }}"
                           placeholder="ty@twoja-stajnia.pl">
                    <div class="helper">{{ __('public/signup.helper.owner_email') }}</div>
                </div>

                <label class="terms">
                    <input type="checkbox" name="terms" required>
                    <span>{!! __('public/signup.label.terms') !!}</span>
                </label>

                <button type="submit">{{ __('public/signup.action.submit') }}</button>
            </form>
        </div>

        <div class="footer-links">
            <a href="{{ url('/demo') }}">{{ __('public/signup.footer.demo') }}</a>
            ·
            <a href="{{ route('pricing.show') }}">{{ __('public/signup.footer.pricing') }}</a>
            ·
            <a href="/app/login">{{ __('public/signup.footer.login') }}</a>
        </div>
    </div>

    <script>
        function suggestSlug(name) {
            const slug = (name || '')
                .toLowerCase()
                .normalize('NFD')
                .replace(/[̀-ͯ]/g, '')
                .replace(/ł/g, 'l')
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '')
                .substring(0, 62);
            const field = document.getElementById('slug');
            if (field && !field.dataset.touched) field.value = slug;
        }
        document.getElementById('slug').addEventListener('input', e => e.target.dataset.touched = '1');
    </script>
</body>
</html>
