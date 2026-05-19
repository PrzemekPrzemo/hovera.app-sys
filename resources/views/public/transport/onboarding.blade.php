<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('public/transporter_onboarding.title') }} — hovera</title>
    <meta name="robots" content="noindex">
    <link rel="icon" href="{{ asset('favicon.svg') }}">
    <x-pwa-head />
    <style>
        :root { --primary: #A8956B; --primary-dark: #8F8576; --bg: #F7F4EF; --text: #1F1A17; --danger: #b91c1c; --success: #166534; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: var(--bg); color: var(--text); }
        body { padding: 2rem 1rem; }
        .container { max-width: 760px; margin: 0 auto; }
        .logo { text-align: center; margin-bottom: 1.5rem; font-size: 1.4rem; font-weight: 700; letter-spacing: -.02em; color: #3D2E22; }
        .card { background: #fff; border-radius: 16px; padding: 2rem; box-shadow: 0 6px 24px rgba(0,0,0,.06); margin-bottom: 1.5rem; }
        h1 { margin: 0 0 .5rem; font-size: 1.65rem; }
        h2 { margin: 0 0 1rem; font-size: 1.1rem; color: #3D2E22; border-bottom: 2px solid var(--bg); padding-bottom: .5rem; }
        .subtitle { color: #6b7280; margin-bottom: 1.5rem; font-size: .96rem; line-height: 1.55; }
        .perks { background: var(--bg); padding: 1rem 1.2rem; border-radius: 10px; margin-bottom: 0; font-size: .85rem; color: #3D2E22; line-height: 1.7; }
        .perks ul { margin: 0; padding-left: 1.2rem; }
        .form-grid { display: grid; gap: 1rem; grid-template-columns: 1fr 1fr; }
        .form-row { display: flex; flex-direction: column; gap: .35rem; }
        .form-row.span-2 { grid-column: span 2; }
        @media (max-width: 600px) { .form-grid { grid-template-columns: 1fr; } .form-row.span-2 { grid-column: span 1; } }
        label { font-weight: 600; font-size: .88rem; color: #3D2E22; }
        .helper { font-size: .78rem; color: #6b7280; line-height: 1.4; }
        input[type=text], input[type=email], input[type=tel], textarea {
            padding: .65rem .85rem; border: 1px solid #d4cdb8; border-radius: 8px;
            font: inherit; background: #fff; color: var(--text); width: 100%;
        }
        input:focus, textarea:focus, input[type=file]:focus { outline: 2px solid var(--primary); outline-offset: 2px; border-color: var(--primary); }
        input.error, textarea.error { border-color: var(--danger); }
        .error-msg { color: var(--danger); font-size: .8rem; margin-top: .15rem; }
        .doc-row { display: grid; gap: .5rem; grid-template-columns: 1fr; padding: .85rem; background: #faf7f1; border-radius: 8px; border: 1px solid #e8e0cc; }
        .doc-row label { display: flex; gap: .4rem; align-items: baseline; }
        .doc-row .doc-name { font-weight: 600; }
        .doc-row .doc-required { color: var(--danger); font-size: .85rem; }
        .doc-row .doc-desc { font-size: .78rem; color: #6b7280; line-height: 1.4; }
        input[type=file] { padding: .35rem 0; font: inherit; color: var(--text); }
        .terms-row { display: flex; gap: .5rem; align-items: flex-start; padding: 1rem; background: var(--bg); border-radius: 8px; margin-top: 1rem; }
        .terms-row input { margin-top: .15rem; }
        .terms-row label { font-weight: 400; font-size: .85rem; line-height: 1.5; }
        .terms-row a { color: var(--primary-dark); }
        button.submit { width: 100%; padding: .9rem 1.2rem; background: var(--primary); color: #fff; border: 0; border-radius: 10px; font-weight: 700; font-size: 1rem; cursor: pointer; margin-top: 1.25rem; }
        button.submit:hover { background: var(--primary-dark); }
        .marketplace-disclaimer { background: #fef9e7; padding: .9rem 1rem; border-radius: 8px; font-size: .8rem; color: #5d4d22; line-height: 1.5; border-left: 4px solid #d4b95c; margin-bottom: 1rem; }
        .alert { padding: .85rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: .9rem; }
        .alert-danger { background: #fef2f2; color: var(--danger); border-left: 4px solid var(--danger); }
        .alert-success { background: #ecfdf5; color: var(--success); border-left: 4px solid var(--success); }
        .field-hint { font-size: .75rem; color: #9ca3af; margin-top: .25rem; }
        .honeypot { position: absolute; left: -9999px; }
    </style>
</head>
<body>
<div class="container">
    <div class="logo">hovera · transport</div>

    <div class="card">
        <h1>{{ __('public/transporter_onboarding.heading') }}</h1>
        <p class="subtitle">{{ __('public/transporter_onboarding.subtitle') }}</p>

        <div class="perks">
            <strong>{{ __('public/transporter_onboarding.perks.title') }}</strong>
            <ul>
                <li>{{ __('public/transporter_onboarding.perks.item_1') }}</li>
                <li>{{ __('public/transporter_onboarding.perks.item_2') }}</li>
                <li>{{ __('public/transporter_onboarding.perks.item_3') }}</li>
                <li>{{ __('public/transporter_onboarding.perks.item_4') }}</li>
            </ul>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>{{ __('public/transporter_onboarding.errors.heading') }}</strong>
            <ul style="margin: .35rem 0 0; padding-left: 1.2rem;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form action="{{ route('public.transport.onboarding.submit') }}" method="post" enctype="multipart/form-data" novalidate>
        @csrf

        {{-- Honeypot — bot wypełnia, real user nie. --}}
        <div class="honeypot" aria-hidden="true">
            <label>Website (zostaw puste)<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>

        {{-- Sekcja 1: Firma --}}
        <div class="card">
            <h2>1. {{ __('public/transporter_onboarding.section.company') }}</h2>
            <div class="form-grid">
                <div class="form-row span-2">
                    <label for="name">{{ __('public/transporter_onboarding.field.name') }} <span class="doc-required">*</span></label>
                    <input id="name" type="text" name="name" value="{{ $old['name'] }}" required maxlength="200" class="@error('name') error @enderror">
                    <span class="field-hint">{{ __('public/transporter_onboarding.field.name_hint') }}</span>
                    @error('name') <span class="error-msg">{{ $message }}</span> @enderror
                </div>
                <div class="form-row">
                    <label for="slug">{{ __('public/transporter_onboarding.field.slug') }} <span class="doc-required">*</span></label>
                    <input id="slug" type="text" name="slug" value="{{ $old['slug'] }}" required maxlength="62" pattern="[a-z0-9](?:[a-z0-9-]{1,60}[a-z0-9])?" class="@error('slug') error @enderror">
                    <span class="field-hint">{{ __('public/transporter_onboarding.field.slug_hint') }}</span>
                    @error('slug') <span class="error-msg">{{ $message }}</span> @enderror
                </div>
                <div class="form-row">
                    <label for="tax_id">{{ __('public/transporter_onboarding.field.tax_id') }} <span class="doc-required">*</span></label>
                    <input id="tax_id" type="text" name="tax_id" value="{{ $old['tax_id'] }}" required maxlength="13" inputmode="numeric" class="@error('tax_id') error @enderror">
                    <span class="field-hint">{{ __('public/transporter_onboarding.field.tax_id_hint') }}</span>
                    @error('tax_id') <span class="error-msg">{{ $message }}</span> @enderror
                </div>
                <div class="form-row">
                    <label for="regon">{{ __('public/transporter_onboarding.field.regon') }} <span class="doc-required">*</span></label>
                    <input id="regon" type="text" name="regon" value="{{ $old['regon'] }}" required maxlength="14" inputmode="numeric" class="@error('regon') error @enderror">
                    <span class="field-hint">{{ __('public/transporter_onboarding.field.regon_hint') }}</span>
                    @error('regon') <span class="error-msg">{{ $message }}</span> @enderror
                </div>
                <div class="form-row span-2">
                    <label for="address">{{ __('public/transporter_onboarding.field.address') }} <span class="doc-required">*</span></label>
                    <input id="address" type="text" name="address" value="{{ $old['address'] }}" required maxlength="255" class="@error('address') error @enderror">
                    @error('address') <span class="error-msg">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        {{-- Sekcja 2: Kontakt owner --}}
        <div class="card">
            <h2>2. {{ __('public/transporter_onboarding.section.owner') }}</h2>
            <div class="form-grid">
                <div class="form-row">
                    <label for="owner_name">{{ __('public/transporter_onboarding.field.owner_name') }} <span class="doc-required">*</span></label>
                    <input id="owner_name" type="text" name="owner_name" value="{{ $old['owner_name'] }}" required maxlength="120" class="@error('owner_name') error @enderror">
                    @error('owner_name') <span class="error-msg">{{ $message }}</span> @enderror
                </div>
                <div class="form-row">
                    <label for="owner_email">{{ __('public/transporter_onboarding.field.owner_email') }} <span class="doc-required">*</span></label>
                    <input id="owner_email" type="email" name="owner_email" value="{{ $old['owner_email'] }}" required maxlength="255" class="@error('owner_email') error @enderror">
                    <span class="field-hint">{{ __('public/transporter_onboarding.field.owner_email_hint') }}</span>
                    @error('owner_email') <span class="error-msg">{{ $message }}</span> @enderror
                </div>
                <div class="form-row span-2">
                    <label for="owner_phone">{{ __('public/transporter_onboarding.field.owner_phone') }} <span class="doc-required">*</span></label>
                    <input id="owner_phone" type="tel" name="owner_phone" value="{{ $old['owner_phone'] }}" required maxlength="40" class="@error('owner_phone') error @enderror" placeholder="+48 600 100 200">
                    @error('owner_phone') <span class="error-msg">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        {{-- Sekcja 3: Dokumenty --}}
        <div class="card">
            <h2>3. {{ __('public/transporter_onboarding.section.documents') }}</h2>
            <div class="marketplace-disclaimer">
                {{ __('public/transporter_onboarding.documents_disclaimer') }}
            </div>
            <div class="form-grid" style="grid-template-columns: 1fr;">
                @foreach ($requiredDocuments as $inputName => $type)
                    <div class="doc-row">
                        <label for="{{ $inputName }}">
                            <span class="doc-name">{{ $type->label() }}</span>
                            <span class="doc-required">*</span>
                        </label>
                        <span class="doc-desc">{{ $type->description() }}</span>
                        <input id="{{ $inputName }}" type="file" name="{{ $inputName }}" required accept=".pdf,.jpg,.jpeg,.png" class="@error($inputName) error @enderror">
                        <span class="field-hint">{{ __('public/transporter_onboarding.documents.file_hint') }}</span>
                        @error($inputName) <span class="error-msg">{{ $message }}</span> @enderror
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Sekcja 4: Zgody --}}
        <div class="card">
            <h2>4. {{ __('public/transporter_onboarding.section.terms') }}</h2>
            <div class="marketplace-disclaimer">
                {{ __('public/transporter_onboarding.terms.marketplace_position') }}
            </div>
            <div class="terms-row">
                <input type="checkbox" id="terms" name="terms" required value="1">
                <label for="terms">
                    {!! __('public/transporter_onboarding.terms.accept_html', [
                        'regulamin' => '<a href="'.route('legal.terms').'" target="_blank" rel="noopener">'.__('public/transporter_onboarding.terms.regulamin').'</a>',
                        'marketplace' => '<a href="'.route('legal.marketplace').'" target="_blank" rel="noopener">'.__('public/transporter_onboarding.terms.marketplace').'</a>',
                        'privacy' => '<a href="'.route('legal.privacy').'" target="_blank" rel="noopener">'.__('public/transporter_onboarding.terms.privacy').'</a>',
                    ]) !!}
                </label>
            </div>
            @error('terms') <span class="error-msg" style="display:block;margin-top:.5rem;">{{ $message }}</span> @enderror
        </div>

        <button type="submit" class="submit">{{ __('public/transporter_onboarding.submit') }}</button>
    </form>
</div>
</body>
</html>
