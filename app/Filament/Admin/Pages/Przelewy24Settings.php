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
 * Master-admin: konfiguracja Przelewy24 dla hovery (link "opłać fakturę"
 * dla FV SaaS wystawianych stajniom).
 *
 * Wcześniej wymagało edycji .env (P24_MERCHANT_ID, P24_POS_ID, P24_API_KEY,
 * P24_CRC, P24_ENV). Teraz UI w panelu master admina — wartości lądują w
 * central.system_settings pod kluczami `przelewy24.*`. Przelewy24Service
 * odczytuje najpierw z SystemSetting, fallback na config('services.przelewy24').
 *
 * api_key + crc są secret → szyfrowane przez SystemSetting::setSecret.
 * merchant_id, pos_id, env trzymane plaintekstem (niewrażliwe).
 */
class Przelewy24Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?int $navigationSort = 15;

    protected static string $view = 'filament.admin.pages.przelewy24-settings';

    /** @var array<string,mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->is_master_admin;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin/przelewy24.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.configuration');
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin/przelewy24.title');
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);

        $this->form->fill([
            'merchant_id' => SystemSetting::getValue('przelewy24.merchant_id', (string) config('services.przelewy24.merchant_id', '')),
            'pos_id' => SystemSetting::getValue('przelewy24.pos_id', (string) config('services.przelewy24.pos_id', '')),
            'env' => SystemSetting::getValue('przelewy24.env', (string) config('services.przelewy24.env', 'sandbox')),
            // Secret fields — nie pokazujemy plaintextu, tylko placeholder "********" jeśli ustawione
            'api_key_set' => SystemSetting::getSecret('przelewy24.api_key') !== null,
            'crc_set' => SystemSetting::getSecret('przelewy24.crc') !== null,
            'api_key' => null,
            'crc' => null,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('admin/przelewy24.section.account'))
                    ->description(__('admin/przelewy24.section.account_help'))
                    ->schema([
                        Forms\Components\Radio::make('env')
                            ->label(__('admin/przelewy24.field.env'))
                            ->options([
                                'sandbox' => __('admin/przelewy24.env.sandbox'),
                                'production' => __('admin/przelewy24.env.production'),
                            ])
                            ->required()
                            ->default('sandbox')
                            ->inline()
                            ->inlineLabel(false),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('merchant_id')
                                    ->label(__('admin/przelewy24.field.merchant_id'))
                                    ->required()
                                    ->numeric()
                                    ->helperText(__('admin/przelewy24.field.merchant_id_help')),
                                Forms\Components\TextInput::make('pos_id')
                                    ->label(__('admin/przelewy24.field.pos_id'))
                                    ->required()
                                    ->numeric()
                                    ->helperText(__('admin/przelewy24.field.pos_id_help')),
                            ]),
                    ]),

                Forms\Components\Section::make(__('admin/przelewy24.section.secrets'))
                    ->description(__('admin/przelewy24.section.secrets_help'))
                    ->schema([
                        Forms\Components\Placeholder::make('api_key_status')
                            ->label(__('admin/przelewy24.field.api_key_status'))
                            ->content(fn () => $this->data['api_key_set'] ?? false
                                ? '✓ '.__('admin/przelewy24.status.configured')
                                : '✗ '.__('admin/przelewy24.status.not_configured')),
                        Forms\Components\TextInput::make('api_key')
                            ->label(__('admin/przelewy24.field.api_key'))
                            ->password()
                            ->revealable()
                            ->maxLength(200)
                            ->helperText(__('admin/przelewy24.field.api_key_help'))
                            ->dehydrated(fn ($state) => filled($state)),

                        Forms\Components\Placeholder::make('crc_status')
                            ->label(__('admin/przelewy24.field.crc_status'))
                            ->content(fn () => $this->data['crc_set'] ?? false
                                ? '✓ '.__('admin/przelewy24.status.configured')
                                : '✗ '.__('admin/przelewy24.status.not_configured')),
                        Forms\Components\TextInput::make('crc')
                            ->label(__('admin/przelewy24.field.crc'))
                            ->password()
                            ->revealable()
                            ->maxLength(200)
                            ->helperText(__('admin/przelewy24.field.crc_help'))
                            ->dehydrated(fn ($state) => filled($state)),
                    ]),

                Forms\Components\Section::make(__('admin/przelewy24.section.webhook'))
                    ->description(__('admin/przelewy24.section.webhook_help'))
                    ->schema([
                        Forms\Components\Placeholder::make('webhook_url')
                            ->label(__('admin/przelewy24.field.webhook_url'))
                            ->content(fn () => url('/webhooks/przelewy24')),
                        Forms\Components\Placeholder::make('return_url')
                            ->label(__('admin/przelewy24.field.return_url'))
                            ->content(fn () => url('/payments/p24/return/{invoiceId}')),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);

        $payload = $this->form->getState();

        SystemSetting::setValue('przelewy24.merchant_id', (string) ($payload['merchant_id'] ?? ''));
        SystemSetting::setValue('przelewy24.pos_id', (string) ($payload['pos_id'] ?? ''));
        SystemSetting::setValue('przelewy24.env', (string) ($payload['env'] ?? 'sandbox'));

        if (filled($payload['api_key'] ?? null)) {
            SystemSetting::setSecret('przelewy24.api_key', (string) $payload['api_key']);
        }
        if (filled($payload['crc'] ?? null)) {
            SystemSetting::setSecret('przelewy24.crc', (string) $payload['crc']);
        }

        Notification::make()
            ->title(__('admin/przelewy24.action.saved'))
            ->success()
            ->send();

        // Reset password fields w UI, status zostaje ✓ jeśli było zapisane
        $this->data['api_key'] = null;
        $this->data['crc'] = null;
        $this->data['api_key_set'] = SystemSetting::getSecret('przelewy24.api_key') !== null;
        $this->data['crc_set'] = SystemSetting::getSecret('przelewy24.crc') !== null;
    }
}
