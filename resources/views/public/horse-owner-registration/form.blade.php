<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('public/horse_owner_registration.meta.title') }}</title>
    <meta name="description" content="{{ __('public/horse_owner_registration.meta.description') }}">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="{{ url('/register/horse-owner') }}">
    <link rel="icon" href="{{ asset('favicon.svg') }}">
    <x-pwa-head />
    <style>
        :root { --primary: #A8956B; --primary-dark: #8F8576; --bg: #F7F4EF; --text: #1F1A17; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: var(--bg); color: var(--text); }
        body { padding: 2rem 1rem; }
        .container { max-width: 560px; margin: 0 auto; }
        .logo { text-align: center; margin-bottom: 1.5rem; font-size: 1.4rem; font-weight: 700; letter-spacing: -.02em; color: #3D2E22; }
        .card { background: #fff; border-radius: 16px; padding: 2rem; box-shadow: 0 6px 24px rgba(0,0,0,.06); }
        h1 { margin: 0 0 .35rem; font-size: 1.55rem; }
        .subtitle { color: #6b7280; margin-bottom: 1.5rem; font-size: .94rem; line-height: 1.5; }
        .invite-banner { background: #fffbeb; border: 1px solid #fde68a; padding: .75rem 1rem; border-radius: 10px; margin-bottom: 1.25rem; font-size: .9rem; color: #78350f; }
        .form-row { display: flex; flex-direction: column; gap: .35rem; margin-bottom: 1rem; }
        label { font-weight: 600; font-size: .88rem; color: #3D2E22; }
        .helper { font-size: .78rem; color: #6b7280; }
        input[type=text], input[type=email], input[type=tel] { padding: .65rem .85rem; border: 1px solid #d4cdb8; border-radius: 8px; font: inherit; background: #fff; color: var(--text); }
        input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 25%, transparent); }
        .terms { display: flex; gap: .5rem; align-items: flex-start; margin: 1rem 0; font-size: .85rem; color: #3D2E22; }
        .terms input { margin-top: .15rem; }
        .terms a { color: var(--primary); }
        button[type=submit] { width: 100%; padding: .9rem 1.2rem; background: var(--primary); color: #fff; border: 0; border-radius: 8px; font-weight: 700; font-size: 1rem; cursor: pointer; }
        button[type=submit]:hover { filter: brightness(0.95); }
        .errors { background: #fef2f2; color: #991b1b; padding: .75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: .88rem; }
        .errors ul { margin: .25rem 0 0; padding-left: 1.25rem; }
        .features { margin-top: 1.75rem; padding-top: 1.5rem; border-top: 1px solid #ede7d8; }
        .features h2 { font-size: 1.05rem; margin: 0 0 .9rem; color: #3D2E22; }
        .feature { display: flex; gap: .8rem; margin-bottom: .8rem; align-items: flex-start; }
        .feature-icon { width: 36px; height: 36px; flex-shrink: 0; background: var(--bg); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--primary); font-weight: 700; }
        .feature-text strong { display: block; font-size: .92rem; color: #3D2E22; }
        .feature-text span { font-size: .82rem; color: #6b7280; }
        .footer-links { text-align: center; margin-top: 1rem; font-size: .85rem; color: #6b7280; }
        .footer-links a { color: var(--primary); text-decoration: none; margin: 0 .35rem; }
        .stable-hint-banner { background: #ecfdf5; border: 1px solid #86efac; padding: .75rem 1rem; border-radius: 10px; margin-bottom: 1.25rem; font-size: .88rem; color: #166534; line-height: 1.5; }
        /* Light mode only — wymog user spec. Brak prefers-color-scheme:dark override. */
        html { color-scheme: light; }
    </style>
    <x-google-analytics />
</head>
<body>
    <div class="container">
        <div class="logo">hovera</div>

        <div class="card">
            <h1>{{ __('public/horse_owner_registration.heading') }}</h1>
            <p class="subtitle">{{ __('public/horse_owner_registration.subheading') }}</p>

            @if (! empty($invite_stable_id))
                <div class="invite-banner">{{ __('public/horse_owner_registration.invite.banner') }}</div>
            @else
                <div class="stable-hint-banner">{{ __('public/horse_owner_registration.stable_hint.banner') }}</div>
            @endif

            @if ($errors->any())
                <div class="errors">
                    <strong>{{ __('common.form.errors_heading') }}</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="post" action="{{ route('register.horse-owner.submit') }}">
                @csrf

                @if (! empty($invite_stable_id))
                    <input type="hidden" name="invite_stable_id" value="{{ $invite_stable_id }}">
                @endif
                @if (! empty($invite_token))
                    <input type="hidden" name="invite_token" value="{{ $invite_token }}">
                @endif

                <div class="form-row">
                    <label for="owner_name">{{ __('public/horse_owner_registration.form.label.owner_name') }}</label>
                    <input type="text" name="owner_name" id="owner_name" required maxlength="120"
                           value="{{ $old['owner_name'] }}"
                           placeholder="{{ __('public/horse_owner_registration.form.placeholder.owner_name') }}">
                </div>

                <div class="form-row">
                    <label for="owner_email">{{ __('public/horse_owner_registration.form.label.owner_email') }}</label>
                    <input type="email" name="owner_email" id="owner_email" required maxlength="255"
                           value="{{ $old['owner_email'] }}"
                           placeholder="{{ __('public/horse_owner_registration.form.placeholder.owner_email') }}">
                </div>

                <div class="form-row">
                    <label for="owner_phone">{{ __('public/horse_owner_registration.form.label.owner_phone') }}</label>
                    <input type="tel" name="owner_phone" id="owner_phone" maxlength="40"
                           value="{{ $old['owner_phone'] }}"
                           placeholder="{{ __('public/horse_owner_registration.form.placeholder.owner_phone') }}">
                </div>

                <label class="terms">
                    <input type="checkbox" name="terms" value="1" required>
                    <span>
                        {!! __('public/horse_owner_registration.form.label.terms', [
                            'terms' => '<a href="'.route('legal.terms').'" target="_blank">'.__('public/horse_owner_registration.form.label.terms_link').'</a>',
                            'privacy' => '<a href="'.route('legal.privacy').'" target="_blank">'.__('public/horse_owner_registration.form.label.privacy_link').'</a>',
                        ]) !!}
                    </span>
                </label>

                <button type="submit">{{ __('public/horse_owner_registration.form.submit') }}</button>
            </form>

            <div class="features">
                <h2>{{ __('public/horse_owner_registration.features.heading') }}</h2>

                <div class="feature">
                    <div class="feature-icon">🚚</div>
                    <div class="feature-text">
                        <strong>{{ __('public/horse_owner_registration.features.order_transport.title') }}</strong>
                        <span>{{ __('public/horse_owner_registration.features.order_transport.body') }}</span>
                    </div>
                </div>

                <div class="feature">
                    <div class="feature-icon">📄</div>
                    <div class="feature-text">
                        <strong>{{ __('public/horse_owner_registration.features.horse_docs.title') }}</strong>
                        <span>{{ __('public/horse_owner_registration.features.horse_docs.body') }}</span>
                    </div>
                </div>

                <div class="feature">
                    <div class="feature-icon">🏇</div>
                    <div class="feature-text">
                        <strong>{{ __('public/horse_owner_registration.features.stable_relation.title') }}</strong>
                        <span>{{ __('public/horse_owner_registration.features.stable_relation.body') }}</span>
                    </div>
                </div>

                <div class="feature">
                    <div class="feature-icon">📊</div>
                    <div class="feature-text">
                        <strong>{{ __('public/horse_owner_registration.features.history.title') }}</strong>
                        <span>{{ __('public/horse_owner_registration.features.history.body') }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer-links">
            <a href="/">hovera.app</a>
            ·
            <a href="{{ route('legal.terms') }}">{{ __('public/horse_owner_registration.form.label.terms_link') }}</a>
        </div>
    </div>
</body>
</html>
