<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('billing.page.title') }} — {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}">
    <style>
        :root {
            --ochre: #A8956B;
            --brown: #3D2E22;
            --bg: #F7F4EF;
            --line: #E9E2D3;
        }
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            background: var(--bg);
            color: var(--brown);
            line-height: 1.5;
        }
        .container { max-width: 1100px; margin: 0 auto; padding: 2rem 1.25rem; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        header img { height: 36px; }
        h1 { font-size: 1.85rem; margin: 0 0 .35rem; }
        .lead { color: #6b5b4a; margin-bottom: 1.5rem; }
        .badge { display: inline-block; padding: .2rem .6rem; border-radius: 999px; font-size: .75rem; font-weight: 600; }
        .badge.trial { background: #fef3c7; color: #92400e; }
        .badge.active { background: #d1fae5; color: #065f46; }
        .badge.expired { background: #fee2e2; color: #991b1b; }
        .toggle { display: inline-flex; gap: 0; background: white; border: 1px solid var(--line); border-radius: 8px; padding: 4px; margin: 0 0 1.5rem; }
        .toggle button {
            padding: .45rem 1rem; border: 0; background: transparent; cursor: pointer; border-radius: 6px;
            font-size: .9rem; font-weight: 600; color: var(--brown);
        }
        .toggle button.active { background: var(--ochre); color: white; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; }
        .card { background: white; border: 1px solid var(--line); border-radius: 10px; padding: 1.25rem; display: flex; flex-direction: column; position: relative; }
        .card.current { border: 2px solid var(--ochre); }
        .card.suggested { border: 2px solid var(--ochre); box-shadow: 0 4px 18px rgba(168, 149, 107, 0.25); }
        .card .recommend-badge {
            position: absolute; top: -10px; right: 12px;
            background: var(--ochre); color: white; padding: .15rem .6rem;
            border-radius: 999px; font-size: .7rem; font-weight: 700; letter-spacing: .03em;
            text-transform: uppercase;
        }
        .card h3 { margin: 0 0 .5rem; font-size: 1.2rem; }
        .price { font-size: 1.6rem; font-weight: 700; margin: .5rem 0; }
        .price small { font-size: .8rem; font-weight: 400; color: #8c7a64; }
        ul.features { list-style: none; padding: 0; margin: .75rem 0 1rem; font-size: .85rem; }
        ul.features li { padding: .2rem 0; }
        ul.features li::before { content: "✓"; color: var(--ochre); margin-right: .35rem; }
        button.primary, a.primary {
            display: inline-block;
            background: var(--ochre); color: white; border: 0; padding: .65rem 1.1rem;
            border-radius: 7px; cursor: pointer; font-weight: 600; font-size: .9rem;
            text-align: center; text-decoration: none; margin-top: auto;
        }
        button.primary[disabled] { background: #c8baa1; cursor: default; }
        a.secondary {
            display: inline-block; color: var(--brown); border: 1px solid var(--brown);
            background: transparent; padding: .55rem 1rem; border-radius: 7px;
            text-decoration: none; font-weight: 600; font-size: .85rem;
        }
        form { margin: 0; }
        .alert { background: #fee2e2; color: #991b1b; padding: .75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: .9rem; }
        .manage-row { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; padding: 1rem 1.25rem; background: white; border: 1px solid var(--line); border-radius: 10px; margin-bottom: 1.5rem; }
    </style>
    <x-google-analytics />
</head>
<body>
<div class="container">
    <header>
        <img src="{{ asset('img/brand/hovera-logo.svg') }}" alt="hovera">
        <a href="/app" class="secondary">{{ __('billing.actions.back_to_app') }}</a>
    </header>

    <h1>{{ __('billing.page.title') }}</h1>
    <p class="lead">{{ __('billing.page.subtitle', ['stable' => $tenant->name]) }}</p>
    <p style="font-size: .85rem; opacity: .7; margin-top: -.5rem;">{{ __('billing.vat_notice') }}</p>

    @if ($errors->any())
        <div class="alert">
            @foreach ($errors->all() as $err)
                <div>{{ $err }}</div>
            @endforeach
        </div>
    @endif

    @if ($hasSubscription)
        <span class="badge active">{{ __('billing.status.active') }}</span>
    @elseif ($trialDaysLeft !== null && $trialDaysLeft > 0)
        <span class="badge trial">
            {{ trans_choice('billing.status.trial_days_left', $trialDaysLeft, ['days' => $trialDaysLeft]) }}
        </span>
    @elseif ($tenant->trialHasExpired())
        <span class="badge expired">{{ __('billing.status.trial_expired') }}</span>
    @endif

    @if (session('status'))
        <div class="manage-row" style="margin-top: 1rem; background: #d1fae5; border-color: #6ee7b7;">
            <div>{{ session('status') }}</div>
        </div>
    @endif

    @if ($hasSubscription && $tenant->stripe_subscription_id !== null)
        <div class="manage-row" style="margin-top: 1rem;">
            <div>
                <strong>{{ __('billing.manage.title') }}</strong>
                <div style="font-size: .85rem; color: #6b5b4a;">{{ __('billing.manage.description') }}</div>
            </div>
            <form method="POST" action="{{ route('billing.portal') }}">
                @csrf
                <button type="submit" class="primary">{{ __('billing.actions.manage') }}</button>
            </form>
        </div>
    @endif

    @if ($payuSubscription !== null)
        <div class="manage-row" style="margin-top: 1rem;">
            <div>
                <strong>{{ __('billing.payu.card.heading') }}</strong>
                <div style="font-size: .9rem; color: #6b5b4a; margin-top: .25rem;">
                    {{ __('billing.payu.card.brand_mask', [
                        'brand' => $payuSubscription->payu_card_brand ?? '—',
                        'mask' => $payuSubscription->payu_card_mask ?? '—',
                    ]) }}
                </div>
                <div style="font-size: .85rem; color: #6b5b4a;">
                    {{ $payuSubscription->payu_card_expires_at
                        ? __('billing.payu.card.expires', ['expires' => $payuSubscription->payu_card_expires_at->format('m/Y')])
                        : __('billing.payu.card.no_expiry') }}
                </div>
                @if ($payuSubscription->status === 'past_due')
                    <div style="font-size: .85rem; color: #991b1b; margin-top: .25rem;">⚠ {{ __('billing.payu.status.past_due') }}</div>
                @endif
            </div>
            <form method="POST" action="{{ route('billing.payu.cancel') }}" onsubmit="return confirm('{{ __('billing.payu.card.cancel_confirm') }}');">
                @csrf
                <button type="submit" class="secondary" style="border-color: #991b1b; color: #991b1b;">
                    {{ __('billing.payu.card.cancel_cta') }}
                </button>
            </form>
        </div>
    @endif

    <div class="toggle" role="tablist" aria-label="{{ __('billing.period.label') }}">
        <button type="button" data-period="monthly" class="active">{{ __('billing.period.monthly') }}</button>
        <button type="button" data-period="yearly">{{ __('billing.period.yearly') }}</button>
    </div>

    <fieldset style="border: 1px solid var(--line); border-radius: 8px; padding: .75rem 1rem; margin: 0 0 1.5rem; background: white;">
        <legend style="padding: 0 .5rem; font-size: .85rem; font-weight: 600; color: #6b5b4a;">
            {{ __('billing.payment_method.label') }}
        </legend>
        <label style="display: flex; align-items: flex-start; gap: .5rem; padding: .35rem 0; cursor: pointer;">
            <input type="radio" name="payment_method" value="stripe" checked data-payment-method>
            <span>
                <strong style="font-size: .9rem;">{{ __('billing.payment_method.stripe') }}</strong><br>
                <span style="font-size: .8rem; color: #6b5b4a;">{{ __('billing.payment_method.stripe_hint') }}</span>
            </span>
        </label>
        <label style="display: flex; align-items: flex-start; gap: .5rem; padding: .35rem 0; cursor: pointer;">
            <input type="radio" name="payment_method" value="payu" data-payment-method>
            <span>
                <strong style="font-size: .9rem;">{{ __('billing.payment_method.payu') }}</strong><br>
                <span style="font-size: .8rem; color: #6b5b4a;">{{ __('billing.payment_method.payu_hint') }}</span>
            </span>
        </label>
    </fieldset>

    <div class="grid">
        @foreach ($plans as $plan)
            @php
                $isCurrent = $currentPlan && $currentPlan->id === $plan->id && $hasSubscription;
                $isSuggested = ! $isCurrent && ($suggestedPlan ?? null) === $plan->code;
                $monthlyPln = number_format($plan->price_monthly_cents / 100, 0, ',', ' ');
                $yearlyPln = number_format($plan->price_yearly_cents / 100, 0, ',', ' ');
                $onboardingFeeCents = (int) ($plan->onboarding_fee_cents ?? 0);
                $onboardingFeePln = $onboardingFeeCents > 0 ? number_format($onboardingFeeCents / 100, 0, ',', ' ') : null;
                $bullets = collect($plan->features ?? [])
                    ->filter(fn ($v, $k) => str_starts_with((string) $k, 'bullet_'))
                    ->values();
                $hasMonthlyPrice = ! empty($plan->stripe_price_monthly_id);
                $hasYearlyPrice = ! empty($plan->stripe_price_yearly_id);
            @endphp
            <div class="card {{ $isCurrent ? 'current' : '' }} {{ $isSuggested ? 'suggested' : '' }}" @if ($isSuggested) data-suggested="1" @endif>
                @if ($isSuggested)
                    <span class="recommend-badge">{{ __('billing.suggested_badge') }}</span>
                @endif
                <h3>{{ $plan->name }}</h3>
                <div class="price" data-price-monthly>
                    {{ $monthlyPln }} <small>{{ $plan->currency }} / {{ __('billing.period.month_short') }}</small>
                    <small style="opacity:.65;">{{ __('billing.vat_notice_short') }}</small>
                </div>
                <div class="price" data-price-yearly style="display:none;">
                    {{ $yearlyPln }} <small>{{ $plan->currency }} / {{ __('billing.period.year_short') }}</small>
                    <small style="opacity:.65;">{{ __('billing.vat_notice_short') }}</small>
                </div>
                @if ($onboardingFeePln !== null)
                    <div style="font-size:.85rem; margin: .25rem 0 .5rem; opacity:.85;">
                        + {{ $onboardingFeePln }} {{ $plan->currency }} {{ __('billing.onboarding_fee_label') }}
                    </div>
                @endif
                @if ($bullets->count())
                    <ul class="features">
                        @foreach ($bullets as $bullet)
                            <li>{{ $bullet }}</li>
                        @endforeach
                    </ul>
                @endif

                @if ($isCurrent)
                    <button class="primary" disabled>{{ __('billing.actions.current') }}</button>
                @else
                    <form method="POST"
                          action="{{ route('billing.checkout') }}"
                          data-plan-form
                          data-action-stripe="{{ route('billing.checkout') }}"
                          data-action-payu="{{ route('billing.payu.checkout') }}">
                        @csrf
                        <input type="hidden" name="plan_code" value="{{ $plan->code }}">
                        <input type="hidden" name="period" value="monthly" data-period-input>
                        <button
                            type="submit"
                            class="primary"
                            data-checkout-monthly="{{ $hasMonthlyPrice ? '1' : '0' }}"
                            data-checkout-yearly="{{ $hasYearlyPrice ? '1' : '0' }}"
                            @if (! $hasMonthlyPrice) disabled @endif
                        >
                            {{ __('billing.actions.choose') }}
                        </button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>

    <p style="margin-top: 2rem; font-size: .8rem; color: #8c7a64;">
        {{ __('billing.footer.disclaimer') }}
    </p>
</div>

<script>
    (function () {
        // Auto-scroll do polecanej karty (po wejściu z banera ?plan=pro).
        const suggested = document.querySelector('[data-suggested="1"]');
        if (suggested) {
            setTimeout(() => suggested.scrollIntoView({ behavior: 'smooth', block: 'center' }), 100);
        }
        const toggle = document.querySelector('.toggle');
        if (!toggle) return;
        toggle.addEventListener('click', (e) => {
            const btn = e.target.closest('button[data-period]');
            if (!btn) return;
            const period = btn.dataset.period;
            toggle.querySelectorAll('button').forEach(b => b.classList.toggle('active', b === btn));
            document.querySelectorAll('[data-price-monthly]').forEach(el => el.style.display = period === 'monthly' ? '' : 'none');
            document.querySelectorAll('[data-price-yearly]').forEach(el => el.style.display = period === 'yearly' ? '' : 'none');
            document.querySelectorAll('[data-period-input]').forEach(el => el.value = period);
            document.querySelectorAll('button[data-checkout-monthly]').forEach(btn => {
                const enabled = period === 'monthly' ? btn.dataset.checkoutMonthly === '1' : btn.dataset.checkoutYearly === '1';
                btn.disabled = !enabled;
            });
        });

        // Payment method picker — swap form action between Stripe i PayU.
        // PayU dla MVP wspiera tylko monthly (yearly = osobny mini-PR).
        function syncFormAction(method) {
            document.querySelectorAll('form[data-plan-form]').forEach(form => {
                const url = method === 'payu'
                    ? form.dataset.actionPayu
                    : form.dataset.actionStripe;
                if (url) {
                    form.setAttribute('action', url);
                }
            });
        }
        document.querySelectorAll('input[data-payment-method]').forEach(radio => {
            radio.addEventListener('change', () => {
                if (radio.checked) syncFormAction(radio.value);
            });
        });
    })();
</script>
</body>
</html>
