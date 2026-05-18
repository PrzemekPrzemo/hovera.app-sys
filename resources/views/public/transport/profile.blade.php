<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tenant->name }} — {{ __('public/transporter_profile.title_suffix') }}</title>
    <meta name="description" content="{{ $description ? \Illuminate\Support\Str::limit(strip_tags($description), 155) : __('public/transporter_profile.meta_description_fallback', ['name' => $tenant->name]) }}">
    <meta property="og:title" content="{{ $tenant->name }}">
    @if ($logo_url)
        <meta property="og:image" content="{{ $logo_url }}">
    @endif
    @if ($hero_image_url)
        <meta property="og:image" content="{{ $hero_image_url }}">
    @endif
    <link rel="icon" href="{{ asset('favicon.svg') }}">
    <style>
        :root { --primary: {{ $primary_color }}; --bg: #F7F4EF; --text: #1F1A17; --muted: #6b7280; --border: #d4cdb8; --card: #fff; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: var(--bg); color: var(--text); }
        body { padding: 0; line-height: 1.55; }
        .hero { background: linear-gradient(135deg, var(--primary) 0%, color-mix(in srgb, var(--primary) 70%, #000) 100%); color: #fff; padding: 3rem 1.25rem 4rem; text-align: center; }
        .hero-inner { max-width: 880px; margin: 0 auto; }
        .logo-img { max-height: 80px; max-width: 220px; margin: 0 auto 1.25rem; display: block; background: rgba(255,255,255,.08); padding: .5rem 1rem; border-radius: 12px; }
        .hero h1 { margin: 0 0 .5rem; font-size: 2.25rem; letter-spacing: -.01em; }
        .hero .tagline { font-size: 1.05rem; opacity: .92; margin-bottom: 1.75rem; }
        .cta { display: inline-block; padding: .9rem 1.6rem; background: #fff; color: var(--primary); border-radius: 10px; font-weight: 700; text-decoration: none; font-size: 1rem; box-shadow: 0 6px 20px rgba(0,0,0,.18); }
        .cta:hover { transform: translateY(-1px); }
        .container { max-width: 880px; margin: 0 auto; padding: 0 1.25rem; }
        .section { padding: 2.5rem 0; }
        .section h2 { margin: 0 0 1.25rem; font-size: 1.4rem; color: #3D2E22; }
        .lead { color: #3D2E22; font-size: 1.05rem; white-space: pre-line; }
        .grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); }
        .vehicle { background: var(--card); border-radius: 14px; padding: 1.1rem 1.2rem; box-shadow: 0 3px 14px rgba(0,0,0,.05); }
        .vehicle img { width: 100%; aspect-ratio: 16 / 10; object-fit: cover; border-radius: 10px; margin-bottom: .75rem; background: #eee; }
        .vehicle h3 { margin: 0 0 .25rem; font-size: 1.05rem; color: #3D2E22; }
        .vehicle .meta { color: var(--muted); font-size: .88rem; margin-bottom: .5rem; }
        .badges { display: flex; flex-wrap: wrap; gap: .35rem; }
        .badge { display: inline-block; padding: .2rem .55rem; border-radius: 999px; font-size: .72rem; font-weight: 600; background: color-mix(in srgb, var(--primary) 15%, transparent); color: color-mix(in srgb, var(--primary) 80%, #000); }
        .voivodeships { display: flex; flex-wrap: wrap; gap: .4rem; }
        .voiv { display: inline-block; padding: .35rem .8rem; border-radius: 999px; font-size: .85rem; background: var(--card); border: 1px solid var(--border); color: #3D2E22; }
        .voiv.primary { background: color-mix(in srgb, var(--primary) 20%, #fff); border-color: var(--primary); color: #3D2E22; font-weight: 600; }
        .contact { background: var(--card); border-radius: 14px; padding: 1.5rem; box-shadow: 0 3px 14px rgba(0,0,0,.05); display: grid; gap: .65rem; }
        .contact-row { display: flex; gap: .6rem; align-items: center; font-size: .95rem; }
        .contact-row .label { color: var(--muted); min-width: 80px; }
        .contact-row a { color: var(--primary); text-decoration: none; }
        .contact-row a:hover { text-decoration: underline; }
        .footer-cta { text-align: center; padding: 3rem 1.25rem; background: #fff; border-top: 1px solid var(--border); }
        .footer-cta .cta { background: var(--primary); color: #fff; }
        .powered { text-align: center; padding: 1.5rem; color: var(--muted); font-size: .82rem; }
        .powered a { color: var(--muted); text-decoration: none; }
        @media (prefers-color-scheme: dark) {
            html, body { background: #1F1A17; color: #F7F4EF; }
            .vehicle, .contact, .footer-cta, .voiv { background: #2a221c; color: #E9E2D3; border-color: #4a3d31; }
            .section h2, .vehicle h3 { color: #E9E2D3; }
            .lead { color: #E9E2D3; }
            .powered, .powered a { color: #C8B8A4; }
        }
        @media (max-width: 600px) {
            .hero { padding: 2rem 1rem 3rem; }
            .hero h1 { font-size: 1.7rem; }
        }
    </style>
</head>
<body>
    <section class="hero">
        <div class="hero-inner">
            @if ($logo_url)
                <img src="{{ $logo_url }}" alt="{{ $tenant->name }}" class="logo-img">
            @endif
            <h1>{{ $tenant->name }}</h1>
            @if ($tagline)
                <div class="tagline">{{ $tagline }}</div>
            @else
                <div class="tagline">{{ __('public/transporter_profile.default_tagline') }}</div>
            @endif
            <a href="{{ route('public.transport.inquiry') }}" class="cta">
                {{ __('public/transporter_profile.cta_inquiry') }}
            </a>
        </div>
    </section>

    @if ($description)
        <section class="section">
            <div class="container">
                <h2>{{ __('public/transporter_profile.section_about') }}</h2>
                <div class="lead">{{ $description }}</div>
            </div>
        </section>
    @endif

    @if (! empty($vehicles))
        <section class="section">
            <div class="container">
                <h2>{{ __('public/transporter_profile.section_fleet') }}</h2>
                <div class="grid">
                    @foreach ($vehicles as $vehicle)
                        <div class="vehicle">
                            @if ($vehicle['photo'])
                                <img src="{{ $vehicle['photo'] }}" alt="{{ $vehicle['name'] }}" loading="lazy">
                            @endif
                            <h3>{{ $vehicle['name'] }}</h3>
                            <div class="meta">
                                @if ($vehicle['capacity'])
                                    {{ trans_choice('public/transporter_profile.vehicle_capacity', $vehicle['capacity'], ['count' => $vehicle['capacity']]) }}
                                @endif
                                @if ($vehicle['year'])
                                    · {{ __('public/transporter_profile.vehicle_year', ['year' => $vehicle['year']]) }}
                                @endif
                            </div>
                            <div class="badges">
                                @if ($vehicle['has_air_suspension'])
                                    <span class="badge">{{ __('public/transporter_profile.feature_air_suspension') }}</span>
                                @endif
                                @if ($vehicle['has_camera'])
                                    <span class="badge">{{ __('public/transporter_profile.feature_camera') }}</span>
                                @endif
                                @if ($vehicle['has_climate_control'])
                                    <span class="badge">{{ __('public/transporter_profile.feature_climate') }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if (! empty($service_areas['primary']))
        <section class="section">
            <div class="container">
                <h2>{{ __('public/transporter_profile.section_coverage') }}</h2>
                <div class="voivodeships">
                    @foreach ($service_areas['primary'] as $v)
                        <span class="voiv primary">{{ $v }}</span>
                    @endforeach
                    @foreach ($service_areas['adjacent'] as $v)
                        <span class="voiv">{{ $v }}</span>
                    @endforeach
                </div>
                @if (! empty($service_areas['adjacent']))
                    <p style="color: var(--muted); font-size: .85rem; margin-top: .9rem;">
                        {{ __('public/transporter_profile.coverage_hint') }}
                    </p>
                @endif
            </div>
        </section>
    @endif

    @if ($contact_email || $contact_phone || $address || $website)
        <section class="section">
            <div class="container">
                <h2>{{ __('public/transporter_profile.section_contact') }}</h2>
                <div class="contact">
                    @if ($contact_email)
                        <div class="contact-row">
                            <span class="label">{{ __('public/transporter_profile.contact_email') }}</span>
                            <a href="mailto:{{ $contact_email }}">{{ $contact_email }}</a>
                        </div>
                    @endif
                    @if ($contact_phone)
                        <div class="contact-row">
                            <span class="label">{{ __('public/transporter_profile.contact_phone') }}</span>
                            <a href="tel:{{ preg_replace('/\s+/', '', $contact_phone) }}">{{ $contact_phone }}</a>
                        </div>
                    @endif
                    @if ($address)
                        <div class="contact-row">
                            <span class="label">{{ __('public/transporter_profile.contact_address') }}</span>
                            <span>{{ $address }}</span>
                        </div>
                    @endif
                    @if ($website)
                        <div class="contact-row">
                            <span class="label">{{ __('public/transporter_profile.contact_website') }}</span>
                            <a href="{{ $website }}" target="_blank" rel="noopener noreferrer">{{ $website }}</a>
                        </div>
                    @endif
                </div>
            </div>
        </section>
    @endif

    <div class="footer-cta">
        <p style="margin: 0 0 1.25rem; font-size: 1.05rem; color: #3D2E22;">
            {{ __('public/transporter_profile.footer_cta_text') }}
        </p>
        <a href="{{ route('public.transport.inquiry') }}" class="cta">
            {{ __('public/transporter_profile.cta_inquiry') }}
        </a>
    </div>

    <div class="powered">
        {{-- Marketplace disclaimer: profil obsługiwany przez transportera —
             Hovera tylko hostuje. Klikalny link do regulaminu marketplace
             — Klient może zweryfikować rolę Hovera zanim wyśle zapytanie. --}}
        <div style="margin-bottom:.5rem;font-size:.78rem;">
            {!! __('public/transporter_profile.disclaimer_intermediary', [
                'transporter_name' => e($tenant->name),
            ]) !!}
        </div>
        <a href="https://hovera.app" target="_blank" rel="noopener">{{ __('public/transporter_profile.powered_by') }}</a>
    </div>
</body>
</html>
