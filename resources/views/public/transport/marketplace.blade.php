<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('public/transport_marketplace.meta_title') }}</title>
    <meta name="description" content="{{ __('public/transport_marketplace.meta_description') }}">
    {{-- Marketplace zmienia się dynamicznie wraz z napływem leadów —
         nie chcemy by Google indeksował konkretne snapshoty list. --}}
    <meta name="robots" content="noindex, follow">
    <link rel="canonical" href="{{ url('/transport/marketplace') }}">
    <link rel="icon" href="{{ asset('favicon.svg') }}">
    <style>
        :root { --primary: #A8956B; --bg: #F7F4EF; --text: #1F1A17; --muted: #6b7280; --border: #d4cdb8; --card: #fff; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: var(--bg); color: var(--text); }
        body { line-height: 1.55; }
        a { color: var(--primary); }
        .hero { background: linear-gradient(135deg, var(--primary) 0%, color-mix(in srgb, var(--primary) 70%, #000) 100%); color: #fff; padding: 2.5rem 1.25rem 2rem; text-align: center; }
        .hero-inner { max-width: 1080px; margin: 0 auto; }
        .hero h1 { margin: 0 0 .5rem; font-size: 1.9rem; letter-spacing: -.01em; }
        .hero .subtitle { font-size: 1rem; opacity: .92; margin: 0; }
        .container { max-width: 1080px; margin: 0 auto; padding: 0 1.25rem; }
        .filters { background: var(--bg); border-bottom: 1px solid var(--border); padding: .9rem 1.25rem; position: sticky; top: 0; z-index: 20; }
        .filters-inner { max-width: 1080px; margin: 0 auto; display: grid; gap: .65rem; grid-template-columns: 2fr 1fr 1fr auto; align-items: end; }
        .filters .field { display: flex; flex-direction: column; gap: .25rem; }
        .filters label { font-size: .76rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; }
        .filters select { padding: .55rem .75rem; border: 1px solid var(--border); border-radius: 8px; background: var(--card); color: var(--text); font: inherit; width: 100%; }
        .filters select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 25%, transparent); }
        .filters-buttons { display: flex; gap: .5rem; align-items: center; }
        .filters button { padding: .6rem 1.1rem; background: var(--primary); color: #fff; border: 0; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: .9rem; }
        .filters button:hover { filter: brightness(0.95); }
        .filters .clear-link { color: var(--muted); text-decoration: none; font-size: .82rem; }
        .filters .clear-link:hover { color: var(--text); text-decoration: underline; }
        @media (max-width: 720px) {
            .filters-inner { grid-template-columns: 1fr; }
        }
        .section { padding: 1.75rem 0 3rem; }
        .results-meta { color: var(--muted); font-size: .9rem; margin: 0 0 1rem; }
        .grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); }
        .card { background: var(--card); border-radius: 14px; padding: 1.1rem 1.2rem; box-shadow: 0 3px 14px rgba(0,0,0,.05); display: flex; flex-direction: column; gap: .6rem; border: 1px solid transparent; transition: border-color .15s, transform .15s; }
        .card:hover { border-color: var(--primary); transform: translateY(-2px); }
        .card .route { font-size: 1rem; font-weight: 700; color: #3D2E22; line-height: 1.3; display: flex; flex-wrap: wrap; align-items: center; gap: .35rem; }
        .card .route .arrow { color: var(--primary); }
        .card .meta-row { display: flex; flex-wrap: wrap; gap: .4rem; align-items: center; }
        .card .pill { display: inline-block; padding: .15rem .65rem; border-radius: 999px; font-size: .75rem; font-weight: 600; background: color-mix(in srgb, var(--primary) 14%, transparent); color: color-mix(in srgb, var(--primary) 80%, #000); }
        .card .pill.urgent { background: #fef3c7; color: #92400e; }
        .card .date-line { font-size: .9rem; color: var(--text); }
        .card .date-line strong { color: #3D2E22; }
        .card .ctas { display: flex; gap: .5rem; margin-top: .5rem; flex-wrap: wrap; }
        .card .cta-primary { display: inline-block; padding: .55rem 1rem; background: var(--primary); color: #fff; border-radius: 8px; font-weight: 700; font-size: .9rem; text-decoration: none; border: 0; cursor: pointer; font-family: inherit; }
        .card .cta-primary:hover { filter: brightness(0.95); }
        .card .cta-secondary { display: inline-block; padding: .55rem 1rem; background: transparent; color: var(--primary); border: 1px solid color-mix(in srgb, var(--primary) 50%, transparent); border-radius: 8px; font-weight: 600; font-size: .88rem; text-decoration: none; }
        .card .cta-secondary:hover { background: color-mix(in srgb, var(--primary) 10%, transparent); }
        .card .privacy-note { color: var(--muted); font-size: .72rem; line-height: 1.4; }
        .empty { background: var(--card); border-radius: 14px; padding: 2.5rem 1.5rem; text-align: center; color: var(--muted); }
        .empty h2 { color: #3D2E22; font-size: 1.15rem; margin: 0 0 .5rem; }
        .empty a { display: inline-block; margin-top: .75rem; padding: .55rem 1rem; background: var(--primary); color: #fff; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: .9rem; }
        .pagination-wrap { margin-top: 2rem; display: flex; justify-content: center; }
        .pagination-wrap nav { display: flex; gap: .25rem; flex-wrap: wrap; justify-content: center; }
        .pagination-wrap a, .pagination-wrap span { padding: .4rem .7rem; border-radius: 6px; text-decoration: none; color: var(--text); font-size: .88rem; }
        .pagination-wrap a { background: var(--card); border: 1px solid var(--border); }
        .pagination-wrap a:hover { border-color: var(--primary); }
        .pagination-wrap .current { background: var(--primary); color: #fff; }
        .info-banner { background: #ecfdf5; border-bottom: 1px solid #86efac; padding: .65rem 1.25rem; text-align: center; color: #166534; font-size: .88rem; font-weight: 600; }
        .footer-strip { text-align: center; padding: 1.5rem 1.25rem; color: var(--muted); font-size: .85rem; }
        .footer-strip a { color: var(--primary); text-decoration: none; font-weight: 600; }
        .footer-strip a:hover { text-decoration: underline; }
        html { color-scheme: light; }
        @media (max-width: 600px) {
            .hero { padding: 2rem 1rem 1.5rem; }
            .hero h1 { font-size: 1.45rem; }
            .filters { padding: .75rem 1rem; }
            .container { padding: 0 1rem; }
            .grid { grid-template-columns: 1fr; gap: .85rem; }
            .filters select { font-size: 16px; /* anty-zoom iOS */ }
        }
    </style>
</head>
<body>
    <div class="info-banner">
        {{ __('public/transport_marketplace.banner') }}
    </div>

    @if (session('error'))
        <div style="background: #fee2e2; border-bottom: 1px solid #fca5a5; padding: .75rem 1.25rem; text-align: center; color: #991b1b; font-size: .9rem; font-weight: 600;">
            {{ session('error') }}
        </div>
    @endif

    <section class="hero">
        <div class="hero-inner">
            <h1>{{ __('public/transport_marketplace.hero_title') }}</h1>
            <p class="subtitle">{{ __('public/transport_marketplace.hero_subtitle') }}</p>
        </div>
    </section>

    <form class="filters" method="get" action="{{ route('public.transport.marketplace') }}">
        <div class="filters-inner">
            <div class="field">
                <label for="voivodeship">{{ __('public/transport_marketplace.filter.voivodeship_label') }}</label>
                <select name="voivodeship" id="voivodeship">
                    <option value="">{{ __('public/transport_marketplace.filter.voivodeship_all') }}</option>
                    @foreach ($voivodeships as $v)
                        <option value="{{ $v }}" @selected($v === $selectedVoivodeship)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="within_days">{{ __('public/transport_marketplace.filter.within_days_label') }}</label>
                <select name="within_days" id="within_days">
                    <option value="">{{ __('public/transport_marketplace.filter.within_days_any') }}</option>
                    @foreach ($allowedWithinDays as $d)
                        <option value="{{ $d }}" @selected($d === $withinDays)>{{ __('public/transport_marketplace.filter.within_days_option', ['days' => $d]) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="min_horses">{{ __('public/transport_marketplace.filter.min_horses_label') }}</label>
                <select name="min_horses" id="min_horses">
                    <option value="">{{ __('public/transport_marketplace.filter.min_horses_any') }}</option>
                    @foreach ($allowedMinHorses as $n)
                        <option value="{{ $n }}" @selected($n === $minHorses)>{{ __('public/transport_marketplace.filter.min_horses_option', ['count' => $n]) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filters-buttons">
                <button type="submit">{{ __('public/transport_marketplace.filter.apply') }}</button>
                @if (! empty($filterQuery))
                    <a href="{{ route('public.transport.marketplace') }}" class="clear-link">{{ __('public/transport_marketplace.filter.clear') }}</a>
                @endif
            </div>
        </div>
    </form>

    <section class="section">
        <div class="container">
            <p class="results-meta">
                {{ trans_choice('public/transport_marketplace.results_meta', $leads->total(), ['count' => $leads->total()]) }}
            </p>

            @if ($leads->isEmpty())
                <div class="empty">
                    <h2>{{ __('public/transport_marketplace.empty.heading') }}</h2>
                    <p>{{ __('public/transport_marketplace.empty.description') }}</p>
                    <a href="{{ route('public.transporters.directory') }}">{{ __('public/transport_marketplace.empty.cta') }}</a>
                </div>
            @else
                <div class="grid">
                    @foreach ($leads as $lead)
                        <article class="card">
                            <div class="route">
                                <span>{{ $lead->pickup_voivodeship !== '' ? $lead->pickup_voivodeship : __('public/transport_marketplace.unknown_voivodeship') }}</span>
                                <span class="arrow">→</span>
                                <span>{{ $lead->dropoff_voivodeship !== '' ? $lead->dropoff_voivodeship : __('public/transport_marketplace.unknown_voivodeship') }}</span>
                            </div>
                            <div class="meta-row">
                                <span class="pill">{{ trans_choice('public/transport_marketplace.horse_count', (int) $lead->horse_count, ['count' => (int) $lead->horse_count]) }}</span>
                                @if (\Carbon\Carbon::parse($lead->preferred_date)->isBefore(now()->addDays(3)))
                                    <span class="pill urgent">{{ __('public/transport_marketplace.urgent_pill') }}</span>
                                @endif
                            </div>
                            <div class="date-line">
                                <strong>{{ \Carbon\Carbon::parse($lead->preferred_date)->translatedFormat('d.m.Y') }}</strong>
                                @if ($lead->preferred_time)
                                    · {{ substr((string) $lead->preferred_time, 0, 5) }}
                                @endif
                            </div>
                            <div class="privacy-note">
                                {{ __('public/transport_marketplace.privacy_note') }}
                            </div>
                            <form class="ctas" method="post" action="{{ route('public.transport.marketplace.claim', ['lead' => $lead->id]) }}">
                                @csrf
                                <button type="submit" class="cta-primary">
                                    {{ __('public/transport_marketplace.submit_quote_cta') }}
                                </button>
                            </form>
                        </article>
                    @endforeach
                </div>

                @if ($leads->hasPages())
                    <div class="pagination-wrap">
                        {{ $leads->appends($filterQuery)->links() }}
                    </div>
                @endif
            @endif
        </div>
    </section>

    <div class="footer-strip">
        <a href="{{ route('public.transport.landing') }}">{{ __('public/transport_marketplace.back_to_landing') }}</a>
    </div>
</body>
</html>
