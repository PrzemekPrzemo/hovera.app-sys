<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tenant->name }} — {{ __('public/transporter_profile.title_suffix') }}</title>
    <meta name="description" content="{{ $description ? \Illuminate\Support\Str::limit(strip_tags($description), 155) : __('public/transporter_profile.meta_description_fallback', ['name' => $tenant->name]) }}">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="{{ route('public.transporter', ['slug' => $tenant->slug]) }}">
    <meta property="og:title" content="{{ $tenant->name }}">
    {{-- Pre-rendered 1200x630 PNG OG image — branded social-share card.
         Generowane on-demand przez TransporterOgImageController + cache na
         storage/app/public/og-images/transporter/{slug}.png. Wcześniejszy
         logo_url/hero_image_url były za małe (max 220px) dla unfurl'i. --}}
    <meta property="og:image" content="{{ route('public.transporter.og_image', ['slug' => $tenant->slug]) }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:type" content="image/png">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="{{ route('public.transporter.og_image', ['slug' => $tenant->slug]) }}">
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
        .certificates { display: grid; gap: .8rem; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); }
        .cert-card { display: flex; align-items: center; gap: .8rem; background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: .85rem 1rem; text-decoration: none; color: inherit; transition: border-color .15s, transform .15s; }
        .cert-card:hover { border-color: var(--primary); transform: translateY(-2px); }
        .cert-card .ico { flex: 0 0 36px; width: 36px; height: 36px; border-radius: 8px; background: color-mix(in srgb, var(--primary) 18%, #fff); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
        .cert-card .body { display: flex; flex-direction: column; min-width: 0; }
        .cert-card .title { font-size: .92rem; font-weight: 600; color: #3D2E22; line-height: 1.25; }
        .cert-card .meta { font-size: .76rem; color: var(--muted); margin-top: .15rem; }
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
        /* Badge „Zweryfikowany przez Hovera" — pokazujemy tylko gdy tenant
           ma verification_status=verified. Tooltip z pełną listą sprawdzonych
           dokumentów + linkiem do regulaminu marketplace (§12 disclaimer). */
        .verified-badge { display: inline-flex; align-items: center; gap: .4rem; margin-top: .9rem; padding: .35rem .85rem; background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.35); border-radius: 999px; color: #fff; font-size: .85rem; font-weight: 600; cursor: help; position: relative; }
        .verified-badge::before { content: "✓"; font-weight: 700; }
        .verified-badge .tooltip { visibility: hidden; opacity: 0; position: absolute; top: calc(100% + 10px); left: 50%; transform: translateX(-50%); background: #2a221c; color: #E9E2D3; padding: .85rem 1rem; border-radius: 8px; width: 320px; max-width: 90vw; font-size: .78rem; font-weight: 400; line-height: 1.4; text-align: left; box-shadow: 0 8px 24px rgba(0,0,0,.3); z-index: 10; }
        .verified-badge:hover .tooltip, .verified-badge:focus-within .tooltip { visibility: visible; opacity: 1; }
        .verified-badge .tooltip a { color: #E9E2D3; text-decoration: underline; }
                .reviews-summary { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.25rem; }
        .reviews-avg { font-size: 2.4rem; font-weight: 700; color: #3D2E22; line-height: 1; }
        .reviews-stars-big { color: var(--primary); font-size: 1.3rem; letter-spacing: .05em; }
        .reviews-count { color: var(--muted); font-size: .95rem; }
        .reviews-dist { display: grid; gap: .3rem; max-width: 320px; margin-bottom: 1.5rem; }
        .reviews-dist-row { display: grid; grid-template-columns: 28px 1fr 36px; gap: .5rem; align-items: center; font-size: .85rem; color: var(--muted); }
        .reviews-dist-bar { background: #ebe4d3; border-radius: 4px; overflow: hidden; height: 8px; }
        .reviews-dist-bar-fill { background: var(--primary); height: 100%; }
        .review { background: var(--card); border-radius: 12px; padding: 1rem 1.2rem; box-shadow: 0 2px 10px rgba(0,0,0,.04); margin-bottom: .75rem; }
        .review-head { display: flex; gap: .75rem; align-items: baseline; flex-wrap: wrap; margin-bottom: .35rem; }
        .review-stars { color: var(--primary); font-weight: 700; }
        .review-author { color: #3D2E22; font-weight: 600; }
        .review-date { color: var(--muted); font-size: .85rem; }
        .review-comment { color: #3D2E22; white-space: pre-line; }
        .review-verified { display: inline-block; padding: .12rem .55rem; border-radius: 999px; font-size: .7rem; background: color-mix(in srgb, var(--primary) 15%, transparent); color: color-mix(in srgb, var(--primary) 80%, #000); }
        .review-response { margin-top: .75rem; padding: .75rem 1rem; background: color-mix(in srgb, var(--primary) 8%, var(--bg)); border-left: 3px solid var(--primary); border-radius: 6px; }
        .review-response-label { font-weight: 600; color: #3D2E22; font-size: .85rem; margin-bottom: .25rem; }
                @media (max-width: 600px) {
            .hero { padding: 2rem 1rem 2.5rem; }
            .hero h1 { font-size: 1.55rem; }
            .hero .tagline { font-size: .95rem; }
            .section { padding: 1.5rem 0; }
            .section h2 { font-size: 1.2rem; }
            .container { padding: 0 1rem; }
            .grid { grid-template-columns: 1fr; gap: .85rem; }
            .vehicle { padding: 1rem; }
            .contact { padding: 1.1rem; }
            /* Etykieta kontaktu nad wartością na małym ekranie żeby dłuższe
               wartości (np. URL strony www) nie wypychały labelki poza ekran. */
            .contact-row { flex-direction: column; align-items: flex-start; gap: .1rem; }
            .contact-row .label { min-width: 0; font-size: .78rem; text-transform: uppercase; letter-spacing: .04em; }
            .contact-row a, .contact-row span { word-break: break-word; }
            .reviews-summary { gap: .65rem; }
            .reviews-avg { font-size: 2rem; }
            .footer-cta { padding: 2rem 1rem; }
            .verified-badge .tooltip { width: 280px; max-width: calc(100vw - 2rem); }
        }
            /* Light mode only — wymog user spec. Brak prefers-color-scheme:dark override. */
        html { color-scheme: light; }
    </style>
    <x-google-analytics />
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
            <a href="{{ route('public.transport.inquiry', ['transporter' => $tenant->slug]) }}" class="cta">
                {{ __('public/transporter_profile.cta_inquiry') }}
            </a>
            @if ($tenant->isVerifiedTransporter())
                {{-- Badge widoczny tylko dla verified tenant'ów. Tooltip rozwija
                     listę dokumentów PWL które Hovera zweryfikowała + link do
                     §12 regulaminu marketplace (disclaimer „Hovera nie odpowiada
                     za realizację transportu"). --}}
                <div class="verified-badge" tabindex="0" aria-label="{{ __('public/transporter_profile.verified_badge_label') }}">
                    {{ __('public/transporter_profile.verified_badge_label') }}
                    <span class="tooltip">
                        {{ __('public/transporter_profile.verified_badge_tooltip') }}
                        <br><br>
                        <a href="/regulamin-marketplace" target="_blank" rel="noopener">{{ __('public/transporter_profile.verified_badge_link_label') }}</a>
                    </span>
                </div>
            @endif
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

    @if (! empty($public_documents))
        <section class="section">
            <div class="container">
                <h2>{{ __('public/transporter_profile.section_certificates') }}</h2>
                <p style="color: var(--muted); font-size: .9rem; margin: -.5rem 0 1rem;">
                    {{ __('public/transporter_profile.section_certificates_intro') }}
                </p>
                <div class="certificates">
                    @foreach ($public_documents as $doc)
                        <a class="cert-card" href="{{ route('public.transporter.document', ['slug' => $tenant->slug, 'document' => $doc['id']]) }}" target="_blank" rel="noopener">
                            <span class="ico" aria-hidden="true">📄</span>
                            <span class="body">
                                <span class="title">{{ $doc['type_label'] }}</span>
                                @if (! empty($doc['uploaded_at']))
                                    <span class="meta">{{ __('public/transporter_profile.cert_uploaded', ['date' => $doc['uploaded_at']->format('Y-m')]) }}</span>
                                @endif
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if (! empty($review_aggregate) && ($review_aggregate['count'] ?? 0) > 0)
        @php
            $avg = (float) ($review_aggregate['average'] ?? 0);
            $cnt = (int) ($review_aggregate['count'] ?? 0);
            $distribution = (array) ($review_aggregate['distribution'] ?? []);
            $maxBar = max(1, ...array_map('intval', $distribution));
            $fullStars = (int) floor($avg);
            $hasHalf = ($avg - $fullStars) >= 0.25 && ($avg - $fullStars) < 0.75;
            $roundedFull = ($avg - $fullStars) >= 0.75 ? $fullStars + 1 : $fullStars;
            $starString = str_repeat('★', $roundedFull).str_repeat('☆', max(0, 5 - $roundedFull));
        @endphp
        <section class="section">
            <div class="container">
                <h2>{{ __('public/transport_review.section.title') }}</h2>
                <div class="reviews-summary">
                    <div class="reviews-avg">{{ number_format($avg, 1, ',', ' ') }}</div>
                    <div>
                        <div class="reviews-stars-big" aria-label="{{ $avg }} / 5">{{ $starString }}</div>
                        <div class="reviews-count">
                            {{ trans_choice('public/transport_review.section.count', $cnt, ['count' => $cnt]) }}
                        </div>
                    </div>
                </div>

                <div class="reviews-dist" role="list" aria-label="{{ __('public/transport_review.section.distribution_label') }}">
                    @for ($s = 5; $s >= 1; $s--)
                        @php $n = (int) ($distribution[$s] ?? 0); $pct = $maxBar > 0 ? (int) round(100 * $n / $maxBar) : 0; @endphp
                        <div class="reviews-dist-row" role="listitem">
                            <span>{{ $s }} ★</span>
                            <span class="reviews-dist-bar" aria-hidden="true">
                                <span class="reviews-dist-bar-fill" style="width: {{ $pct }}%; display:block;"></span>
                            </span>
                            <span style="text-align:right;">{{ $n }}</span>
                        </div>
                    @endfor
                </div>

                @foreach (($latest_reviews ?? []) as $rv)
                    <article class="review">
                        <div class="review-head">
                            <span class="review-stars" aria-label="{{ $rv['rating'] }} / 5">
                                {{ str_repeat('★', (int) $rv['rating']) }}{{ str_repeat('☆', max(0, 5 - (int) $rv['rating'])) }}
                            </span>
                            <span class="review-author">{{ $rv['customer'] }}</span>
                            @if ($rv['submitted_at'])
                                <span class="review-date">·&nbsp;{{ $rv['submitted_at']->format('Y-m-d') }}</span>
                            @endif
                            <span class="review-verified">{{ __('public/transport_review.section.verified_badge') }}</span>
                        </div>
                        @if (! empty($rv['comment']))
                            <div class="review-comment">{{ $rv['comment'] }}</div>
                        @endif
                        @if (! empty($rv['transporter_response']))
                            <div class="review-response">
                                <div class="review-response-label">
                                    {{ __('public/transport_review.section.response_label', ['transporter' => $tenant->name]) }}
                                </div>
                                <div>{{ $rv['transporter_response'] }}</div>
                            </div>
                        @endif
                    </article>
                @endforeach
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
        <a href="{{ route('public.transport.inquiry', ['transporter' => $tenant->slug]) }}" class="cta">
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
