<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Models\Central\SystemSetting;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

/**
 * Master-admin: dane spółki hovera (sprzedawca na fakturach SaaS-owych
 * wystawianych stajniom). Używane przez CentralKsefService przy generowaniu
 * XML FA(3), Przelewy24Service przy parametrach session i PDF generator
 * dla nagłówka faktury.
 *
 * Wszystkie wartości w `central.system_settings` pod kluczami `hovera_company.*`.
 * Dane firmowe NIE są secret — zapisujemy plaintekstem przez setValue/getValue.
 */
class HoveraCompanySettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.admin.pages.hovera-company-settings';

    /** @var array<string,mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->is_master_admin;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin/hovera_company.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.configuration');
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin/hovera_company.title');
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);

        $this->form->fill([
            'name' => SystemSetting::getValue('hovera_company.name'),
            'legal_form' => SystemSetting::getValue('hovera_company.legal_form', 'sp. z o.o.'),
            'nip' => SystemSetting::getValue('hovera_company.nip'),
            'regon' => SystemSetting::getValue('hovera_company.regon'),
            'krs' => SystemSetting::getValue('hovera_company.krs'),
            'court' => SystemSetting::getValue('hovera_company.court'),
            'capital' => SystemSetting::getValue('hovera_company.capital'),
            'street' => SystemSetting::getValue('hovera_company.street'),
            'postal_code' => SystemSetting::getValue('hovera_company.postal_code'),
            'city' => SystemSetting::getValue('hovera_company.city'),
            'country' => SystemSetting::getValue('hovera_company.country', 'PL'),
            'email' => SystemSetting::getValue('hovera_company.email'),
            'phone' => SystemSetting::getValue('hovera_company.phone'),
            'bank_name' => SystemSetting::getValue('hovera_company.bank_name'),
            'iban' => SystemSetting::getValue('hovera_company.iban'),
            'swift' => SystemSetting::getValue('hovera_company.swift'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('admin/hovera_company.section.identity'))
                    ->description(__('admin/hovera_company.section.identity_help'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('admin/hovera_company.field.name'))
                            ->required()
                            ->maxLength(200),
                        Forms\Components\TextInput::make('legal_form')
                            ->label(__('admin/hovera_company.field.legal_form'))
                            ->maxLength(40)
                            ->placeholder('sp. z o.o.'),
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('nip')
                                    ->label(__('admin/hovera_company.field.nip'))
                                    ->required()
                                    ->maxLength(20),
                                Forms\Components\TextInput::make('regon')
                                    ->label(__('admin/hovera_company.field.regon'))
                                    ->maxLength(20),
                                Forms\Components\TextInput::make('krs')
                                    ->label(__('admin/hovera_company.field.krs'))
                                    ->maxLength(20),
                            ]),
                        Forms\Components\TextInput::make('court')
                            ->label(__('admin/hovera_company.field.court'))
                            ->maxLength(200),
                        Forms\Components\TextInput::make('capital')
                            ->label(__('admin/hovera_company.field.capital'))
                            ->maxLength(40)
                            ->placeholder('5 000 zł'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('admin/hovera_company.section.address'))
                    ->schema([
                        Forms\Components\TextInput::make('street')
                            ->label(__('admin/hovera_company.field.street'))
                            ->required()
                            ->maxLength(200),
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('postal_code')
                                    ->label(__('admin/hovera_company.field.postal_code'))
                                    ->required()
                                    ->maxLength(10)
                                    ->placeholder('00-819'),
                                Forms\Components\TextInput::make('city')
                                    ->label(__('admin/hovera_company.field.city'))
                                    ->required()
                                    ->maxLength(80),
                                Forms\Components\TextInput::make('country')
                                    ->label(__('admin/hovera_company.field.country'))
                                    ->required()
                                    ->maxLength(2)
                                    ->placeholder('PL'),
                            ]),
                    ]),

                Forms\Components\Section::make(__('admin/hovera_company.section.contact'))
                    ->schema([
                        Forms\Components\TextInput::make('email')
                            ->label(__('admin/hovera_company.field.email'))
                            ->email()
                            ->maxLength(120),
                        Forms\Components\TextInput::make('phone')
                            ->label(__('admin/hovera_company.field.phone'))
                            ->maxLength(40),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('admin/hovera_company.section.bank'))
                    ->description(__('admin/hovera_company.section.bank_help'))
                    ->schema([
                        Forms\Components\TextInput::make('bank_name')
                            ->label(__('admin/hovera_company.field.bank_name'))
                            ->maxLength(200),
                        Forms\Components\TextInput::make('iban')
                            ->label(__('admin/hovera_company.field.iban'))
                            ->maxLength(40)
                            ->placeholder('PL00 0000 0000 0000 0000 0000 0000'),
                        Forms\Components\TextInput::make('swift')
                            ->label(__('admin/hovera_company.field.swift'))
                            ->maxLength(11)
                            ->placeholder('XYZBPLPWXXX'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);

        $payload = $this->form->getState();

        foreach ($payload as $key => $value) {
            SystemSetting::setValue('hovera_company.'.$key, $value);
        }

        Notification::make()
            ->title(__('admin/hovera_company.action.saved'))
            ->success()
            ->send();
    }
}
