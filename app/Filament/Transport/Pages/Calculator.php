<?php

declare(strict_types=1);

namespace App\Filament\Transport\Pages;

use App\Domain\Transport\Calculator\CalculatorService;
use App\Domain\Transport\Calculator\Data\CalculationOptions;
use App\Domain\Transport\Calculator\Data\Quotation;
use App\Domain\Transport\Geocoding\Exceptions\GeocodingException;
use App\Domain\Transport\Geocoding\MapboxGeocoder;
use App\Domain\Transport\Routing\Exceptions\RoutingException;
use App\Filament\Concerns\RestrictedByTenantRole;
use App\Models\Central\Tenant;
use App\Services\Tenancy\TenantRoleGate;
use App\Tenancy\TenantManager;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
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
        return TenantRoleGate::FULL_ADMINS_AND_MANAGERS;
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
    ];

    public ?Quotation $quotation = null;

    public ?string $fromDisplayName = null;

    public ?string $toDisplayName = null;

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
        $this->form->fill($this->data);
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
                            ->placeholder(__('transport/calculator.form.placeholder.from_address')),
                        Forms\Components\TextInput::make('to_address')
                            ->label(__('transport/calculator.form.label.to_address'))
                            ->required()
                            ->placeholder(__('transport/calculator.form.placeholder.to_address')),
                    ]),
                Forms\Components\Section::make(__('transport/calculator.section.options'))
                    ->columns(3)
                    ->schema([
                        Forms\Components\Toggle::make('loaded')
                            ->label(__('transport/calculator.form.label.loaded'))
                            ->default(true)
                            ->inline(false),
                        Forms\Components\Toggle::make('round_trip')
                            ->label(__('transport/calculator.form.label.round_trip'))
                            ->inline(false),
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

        $geocoder = app(MapboxGeocoder::class);
        try {
            $from = $geocoder->geocode((string) $form['from_address']);
            $to = $geocoder->geocode((string) $form['to_address']);
        } catch (GeocodingException $e) {
            $this->fail($e->getMessage());

            return;
        }

        try {
            $quotation = app(CalculatorService::class)->calculate(
                $tenant,
                $from->coords,
                $to->coords,
                new CalculationOptions(
                    loaded: (bool) ($form['loaded'] ?? true),
                    roundTrip: (bool) ($form['round_trip'] ?? false),
                    avoidTolls: (bool) ($form['avoid_tolls'] ?? false),
                    avoidFerries: (bool) ($form['avoid_ferries'] ?? false),
                    routingProfile: (string) ($form['profile'] ?? 'truck'),
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
        $this->quotation = $quotation;

        Notification::make()
            ->success()
            ->title(__('transport/calculator.action.calculated'))
            ->send();
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
