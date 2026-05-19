<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('public/transporter_directory.meta_title', ['count' => $totalVerifiedCount]) }}</title>
    <meta name="description" content="{{ __('public/transporter_directory.meta_description') }}">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="{{ url('/przewoznicy') }}">
    <meta property="og:title" content="{{ __('public/transporter_directory.hero_title') }}">
    <meta property="og:description" content="{{ __('public/transporter_directory.meta_description') }}">
    <meta property="og:type" content="website">
    <link rel="icon" href="{{ asset('favicon.svg') }}">
    <style>
        :root { --primary: #A8956B; --bg: #F7F4EF; --text: #1F1A17; --muted: #6b7280; --border: #d4cdb8; --card: #fff; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: var(--bg); color: var(--text); }
        body { padding: 0; line-height: 1.55; }
        a { color: var(--primary); }
        .hero { background: linear-gradient(135deg, var(--primary) 0%, color-mix(in srgb, var(--primary) 70%, #000) 100%); color: #fff; padding: 3rem 1.25rem 2rem; text-align: center; }
        .hero-inner { max-width: 1080px; margin: 0 auto; }
        .hero h1 { margin: 0 0 .5rem; font-size: 2.1rem; letter-spacing: -.01em; }
        .hero .subtitle { font-size: 1.02rem; opacity: .92; margin: 0 0 1.5rem; }
        .hero-cta { display: inline-block; padding: .8rem 1.6rem; background: #fff; color: var(--primary); border-radius: 10px; font-weight: 700; text-decoration: none; box-shadow: 0 4px 14px rgba(0,0,0,.15); transition: transform .15s; }
        .hero-cta:hover { transform: translateY(-2px); }
        .container { max-width: 1080px; margin: 0 auto; padding: 0 1.25rem; }
        .filters { position: sticky; top: 0; z-index: 20; background: var(--bg); border-bottom: 1px solid var(--border); padding: .9rem 1.25rem; }
        .filters-inner { max-width: 1080px; margin: 0 auto; display: grid; gap: .65rem; grid-template-columns: 1fr 1fr 1fr auto; align-items: end; }
        .filters .field { display: flex; flex-direction: column; gap: .25rem; }
        .filters label { font-size: .78rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; }
        .filters input, .filters select { padding: .55rem .75rem; border: 1px solid var(--border); border-radius: 8px; background: var(--card); color: var(--text); font: inherit; width: 100%; }
        .filters input:focus, .filters select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 25%, transparent); }
        .filters-buttons { display: flex; gap: .5rem; align-items: center; }
        .filters button { padding: .6rem 1.1rem; background: var(--primary); color: #fff; border: 0; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: .9rem; }
        .filters button:hover { filter: brightness(0.95); }
        .filters .clear-link { color: var(--muted); text-decoration: none; font-size: .82rem; }
        .filters .clear-link:hover { color: var(--text); text-decoration: underline; }
        @media (max-width: 720px) {
            .filters-inner { grid-template-columns: 1fr; }
        }
        .section { padding: 2rem 0 3rem; }
        .results-meta { color: var(--muted); font-size: .9rem; margin: .25rem 0 1.25rem; }
        .grid { display: grid; gap: 1.1rem; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); }
        .card { background: var(--card); border-radius: 14px; padding: 1.2rem 1.25rem; box-shadow: 0 3px 14px rgba(0,0,0,.05); display: flex; flex-direction: column; min-height: 220px; border: 1px solid transparent; transition: border-color .15s, transform .15s; }
        .card:hover { border-color: var(--primary); transform: translateY(-2px); }
        .card-head { display: flex; gap: .9rem; align-items: center; margin-bottom: .65rem; }
        .card-logo { width: 56px; height: 56px; border-radius: 10px; object-fit: contain; background: #eee; padding: 4px; flex-shrink: 0; }
        .card-logo-placeholder { width: 56px; height: 56px; border-radius: 10px; background: color-mix(in srgb, var(--primary) 18%, #fff); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.4rem; font-weight: 700; flex-shrink: 0; }
        .card h3 { margin: 0; font-size: 1.05rem; color: #3D2E22; line-height: 1.25; }
        .card .voiv-pill { display: inline-block; padding: .12rem .55rem; border-radius: 999px; font-size: .72rem; font-weight: 600; background: color-mix(in srgb, var(--primary) 15%, transparent); color: color-mix(in srgb, var(--primary) 80%, #000); }
        .card .voivs { display: flex; flex-wrap: wrap; gap: .25rem; margin-top: .3rem; }
        .card .voiv-more { display: inline-block; padding: .12rem .5rem; border-radius: 999px; font-size: .7rem; font-weight: 600; background: color-mix(in srgb, var(--primary) 8%, transparent); color: var(--muted); }
        .card .featured-badge { display: inline-block; margin-left: .35rem; padding: .12rem .55rem; border-radius: 999px; font-size: .7rem; font-weight: 700; background: linear-gradient(135deg, #f5c542 0%, #d99e1f 50%, #f5c542 100%); background-size: 200% 100%; color: #5b3a00; letter-spacing: .02em; animation: featured-shimmer 3s ease-in-out infinite; }
        @keyframes featured-shimmer { 0%, 100% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } }
        @media (prefers-reduced-motion: reduce) { .card .featured-badge { animation: none; } }
        .card .tagline { color: var(--muted); font-size: .88rem; margin: .35rem 0 .65rem; flex-grow: 1; }
        .card .rating-row { display: flex; align-items: center; gap: .4rem; font-size: .86rem; margin-bottom: .35rem; }
        .card .rating-stars { color: var(--primary); font-weight: 700; letter-spacing: .04em; }
        .card .rating-num { color: var(--muted); }
        .card .rating-empty { color: var(--muted); font-size: .82rem; font-style: italic; margin-bottom: .35rem; }
        .card .disclaimer { color: var(--muted); font-size: .72rem; margin-top: .35rem; line-height: 1.4; }
        .card .disclaimer a { color: var(--muted); }
        .card .view-link { display: inline-block; margin-top: .75rem; color: var(--primary); text-decoration: none; font-weight: 700; font-size: .92rem; }
        .card .view-link:hover { text-decoration: underline; }
        .empty { background: var(--card); border-radius: 14px; padding: 3rem 1.5rem; text-align: center; color: var(--muted); }
        .empty h2 { color: #3D2E22; font-size: 1.2rem; margin: 0 0 .75rem; }
        .empty a { display: inline-block; margin-top: .5rem; padding: .6rem 1.1rem; background: var(--primary); color: #fff; border-radius: 8px; text-decoration: none; font-weight: 700; }
        .pagination-wrap { margin-top: 2rem; display: flex; justify-content: center; }
        .pagination-wrap nav { display: flex; gap: .25rem; flex-wrap: wrap; justify-content: center; }
        .pagination-wrap a, .pagination-wrap span { padding: .4rem .7rem; border-radius: 6px; text-decoration: none; color: var(--text); font-size: .88rem; }
        .pagination-wrap a { background: var(--card); border: 1px solid var(--border); }
        .pagination-wrap a:hover { border-color: var(--primary); }
        .pagination-wrap .current { background: var(--primary); color: #fff; }
        .cta-section { background: #fff; padding: 2.5rem 1.25rem; text-align: center; border-top: 1px solid var(--border); }
        .cta-section h2 { margin: 0 0 .5rem; color: #3D2E22; font-size: 1.4rem; }
        .cta-section p { color: var(--muted); margin: 0 0 1.25rem; }
        .cta-section .cta { display: inline-block; padding: .9rem 1.6rem; background: var(--primary); color: #fff; border-radius: 10px; font-weight: 700; text-decoration: none; box-shadow: 0 6px 20px rgba(168,149,107,.3); }
        .footer-strip { text-align: center; padding: 1.5rem 1.25rem; color: var(--muted); font-size: .85rem; background: var(--bg); }
        .footer-strip a { color: var(--primary); text-decoration: none; font-weight: 600; }
        .footer-strip a:hover { text-decoration: underline; }
        @media (prefers-color-scheme: dark) {
            html, body { background: #1F1A17; color: #F7F4EF; }
            .card, .cta-section, .empty { background: #2a221c; color: #E9E2D3; }
            .card h3, .cta-section h2, .empty h2 { color: #E9E2D3; }
            .filters { background: #1F1A17; border-color: #4a3d31; }
            .filters input, .filters select { background: #2a221c; border-color: #4a3d31; color: #F7F4EF; }
            .pagination-wrap a { background: #2a221c; border-color: #4a3d31; color: #E9E2D3; }
            .footer-strip { background: #1F1A17; }
            .footer-strip, .footer-strip a { color: #C8B8A4; }
            .footer-strip a { color: var(--primary); }
        }
        @media (max-width: 600px) {
            .hero { padding: 2rem 1rem 1.5rem; }
            .hero h1 { font-size: 1.5rem; }
            .hero .subtitle { font-size: .92rem; }
            .filters { padding: .75rem 1rem; }
            .container { padding: 0 1rem; }
            .grid { grid-template-columns: 1fr; gap: .85rem; }
            .card { min-height: 0; padding: 1rem; }
            .card-head { gap: .7rem; }
            .card-logo, .card-logo-placeholder { width: 48px; height: 48px; }
            .cta-section { padding: 2rem 1rem; }
            .cta-section h2 { font-size: 1.2rem; }
            .filters input, .filters select { font-size: 16px; /* anty-zoom iOS */ }
        }
    </style>
</head>
<body>
    <section class="hero">
        <div class="hero-inner">
            <h1>{{ __('public/transporter_directory.hero_title') }}</h1>
            <p class="subtitle">
                {{ __('public/transporter_directory.hero_subtitle', ['count' => $totalVerifiedCount]) }}
            </p>
            <a href="{{ url('/przewoznicy/dolacz') }}" class="hero-cta">
                {{ __('public/transporter_directory.hero_cta_join') }} →
            </a>
        </div>
    </section>

    {{-- Sticky filters bar. Form submit GET — przyjazne SEO i shareable URL.
         Voivodeship + sort selecty zmieniają stronę przez native onchange submit
         (bez JS framework'a) — drobne udogodnienie UX bez wpływu na boty. --}}
    <form class="filters" method="get" action="{{ url('/przewoznicy') }}" role="search">
        <div class="filters-inner">
            <div class="field">
                <label for="filter-voivodeship">{{ __('public/transporter_directory.filter_voivodeship_label') }}</label>
                <select name="voivodeship" id="filter-voivodeship" onchange="this.form.submit()">
                    <option value="">{{ __('public/transporter_directory.filter_voivodeship_all') }}</option>
                    @foreach ($voivodeships as $v)
                        <option value="{{ $v }}" @selected($selectedVoivodeship === $v)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="filter-q">{{ __('public/transporter_directory.filter_search_label') }}</label>
                <input type="search" name="q" id="filter-q" maxlength="80"
                       placeholder="{{ __('public/transporter_directory.filter_search_placeholder') }}"
                       value="{{ $query }}">
            </div>
            <div class="field">
                <label for="filter-sort">{{ __('public/transporter_directory.sort_label') }}</label>
                <select name="sort" id="filter-sort" onchange="this.form.submit()">
                    <option value="rating_desc" @selected($sort === 'rating_desc')>{{ __('public/transporter_directory.sort_rating_desc') }}</option>
                    <option value="recent" @selected($sort === 'recent')>{{ __('public/transporter_directory.sort_recent') }}</option>
                    <option value="name" @selected($sort === 'name')>{{ __('public/transporter_directory.sort_name_asc') }}</option>
                </select>
            </div>
            <div class="filters-buttons">
                <button type="submit">{{ __('public/transporter_directory.filter_apply') }}</button>
                @if ($selectedVoivodeship !== null || $query !== '' || $sort !== 'rating_desc')
                    <a href="{{ url('/przewoznicy') }}" class="clear-link">{{ __('public/transporter_directory.clear_filters') }}</a>
                @endif
            </div>
        </div>
    </form>

    <section class="section">
        <div class="container">
            @if ($transporters->isEmpty())
                <div class="empty">
                    <h2>{{ __('public/transporter_directory.empty_state_title') }}</h2>
                    <p style="margin-bottom: 1rem;">{{ __('public/transporter_directory.empty_state_subtitle') }}</p>
                    <a href="{{ url('/przewoznicy') }}">{{ __('public/transporter_directory.empty_state_action') }}</a>
                    <p style="margin: 1.25rem 0 .5rem; font-size: .85rem;">{{ __('public/transporter_directory.empty_state_transporter_hint') }}</p>
                    <a href="{{ url('/przewoznicy/dolacz') }}" style="background: transparent; color: var(--primary); border: 2px solid var(--primary);">
                        {{ __('public/transporter_directory.empty_state_transporter_cta') }} →
                    </a>
                </div>
            @else
                <p class="results-meta">
                    {{ trans_choice('public/transporter_directory.card_reviews_avg', 0, ['avg' => '', 'count' => '']) === '' ? '' : '' }}
                    {{-- Liczba wyników zostaje w hero subtitle — tutaj nic dodatkowego --}}
                </p>
                <div class="grid">
                    @foreach ($transporters as $tenant)
                        @php
                            $logo = (string) (($tenant->branding['logo_url'] ?? '') ?: '');
                            $tagline = (string) (($tenant->settings['public_profile']['tagline'] ?? '') ?: '');
                            $avg = (float) ($tenant->review_average ?? 0);
                            $cnt = (int) ($tenant->review_count ?? 0);
                            $voiv = $tenant->primary_voivodeship ?? null;
                            $stars = $cnt > 0 ? str_repeat('★', (int) round($avg)).str_repeat('☆', max(0, 5 - (int) round($avg))) : '';
                            $initial = mb_strtoupper(mb_substr((string) $tenant->name, 0, 1));
                        @endphp
                        <article class="card">
                            <div class="card-head">
                                @if ($logo !== '')
                                    <img src="{{ $logo }}" alt="{{ $tenant->name }}" class="card-logo" loading="lazy">
                                @else
                                    <div class="card-logo-placeholder" aria-hidden="true">{{ $initial }}</div>
                                @endif
                                <div style="flex-grow:1;min-width:0;">
                                    <h3>{{ $tenant->name }}@if ($tenant->is_featured)<span class="featured-badge">★ {{ __('public/transporter_directory.featured_badge') }}</span>@endif</h3>
                                    @php
                                        $allVoivs = is_array($tenant->all_voivodeships ?? null) ? $tenant->all_voivodeships : [];
                                        $shown = array_slice($allVoivs, 0, 3);
                                        $extra = max(0, count($allVoivs) - count($shown));
                                    @endphp
                                    @if ($shown !== [])
                                        <div class="voivs">
                                            @foreach ($shown as $v)
                                                <span class="voiv-pill">{{ $v }}</span>
                                            @endforeach
                                            @if ($extra > 0)
                                                <span class="voiv-more" title="{{ __('public/transporter_directory.card_voiv_more_tooltip') }}">+{{ $extra }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>

                            @if ($tagline !== '')
                                <div class="tagline">{{ \Illuminate\Support\Str::limit($tagline, 110) }}</div>
                            @else
                                <div class="tagline">{{ __('public/transporter_profile.default_tagline') }}</div>
                            @endif

                            @if ($cnt > 0)
                                <div class="rating-row">
                                    <span class="rating-stars" aria-label="{{ $avg }} / 5">{{ $stars }}</span>
                                    <span class="rating-num">
                                        {{ trans_choice('public/transporter_directory.card_reviews_avg', $cnt, [
                                            'avg' => number_format($avg, 1, ',', ' '),
                                            'count' => $cnt,
                                        ]) }}
                                    </span>
                                </div>
                            @else
                                <div class="rating-empty">{{ __('public/transporter_directory.card_reviews_count_zero') }}</div>
                            @endif

                            <a href="{{ route('public.transporter', ['slug' => $tenant->slug]) }}" class="view-link">
                                {{ __('public/transporter_directory.card_view_profile') }} →
                            </a>

                            <div class="disclaimer">
                                {{ __('public/transporter_directory.card_disclaimer_verified') }}
                                <a href="{{ route('legal.marketplace') }}" target="_blank" rel="noopener">
                                    {{ __('public/transporter_directory.card_disclaimer_link_label') }}
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>

                @if ($transporters->hasPages())
                    <div class="pagination-wrap">
                        {{ $transporters->appends($filterQuery)->onEachSide(1)->links() }}
                    </div>
                @endif
            @endif
        </div>
    </section>

    {{-- CTA dla zamawiających — alternatywa do ręcznego przeglądania. --}}
    <section class="cta-section">
        <h2>{{ __('public/transporter_directory.cta_inquiry_section_title') }}</h2>
        <p>{{ __('public/transporter_directory.cta_inquiry_section_text') }}</p>
        <a href="{{ route('public.transport.inquiry') }}" class="cta">
            {{ __('public/transporter_directory.cta_inquiry_button') }}
        </a>
    </section>

    {{-- Stopka — CTA dla transporterów + powered by. --}}
    <div class="footer-strip">
        <a href="{{ route('public.transport.onboarding.show') }}">{{ __('public/transporter_directory.footer_cta_join_marketplace') }} →</a>
    </div>
</body>
</html>
