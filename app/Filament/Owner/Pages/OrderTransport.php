<?php

declare(strict_types=1);

namespace App\Filament\Owner\Pages;

use App\Domain\Transport\Geocoding\Exceptions\GeocodingException;
use App\Domain\Transport\Geocoding\MapboxGeocoder;
use App\Domain\Transport\Leads\LeadDispatcher;
use App\Enums\CalculationMode;
use App\Filament\Owner\Resources\TransportOrderResource;
use App\Models\Central\OwnerFavoriteRoute;
use App\Models\Central\OwnerFavoriteTransporter;
use App\Models\Central\Tenant;
use App\Models\Central\TransportLead;
use App\Models\Tenant\OwnerHorse;
use App\Models\Tenant\TransportOrder;
use App\Tenancy\TenantManager;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Throwable;

/**
 * Owner: "Zamów transport" — mini-Calculator. Patrz docs/MARKETPLACE-ROADMAP.md
 * PR 6 §"`OrderTransport` page".
 *
 * Flow:
 *   1. User wybiera horse (lub null), pickup + dropoff (autocomplete),
 *      preferred_date, calculation_mode, notes.
 *   2. Submit → geocode adresów (Mapbox), utwórz TransportLead w central
 *      DB (mode=broadcast, originator_user_id=auth, originator_tenant_id=
 *      tenant), zapisz lokalny TransportOrder row z snapshot'em trasy.
 *   3. LeadDispatcher rozsyła do verified transporterów w voivodeship.
 *   4. Redirect na /owner/transport-orders/{id} (View).
 *
 * Brak inline cenówki — owner widzi tylko "Zapytanie wysłane", konkretne
 * oferty przychodzą jako responses (PR 11 doda widget z notyfikacjami).
 */
class OrderTransport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';

    protected static string $view = 'filament.owner.pages.order-transport';

    protected static ?int $navigationSort = 10;

    /** @var array<string,mixed> */
    public array $data = [];

    /**
     * Pre-fill z linka "Zamów transport" na karcie konia
     * (HorseResource\EditHorse). Tworzymy formularz z wybranym horse'em
     * od razu, user uzupełnia tylko adresy.
     */
    #[Url(as: 'horse')]
    public ?string $horseQueryParam = null;

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.owner_transport');
    }

    public static function getNavigationLabel(): string
    {
        return __('owner/transport.order.navigation');
    }

    public function getTitle(): string|Htmlable
    {
        return __('owner/transport.order.title');
    }

    public function getHeading(): string|Htmlable
    {
        return __('owner/transport.order.heading');
    }

    public function mount(): void
    {
        $defaults = [
            'horse_id' => null,
            'favorite_route_id' => null,
            'pickup_address' => '',
            'dropoff_address' => '',
            'preferred_date' => now()->addDays(7)->toDateString(),
            'preferred_time' => null,
            'calculation_mode' => CalculationMode::OneWay->value,
            'notes' => '',
            'use_favorite_transporters_only' => false,
            'favorite_transporter_ids' => [],
        ];

        if ($this->horseQueryParam !== null && preg_match('/^[0-9A-Za-z]{26}$/', $this->horseQueryParam)) {
            $horse = OwnerHorse::query()->find($this->horseQueryParam);
            if ($horse !== null) {
                $defaults['horse_id'] = $horse->id;
            }
        }

        $this->form->fill($defaults);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make(__('owner/transport.order.section.favorite_route'))
                    ->description(__('owner/transport.order.helper.favorite_route'))
                    ->collapsed()
                    ->visible(fn () => self::userFavoriteRoutes()->isNotEmpty())
                    ->schema([
                        Forms\Components\Select::make('favorite_route_id')
                            ->label(__('owner/transport.order.label.favorite_route'))
                            ->options(fn () => self::userFavoriteRoutes()->pluck('label', 'id'))
                            ->placeholder(__('owner/transport.order.placeholder.favorite_route'))
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state === null) {
                                    return;
                                }
                                $route = OwnerFavoriteRoute::query()
                                    ->where('id', $state)
                                    ->where('owner_user_id', Auth::id())
                                    ->first();
                                if ($route === null) {
                                    return;
                                }
                                $set('pickup_address', $route->pickup_address);
                                $set('dropoff_address', $route->dropoff_address);
                                if ($route->notes !== null && $route->notes !== '') {
                                    $set('notes', $route->notes);
                                }
                            }),
                    ]),

                Forms\Components\Section::make(__('owner/transport.order.section.horse'))
                    ->schema([
                        Forms\Components\Select::make('horse_id')
                            ->label(__('owner/transport.order.label.horse'))
                            ->helperText(__('owner/transport.order.helper.horse'))
                            ->options(fn () => OwnerHorse::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->placeholder(__('owner/transport.order.placeholder.horse')),
                    ]),

                Forms\Components\Section::make(__('owner/transport.order.section.route'))
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('pickup_address')
                            ->label(__('owner/transport.order.label.pickup'))
                            ->required()
                            ->maxLength(255)
                            ->extraInputAttributes(['data-places-autocomplete' => 'panel', 'autocomplete' => 'off'])
                            ->placeholder(__('owner/transport.order.placeholder.pickup')),
                        Forms\Components\TextInput::make('dropoff_address')
                            ->label(__('owner/transport.order.label.dropoff'))
                            ->required()
                            ->maxLength(255)
                            ->extraInputAttributes(['data-places-autocomplete' => 'panel', 'autocomplete' => 'off'])
                            ->placeholder(__('owner/transport.order.placeholder.dropoff')),
                        Forms\Components\DatePicker::make('preferred_date')
                            ->label(__('owner/transport.order.label.preferred_date'))
                            ->required()
                            ->minDate(now()->toDateString())
                            ->native(false),
                        Forms\Components\TimePicker::make('preferred_time')
                            ->label(__('owner/transport.order.label.preferred_time'))
                            ->seconds(false),
                        Forms\Components\Select::make('calculation_mode')
                            ->label(__('owner/transport.order.label.mode'))
                            ->options(CalculationMode::options())
                            ->default(CalculationMode::OneWay->value)
                            ->required()
                            ->native(false)
                            ->helperText(__('owner/transport.order.helper.mode')),
                    ]),

                Forms\Components\Section::make(__('owner/transport.order.section.notes'))
                    ->collapsed()
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label(__('owner/transport.order.label.notes'))
                            ->placeholder(__('owner/transport.order.placeholder.notes'))
                            ->rows(4)
                            ->maxLength(2000),
                    ]),

                Forms\Components\Section::make(__('owner/transport.order.section.favorite_transporters'))
                    ->description(__('owner/transport.order.helper.favorite_transporters'))
                    ->collapsed()
                    ->visible(fn () => self::userFavoriteTransporters()->isNotEmpty())
                    ->schema([
                        Forms\Components\Toggle::make('use_favorite_transporters_only')
                            ->label(__('owner/transport.order.label.targeted_mode'))
                            ->helperText(__('owner/transport.order.helper.targeted_mode'))
                            ->live(),
                        Forms\Components\CheckboxList::make('favorite_transporter_ids')
                            ->label(__('owner/transport.order.label.favorite_transporters'))
                            ->options(fn () => self::userFavoriteTransporters()
                                ->mapWithKeys(fn ($fav) => [$fav->transporter_tenant_id => (string) ($fav->transporter->name ?? $fav->transporter_tenant_id)])
                                ->all())
                            ->visible(fn (Forms\Get $get) => (bool) $get('use_favorite_transporters_only'))
                            ->columns(2)
                            ->bulkToggleable(),
                    ]),
            ]);
    }

    /**
     * Akcja w header: zapisz aktualną parę pickup/dropoff jako "ulubioną
     * trasę" pod label'em (modal z input'em). Dla powtarzalnych zleceń
     * (klinika weterynaryjna, zawody) — owner wybiera potem z dropdown'u
     * "Favorite route" na górze formularza.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save_as_favorite_route')
                ->label(__('owner/transport.order.action.save_as_favorite.label'))
                ->icon('heroicon-o-bookmark')
                ->color('gray')
                ->form([
                    Forms\Components\TextInput::make('label')
                        ->label(__('owner/transport.order.action.save_as_favorite.label_input'))
                        ->required()
                        ->maxLength(120)
                        ->placeholder(__('owner/transport.order.action.save_as_favorite.placeholder')),
                ])
                ->action(function (array $data) {
                    $state = $this->form->getState();
                    $pickup = trim((string) ($state['pickup_address'] ?? ''));
                    $dropoff = trim((string) ($state['dropoff_address'] ?? ''));
                    if ($pickup === '' || $dropoff === '') {
                        Notification::make()->danger()
                            ->title(__('owner/transport.order.action.save_as_favorite.missing_addresses'))
                            ->send();

                        return;
                    }

                    OwnerFavoriteRoute::create([
                        'id' => (string) Str::ulid(),
                        'owner_user_id' => Auth::id(),
                        'label' => $data['label'],
                        'pickup_address' => $pickup,
                        'dropoff_address' => $dropoff,
                        'notes' => $state['notes'] ?? null,
                    ]);

                    Notification::make()->success()
                        ->title(__('owner/transport.order.action.save_as_favorite.success', ['label' => $data['label']]))
                        ->send();
                }),
        ];
    }

    /** @return Collection<int,OwnerFavoriteRoute> */
    private static function userFavoriteRoutes(): Collection
    {
        return OwnerFavoriteRoute::query()
            ->where('owner_user_id', Auth::id() ?? '_no_user_')
            ->orderBy('label')
            ->get();
    }

    /** @return Collection<int,OwnerFavoriteTransporter> */
    private static function userFavoriteTransporters(): Collection
    {
        return OwnerFavoriteTransporter::query()
            ->where('owner_user_id', Auth::id() ?? '_no_user_')
            ->with('transporter:id,name,slug')
            ->get();
    }

    public function submit(MapboxGeocoder $geocoder, LeadDispatcher $dispatcher): ?RedirectResponse
    {
        $form = $this->form->getState();

        try {
            $from = $geocoder->geocode((string) $form['pickup_address']);
            $to = $geocoder->geocode((string) $form['dropoff_address']);
        } catch (GeocodingException $e) {
            Notification::make()
                ->danger()
                ->title(__('owner/transport.order.notify.geocoding_failed_title'))
                ->body($e->getMessage())
                ->send();

            return null;
        }

        $mode = CalculationMode::tryFrom((string) ($form['calculation_mode'] ?? ''))
            ?? CalculationMode::OneWay;

        $user = Auth::user();
        // OwnerPanelProvider hydratuje tenant przez InitialiseTenantFromSession;
        // sięgamy do TenantManager bo to source of truth (a `tenant()` helper'a
        // tu nie ma w SDK).
        $tenant = app(TenantManager::class)->current();

        // PR feature — targeted mode jeśli owner zaznaczył "Wyślij tylko
        // do ulubionych" + ma valid list ID'ów (anti-injection: filter
        // przez OwnerFavoriteTransporter żeby user nie mógł wstrzyknąć
        // cudzego transporter_tenant_id w request).
        $targetedIds = null;
        $mode_str = 'broadcast';
        if (! empty($form['use_favorite_transporters_only']) && ! empty($form['favorite_transporter_ids'])) {
            $requestedIds = array_filter((array) $form['favorite_transporter_ids']);
            $validIds = OwnerFavoriteTransporter::query()
                ->where('owner_user_id', $user?->id)
                ->whereIn('transporter_tenant_id', $requestedIds)
                ->pluck('transporter_tenant_id')
                ->all();
            if ($validIds !== []) {
                $targetedIds = $validIds;
                $mode_str = 'targeted';
            }
        }

        try {
            $lead = TransportLead::create([
                'id' => (string) Str::ulid(),
                'access_slug' => (string) Str::uuid(),
                'mode' => $mode_str,
                'targeted_transporter_ids' => $targetedIds,
                'originator_tenant_id' => $tenant?->id,
                'originator_user_id' => $user?->id,
                'originator_name' => (string) ($user?->name ?? ''),
                'originator_email' => (string) ($user?->email ?? ''),
                'originator_phone' => null,
                'pickup_address' => $from->displayName,
                'pickup_lat' => $from->coords->lat,
                'pickup_lng' => $from->coords->lng,
                'pickup_voivodeship' => $from->voivodeship ?? '',
                'dropoff_address' => $to->displayName,
                'dropoff_lat' => $to->coords->lat,
                'dropoff_lng' => $to->coords->lng,
                'dropoff_voivodeship' => $to->voivodeship ?? '',
                'preferred_date' => $form['preferred_date'],
                'preferred_time' => $form['preferred_time'] ?? null,
                'flexible_date' => false,
                'horse_count' => 1,
                'notes' => $form['notes'] ?? null,
                'status' => 'open',
                'expires_at' => Carbon::now()->addDays((int) config('transport.leads.expires_after_days', 14)),
            ]);
        } catch (Throwable $e) {
            report($e);
            Notification::make()
                ->danger()
                ->title(__('owner/transport.order.notify.failed_title'))
                ->body(__('owner/transport.order.notify.failed_body'))
                ->send();

            return null;
        }

        // Lokalny rekord — soft FK do central lead'a. Tworzymy ZAWSZE, nawet
        // gdy dispatcher nie znajdzie żadnego transportera; owner zobaczy
        // status='open' i będzie mógł obserwować czy ktoś odpowiedział.
        $order = TransportOrder::create([
            'id' => (string) Str::ulid(),
            'central_lead_id' => $lead->id,
            'horse_id' => $form['horse_id'] ?? null,
            'pickup_address' => $from->displayName,
            'dropoff_address' => $to->displayName,
            'preferred_date' => $form['preferred_date'],
            'preferred_time' => $form['preferred_time'] ?? null,
            'calculation_mode' => $mode->value,
            'status' => 'open',
            'notes' => $form['notes'] ?? null,
        ]);

        // Dispatch — soft fail. Jeśli wysyłka się wywali, lead i tak żyje
        // w central'u; transporter może go zobaczyć w Open Board (PR 8).
        try {
            $dispatcher->dispatch($lead);
        } catch (Throwable $e) {
            Log::warning('Owner OrderTransport dispatch failed', [
                'lead_id' => $lead->id,
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }

        Notification::make()
            ->success()
            ->title(__('owner/transport.order.notify.success_title'))
            ->body(__('owner/transport.order.notify.success_body'))
            ->send();

        return redirect()->to(
            TransportOrderResource::getUrl('view', ['record' => $order->id])
        );
    }
}
