<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('public/pricing.title') }} — hovera</title>
    <meta name="description" content="{{ __('public/pricing.meta_description') }}">
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
        .nav-links a:hover { color: var(--primary); }
        .nav-cta { display: inline-block; padding: .5rem 1rem; background: var(--primary); color: #fff !important; border-radius: 8px; font-weight: 600; }
        .nav-cta:hover { filter: brightness(.95); }
        .hero { text-align: center; margin-bottom: 2.5rem; }
        h1 { margin: 0 0 .75rem; font-size: 2.4rem; font-weight: 700; letter-spacing: -.02em; color: var(--brown); }
        .lede { color: #4b5563; font-size: 1.05rem; max-width: 640px; margin: 0 auto 1rem; line-height: 1.55; }
        .differentiator { display: inline-block; background: var(--sand); color: var(--brown); padding: .5rem 1rem; border-radius: 999px; font-size: .85rem; font-weight: 600; margin-top: .25rem; }
        .toggle-wrap { display: flex; justify-content: center; gap: .5rem; align-items: center; margin: 2rem 0 1.5rem; }
        .toggle { background: var(--sand); border-radius: 999px; padding: .25rem; display: inline-flex; }
        .toggle button { background: transparent; border: 0; padding: .5rem 1.25rem; border-radius: 999px; cursor: pointer; font: inherit; font-weight: 600; color: var(--brown); transition: background .15s; }
        .toggle button.active { background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .save-badge { background: #d1fae5; color: #065f46; padding: .2rem .6rem; border-radius: 999px; font-size: .75rem; font-weight: 700; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 3rem; }
        .plan { background: #fff; border-radius: 14px; padding: 1.75rem 1.5rem; box-shadow: 0 4px 14px rgba(0,0,0,.05); border: 2px solid transparent; display: flex; flex-direction: column; }
        .plan.featured { border-color: var(--primary); position: relative; }
        .plan.featured::before { content: "{{ __('public/pricing.most_popular') }}"; position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: var(--primary); color: #fff; padding: .25rem .85rem; border-radius: 999px; font-size: .72rem; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; }
        .plan-name { font-size: 1.15rem; font-weight: 700; color: var(--brown); margin: 0 0 .35rem; }
        .plan-tagline { color: #6b7280; font-size: .85rem; min-height: 2.6em; line-height: 1.4; margin-bottom: 1rem; }
        .price-block { margin-bottom: 1.25rem; }
        .price { font-size: 2.2rem; font-weight: 700; color: var(--brown); letter-spacing: -.02em; }
        .price-suffix { color: #6b7280; font-size: .85rem; margin-left: .15rem; }
        .price-yearly-note { font-size: .78rem; color: #6b7280; margin-top: .15rem; min-height: 1.2em; }
        .features { list-style: none; padding: 0; margin: 0 0 1.25rem; flex: 1; }
        .features li { padding: .35rem 0 .35rem 1.5rem; position: relative; font-size: .88rem; color: #3D2E22; line-height: 1.5; }
        .features li::before { content: "✓"; position: absolute; left: 0; color: var(--primary); font-weight: 700; }
        .cta { display: block; width: 100%; padding: .8rem 1rem; background: var(--primary); color: #fff; text-align: center; text-decoration: none; border-radius: 8px; font-weight: 700; transition: filter .15s; }
        .cta:hover { filter: brightness(.95); }
        .cta.secondary { background: transparent; color: var(--primary); border: 2px solid var(--primary); }
        .cta.secondary:hover { background: var(--primary); color: #fff; filter: none; }
        .price-custom { font-size: 1.4rem; color: var(--brown); }
        h2 { font-size: 1.6rem; color: var(--brown); margin: 3rem 0 .5rem; text-align: center; }
        .h2-sub { text-align: center; color: #6b7280; margin-bottom: 2rem; }
        .compare { background: #fff; border-radius: 14px; padding: 1rem; box-shadow: 0 4px 14px rgba(0,0,0,.05); overflow-x: auto; }
        .compare table { width: 100%; border-collapse: collapse; min-width: 720px; }
        .compare th, .compare td { padding: .85rem 1rem; text-align: left; border-bottom: 1px solid var(--sand); font-size: .9rem; }
        .compare th { color: var(--brown); font-weight: 700; background: var(--bg); position: sticky; top: 0; }
        .compare th.center, .compare td.center { text-align: center; }
        .compare .yes { color: #065f46; font-weight: 700; }
        .compare .no { color: #9ca3af; }
        .compare tr:last-child td { border-bottom: 0; }
        .compare .group-header td { background: var(--bg); font-weight: 700; color: var(--brown); padding: .65rem 1rem; }
        .faq { margin-top: 3rem; }
        .faq details { background: #fff; border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: .65rem; box-shadow: 0 2px 8px rgba(0,0,0,.04); }
        .faq summary { cursor: pointer; font-weight: 600; color: var(--brown); }
        .faq summary::marker { color: var(--primary); }
        .faq details[open] summary { margin-bottom: .65rem; }
        .faq p { margin: 0; color: #4b5563; line-height: 1.6; font-size: .92rem; }
        .footer { text-align: center; margin-top: 3rem; color: #6b7280; font-size: .85rem; }
        .footer a { color: var(--primary); text-decoration: none; margin: 0 .5rem; }
        .footer a:hover { text-decoration: underline; }
        @media (max-width: 720px) { h1 { font-size: 1.8rem; } .nav-links a { display: none; } .nav-cta { display: inline-block !important; } }
        @media (prefers-color-scheme: dark) {
            html, body { background: #1F1A17; color: #F7F4EF; }
            .plan, .compare, .faq details { background: #2a221c; }
            .logo, .nav-links a, .plan-name, h1, h2, .faq summary { color: #E9E2D3; }
            .lede, .plan-tagline, .price-yearly-note, .price-suffix, .h2-sub, .footer { color: #C8B8A4; }
            .compare th, .compare .group-header td { background: #1F1A17; color: #E9E2D3; border-bottom-color: #4a3d31; }
            .compare th, .compare td { border-bottom-color: #4a3d31; }
            .toggle { background: #2a221c; }
            .toggle button { color: #E9E2D3; }
            .toggle button.active { background: #1F1A17; }
            .differentiator { background: #2a221c; color: #E9E2D3; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav">
            <a href="{{ url('/') }}" class="logo">hovera</a>
            <div class="nav-links">
                <a href="{{ url('/demo') }}">{{ __('public/pricing.nav.demo') }}</a>
                <a href="/app/login">{{ __('public/pricing.nav.login') }}</a>
                <a href="{{ route('signup.show') }}" class="nav-cta">{{ __('public/pricing.nav.signup') }}</a>
            </div>
        </div>

        <div class="hero">
            <h1>{{ __('public/pricing.heading') }}</h1>
            <p class="lede">{{ __('public/pricing.lede') }}</p>
            <span class="differentiator">{{ __('public/pricing.differentiator') }}</span>
            <div style="margin-top: .65rem; font-size: .85rem; color: #6b7280;">
                {{ __('public/pricing.vat_notice') }}
            </div>
        </div>

        <div class="toggle-wrap">
            <div class="toggle" role="tablist" aria-label="{{ __('public/pricing.billing_label') }}">
                <button type="button" class="active" data-period="monthly">{{ __('public/pricing.monthly') }}</button>
                <button type="button" data-period="yearly">{{ __('public/pricing.yearly') }}</button>
            </div>
            <span class="save-badge">{{ __('public/pricing.save_yearly') }}</span>
        </div>

        <div class="grid">
            @foreach ($plans as $plan)
                @php
                    $isFeatured = $plan->code === 'stable';
                    $isCustom = ! empty($plan->features['is_custom_pricing']);
                    $monthly = (int) $plan->price_monthly_cents;
                    $yearly = (int) $plan->price_yearly_cents;
                    $onboardingFee = (int) ($plan->onboarding_fee_cents ?? 0);
                    $monthlyPLN = $monthly === 0 ? 0 : (int) round($monthly / 100);
                    $yearlyPerMonthPLN = $yearly === 0 ? 0 : (int) round(($yearly / 12) / 100);
                    $yearlyTotalPLN = $yearly === 0 ? 0 : (int) round($yearly / 100);
                    $onboardingFeePLN = $onboardingFee === 0 ? 0 : (int) round($onboardingFee / 100);
                    $bullets = collect($plan->features ?? [])
                        ->filter(fn ($v, $k) => str_starts_with((string) $k, 'bullet_'))
                        ->values()
                        ->all();
                @endphp
                <div class="plan @if ($isFeatured) featured @endif">
                    <h3 class="plan-name">{{ $plan->name }}</h3>
                    <div class="plan-tagline">{{ __('public/pricing.tagline.'.$plan->code) }}</div>

                    <div class="price-block">
                        @if ($isCustom)
                            <div class="price-custom">{{ __('public/pricing.custom_price') }}</div>
                            <div class="price-yearly-note">{{ __('public/pricing.custom_price_note') }}</div>
                        @elseif ($monthly === 0)
                            <span class="price">0&nbsp;zł</span>
                            <span class="price-suffix">/{{ __('public/pricing.month_short') }}</span>
                            <div class="price-yearly-note">{{ __('public/pricing.free_forever') }}</div>
                        @else
                            <span class="price" data-monthly="{{ $monthlyPLN }}" data-yearly="{{ $yearlyPerMonthPLN }}">{{ $monthlyPLN }}&nbsp;zł</span>
                            <span class="price-suffix">/{{ __('public/pricing.month_short') }}</span>
                            <span class="price-suffix" style="opacity:.7;">{{ __('public/pricing.vat_notice_short') }}</span>
                            <div class="price-yearly-note"
                                 data-monthly-text="{{ __('public/pricing.billed_monthly') }}"
                                 data-yearly-text="{{ __('public/pricing.billed_yearly_total', ['total' => $yearlyTotalPLN]) }}">
                                {{ __('public/pricing.billed_monthly') }}
                            </div>
                            @if ($onboardingFee > 0)
                                <div class="price-yearly-note" style="margin-top:.35rem; color: var(--brown); font-weight: 500;">
                                    + {{ number_format($onboardingFeePLN, 0, ',', ' ') }}&nbsp;zł {{ __('public/pricing.onboarding_fee_label') }}
                                </div>
                            @endif
                        @endif
                    </div>

                    <ul class="features">
                        @foreach ($bullets as $bullet)
                            <li>{{ $bullet }}</li>
                        @endforeach
                    </ul>

                    @if ($isCustom)
                        <a href="mailto:sales@hovera.app?subject=Hovera Enterprise" class="cta">{{ __('public/pricing.cta.contact') }}</a>
                    @elseif ($monthly === 0)
                        <a href="{{ route('signup.show') }}" class="cta secondary">{{ __('public/pricing.cta.start_free') }}</a>
                    @else
                        <a href="{{ route('signup.show') }}" class="cta">{{ __('public/pricing.cta.start_trial') }}</a>
                    @endif
                </div>
            @endforeach
        </div>

        <h2>{{ __('public/pricing.compare.heading') }}</h2>
        <div class="h2-sub">{{ __('public/pricing.compare.sub') }}</div>

        <div class="compare">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('public/pricing.compare.feature') }}</th>
                        @foreach ($plans as $plan)
                            <th class="center">{{ $plan->name }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <tr class="group-header"><td colspan="{{ $plans->count() + 1 }}">{{ __('public/pricing.compare.group.limits') }}</td></tr>
                    @foreach (['max_horses', 'max_clients', 'max_users', 'max_storage_mb'] as $limitKey)
                        <tr>
                            <td>{{ __('public/pricing.compare.limits.'.$limitKey) }}</td>
                            @foreach ($plans as $plan)
                                @php
                                    $val = $plan->limits[$limitKey] ?? null;
                                    $display = $val === null ? '—' : ($val === -1 ? __('public/pricing.unlimited') : ($limitKey === 'max_storage_mb' ? number_format($val).' MB' : number_format($val)));
                                @endphp
                                <td class="center">{{ $display }}</td>
                            @endforeach
                        </tr>
                    @endforeach

                    <tr class="group-header"><td colspan="{{ $plans->count() + 1 }}">{{ __('public/pricing.compare.group.core') }}</td></tr>
                    @php
                        $coreMatrix = [
                            'multi_calendar' => ['free' => true, 'solo' => true, 'stable' => true, 'pro' => true, 'enterprise' => true],
                            'horse_crm' => ['free' => true, 'solo' => true, 'stable' => true, 'pro' => true, 'enterprise' => true],
                            'online_booking' => ['free' => false, 'solo' => true, 'stable' => true, 'pro' => true, 'enterprise' => true],
                            'passes' => ['free' => false, 'solo' => true, 'stable' => true, 'pro' => true, 'enterprise' => true],
                            'invoices_ksef' => ['free' => false, 'solo' => false, 'stable' => true, 'pro' => true, 'enterprise' => true],
                            'breeding_journal' => ['free' => false, 'solo' => false, 'stable' => true, 'pro' => true, 'enterprise' => true],
                            'boarding_portal' => ['free' => false, 'solo' => false, 'stable' => false, 'pro' => true, 'enterprise' => true],
                            'public_api' => ['free' => false, 'solo' => false, 'stable' => false, 'pro' => true, 'enterprise' => true],
                            'vanity_domain' => ['free' => false, 'solo' => false, 'stable' => false, 'pro' => true, 'enterprise' => true],
                            'white_label' => ['free' => false, 'solo' => false, 'stable' => false, 'pro' => false, 'enterprise' => true],
                            'sso' => ['free' => false, 'solo' => false, 'stable' => false, 'pro' => false, 'enterprise' => true],
                        ];
                    @endphp
                    @foreach ($coreMatrix as $featureKey => $rowMap)
                        <tr>
                            <td>{{ __('public/pricing.compare.features.'.$featureKey) }}</td>
                            @foreach ($plans as $plan)
                                <td class="center">
                                    @if (! empty($rowMap[$plan->code]))
                                        <span class="yes">✓</span>
                                    @else
                                        <span class="no">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach

                    <tr class="group-header"><td colspan="{{ $plans->count() + 1 }}">{{ __('public/pricing.compare.group.support') }}</td></tr>
                    <tr>
                        <td>{{ __('public/pricing.compare.support_level') }}</td>
                        @foreach ($plans as $plan)
                            <td class="center">{{ __('public/pricing.support.'.($plan->features['support'] ?? 'community')) }}</td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="faq">
            <h2>{{ __('public/pricing.faq.heading') }}</h2>
            @foreach (['trial', 'change_plan', 'cancel', 'data_ownership', 'invoice', 'limits_exceeded'] as $faqKey)
                <details>
                    <summary>{{ __('public/pricing.faq.'.$faqKey.'.q') }}</summary>
                    <p>{{ __('public/pricing.faq.'.$faqKey.'.a') }}</p>
                </details>
            @endforeach
        </div>

        <div class="footer">
            <a href="{{ route('signup.show') }}">{{ __('public/pricing.footer.signup') }}</a>
            ·
            <a href="{{ url('/demo') }}">{{ __('public/pricing.footer.demo') }}</a>
            ·
            <a href="mailto:support@hovera.app">support@hovera.app</a>
        </div>
    </div>

    <script>
        (function () {
            const buttons = document.querySelectorAll('.toggle button');
            const prices = document.querySelectorAll('.price[data-monthly]');
            const notes = document.querySelectorAll('.price-yearly-note[data-monthly-text]');

            buttons.forEach(btn => btn.addEventListener('click', () => {
                buttons.forEach(b => b.classList.toggle('active', b === btn));
                const period = btn.dataset.period;
                prices.forEach(p => {
                    const v = period === 'yearly' ? p.dataset.yearly : p.dataset.monthly;
                    p.firstChild ? p.firstChild.nodeValue = '' : null;
                    p.innerHTML = v + ' zł';
                });
                notes.forEach(n => {
                    n.textContent = period === 'yearly' ? n.dataset.yearlyText : n.dataset.monthlyText;
                });
            }));
        })();
    </script>
</body>
</html>
