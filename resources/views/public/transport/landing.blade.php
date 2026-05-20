<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('public/transport_landing.meta_title') }}</title>
    <meta name="description" content="{{ __('public/transport_landing.meta_description') }}">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="{{ url('/transport') }}">
    <meta property="og:title" content="{{ __('public/transport_landing.hero.title') }}">
    <meta property="og:description" content="{{ __('public/transport_landing.meta_description') }}">
    <meta property="og:type" content="website">
    <link rel="icon" href="{{ asset('favicon.svg') }}">
    <style>
        :root { --primary: #A8956B; --bg: #F7F4EF; --text: #1F1A17; --muted: #6b7280; --border: #d4cdb8; --card: #fff; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: var(--bg); color: var(--text); line-height: 1.55; }
        a { color: var(--primary); }

        .hero { background: linear-gradient(135deg, var(--primary) 0%, color-mix(in srgb, var(--primary) 70%, #000) 100%); color: #fff; padding: 3.5rem 1.25rem 4.5rem; text-align: center; }
        .hero-inner { max-width: 880px; margin: 0 auto; }
        .hero .logo { font-size: 1.15rem; font-weight: 700; margin-bottom: 1.5rem; letter-spacing: .04em; text-transform: lowercase; opacity: .92; }
        .hero h1 { margin: 0 0 1rem; font-size: 2.3rem; letter-spacing: -.01em; line-height: 1.2; }
        .hero .subtitle { font-size: 1.1rem; opacity: .94; margin: 0 auto 1.75rem; max-width: 680px; }
        .hero-ctas { display: flex; gap: .75rem; justify-content: center; flex-wrap: wrap; }
        .hero-ctas a { display: inline-block; padding: .85rem 1.4rem; border-radius: 10px; font-weight: 700; text-decoration: none; font-size: .98rem; }
        .hero-ctas .cta-primary { background: #fff; color: var(--primary); box-shadow: 0 6px 20px rgba(0,0,0,.15); }
        .hero-ctas .cta-primary:hover { background: #f7f0e0; }
        .hero-ctas .cta-secondary { background: rgba(255,255,255,.12); color: #fff; border: 1px solid rgba(255,255,255,.4); }
        .hero-ctas .cta-secondary:hover { background: rgba(255,255,255,.2); }

        .section { padding: 3rem 1.25rem; }
        .section-inner { max-width: 1080px; margin: 0 auto; }
        .section-title { margin: 0 0 .5rem; font-size: 1.65rem; color: #3D2E22; }
        .section-subtitle { color: var(--muted); margin: 0 0 2rem; max-width: 720px; }

        .inquiry-section { background: var(--bg); padding: 3rem 1.25rem 4rem; }
        .inquiry-card { max-width: 680px; margin: -3rem auto 0; background: #fff; border-radius: 16px; padding: 2rem; box-shadow: 0 10px 30px rgba(0,0,0,.08); position: relative; z-index: 2; }
        .inquiry-card h2 { margin: 0 0 .35rem; font-size: 1.45rem; color: #3D2E22; }
        .inquiry-card .subtitle { color: var(--muted); margin-bottom: 1.5rem; font-size: .92rem; }

        /* Form styling — reuse z inquiry.blade. */
        .form-row { display: flex; flex-direction: column; gap: .35rem; margin-bottom: 1rem; }
        .row-two { display: grid; gap: 1rem; grid-template-columns: 1fr 1fr; }
        label { font-weight: 600; font-size: .88rem; color: #3D2E22; }
        input[type=text], input[type=email], input[type=tel], input[type=number], input[type=date], input[type=time], textarea { padding: .65rem .85rem; border: 1px solid var(--border); border-radius: 8px; font: inherit; background: #fff; color: var(--text); width: 100%; }
        textarea { min-height: 80px; resize: vertical; }
        input:focus, textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 25%, transparent); }
        .checkbox { display: flex; gap: .5rem; align-items: flex-start; margin: 1rem 0; font-size: .85rem; }
        button[type=submit] { width: 100%; padding: .9rem 1.2rem; background: var(--primary); color: #fff; border: 0; border-radius: 8px; font-weight: 700; font-size: 1rem; cursor: pointer; }
        button[type=submit]:hover { filter: brightness(0.95); }
        .errors { background: #fef2f2; color: #991b1b; padding: .75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: .88rem; }
        .errors ul { margin: .25rem 0 0; padding-left: 1.25rem; }

        /* 3 paths section — pomiędzy inquiry form a top carriers. */
        .paths-section { background: #fff; padding: 3rem 1.25rem 2.5rem; }
        .paths-grid { display: grid; gap: 1.1rem; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); max-width: 1080px; margin: 1.5rem auto 0; }
        .path-card { background: var(--bg); border-radius: 14px; padding: 1.5rem 1.4rem; display: flex; flex-direction: column; border: 1px solid transparent; transition: border-color .15s, transform .15s; position: relative; }
        .path-card:hover { border-color: var(--primary); transform: translateY(-2px); }
        .path-card .tag { position: absolute; top: 1rem; right: 1rem; font-size: .7rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; padding: .15rem .55rem; border-radius: 999px; background: color-mix(in srgb, var(--primary) 15%, transparent); color: color-mix(in srgb, var(--primary) 75%, #000); }
        .path-card.recommended .tag { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #fff; }
        .path-card .icon { width: 44px; height: 44px; border-radius: 10px; background: color-mix(in srgb, var(--primary) 18%, #fff); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.4rem; margin-bottom: 1rem; }
        .path-card h3 { margin: 0 0 .5rem; font-size: 1.1rem; color: #3D2E22; }
        .path-card p { margin: 0 0 1.25rem; color: var(--muted); font-size: .92rem; flex-grow: 1; }
        .path-card .path-cta { display: inline-block; padding: .6rem 1.1rem; background: var(--primary); color: #fff; border-radius: 8px; font-weight: 700; font-size: .92rem; text-decoration: none; text-align: center; }
        .path-card .path-cta:hover { filter: brightness(0.95); }

        .top-section { background: var(--bg); }
        .top-grid { display: grid; gap: 1.1rem; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); }
        .card { background: var(--card); border-radius: 14px; padding: 1.2rem 1.25rem; box-shadow: 0 3px 14px rgba(0,0,0,.05); display: flex; flex-direction: column; min-height: 220px; border: 1px solid transparent; transition: border-color .15s, transform .15s; text-decoration: none; color: inherit; }
        .card:hover { border-color: var(--primary); transform: translateY(-2px); }
        .card-rank { font-size: .75rem; color: var(--muted); font-weight: 700; letter-spacing: .08em; text-transform: uppercase; margin-bottom: .35rem; }
        .card-head { display: flex; gap: .9rem; align-items: center; margin-bottom: .65rem; }
        .card-logo { width: 56px; height: 56px; border-radius: 10px; object-fit: contain; background: #eee; padding: 4px; flex-shrink: 0; }
        .card-logo-placeholder { width: 56px; height: 56px; border-radius: 10px; background: color-mix(in srgb, var(--primary) 18%, #fff); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.4rem; font-weight: 700; flex-shrink: 0; }
        .card h3 { margin: 0; font-size: 1.05rem; color: #3D2E22; line-height: 1.25; }
        .card .voiv-pill { display: inline-block; margin-top: .25rem; padding: .12rem .55rem; border-radius: 999px; font-size: .72rem; font-weight: 600; background: color-mix(in srgb, var(--primary) 15%, transparent); color: color-mix(in srgb, var(--primary) 80%, #000); }
        .card .featured-badge { display: inline-block; margin-left: .35rem; padding: .12rem .55rem; border-radius: 999px; font-size: .7rem; font-weight: 700; background: linear-gradient(135deg, #f5c542 0%, #d99e1f 100%); color: #5b3a00; letter-spacing: .02em; }
        .card .tagline { color: var(--muted); font-size: .88rem; margin: .35rem 0 .65rem; flex-grow: 1; }
        .card .rating-row { display: flex; align-items: center; gap: .4rem; font-size: .86rem; margin-bottom: .35rem; }
        .card .rating-stars { color: var(--primary); font-weight: 700; letter-spacing: .04em; }
        .card .rating-num { color: var(--muted); }
        .card .rating-empty { color: var(--muted); font-size: .82rem; font-style: italic; margin-bottom: .35rem; }
        .card .view-link { display: inline-block; margin-top: .75rem; color: var(--primary); font-weight: 700; font-size: .92rem; }

        .empty-state { background: var(--bg); border: 1px dashed var(--border); border-radius: 14px; padding: 2.5rem 1.5rem; text-align: center; color: var(--muted); }

        .browse-all-wrap { text-align: center; margin-top: 2rem; }
        .browse-all { display: inline-block; padding: .85rem 1.6rem; background: var(--primary); color: #fff; border-radius: 10px; font-weight: 700; text-decoration: none; box-shadow: 0 6px 20px rgba(168,149,107,.3); }
        .browse-all:hover { filter: brightness(0.95); }

        .footer-disclaimer { background: var(--bg); padding: 2rem 1.25rem; text-align: center; color: var(--muted); font-size: .82rem; line-height: 1.6; }
        .footer-disclaimer-inner { max-width: 720px; margin: 0 auto; }

        @media (prefers-color-scheme: dark) {
            html, body { background: #1F1A17; color: #F7F4EF; }
            .inquiry-card, .card, .top-section, .paths-section { background: #2a221c; color: #E9E2D3; }
            .inquiry-card h2, .card h3, .path-card h3, .section-title { color: #E9E2D3; }
            label { color: #E9E2D3; }
            input, textarea { background: #1F1A17; border-color: #4a3d31; color: #F7F4EF; }
            .footer-disclaimer, .inquiry-section, .empty-state, .top-section { background: #1F1A17; }
            .path-card { background: #1F1A17; }
            .path-card p { color: #C8B8A4; }
        }

        @media (max-width: 600px) {
            .hero { padding: 2.5rem 1rem 3.5rem; }
            .hero h1 { font-size: 1.65rem; }
            .hero .subtitle { font-size: .98rem; }
            .hero-ctas a { width: 100%; }
            .inquiry-card { padding: 1.25rem 1rem; margin-top: -2.5rem; }
            .inquiry-card h2 { font-size: 1.2rem; }
            .row-two { grid-template-columns: 1fr; gap: 0; }
            .section { padding: 2rem 1rem; }
            .section-title { font-size: 1.3rem; }
            .top-grid { grid-template-columns: 1fr; gap: .85rem; }
            .card { min-height: 0; padding: 1rem; }
            input[type=text], input[type=email], input[type=tel], input[type=number],
            input[type=date], input[type=time], textarea {
                font-size: 16px; /* anty-zoom iOS */
            }
        }
    </style>
</head>
<body>
    <section class="hero">
        <div class="hero-inner">
            <div class="logo">hovera</div>
            <h1>{{ __('public/transport_landing.hero.title') }}</h1>
            <p class="subtitle">{{ __('public/transport_landing.hero.subtitle') }}</p>
            <div class="hero-ctas">
                <a href="#inquiry" class="cta-primary">{{ __('public/transport_landing.hero.cta_form') }}</a>
                <a href="{{ route('register.horse-owner.show') }}" class="cta-secondary">{{ __('public/transport_landing.hero.cta_account') }}</a>
                <a href="{{ url('/przewoznicy') }}" class="cta-secondary">{{ __('public/transport_landing.hero.cta_browse') }}</a>
            </div>
        </div>
    </section>

    <section class="inquiry-section" id="inquiry">
        <div class="inquiry-card">
            <h2>{{ __('public/transport_landing.inquiry_section.title') }}</h2>
            <div class="subtitle">{{ __('public/transport_landing.inquiry_section.subtitle') }}</div>

            @include('public.transport._inquiry-form', [
                'old' => $old,
                'targetTransporter' => $targetTransporter,
                'formId' => 'landing-inquiry',
            ])
        </div>
    </section>

    <section class="paths-section">
        <div class="section-inner">
            <h2 class="section-title">{{ __('public/transport_landing.paths_section.title') }}</h2>
            <p class="section-subtitle">{{ __('public/transport_landing.paths_section.subtitle') }}</p>

            <div class="paths-grid">
                <div class="path-card">
                    <span class="tag">{{ __('public/transport_landing.paths_section.path_broadcast.tag') }}</span>
                    <div class="icon" aria-hidden="true">📢</div>
                    <h3>{{ __('public/transport_landing.paths_section.path_broadcast.title') }}</h3>
                    <p>{{ __('public/transport_landing.paths_section.path_broadcast.body') }}</p>
                    <a href="#inquiry" class="path-cta">{{ __('public/transport_landing.paths_section.path_broadcast.cta') }}</a>
                </div>

                <div class="path-card recommended">
                    <span class="tag">{{ __('public/transport_landing.paths_section.path_account.tag') }}</span>
                    <div class="icon" aria-hidden="true">👤</div>
                    <h3>{{ __('public/transport_landing.paths_section.path_account.title') }}</h3>
                    <p>{{ __('public/transport_landing.paths_section.path_account.body') }}</p>
                    <a href="{{ route('register.horse-owner.show') }}" class="path-cta">{{ __('public/transport_landing.paths_section.path_account.cta') }}</a>
                </div>

                <div class="path-card">
                    <span class="tag">{{ __('public/transport_landing.paths_section.path_directory.tag') }}</span>
                    <div class="icon" aria-hidden="true">🔍</div>
                    <h3>{{ __('public/transport_landing.paths_section.path_directory.title') }}</h3>
                    <p>{{ __('public/transport_landing.paths_section.path_directory.body') }}</p>
                    <a href="{{ url('/przewoznicy') }}" class="path-cta">{{ __('public/transport_landing.paths_section.path_directory.cta') }}</a>
                </div>
            </div>
        </div>
    </section>

    <section class="section top-section">
        <div class="section-inner">
            <h2 class="section-title">{{ __('public/transport_landing.top_section.title') }}</h2>
            <p class="section-subtitle">{{ __('public/transport_landing.top_section.subtitle') }}</p>

            @if ($topTransporters->isEmpty())
                <div class="empty-state">
                    {{ __('public/transport_landing.top_section.empty_state') }}
                </div>
            @else
                <div class="top-grid">
                    @foreach ($topTransporters as $idx => $tenant)
                        @php
                            $logo = (string) (($tenant->branding['logo_url'] ?? '') ?: '');
                            $tagline = (string) (($tenant->settings['public_profile']['tagline'] ?? '') ?: '');
                            $avg = (float) ($tenant->review_average ?? 0);
                            $cnt = (int) ($tenant->review_count ?? 0);
                            $voiv = $tenant->primary_voivodeship ?? null;
                            $stars = $cnt > 0 ? str_repeat('★', (int) round($avg)).str_repeat('☆', max(0, 5 - (int) round($avg))) : '';
                            $initial = mb_strtoupper(mb_substr((string) $tenant->name, 0, 1));
                        @endphp
                        <a href="{{ route('public.transporter', ['slug' => $tenant->slug]) }}" class="card">
                            <div class="card-rank">#{{ $idx + 1 }}</div>
                            <div class="card-head">
                                @if ($logo !== '')
                                    <img src="{{ $logo }}" alt="{{ $tenant->name }}" class="card-logo" loading="lazy">
                                @else
                                    <div class="card-logo-placeholder" aria-hidden="true">{{ $initial }}</div>
                                @endif
                                <div>
                                    <h3>{{ $tenant->name }}@if ($tenant->is_featured)<span class="featured-badge">{{ __('public/transport_landing.top_section.featured_badge') }}</span>@endif</h3>
                                    @if ($voiv)
                                        <span class="voiv-pill">{{ $voiv }}</span>
                                    @endif
                                </div>
                            </div>

                            @if ($tagline !== '')
                                <div class="tagline">{{ \Illuminate\Support\Str::limit($tagline, 110) }}</div>
                            @endif

                            @if ($cnt > 0)
                                <div class="rating-row">
                                    <span class="rating-stars" aria-label="{{ $avg }} / 5">{{ $stars }}</span>
                                    <span class="rating-num">{{ __('public/transport_landing.top_section.rating_avg', ['avg' => number_format($avg, 1, ',', ' '), 'count' => $cnt]) }}</span>
                                </div>
                            @else
                                <div class="rating-empty">{{ __('public/transport_landing.top_section.rating_empty') }}</div>
                            @endif

                            <span class="view-link">{{ __('public/transport_landing.top_section.view_profile') }}</span>
                        </a>
                    @endforeach
                </div>

                <div class="browse-all-wrap">
                    <a href="{{ url('/przewoznicy') }}" class="browse-all">{{ __('public/transport_landing.top_section.browse_all') }}</a>
                </div>
            @endif
        </div>
    </section>

    <section class="footer-disclaimer">
        <div class="footer-disclaimer-inner">
            {{ __('public/transport_landing.disclaimer') }}
        </div>
    </section>
</body>
</html>
