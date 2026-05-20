<?php

declare(strict_types=1);

namespace App\Filament\Transport\Resources\QuoteResource\Pages;

use App\Domain\Transport\Calculator\CalculatorService;
use App\Domain\Transport\Calculator\Data\CalculationOptions;
use App\Domain\Transport\Geocoding\Exceptions\GeocodingException;
use App\Domain\Transport\Geocoding\MapboxGeocoder;
use App\Domain\Transport\Payments\PaymentUrlTemplate;
use App\Domain\Transport\Payments\PayU\TransporterPayUQuoteService;
use App\Domain\Transport\Payments\Przelewy24\TransporterP24QuoteService;
use App\Domain\Transport\Payments\Stripe\TransporterStripeConnectService;
use App\Domain\Transport\Quotes\QuoteNumberGenerator;
use App\Domain\Transport\Routing\Data\Coords;
use App\Enums\CalculationMode;
use App\Filament\Transport\Resources\QuoteResource;
use App\Models\Tenant\Quote;
use App\Models\Tenant\TransportSettings;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreateQuote extends CreateRecord
{
    protected static string $resource = QuoteResource::class;

    /**
     * Backlinki do central marketplace (lead_id, response_id) — przekazane
     * z session pre-fill (Calculator → Quote albo Lead inbox → Quote).
     * Nie są w form schema (klient nie powinien móc zmienić), więc trzymamy
     * je tu i wstrzykujemy w mutateFormDataBeforeCreate.
     */
    public ?string $pendingLeadId = null;

    public ?string $pendingResponseId = null;

    /**
     * Pre-fill from session — używane gdy user kliknie "Save as quote"
     * w `/transport/calculator` lub "Odpowiedz ofertą" w LeadResource.
     * Calculator/Lead zapisują pre-fill do session['transport.calc.pending'],
     * my je tu konsumujemy (i czyścimy żeby kolejny direct create dał
     * czysty form).
     */
    protected function fillForm(): void
    {
        $pending = session('transport.calc.pending');

        if (! is_array($pending)) {
            parent::fillForm();

            return;
        }

        session()->forget('transport.calc.pending');

        // Wyciągamy backlinki przed wlaniem do form'a (form schema ich nie ma)
        $this->pendingLeadId = isset($pending['lead_id']) ? (string) $pending['lead_id'] : null;
        $this->pendingResponseId = isset($pending['response_id']) ? (string) $pending['response_id'] : null;
        unset($pending['lead_id'], $pending['response_id']);

        // Pre-fill z Calculator'a (Save as quote) zawiera już pełną wycenę —
        // wyłączamy auto-routing toggle, żeby mutate hook NIE wywołał drugi
        // raz CalculatorService'u (waste call + ryzyko że rate się
        // rozjedzie z tym co user widział na Calculator'ze).
        $pending['auto_routing'] = false;

        $this->form->fill($pending);
    }

    /**
     * Numer generujemy w mutateFormDataBeforeCreate — żeby liczyć counter
     * dopiero po validacji formularza, ale przed insertem. Atomic transakcja
     * w QuoteNumberGenerator gwarantuje brak race condition'u. Tu też
     * wstrzykujemy backlinki lead_id / response_id z pre-fill'u.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['number'] = app(QuoteNumberGenerator::class)->next();

        if ($this->pendingLeadId) {
            $data['lead_id'] = $this->pendingLeadId;
        }
        if ($this->pendingResponseId) {
            $data['response_id'] = $this->pendingResponseId;
        }

        // Auto-routing toggle — domyślnie ON. Gdy włączony, geocodujemy
        // pickup/dropoff i przepuszczamy przez CalculatorService — wszystkie
        // pola finansowe (base_cost, fuel_surcharge, extra_horse_fee, VAT,
        // gross) lecą wprost ze świeżej kalkulacji. User widzi tylko stawkę
        // i totals read-only, reszta jest Hidden.
        //
        // Toggle nie jest kolumną w `quotes` — usuwamy z $data przed insertem
        // (niezależnie od auto-routing path).
        $autoRouting = (bool) ($data['auto_routing'] ?? true);
        unset($data['auto_routing']);

        if ($autoRouting) {
            $data = $this->autoCalculatePricing($data);
        }

        // Currency snapshot z TransportSettings — UI nie pokazuje pola
        // (Hidden), ale wartość musi być spójna z konfiguracją tenant'a
        // żeby PDF i payment_url'e używały tej samej waluty.
        if (empty($data['currency'])) {
            $data['currency'] = (string) TransportSettings::current()->currency;
        }

        // Line items — ad-hoc pozycje wyceny (Repeater w form'ie). Każda
        // pozycja dolicza się do net_total, VAT i gross przeliczane.
        // Patrz Quote::normaliseLineItems + docs/MARKETPLACE-ROADMAP.md
        // "Calculator: quote_items line items + PDF".
        $data = $this->applyLineItemsToTotals($data);

        return $data;
    }

    /**
     * Aplikuje line_items'y do net_total (+ vat + gross). Idempotent —
     * gdy line_items jest puste / null, nic nie modyfikuje.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function applyLineItemsToTotals(array $data): array
    {
        $rawItems = $data['line_items'] ?? [];
        if (! is_array($rawItems) || $rawItems === []) {
            $data['line_items'] = [];

            return $data;
        }

        $items = Quote::normaliseLineItems($rawItems);
        $data['line_items'] = $items;

        $itemsTotal = array_sum(array_column($items, 'line_total_net'));
        if ($itemsTotal <= 0) {
            return $data;
        }

        $netTotal = (float) ($data['net_total'] ?? 0) + $itemsTotal;
        $vatRate = (float) ($data['vat_rate'] ?? 0);
        $vatAmount = round($netTotal * ($vatRate / 100), 2);

        $data['net_total'] = round($netTotal, 2);
        $data['vat_amount'] = $vatAmount;
        $data['gross_total'] = round($netTotal + $vatAmount, 2);

        return $data;
    }

    /**
     * Geocode adresów + przelot przez CalculatorService → pełna wycena
     * z snapshot'em wszystkich komponentów. Wynik nadpisuje wszystkie
     * finansowe pola w $data; UI form ma je jako Hidden więc to jest
     * jedyne źródło prawdy dla auto-routing path.
     *
     * Soft-fail: jeśli geocoding albo routing padnie, logujemy +
     * notification, ale create nie crashuje — quote zostaje stworzony
     * z wartościami z form state (user mógł wpisać net_total/gross_total
     * po wyłączeniu toggle'a).
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function autoCalculatePricing(array $data): array
    {
        $tenant = app(TenantManager::class)->current();
        if ($tenant === null) {
            return $data;
        }

        try {
            $geocoder = app(MapboxGeocoder::class);
            $from = $geocoder->geocode((string) ($data['pickup_address'] ?? ''));
            $to = $geocoder->geocode((string) ($data['dropoff_address'] ?? ''));
        } catch (GeocodingException $e) {
            Notification::make()
                ->warning()
                ->title(__('transport/quote.notify.geocoding_failed_title'))
                ->body($e->getMessage())
                ->send();

            return $data;
        }

        $mode = CalculationMode::tryFrom((string) ($data['calculation_mode'] ?? ''))
            ?? CalculationMode::OneWay;

        try {
            $quotation = app(CalculatorService::class)->calculate(
                $tenant,
                new Coords($from->coords->lat, $from->coords->lng),
                new Coords($to->coords->lat, $to->coords->lng),
                new CalculationOptions(
                    loaded: (bool) ($data['loaded'] ?? true),
                    mode: $mode,
                    horsesCount: max(1, (int) ($data['horses_count'] ?? 1)),
                ),
            );
        } catch (Throwable $e) {
            report($e);
            Notification::make()
                ->warning()
                ->title(__('transport/quote.notify.calculation_failed_title'))
                ->body($e->getMessage())
                ->send();

            return $data;
        }

        // Override pól z lat/lng + snapshot wyceny. Zachowuje
        // user-edited rate_per_km (jeśli zmienił domyślną stawkę z
        // settings), bo CalculatorService używa stawki z settings na
        // input, a user może chcieć ad-hoc wycenę z inną stawką.
        $data['pickup_lat'] = $from->coords->lat;
        $data['pickup_lng'] = $from->coords->lng;
        $data['dropoff_lat'] = $to->coords->lat;
        $data['dropoff_lng'] = $to->coords->lng;
        $data['distance_km'] = $quotation->distanceKm;
        $data['duration_seconds'] = $quotation->durationSeconds;
        $data['routing_provider'] = $quotation->routingProvider;

        // Pricing — jeśli user nie nadpisał `rate_per_km` (puste lub 0),
        // bierzemy ze świeżej kalkulacji. W innym wypadku zachowujemy
        // wartość użytkownika; oznacza to też że całe komponenty
        // (base_cost, fuel_surcharge, ...) wyliczone CalculatorService'em
        // używają DOMYŚLNYCH stawek tenant'a — nie ad-hoc rate_per_km.
        // To akceptowalny kompromis dla MVP; pełen live-recalc przyjdzie
        // w roadmapie (PR "Calculator live UX").
        $data['rate_per_km'] = $data['rate_per_km'] ?? $quotation->rateUsed;
        $data['base_cost'] = $quotation->baseCost;
        $data['fuel_surcharge'] = $quotation->fuelSurcharge;
        $data['extra_horse_fee_snapshot'] = $quotation->extraHorseFeePerHead;
        $data['fixed_fees_snapshot'] = $quotation->fixedFees;
        $data['surcharge_percent_snapshot'] = $quotation->surchargePercent;
        $data['surcharge_amount_snapshot'] = $quotation->surchargeAmount;
        $data['exchange_rate_to_pln'] = $quotation->exchangeRateToPln !== 1.0 ? $quotation->exchangeRateToPln : null;
        $data['exchange_rate_date'] = $quotation->exchangeRateDate;
        $data['minimum_adjustment'] = $quotation->minimumAdjustment;
        $data['net_total'] = $quotation->netTotal;
        $data['vat_rate'] = $data['vat_rate'] ?? $quotation->vatRate;
        $data['vat_amount'] = $quotation->vatAmount;
        $data['gross_total'] = $quotation->grossTotal;
        $data['currency'] = $quotation->currency;

        return $data;
    }

    protected function afterCreate(): void
    {
        // Kolejność preferencji payment_url (każdy guard early-returnuje gdy
        // $quote->payment_url jest już ustawione, więc pierwszy konfigurowany
        // wygrywa):
        //   1. Stripe Connect Express — server-side Checkout Session
        //      (docs/TRANSPORT.md §15.6)
        //   2. Przelewy24 autopay — hosted checkout transportera
        //      (docs/TRANSPORT.md §15.5)
        //   3. PayU autopay — hosted checkout transportera (docs/TRANSPORT.md §16)
        //   4. default_payment_url_template — fallback na własny URL transportera
        $this->applyStripeConnectCheckoutIfEnabled();
        $this->tryGenerateP24PaymentLink();
        $this->tryGeneratePayUPaymentLink();
        $this->applyDefaultPaymentUrlIfBlank();

        app(TenantAuditLogger::class)->record(
            'quote.create',
            'Quote',
            (string) $this->record->getKey(),
            ['number' => $this->record->number],
        );
    }

    /**
     * P24 quote autopay — patrz docs/TRANSPORT.md §15.5.
     *
     * Jeśli transporter ma:
     *   1. `transport_settings.p24_quote_autopay_enabled = true`
     *   2. skonfigurowane creds w `tenants.settings.payments.p24`
     *   3. quote w PLN i z dodatnią kwotą
     * → generuje sesję P24 i ustawia quote.payment_url + payment_method_label.
     *
     * Soft-fail — jeśli P24 zwróci błąd (np. wygasł api_key), logujemy
     * + notification w panelu, ale create quote'a nie roluje wstecz.
     * Transporter może wrócić do edycji i wkleić URL ręcznie lub naprawić
     * settings.
     */
    private function tryGenerateP24PaymentLink(): void
    {
        /** @var Quote $quote */
        $quote = $this->record;

        if ($quote->payment_url) {
            return; // user already provided own URL — don't override
        }

        $settings = TransportSettings::current();
        if (! ($settings->p24_quote_autopay_enabled ?? false)) {
            return;
        }

        $tenant = app(TenantManager::class)->current();
        if ($tenant === null) {
            return;
        }

        $svc = app(TransporterP24QuoteService::class);
        if (! $svc->isConfigured($tenant)) {
            // Toggle on, ale brak creds — nie spamujemy logów, transporter
            // sam zobaczy w /app/payment-settings że nic nie wpisał.
            return;
        }

        try {
            $url = $svc->createPaymentSession($tenant, $quote);

            $quote->forceFill([
                'payment_url' => $url,
                'payment_method_label' => $quote->payment_method_label ?: 'Przelewy24',
            ])->save();
        } catch (Throwable $e) {
            // Soft-fail — quote zostaje stworzona bez payment_url, fallback
            // do template'a (applyDefaultPaymentUrlIfBlank) ewentualnie
            // zadziała. Logujemy + notification dla user'a.
            Log::warning('P24 quote autopay failed', [
                'quote_id' => $quote->id,
                'tenant' => $tenant->slug,
                'error' => $e->getMessage(),
            ]);

            Notification::make()
                ->warning()
                ->title(__('transport/p24.notify.autopay_failed'))
                ->body($e->getMessage())
                ->persistent()
                ->send();
        }
    }

    /**
     * PayU quote autopay — patrz docs/TRANSPORT.md §16.
     *
     * Analogiczne do `tryGenerateP24PaymentLink` ale PayU. Wymaga:
     *   1. `transport_settings.payu_quote_autopay_enabled = true`
     *   2. creds w `tenants.settings.payments.payu` (pos_id, oauth_client_id,
     *      oauth_client_secret, md5_key)
     *   3. quote w PLN, gross > 0
     *
     * Soft-fail — jeśli PayU OAuth/order endpoint padnie, logujemy +
     * warning notification, quote zostaje bez payment_url, ewentualnie
     * fallback do default_payment_url_template.
     */
    private function tryGeneratePayUPaymentLink(): void
    {
        /** @var Quote $quote */
        $quote = $this->record;

        if ($quote->payment_url) {
            return; // już ustawione (manual / Stripe / P24)
        }

        $settings = TransportSettings::current();
        if (! ($settings->payu_quote_autopay_enabled ?? false)) {
            return;
        }

        $tenant = app(TenantManager::class)->current();
        if ($tenant === null) {
            return;
        }

        $svc = app(TransporterPayUQuoteService::class);
        if (! $svc->isConfigured($tenant)) {
            // Toggle on, ale brak creds — silent skip (transporter zobaczy
            // w /app/payment-settings że pól brakuje).
            return;
        }

        try {
            $url = $svc->createPaymentSession($tenant, $quote);

            $quote->forceFill([
                'payment_url' => $url,
                'payment_method_label' => $quote->payment_method_label ?: 'PayU',
            ])->save();
        } catch (Throwable $e) {
            Log::warning('PayU quote autopay failed', [
                'quote_id' => $quote->id,
                'tenant' => $tenant->slug,
                'error' => $e->getMessage(),
            ]);

            Notification::make()
                ->warning()
                ->title(__('transport/payu.notify.autopay_failed'))
                ->body($e->getMessage())
                ->persistent()
                ->send();
        }
    }

    /**
     * Direct-charge payments MVP — patrz docs/TRANSPORT.md §13.
     *
     * Jeśli transporter nie wkleił payment_url w formularzu, a w settings
     * ma ustawiony `default_payment_url_template` — rozwijamy placeholdery
     * z aktualnego Quote'u i zapisujemy. Robimy to po create (a nie w
     * mutateFormDataBeforeCreate), bo template potrzebuje wyliczonego
     * `number` + zapisanych `gross_total` / `customer_name`.
     */
    private function applyDefaultPaymentUrlIfBlank(): void
    {
        /** @var Quote $quote */
        $quote = $this->record;

        if ($quote->payment_url) {
            return;
        }

        $settings = TransportSettings::current();
        $template = trim((string) ($settings->default_payment_url_template ?? ''));
        if ($template === '') {
            return;
        }

        $quote->forceFill([
            'payment_url' => PaymentUrlTemplate::expand($template, $quote),
            'payment_method_label' => $quote->payment_method_label
                ?: ($settings->default_payment_method_label ?: null),
        ])->save();
    }

    /**
     * Stripe Connect Express — patrz docs/TRANSPORT.md §15.6.
     *
     * Jeśli transporter ma aktywne Stripe Connect Express konto i quote nie
     * ma jeszcze ręcznie wklejonego payment_url'a — generujemy server-side
     * Stripe Checkout Session na koncie transportera i wstawiamy redirect URL.
     *
     * Pieniądze idą BEZPOŚREDNIO do transportera (direct charge); webhook
     * `checkout.session.completed` po sukcesie ustawi payment_completed_at.
     *
     * Failure mode: jeśli Stripe API rzuci, logujemy + fallback do
     * default_payment_url_template (zwracamy false → caller wywoła template path).
     */
    private function applyStripeConnectCheckoutIfEnabled(): bool
    {
        /** @var Quote $quote */
        $quote = $this->record;

        if ($quote->payment_url) {
            return true; // już ustawione ręcznie, nie nadpisujemy
        }

        $tenant = app(TenantManager::class)->current();
        if ($tenant === null || ! $tenant->hasStripeConnectEnabled()) {
            return false;
        }

        try {
            $session = app(TransporterStripeConnectService::class)->createCheckoutSession(
                quote: $quote,
                tenant: $tenant,
                successUrl: url("/transport/quote/{$tenant->slug}/{$quote->accept_token}?paid=1"),
                cancelUrl: url("/transport/quote/{$tenant->slug}/{$quote->accept_token}?cancelled=1"),
            );

            if (! is_string($session->url) || $session->url === '') {
                return false;
            }

            $quote->forceFill([
                'payment_url' => $session->url,
                'payment_method_label' => __('transport/stripe_connect.payment_method_label'),
            ])->save();

            return true;
        } catch (Throwable $e) {
            // Nie blokujemy create — quote zostaje bez Stripe URL, transporter
            // zobaczy w UI że można wkleić linka ręcznie. Logujemy żeby ops
            // wiedział że konfiguracja Stripe transportera się rozjechała.
            Log::warning('Stripe Connect Checkout creation failed; falling back', [
                'tenant_id' => $tenant->id,
                'quote_id' => $quote->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
