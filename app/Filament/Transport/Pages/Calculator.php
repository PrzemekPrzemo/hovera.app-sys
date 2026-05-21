<?php

declare(strict_types=1);

namespace App\Filament\Transport\Pages;

use App\Domain\Transport\Calculator\CalculatorService;
use App\Domain\Transport\Calculator\Data\CalculationOptions;
use App\Domain\Transport\Calculator\Data\Quotation;
use App\Domain\Transport\Geocoding\Data\GeocodedAddress;
use App\Domain\Transport\Geocoding\Exceptions\GeocodingException;
use App\Domain\Transport\Geocoding\MapboxGeocoder;
use App\Domain\Transport\Quotes\QuoteNumberGenerator;
use App\Domain\Transport\Routing\Data\Coords;
use App\Domain\Transport\Routing\Exceptions\RoutingException;
use App\Enums\CalculationMode;
use App\Enums\QuoteStatus;
use App\Filament\Concerns\RestrictedByTenantRole;
use App\Filament\Transport\Resources\QuoteResource;
use App\Models\Central\Tenant;
use App\Models\Tenant\Quote;
use App\Services\Tenancy\TenantRoleGate;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;
use Throwable;

/**
 * Filament page — interaktywny kalkulator wyceny transportu. Wprowadzasz
 * adres "skąd / dokąd" + opcje, system geokoduje (Mapbox), routuje
 * (RoutingService — plan-aware), liczy paliwo (FuelPriceService) i
 * składa pełną wycenę (CalculatorService → Quotation DTO).
 *
 * Patrz docs/TRANSPORT.md §3.3 + §9 faza 2 punkt 4.
 *
 * UI:
 *   - form: from_address, to_address, loaded toggle, round_trip,
 *     avoid_tolls/ferries, profile (truck/car)
 *   - po submit: wyświetlamy quotation w widoku per komponent
 *   - "Zapisz jako ofertę" przycisk → przygotowane pod fazę 3 (QuoteResource)
 */
class Calculator extends Page implements HasForms
{
    use InteractsWithForms;
    use RestrictedByTenantRole;

    /** @return list<string> */
    protected static function allowedRoles(): array
    {
        return TenantRoleGate::TRANSPORT_OPERATORS;
    }

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    public static function getNavigationLabel(): string
    {
        return __('transport/calculator.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.dispatch');
    }

    public function getTitle(): string|Htmlable
    {
        return __('transport/calculator.title');
    }

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.transport.pages.calculator';

    /** @var array<string,mixed> */
    public array $data = [
        'from_address' => null,
        'to_address' => null,
        'loaded' => true,
        'round_trip' => false,
        'avoid_tolls' => false,
        'avoid_ferries' => false,
        'profile' => 'truck',
        'horses_count' => 1,
        'fixed_fees' => [],
        'surcharge_percent' => null,
    ];

    public ?Quotation $quotation = null;

    public ?string $fromDisplayName = null;

    public ?string $toDisplayName = null;

    /**
     * Backlinki przekazywane z lead inbox (LeadResource::openInCalculator).
     * Po `saveAsQuote` doszyte do session pending, dzięki czemu CreateQuote
     * dostaje `lead_id` w pre-fillu i tworzy backlink Quote ↔ Lead.
     */
    public ?string $pendingLeadId = null;

    /**
     * Koordynaty z lead pre-fill — geocoding wykonany podczas tworzenia leada
     * w TransportInquiryController. Pomijamy ponowny call do Mapbox/ORS w
     * calculate(); jeśli user zmienił adres ręcznie po pre-fillu, geocoder
     * i tak przeliczy. Patrz docs/TRANSPORT.md §16 (lead → calculator flow).
     */
    public ?float $pendingPickupLat = null;

    public ?float $pendingPickupLng = null;

    public ?float $pendingDropoffLat = null;

    public ?float $pendingDropoffLng = null;

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);

        $this->consumeLeadPreFill();

        $this->form->fill($this->data);
    }

    /**
     * Konsumuje `transport.calc.pending` z session jeśli zawiera lead context.
     * NIE czyści sesji — `saveAsQuote()` doszywa do niej dodatkowe pola
     * (distance/cost) i przekazuje do CreateQuote. Pre-fill kończy się
     * wlanym formularzem + bannerem „Zaciągnięto dane z zapytania".
     */
    private function consumeLeadPreFill(): void
    {
        $pending = session('transport.calc.pending');
        if (! is_array($pending)) {
            return;
        }

        // Tylko gdy pending pochodzi z lead'a (ma lead_id) — inaczej to mogło
        // być pending z poprzedniego Calculator → Quote round-trip (gross_total
        // wpisane), nie chcemy psuć formu pustymi adresami.
        $leadId = $pending['lead_id'] ?? null;
        $fromAddress = $pending['from_address'] ?? $pending['pickup_address'] ?? null;
        $toAddress = $pending['to_address'] ?? $pending['dropoff_address'] ?? null;

        if ($leadId === null || $fromAddress === null || $toAddress === null) {
            return;
        }

        $this->pendingLeadId = (string) $leadId;
        $this->data['from_address'] = (string) $fromAddress;
        $this->data['to_address'] = (string) $toAddress;

        // Zachowujemy lat/lng z lead'a — w calculate() pomijamy geocoding jeśli
        // user nie zmienił adresów. Mapbox call dla niezmienionego adresu byłby
        // wasted spend.
        $this->pendingPickupLat = isset($pending['pickup_lat']) ? (float) $pending['pickup_lat'] : null;
        $this->pendingPickupLng = isset($pending['pickup_lng']) ? (float) $pending['pickup_lng'] : null;
        $this->pendingDropoffLat = isset($pending['dropoff_lat']) ? (float) $pending['dropoff_lat'] : null;
        $this->pendingDropoffLng = isset($pending['dropoff_lng']) ? (float) $pending['dropoff_lng'] : null;

        Notification::make()
            ->info()
            ->title(__('transport/calculator.notify.lead_prefilled_title'))
            ->body(__('transport/calculator.notify.lead_prefilled_body'))
            ->send();
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make(__('transport/calculator.section.route'))
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('from_address')
                            ->label(__('transport/calculator.form.label.from_address'))
                            ->required()
                            ->extraInputAttributes(['data-places-autocomplete' => 'panel', 'autocomplete' => 'off'])
                            ->placeholder(__('transport/calculator.form.placeholder.from_address')),
                        Forms\Components\TextInput::make('to_address')
                            ->label(__('transport/calculator.form.label.to_address'))
                            ->required()
                            ->extraInputAttributes(['data-places-autocomplete' => 'panel', 'autocomplete' => 'off'])
                            ->placeholder(__('transport/calculator.form.placeholder.to_address')),
                    ]),
                Forms\Components\Section::make(__('transport/calculator.section.options'))
                    ->columns(3)
                    ->schema([
                        Forms\Components\Select::make('mode')
                            ->label(__('transport/calculator.form.label.mode'))
                            ->helperText(__('transport/calculator.form.helper.mode'))
                            ->options(CalculationMode::options())
                            ->default(CalculationMode::OneWay->value)
                            ->required()
                            ->native(false),
                        Forms\Components\Toggle::make('loaded')
                            ->label(__('transport/calculator.form.label.loaded'))
                            ->default(true)
                            ->inline(false),
                        Forms\Components\TextInput::make('horses_count')
                            ->label(__('transport/calculator.form.label.horses_count'))
                            ->helperText(__('transport/calculator.form.helper.horses_count'))
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(30)
                            ->default(1)
                            ->required(),
                        Forms\Components\Select::make('profile')
                            ->label(__('transport/calculator.form.label.profile'))
                            ->options([
                                'truck' => __('transport/calculator.form.option.profile.truck'),
                                'car' => __('transport/calculator.form.option.profile.car'),
                            ])
                            ->default('truck')
                            ->native(false),
                        Forms\Components\Toggle::make('avoid_tolls')
                            ->label(__('transport/calculator.form.label.avoid_tolls'))
                            ->inline(false),
                        Forms\Components\Toggle::make('avoid_ferries')
                            ->label(__('transport/calculator.form.label.avoid_ferries'))
                            ->inline(false),
                    ]),
                Forms\Components\Section::make(__('transport/calculator.section.extra_costs'))
                    ->description(__('transport/calculator.section.extra_costs_description'))
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        Forms\Components\Repeater::make('fixed_fees')
                            ->label(__('transport/calculator.form.label.fixed_fees'))
                            ->helperText(__('transport/calculator.form.helper.fixed_fees'))
                            ->columnSpanFull()
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label(__('transport/calculator.form.label.fixed_fees_name'))
                                    ->required()
                                    ->maxLength(120),
                                Forms\Components\TextInput::make('amount')
                                    ->label(__('transport/calculator.form.label.fixed_fees_amount'))
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.01),
                            ])
                            ->columns(2)
                            ->reorderable(false)
                            ->defaultItems(0)
                            ->addActionLabel(__('transport/calculator.form.action.add_fixed_fee')),
                        Forms\Components\TextInput::make('surcharge_percent')
                            ->label(__('transport/calculator.form.label.surcharge_percent'))
                            ->helperText(__('transport/calculator.form.helper.surcharge_percent'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(500)
                            ->step(0.01)
                            ->suffix('%'),
                    ]),
            ]);
    }

    public function calculate(): void
    {
        abort_unless(self::canAccess(), 403);

        $form = $this->form->getState();
        $tenant = app(TenantManager::class)->current();
        if (! $tenant instanceof Tenant) {
            $this->fail(__('transport/calculator.error.no_tenant'));

            return;
        }

        // Optymalizacja: jeśli adres jest niezmieniony od lead pre-fillu i mamy
        // zachowane koordynaty, omijamy ponowny call do Mapbox (geocoding już
        // został zrobiony przy submit leada). Patrz docs/TRANSPORT.md §16.
        $geocoder = app(MapboxGeocoder::class);
        try {
            $from = $this->geocodeOrReuseFromLead(
                $geocoder,
                (string) $form['from_address'],
                $this->pendingPickupLat,
                $this->pendingPickupLng,
            );
            $to = $this->geocodeOrReuseFromLead(
                $geocoder,
                (string) $form['to_address'],
                $this->pendingDropoffLat,
                $this->pendingDropoffLng,
            );
        } catch (GeocodingException $e) {
            $this->fail($e->getMessage());

            return;
        }

        try {
            $mode = CalculationMode::tryFrom((string) ($form['mode'] ?? ''))
                ?? CalculationMode::OneWay;

            $quotation = app(CalculatorService::class)->calculate(
                $tenant,
                $from->coords,
                $to->coords,
                new CalculationOptions(
                    loaded: (bool) ($form['loaded'] ?? true),
                    roundTrip: $mode === CalculationMode::RoundTrip,
                    avoidTolls: (bool) ($form['avoid_tolls'] ?? false),
                    avoidFerries: (bool) ($form['avoid_ferries'] ?? false),
                    mode: $mode,
                    routingProfile: (string) ($form['profile'] ?? 'truck'),
                    horsesCount: max(1, (int) ($form['horses_count'] ?? 1)),
                    // null = bierz default z settings. Pusta tablica =
                    // user opt-out (Repeater bez itemów = []). Surcharge:
                    // null/empty string = settings default, 0 = explicit
                    // opt-out.
                    fixedFees: is_array($form['fixed_fees'] ?? null)
                        ? $form['fixed_fees']
                        : null,
                    surchargePercent: isset($form['surcharge_percent']) && $form['surcharge_percent'] !== ''
                        ? (float) $form['surcharge_percent']
                        : null,
                ),
            );
        } catch (RoutingException $e) {
            $this->fail($e->getMessage());

            return;
        } catch (Throwable $e) {
            report($e);
            $this->fail(__('transport/calculator.error.unknown'));

            return;
        }

        $this->fromDisplayName = $from->displayName;
        $this->toDisplayName = $to->displayName;
        // Zapisujemy lat/lng z geocodingu do public props — Leaflet w
        // result section renderuje markery start/end (patrz x-route-map).
        $this->pendingPickupLat = $from->coords->lat;
        $this->pendingPickupLng = $from->coords->lng;
        $this->pendingDropoffLat = $to->coords->lat;
        $this->pendingDropoffLng = $to->coords->lng;
        $this->quotation = $quotation;

        Notification::make()
            ->success()
            ->title(__('transport/calculator.action.calculated'))
            ->send();
    }

    /**
     * Zapisuje aktualną wycenę do session i przekierowuje na CreateQuote
     * (CreateQuote::fillForm konsumuje pending). Wymaga wcześniejszego
     * udanego `calculate()` — przycisk widoczny tylko gdy mamy `$quotation`.
     *
     * 2-step flow — używany dla complex quote'ów wymagających customer
     * picker'a, line items'ów, valid_until i innych pól które Calculator
     * sam nie zbiera. Dla szybkiej ścieżki bez customer'a — patrz
     * `saveAsQuoteInline`.
     */
    public function saveAsQuote(): RedirectResponse|Redirector
    {
        abort_unless(self::canAccess(), 403);
        abort_unless($this->quotation !== null, 422);

        $base = $this->buildQuoteSnapshot();

        session()->put('transport.calc.pending', $base);

        return redirect()->to(QuoteResource::getUrl('create'));
    }

    /**
     * One-shot save: tworzy Quote bezpośrednio z aktualnej kalkulacji i
     * przekierowuje na EditQuote. W odróżnieniu od `saveAsQuote` (2-step),
     * pomijamy CreateQuote::fillForm — quote już istnieje, user trafia od
     * razu na edycję żeby dopiąć customer'a, line items'y, valid_until itp.
     *
     * Co robimy:
     *   - generujemy number (QuoteNumberGenerator, atomic)
     *   - insertujemy Quote ze snapshot'em wszystkich komponentów wyceny
     *   - audit log (quote.create) — parity z CreateQuote::afterCreate
     *
     * Czego NIE robimy (zostaje dla user'a na EditQuote):
     *   - customer picker (customer_id) — Calculator nie ma tego pola
     *   - line items — Calculator nie ma Repeater'a
     *   - payment URL generation (Stripe Connect / P24 / PayU) — generuje
     *     się dopiero przy sendzie quote'a w QuoteResource::sendQuote()
     *   - waypoint geocoding — Calculator obecnie nie używa waypointów
     *     (przyjdzie z roadmapy gdy doposażymy form)
     */
    public function saveAsQuoteInline(QuoteNumberGenerator $numbers, TenantAuditLogger $audit): RedirectResponse|Redirector
    {
        abort_unless(self::canAccess(), 403);
        abort_unless($this->quotation !== null, 422);

        $tenant = app(TenantManager::class)->current();
        if (! $tenant instanceof Tenant) {
            $this->fail(__('transport/calculator.error.no_tenant'));

            return redirect()->to(self::getUrl());
        }

        $snapshot = $this->buildQuoteSnapshot();
        $snapshot['number'] = $numbers->next();

        // `customer_name` jest NOT NULL w DB; gdy Calculator nie miał lead
        // pre-fillu z customer'em, wpisujemy placeholder — user uzupełni
        // na EditQuote. Pusty string też przeszedłby (NOT NULL nie blokuje
        // ''), ale label jest pomocny w listing'u quote'ów.
        if (empty($snapshot['customer_name'])) {
            $snapshot['customer_name'] = __('transport/calculator.action.saved_as_quote_inline_placeholder_customer');
        }

        // Status z snapshot'u jako enum value (string) — Quote model cast'uje
        // automatycznie do QuoteStatus przez `casts()`.
        $quote = Quote::create($snapshot);

        $audit->record(
            'quote.create',
            'Quote',
            (string) $quote->getKey(),
            ['number' => $quote->number, 'source' => 'calculator_inline'],
        );

        Notification::make()
            ->success()
            ->title(__('transport/calculator.action.saved_as_quote_inline_title'))
            ->body(__('transport/calculator.action.saved_as_quote_inline_body', ['number' => $quote->number]))
            ->send();

        return redirect()->to(QuoteResource::getUrl('edit', ['record' => $quote]));
    }

    /**
     * Składa snapshot quote'a z aktualnej kalkulacji + lead pre-fillu.
     * Współdzielona logika dla `saveAsQuote` (2-step → session pending)
     * i `saveAsQuoteInline` (1-step → bezpośredni Quote::create).
     *
     * @return array<string, mixed>
     */
    private function buildQuoteSnapshot(): array
    {
        $q = $this->quotation;
        $form = $this->data;

        // Zachowanie lead pre-fill: jeśli Calculator został otwarty z lead'a
        // (LeadResource::openInCalculator), dorzucamy `lead_id` + lat/lng z
        // pending do nowego session pending. CreateQuote::fillForm konsumuje
        // `lead_id` i tworzy backlink Quote ↔ Lead. Pre-existing customer_*
        // pola też zostaną przekazane (CreateQuote je używa).
        $existingPending = (array) session('transport.calc.pending', []);
        $leadId = $existingPending['lead_id'] ?? $this->pendingLeadId;
        $pickupLat = isset($existingPending['pickup_lat']) ? (float) $existingPending['pickup_lat'] : ($this->pendingPickupLat ?? 0.0);
        $pickupLng = isset($existingPending['pickup_lng']) ? (float) $existingPending['pickup_lng'] : ($this->pendingPickupLng ?? 0.0);
        $dropoffLat = isset($existingPending['dropoff_lat']) ? (float) $existingPending['dropoff_lat'] : ($this->pendingDropoffLat ?? 0.0);
        $dropoffLng = isset($existingPending['dropoff_lng']) ? (float) $existingPending['dropoff_lng'] : ($this->pendingDropoffLng ?? 0.0);

        $base = [
            'pickup_address' => $this->fromDisplayName ?? ($form['from_address'] ?? ''),
            'dropoff_address' => $this->toDisplayName ?? ($form['to_address'] ?? ''),
            'pickup_lat' => $pickupLat,
            'pickup_lng' => $pickupLng,
            'dropoff_lat' => $dropoffLat,
            'dropoff_lng' => $dropoffLng,
            'preferred_date' => $existingPending['preferred_date'] ?? now()->addDays(7)->toDateString(),
            'round_trip' => (bool) ($form['round_trip'] ?? false),
            'calculation_mode' => (string) ($form['mode'] ?? CalculationMode::OneWay->value),
            'loaded' => (bool) ($form['loaded'] ?? true),
            'horses_count' => max(1, (int) ($form['horses_count'] ?? 1)),
            'distance_km' => $q->distanceKm,
            'duration_seconds' => $q->durationSeconds,
            'routing_provider' => $q->routingProvider,
            'polyline' => $q->polyline,
            'rate_per_km' => $q->rateUsed,
            'base_cost' => $q->baseCost,
            'fuel_surcharge' => $q->fuelSurcharge,
            'extra_horse_fee_snapshot' => $q->extraHorseFeePerHead,
            'fixed_fees_snapshot' => $q->fixedFees,
            'surcharge_percent_snapshot' => $q->surchargePercent,
            'surcharge_amount_snapshot' => $q->surchargeAmount,
            'exchange_rate_to_pln' => $q->exchangeRateToPln,
            'exchange_rate_date' => $q->exchangeRateDate,
            'minimum_adjustment' => $q->minimumAdjustment,
            'net_total' => $q->netTotal,
            'vat_rate' => $q->vatRate,
            'vat_amount' => $q->vatAmount,
            'gross_total' => $q->grossTotal,
            'currency' => $q->currency,
            'status' => QuoteStatus::Draft->value,
        ];

        if ($leadId !== null) {
            $base['lead_id'] = (string) $leadId;
        }

        // Customer info z lead pre-fillu (CreateQuote używa do pre-fill quote'a).
        foreach (['customer_name', 'customer_email', 'customer_phone', 'notes', 'horse_count', 'preferred_time'] as $key) {
            if (isset($existingPending[$key])) {
                $base[$key] = $existingPending[$key];
            }
        }

        return $base;
    }

    /**
     * Reusuje koordynaty z lead pre-fillu (jeśli są) zamiast wołać Mapbox.
     * Trafia wtedy, gdy: (a) mamy lead context, (b) zachowane lat/lng,
     * (c) adres w formularzu jest dokładnie taki sam jak wpisany przy
     * pre-fillu (porównanie string-wise). Inaczej geocoduje normalnie.
     */
    private function geocodeOrReuseFromLead(
        MapboxGeocoder $geocoder,
        string $address,
        ?float $cachedLat,
        ?float $cachedLng,
    ): GeocodedAddress {
        if ($this->pendingLeadId !== null && $cachedLat !== null && $cachedLng !== null) {
            return new GeocodedAddress(
                displayName: $address,
                coords: new Coords($cachedLat, $cachedLng),
            );
        }

        return $geocoder->geocode($address);
    }

    private function fail(string $message): void
    {
        $this->quotation = null;
        Notification::make()
            ->danger()
            ->title(__('transport/calculator.action.failed'))
            ->body($message)
            ->persistent()
            ->send();
    }
}
