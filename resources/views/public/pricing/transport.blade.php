<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('transport/plans.page_title') }} — hovera</title>
    <meta name="description" content="{{ __('transport/plans.meta_description') }}">
    <link rel="icon" href="{{ asset('favicon.svg') }}">
    <style>
        :root { --primary: #A8956B; --primary-dark: #8F8576; --bg: #F7F4EF; --text: #1F1A17; --brown: #3D2E22; --sand: #E9E2D3; --taupe: #C8B8A4; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: var(--bg); color: var(--text); }
        body { padding: 2rem 1rem 4rem; }
        .container { max-width: 1180px; margin: 0 auto; }
        .nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2.5rem; }
        .logo { font-size: 1.4rem; font-weight: 700; letter-spacing: -.02em; color: var(--brown); text-decoration: none; }
        .nav-links a { color: var(--brown); text-decoration: none; margin-left: 1.25rem; font-size: .92rem; }
        .nav-cta { display: inline-block; padding: .5rem 1rem; background: var(--primary); color: #fff !important; border-radius: 8px; font-weight: 600; }
        .hero { text-align: center; margin-bottom: 1.5rem; }
        h1 { margin: 0 0 .75rem; font-size: 2.2rem; font-weight: 700; letter-spacing: -.02em; color: var(--brown); }
        .lede { color: #4b5563; font-size: 1.05rem; max-width: 720px; margin: 0 auto 1rem; line-height: 1.55; }
        .lock-note { display: inline-block; background: #fef3c7; color: #78350f; padding: .35rem .85rem; border-radius: 999px; font-size: .82rem; font-weight: 600; margin: .25rem; }
        .promo-note { display: inline-block; background: #d1fae5; color: #065f46; padding: .35rem .85rem; border-radius: 999px; font-size: .82rem; font-weight: 600; margin: .25rem; }
        .currency-toggle { display: flex; justify-content: center; gap: .35rem; margin: 1.5rem 0 2rem; flex-wrap: wrap; }
        .currency-toggle a { padding: .45rem 1rem; border-radius: 999px; background: var(--sand); color: var(--brown); text-decoration: none; font-weight: 600; font-size: .9rem; border: 2px solid transparent; }
        .currency-toggle a.active { background: var(--primary); color: #fff; border-color: var(--primary); }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; margin-bottom: 3rem; }
        .plan { background: #fff; border-radius: 14px; padding: 1.75rem 1.5rem; box-shadow: 0 4px 14px rgba(0,0,0,.05); border: 2px solid transparent; display: flex; flex-direction: column; }
        .plan.featured { border-color: var(--primary); position: relative; }
        .plan.featured::before { content: "{{ __('transport/plans.most_popular') }}"; position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: var(--primary); color: #fff; padding: .25rem .85rem; border-radius: 999px; font-size: .72rem; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; }
        .plan-name { font-size: 1.2rem; font-weight: 700; color: var(--brown); margin: 0 0 .35rem; }
        .plan-audience { color: #6b7280; font-size: .85rem; min-height: 2.6em; line-height: 1.4; margin-bottom: 1rem; }
        .price-block { margin-bottom: 1.25rem; }
        .price { font-size: 2.1rem; font-weight: 700; color: var(--brown); letter-spacing: -.02em; }
        .price-suffix { color: #6b7280; font-size: .85rem; margin-left: .15rem; }
        .price-custom { font-size: 1.4rem; color: var(--brown); font-weight: 600; }
        .price-yearly-note { font-size: .78rem; color: #6b7280; margin-top: .3rem; min-height: 1.2em; }
        .features { list-style: none; padding: 0; margin: 0 0 1.25rem; flex: 1; }
        .features li { padding: .35rem 0 .35rem 1.5rem; position: relative; font-size: .88rem; color: #3D2E22; line-height: 1.45; }
        .features li::before { content: "✓"; position: absolute; left: 0; color: var(--primary); font-weight: 700; }
        .cta { display: block; width: 100%; padding: .8rem 1rem; background: var(--primary); color: #fff; text-align: center; text-decoration: none; border-radius: 8px; font-weight: 700; }
        .cta.secondary { background: transparent; color: var(--primary); border: 2px solid var(--primary); }
        h2 { font-size: 1.6rem; color: var(--brown); margin: 3rem 0 .5rem; text-align: center; }
        .h2-sub { text-align: center; color: #6b7280; margin-bottom: 2rem; }
        .addons-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 14px; box-shadow: 0 4px 14px rgba(0,0,0,.05); }
        .addons-table { width: 100%; min-width: 520px; border-collapse: collapse; background: #fff; overflow: hidden; }
        .addons-table th, .addons-table td { padding: .85rem 1rem; text-align: left; border-bottom: 1px solid var(--sand); font-size: .9rem; }
        .addons-table th { color: var(--brown); font-weight: 700; background: var(--bg); }
        .addons-table tr:last-child td { border-bottom: 0; }
        .addons-table .price-col { text-align: right; font-weight: 600; color: var(--brown); white-space: nowrap; }
        .addons-table .type-col { color: #6b7280; font-size: .82rem; }
        .footer { text-align: center; margin-top: 3rem; color: #6b7280; font-size: .85rem; }
        .footer a { color: var(--primary); text-decoration: none; margin: 0 .5rem; }
        @media (max-width: 720px) {
            h1 { font-size: 1.6rem; }
            .nav-links a { display: none; }
            .nav-links .nav-cta { display: inline-block; }
        }
        @media (max-width: 600px) {
            body { padding: 1.25rem .75rem 3rem; }
            .nav { margin-bottom: 1.5rem; }
            .hero h1 { font-size: 1.45rem; }
            .lede { font-size: .95rem; }
            .grid { grid-template-columns: 1fr; gap: 1rem; }
            .plan { padding: 1.4rem 1.2rem; }
            .price { font-size: 1.8rem; }
            h2 { font-size: 1.3rem; margin-top: 2rem; }
            .addons-table th, .addons-table td { padding: .65rem .75rem; font-size: .85rem; }
        }
    </style>
    <x-google-analytics />
</head>
<body>
    <div class="container">
        <div class="nav">
            <a href="{{ url('/') }}" class="logo">hovera</a>
            <div class="nav-links">
                <a href="{{ url('/pricing') }}">{{ __('transport/plans.nav.stable_pricing') }}</a>
                <a href="{{ route('signup.show') }}" class="nav-cta">{{ __('transport/plans.nav.signup') }}</a>
            </div>
        </div>

        <div class="hero">
            <h1>{{ __('transport/plans.heading') }}</h1>
            <p class="lede">{{ __('transport/plans.lede') }}</p>
            <div>
                <span class="lock-note">{{ __('transport/plans.lock_in_note') }}</span>
                <span class="promo-note">{{ __('transport/plans.promo_note') }}</span>
            </div>
        </div>

        <div class="currency-toggle" role="tablist" aria-label="{{ __('transport/plans.currency_label') }}">
            @foreach ($allCurrencies as $cur)
                <a href="{{ route('pricing.transport', ['currency' => $cur]) }}"
                   class="{{ $cur === $currency ? 'active' : '' }}"
                   role="tab"
                   aria-selected="{{ $cur === $currency ? 'true' : 'false' }}">{{ $cur }}</a>
            @endforeach
        </div>

        <div class="grid">
            @foreach ($plans as $plan)
                @php
                    $isMostPopular = data_get($plan->features, 'highlight') === 'most_popular';
                    $isCustom = ! empty(data_get($plan->features, 'is_custom_pricing'))
                                || data_get($plan->features, 'marketing_cta') === 'contact_sales';
                    $monthlyCents = $isCustom ? null : $plan->priceFor($currency, 'monthly');
                    $monthlyMajor = $monthlyCents !== null ? (int) round($monthlyCents / 100) : null;
                    $audienceHint = data_get($plan->features, 'audience_hint', '');
                    $bullets = (array) data_get($plan->features, 'bullets', []);
                @endphp
                <div class="plan @if ($isMostPopular) featured @endif">
                    <h3 class="plan-name">{{ $plan->name }}</h3>
                    <div class="plan-audience">{{ __('transport/plans.audience_hint.'.($audienceHint ?: 'default')) }}</div>

                    <div class="price-block">
                        @if ($isCustom)
                            <div class="price-custom">{{ __('transport/plans.custom_price') }}</div>
                            <div class="price-yearly-note">{{ __('transport/plans.custom_price_note') }}</div>
                        @elseif ($monthlyMajor === null)
                            <div class="price-custom">—</div>
                            <div class="price-yearly-note">{{ __('transport/plans.price_unavailable', ['currency' => $currency]) }}</div>
                        @else
                            <span class="price">{{ number_format($monthlyMajor, 0, ',', ' ') }}</span>
                            <span class="price-suffix">{{ $currency }} / {{ __('transport/plans.month_short') }}</span>
                            <div class="price-yearly-note">{{ __('transport/plans.net_notice') }}</div>
                        @endif
                    </div>

                    <ul class="features">
                        @foreach ($bullets as $bulletKey)
                            <li>{{ __('transport/plans.feature.'.$bulletKey) }}</li>
                        @endforeach
                    </ul>

                    @if ($isCustom)
                        <a href="mailto:sales@hovera.app?subject={{ rawurlencode(__('transport/plans.cta.contact_subject')) }}"
                           class="cta">{{ __('transport/plans.cta.contact') }}</a>
                    @else
                        <a href="{{ route('signup.show', ['plan' => $plan->code]) }}"
                           class="cta">{{ __('transport/plans.cta.start_trial') }}</a>
                    @endif
                </div>
            @endforeach
        </div>

        <h2>{{ __('transport/plans.addons_heading') }}</h2>
        <div class="h2-sub">{{ __('transport/plans.addons_sub') }}</div>

        <div class="addons-table-wrap">
        <table class="addons-table">
            <thead>
                <tr>
                    <th>{{ __('transport/plans.addons_table.name') }}</th>
                    <th>{{ __('transport/plans.addons_table.type') }}</th>
                    <th style="text-align: right;">{{ __('transport/plans.addons_table.price') }} ({{ $currency }})</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($addons as $addon)
                    @php
                        $cents = $addon->priceFor($currency, 'monthly');
                        $major = $cents !== null ? $cents / 100 : null;
                        $typeKey = $addon->addon_type === \App\Models\Central\PlanAddon::TYPE_ONE_TIME
                            ? 'one_time' : 'recurring_monthly';
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $addon->name }}</strong>
                            @if ($addon->description)
                                <div style="color:#6b7280;font-size:.82rem;margin-top:.15rem;">{{ $addon->description }}</div>
                            @endif
                        </td>
                        <td class="type-col">{{ __('transport/plans.addon_type.'.$typeKey) }}</td>
                        <td class="price-col">
                            @if ($major === null)
                                —
                            @else
                                {{ number_format($major, $major < 10 ? 2 : 0, ',', ' ') }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>

        <div class="footer">
            <a href="{{ route('signup.show') }}">{{ __('transport/plans.footer.signup') }}</a>
            ·
            <a href="mailto:office@hovera.app">office@hovera.app</a>
            ·
            <a href="{{ url('/regulamin') }}">{{ __('transport/plans.footer.terms') }}</a>
        </div>
    </div>
</body>
</html>
