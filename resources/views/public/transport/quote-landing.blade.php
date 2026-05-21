<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('transport/landing.title', ['number' => $quote->number]) }}</title>
    <meta name="robots" content="noindex">
    <link rel="icon" href="{{ asset('favicon.svg') }}">
    <style>
        :root { --primary: #A8956B; --bg: #F7F4EF; --text: #1F1A17; --muted: #6b7280; --border: #d4cdb8; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: var(--bg); color: var(--text); }
        body { padding: 2rem 1rem; }
        .container { max-width: 640px; margin: 0 auto; }
        .seller { text-align: center; margin-bottom: 1.5rem; color: #3D2E22; font-weight: 700; font-size: 1.1rem; }
        .card { background: #fff; border-radius: 16px; padding: 2rem; box-shadow: 0 6px 24px rgba(0,0,0,.06); margin-bottom: 1rem; }
        .number { font-size: .85rem; color: var(--muted); letter-spacing: .04em; }
        h1 { margin: .25rem 0 1.25rem; font-size: 1.5rem; color: #3D2E22; }
        .grid { display: grid; gap: .35rem; font-size: .92rem; margin-bottom: 1.25rem; }
        .grid .row { display: flex; }
        .grid .row .l { color: var(--muted); flex: 0 0 9rem; }
        .grid .row .v { color: var(--text); font-weight: 500; }
        .pricing { margin-top: 1rem; border-top: 1px solid var(--border); padding-top: .75rem; }
        .pricing-row { display: flex; justify-content: space-between; padding: .25rem 0; font-size: .92rem; }
        .pricing-row.total { border-top: 2px solid var(--primary); padding-top: .75rem; margin-top: .5rem; font-weight: 700; font-size: 1.15rem; }
        .actions { display: flex; gap: .75rem; margin-top: 1.5rem; }
        .btn { flex: 1; padding: .9rem; border-radius: 10px; font-weight: 700; cursor: pointer; border: 0; font-size: 1rem; }
        .btn-accept { background: var(--primary); color: #fff; }
        .btn-accept:hover { filter: brightness(.95); }
        .btn-reject { background: #fff; color: #b91c1c; border: 1px solid #fecaca; }
        .btn-reject:hover { background: #fef2f2; }
        .banner { padding: 1.25rem; border-radius: 12px; text-align: center; font-weight: 600; margin-bottom: 1rem; }
        .banner.accepted { background: #ecfdf5; color: #047857; border: 1px solid #6ee7b7; }
        .banner.rejected { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .banner.final { background: #f3f4f6; color: var(--muted); border: 1px solid #e5e7eb; font-weight: 400; }
        .terms { margin-top: 1.25rem; padding: 1rem; background: #f7f4ef; border-radius: 10px; font-size: .85rem; line-height: 1.6; white-space: pre-wrap; }
        .payment { margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid var(--border); }
        .payment h2 { margin: 0 0 .75rem; font-size: 1.05rem; color: #3D2E22; }
        .payment-disclaimer { padding: .75rem 1rem; background: #fffbeb; color: #92400e; border: 1px solid #fde68a; border-radius: 10px; font-size: .82rem; line-height: 1.5; margin-bottom: 1rem; }
        .payment-pay-btn { display: block; padding: 1rem; background: #16a34a; color: #fff; border-radius: 10px; text-align: center; text-decoration: none; font-weight: 700; font-size: 1.05rem; }
        .payment-pay-btn:hover { background: #15803d; }
        .payment-method { text-align: center; margin-top: .5rem; font-size: .85rem; color: var(--muted); }
        .payment-confirmed { padding: 1rem; background: #ecfdf5; color: #047857; border: 1px solid #6ee7b7; border-radius: 10px; text-align: center; font-weight: 600; }
        .payment-instructions { padding: 1rem; background: #eff6ff; color: #1e3a8a; border: 1px solid #bfdbfe; border-radius: 10px; font-size: .9rem; line-height: 1.55; white-space: pre-wrap; }
        .payment-instructions strong { display: block; margin-bottom: .25rem; }
        .payment-contact { padding: 1rem; background: #f3f4f6; color: #374151; border: 1px solid #e5e7eb; border-radius: 10px; font-size: .9rem; line-height: 1.5; }
        .buyer { margin-top: 1.25rem; padding: 1rem; background: #f7f4ef; border: 1px solid var(--border); border-radius: 10px; }
        .buyer h2 { margin: 0 0 .75rem; font-size: 1rem; color: #3D2E22; }
        .buyer-choice { display: flex; gap: 1rem; margin-bottom: .75rem; flex-wrap: wrap; }
        .buyer-choice label { display: inline-flex; align-items: center; gap: .4rem; cursor: pointer; font-size: .95rem; }
        .buyer-fields { display: none; margin-top: .75rem; }
        .buyer-fields.show { display: block; }
        .buyer-field { margin-bottom: .6rem; }
        .buyer-field label { display: block; font-size: .8rem; color: var(--muted); margin-bottom: .2rem; }
        .buyer-field input, .buyer-field textarea { width: 100%; padding: .55rem .7rem; border: 1px solid var(--border); border-radius: 8px; font-size: .95rem; font-family: inherit; background: #fff; color: var(--text); }
        .buyer-field textarea { resize: vertical; min-height: 3rem; }
        .buyer-nip-row { display: flex; gap: .5rem; align-items: stretch; }
        .buyer-nip-row input { flex: 1; }
        .btn-lookup { padding: 0 .9rem; background: #fff; color: var(--primary); border: 1px solid var(--primary); border-radius: 8px; cursor: pointer; font-weight: 600; font-size: .9rem; white-space: nowrap; }
        .btn-lookup:hover:not(:disabled) { background: var(--primary); color: #fff; }
        .btn-lookup:disabled { opacity: .5; cursor: wait; }
        .buyer-feedback { font-size: .85rem; margin-top: .35rem; min-height: 1.1rem; }
        .buyer-feedback.ok { color: #047857; }
        .buyer-feedback.err { color: #b91c1c; }
        .footer { text-align: center; margin-top: 1.5rem; font-size: .75rem; color: var(--muted); }
                /* Mobile (≤600px) — etykiety nad wartościami, przyciski jeden pod drugim,
           mniej paddingu w karcie, większe taps dla accept/reject. */
        @media (max-width: 600px) {
            body { padding: 1rem .75rem; }
            .card { padding: 1.25rem 1rem; border-radius: 12px; }
            h1 { font-size: 1.3rem; }
            .grid .row { flex-direction: column; gap: .1rem; padding-bottom: .35rem; }
            .grid .row .l { flex: none; font-size: .8rem; text-transform: uppercase; letter-spacing: .04em; }
            .grid .row .v { word-break: break-word; }
            .actions { flex-direction: column; gap: .6rem; }
            .btn { padding: 1rem; font-size: 1.05rem; }
            .pricing-row.total { font-size: 1.05rem; }
            .payment-pay-btn { padding: 1.1rem; font-size: 1rem; }
        }
            /* Light mode only — wymog user spec. Brak prefers-color-scheme:dark override. */
        html { color-scheme: light; }
    </style>
    <x-google-analytics />
</head>
<body>
    <div class="container">
        <div class="seller">{{ $tenant->legal_name ?? $tenant->name }}</div>

        @if (session('accepted'))
            <div class="banner accepted">✓ {{ __('transport/landing.accepted_banner') }}</div>
        @elseif (session('rejected'))
            <div class="banner rejected">✕ {{ __('transport/landing.rejected_banner') }}</div>
        @elseif ($quote->status->value === 'accepted')
            <div class="banner final">{{ __('transport/landing.already_accepted') }} ({{ $quote->accepted_at?->format('Y-m-d H:i') }})</div>
        @elseif ($quote->status->value === 'rejected')
            <div class="banner final">{{ __('transport/landing.already_rejected') }} ({{ $quote->rejected_at?->format('Y-m-d H:i') }})</div>
        @endif

        <div class="card">
            <div class="number">{{ __('transport/landing.quote_number') }}</div>
            <h1>{{ $quote->number }}</h1>

            <div class="grid">
                <div class="row"><span class="l">{{ __('transport/landing.label.from') }}</span><span class="v">{{ $quote->pickup_address }}</span></div>
                <div class="row"><span class="l">{{ __('transport/landing.label.to') }}</span><span class="v">{{ $quote->dropoff_address }}</span></div>
                <div class="row"><span class="l">{{ __('transport/landing.label.date') }}</span><span class="v">{{ $quote->preferred_date->format('Y-m-d') }}@if ($quote->preferred_time) · {{ $quote->preferred_time }}@endif</span></div>
                <div class="row"><span class="l">{{ __('transport/landing.label.distance') }}</span><span class="v">{{ number_format((float) $quote->distance_km, 2, ',', ' ') }} km</span></div>
                @if ($quote->valid_until)
                    <div class="row"><span class="l">{{ __('transport/landing.label.valid_until') }}</span><span class="v">{{ $quote->valid_until->format('Y-m-d') }}</span></div>
                @endif
            </div>

            <div class="pricing">
                <div class="pricing-row"><span>{{ __('transport/landing.label.net') }}</span><span>{{ number_format((float) $quote->net_total, 2, ',', ' ') }} {{ $quote->currency }}</span></div>
                <div class="pricing-row"><span>{{ __('transport/landing.label.vat', ['rate' => number_format((float) $quote->vat_rate, 0)]) }}</span><span>{{ number_format((float) $quote->vat_amount, 2, ',', ' ') }} {{ $quote->currency }}</span></div>
                <div class="pricing-row total"><span>{{ __('transport/landing.label.gross') }}</span><span>{{ number_format((float) $quote->gross_total, 2, ',', ' ') }} {{ $quote->currency }}</span></div>
            </div>

            @if ($quote->terms)
                <div class="terms">{{ $quote->terms }}</div>
            @endif

            @if ($quote->status->value === 'sent')
                {{-- KRYTYCZNY disclaimer: akceptacja oferty = zawarcie umowy
                     BEZPOŚREDNIO z przewoźnikiem (nie z Hovera). Wyświetlamy
                     ZAWSZE przed przyciskami akcept/reject. Tenant data: NIP,
                     adres prawny — z legal_name/tax_id (mogą być NULL → pokazujemy
                     tyle ile jest). --}}
                <div style="margin-top:1.25rem;padding:1rem;background:#fef9e7;border:1px solid #f1e4a6;border-radius:10px;font-size:.85rem;line-height:1.55;color:#4a3d10;" role="note" aria-label="Marketplace intermediary disclaimer">
                    {!! __('transport/landing.disclaimer_intermediary_html', [
                        'transporter_name' => e($tenant->legal_name ?? $tenant->name),
                        'transporter_nip' => $tenant->tax_id ? 'NIP: '.e($tenant->tax_id) : '',
                    ]) !!}
                </div>

                {{-- Form akceptacji = otacza buyer choice + accept button. Klient
                     wybiera "osoba prywatna" (default) lub "firma" → wtedy musi
                     wpisać NIP, nazwę, adres. Dane lecą do quote.customer_* a
                     IssueTransportInvoiceFromQuote snapshot'uje je na FV.
                     Reject jest osobnym form'em poniżej (bez buyer fields). --}}
                <form method="post" action="{{ route('public.transport.quote.accept', ['slug' => $slug, 'token' => $token]) }}" id="accept-form">
                    @csrf
                    <div class="buyer">
                        <h2>{{ __('transport/landing.company.heading') }}</h2>
                        <div class="buyer-choice">
                            <label><input type="radio" name="buyer_type" value="private" {{ old('buyer_type', $quote->customer_tax_id ? 'company' : 'private') === 'private' ? 'checked' : '' }}> {{ __('transport/landing.company.as_private') }}</label>
                            <label><input type="radio" name="buyer_type" value="company" {{ old('buyer_type', $quote->customer_tax_id ? 'company' : 'private') === 'company' ? 'checked' : '' }}> {{ __('transport/landing.company.as_company') }}</label>
                        </div>
                        <div class="buyer-fields" id="buyer-company-fields">
                            <div class="buyer-field">
                                <label for="customer_tax_id">{{ __('transport/landing.company.tax_id_label') }}</label>
                                <div class="buyer-nip-row">
                                    <input type="text" id="customer_tax_id" name="customer_tax_id"
                                           value="{{ old('customer_tax_id', $quote->customer_tax_id) }}"
                                           placeholder="{{ __('transport/landing.company.tax_id_placeholder') }}"
                                           maxlength="32" inputmode="numeric" autocomplete="off">
                                    <button type="button" class="btn-lookup" id="btn-lookup-nip">{{ __('transport/landing.company.lookup_action') }}</button>
                                </div>
                                <div class="buyer-feedback" id="buyer-feedback"></div>
                            </div>
                            <div class="buyer-field">
                                <label for="customer_company">{{ __('transport/landing.company.company_name_label') }}</label>
                                <input type="text" id="customer_company" name="customer_company"
                                       value="{{ old('customer_company', $quote->customer_company) }}"
                                       maxlength="255" autocomplete="organization">
                            </div>
                            <div class="buyer-field">
                                <label for="customer_address">{{ __('transport/landing.company.address_label') }}</label>
                                <textarea id="customer_address" name="customer_address"
                                          rows="2" maxlength="1000"
                                          placeholder="{{ __('transport/landing.company.address_placeholder') }}">{{ old('customer_address', $quote->customer_address) }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="actions">
                        <button type="submit" class="btn btn-accept">✓ {{ __('transport/landing.action.accept') }}</button>
                    </div>
                </form>
                <form method="post" action="{{ route('public.transport.quote.reject', ['slug' => $slug, 'token' => $token]) }}">
                    @csrf
                    <div class="actions">
                        <button type="submit" class="btn btn-reject">✕ {{ __('transport/landing.action.reject') }}</button>
                    </div>
                </form>

                {{-- JS: toggle buyer fields on radio + AJAX GUS lookup. Pure vanilla
                     bez bundle'u (mobile-first, no JS framework w public flow).
                     CSRF z accept-form (input[name="_token"]). --}}
                <script>
                    (function () {
                        const radios = document.querySelectorAll('input[name="buyer_type"]');
                        const fields = document.getElementById('buyer-company-fields');
                        const nipInput = document.getElementById('customer_tax_id');
                        const nameInput = document.getElementById('customer_company');
                        const addrInput = document.getElementById('customer_address');
                        const lookupBtn = document.getElementById('btn-lookup-nip');
                        const feedback = document.getElementById('buyer-feedback');
                        const csrfToken = document.querySelector('#accept-form input[name="_token"]').value;

                        function syncVisibility() {
                            const company = document.querySelector('input[name="buyer_type"]:checked').value === 'company';
                            fields.classList.toggle('show', company);
                            nameInput.required = company;
                            nipInput.required = company;
                            addrInput.required = company;
                        }
                        radios.forEach(r => r.addEventListener('change', syncVisibility));
                        syncVisibility();

                        lookupBtn.addEventListener('click', async () => {
                            const nip = (nipInput.value || '').trim();
                            if (!nip) return;
                            lookupBtn.disabled = true;
                            const origText = lookupBtn.textContent;
                            lookupBtn.textContent = @json(__('transport/landing.company.lookup_loading'));
                            feedback.textContent = '';
                            feedback.className = 'buyer-feedback';
                            try {
                                const resp = await fetch(@json(route('public.transport.quote.lookup_nip', ['slug' => $slug, 'token' => $token])), {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': csrfToken,
                                    },
                                    body: JSON.stringify({ nip }),
                                });
                                const data = await resp.json();
                                if (resp.status === 422 || data.error === 'invalid_nip') {
                                    feedback.className = 'buyer-feedback err';
                                    feedback.textContent = @json(__('transport/landing.company.invalid_nip'));
                                } else if (!data.ok) {
                                    feedback.className = 'buyer-feedback err';
                                    feedback.textContent = @json(__('transport/landing.company.lookup_not_found'));
                                } else {
                                    if (data.name) nameInput.value = data.name;
                                    if (data.address) addrInput.value = data.address;
                                    const sources = (data.sources || []).join(', ') || 'GUS';
                                    feedback.className = 'buyer-feedback ok';
                                    feedback.textContent = @json(__('transport/landing.company.lookup_success', ['sources' => ':S'])).replace(':S', sources);
                                }
                            } catch (e) {
                                feedback.className = 'buyer-feedback err';
                                feedback.textContent = @json(__('transport/landing.company.lookup_error'));
                            } finally {
                                lookupBtn.disabled = false;
                                lookupBtn.textContent = origText;
                            }
                        });
                    })();
                </script>
            @endif

            {{--
                Direct-charge payments MVP — patrz docs/TRANSPORT.md §13.
                Pokazujemy sekcję płatności WYŁĄCZNIE gdy oferta jest accepted —
                klient widzi tu jedną z 4 sytuacji (priorytetowo):
                  1. payment_completed_at → potwierdzenie przez przewoźnika
                  2. payment_url → przycisk "Zapłać teraz"
                  3. payment_instructions (z settings) → fallback z danymi do przelewu
                  4. nic z powyższych → CTA "skontaktuj się z przewoźnikiem"

                Disclaimer "Hovera NIE przyjmuje płatności" jest ZAWSZE widoczny
                w sekcji płatności — to wymóg pozycjonowania marketplace'u
                (patrz §1.1 — Hovera jest pośrednikiem, nie merchantem).
            --}}
            @if ($quote->status->value === 'accepted')
                @php
                    $tenantName = $tenant->legal_name ?? $tenant->name;
                    $transportSettings = $transportSettings ?? null;
                    $paymentInstructions = $quote->payment_completed_at === null && ! $quote->payment_url
                        ? trim((string) ($transportSettings?->payment_instructions ?? ''))
                        : '';
                @endphp
                <div class="payment" data-testid="payment-section">
                    <h2>{{ __('transport/landing.payment.heading') }}</h2>

                    <div class="payment-disclaimer" data-testid="payment-disclaimer">
                        {{ __('transport/landing.payment.disclaimer', ['transporter' => $tenantName]) }}
                    </div>

                    @if ($quote->payment_completed_at)
                        <div class="payment-confirmed" data-testid="payment-confirmed">
                            ✓ {{ __('transport/landing.payment.confirmed', ['date' => $quote->payment_completed_at->format('Y-m-d H:i')]) }}
                        </div>
                    @elseif ($quote->payment_url)
                        <a href="{{ $quote->payment_url }}" target="_blank" rel="noopener noreferrer nofollow" class="payment-pay-btn" data-testid="payment-pay-btn">
                            {{ __('transport/landing.payment.pay_now', [
                                'amount' => number_format((float) $quote->gross_total, 2, ',', ' '),
                                'currency' => $quote->currency,
                            ]) }}
                        </a>
                        @if ($quote->payment_method_label)
                            <div class="payment-method">{{ $quote->payment_method_label }}</div>
                        @endif
                    @elseif ($paymentInstructions !== '')
                        <div class="payment-instructions" data-testid="payment-instructions">
                            <strong>{{ __('transport/landing.payment.instructions_heading') }}</strong>
                            {{ $paymentInstructions }}
                        </div>
                    @else
                        <div class="payment-contact" data-testid="payment-contact">
                            {{ __('transport/landing.payment.contact_transporter', ['transporter' => $tenantName]) }}
                            @php
                                $contactEmail = data_get($tenant->branding, 'contact_email');
                                $contactPhone = data_get($tenant->branding, 'contact_phone');
                            @endphp
                            @if ($contactEmail || $contactPhone)
                                <div style="margin-top: .5rem;">
                                    @if ($contactEmail)<a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>@endif
                                    @if ($contactEmail && $contactPhone) · @endif
                                    @if ($contactPhone)<a href="tel:{{ $contactPhone }}">{{ $contactPhone }}</a>@endif
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <div class="footer">{{ __('transport/landing.footer', ['app' => config('app.name')]) }}</div>
    </div>
</body>
</html>
