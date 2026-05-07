<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Enums\PaymentProvider;
use App\Services\Payments\Providers\MolliePaymentProvider;
use App\Services\Payments\Providers\StripePaymentProvider;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

/**
 * Per-stable payment provider configuration. Stable owners pick which
 * gateway they want to use and paste their API credentials.
 *
 * Sensitive fields (api_key, client_secret, ...) are encrypted at rest
 * with Laravel's Crypt — same pattern as tenants.db_password_encrypted.
 *
 * Visibility per provider is reactive — only the config block for the
 * selected default provider is shown, plus an "advanced" toggle to
 * pre-configure others (e.g. for testing fallback).
 */
class PaymentSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Płatności online';

    protected static ?string $title = 'Płatności online';

    protected static ?string $navigationGroup = 'Stajnia';

    protected static ?int $navigationSort = 98;

    protected static string $view = 'filament.pages.payment-settings';

    /** @var array<string,mixed> */
    public array $data = [];

    /**
     * Sensitive fields that should be encrypted before persisting and
     * decrypted only when displayed back. Per-provider keyed by enum
     * value.
     *
     * @var array<string,array<int,string>>
     */
    private const ENCRYPTED_FIELDS = [
        'p24' => ['crc_key', 'api_key'],
        'payu' => ['client_secret', 'md5_key'],
        'stripe' => ['secret_key', 'webhook_secret'],
        'mollie' => ['api_key'],
    ];

    public static function canAccess(): bool
    {
        $tenant = app(TenantManager::class)->current();
        if (! $tenant) {
            return false;
        }
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        return $tenant->memberships()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->whereIn('role', ['owner', 'admin'])
            ->exists();
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);

        $tenant = app(TenantManager::class)->tenantOrFail();
        $payments = (array) (data_get($tenant->settings, 'payments') ?? []);

        $decoded = ['default_provider' => $payments['default_provider'] ?? 'none'];

        foreach (self::ENCRYPTED_FIELDS as $provider => $fields) {
            $providerCfg = (array) ($payments[$provider] ?? []);
            foreach ($providerCfg as $k => $v) {
                if (in_array($k, $fields, true) && is_string($v) && $v !== '') {
                    try {
                        $providerCfg[$k] = Crypt::decryptString($v);
                    } catch (\Throwable) {
                        $providerCfg[$k] = '';
                    }
                }
            }
            $decoded[$provider] = $providerCfg;
        }

        $this->form->fill($decoded);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make('Domyślny dostawca')
                    ->description('Wybierz, przez którą bramkę klienci mają płacić online. "Brak" = wszystko offline (przelew tradycyjny / gotówka).')
                    ->schema([
                        Forms\Components\Select::make('default_provider')
                            ->label('Domyślna bramka')
                            ->options(PaymentProvider::tenantOptions())
                            ->required()
                            ->reactive()
                            ->default('none'),
                    ]),

                Forms\Components\Section::make('Przelewy24')
                    ->visible(fn (Forms\Get $get) => $get('default_provider') === 'p24')
                    ->collapsed(false)
                    ->schema($this->p24Schema()),

                Forms\Components\Section::make('PayU')
                    ->visible(fn (Forms\Get $get) => $get('default_provider') === 'payu')
                    ->collapsed(false)
                    ->schema($this->payuSchema()),

                Forms\Components\Section::make('Stripe')
                    ->visible(fn (Forms\Get $get) => $get('default_provider') === 'stripe')
                    ->collapsed(false)
                    ->schema($this->stripeSchema()),

                Forms\Components\Section::make('Mollie')
                    ->visible(fn (Forms\Get $get) => $get('default_provider') === 'mollie')
                    ->collapsed(false)
                    ->schema($this->mollieSchema()),
            ]);
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);

        $tenant = app(TenantManager::class)->tenantOrFail();
        $form = $this->form->getState();

        $persisted = ['default_provider' => $form['default_provider'] ?? 'none'];

        foreach (self::ENCRYPTED_FIELDS as $provider => $fields) {
            $providerCfg = (array) ($form[$provider] ?? []);
            foreach ($providerCfg as $k => $v) {
                if (in_array($k, $fields, true) && is_string($v) && $v !== '') {
                    $providerCfg[$k] = Crypt::encryptString($v);
                }
            }
            if (! empty($providerCfg)) {
                $persisted[$provider] = $providerCfg;
            }
        }

        $settings = (array) ($tenant->settings ?? []);
        $settings['payments'] = $persisted;
        $tenant->forceFill(['settings' => $settings])->save();

        app(TenantAuditLogger::class)->record('payments.settings_updated', 'Tenant', (string) $tenant->id, [
            'default_provider' => $persisted['default_provider'],
        ]);

        Notification::make()->title('Zapisano ustawienia płatności')->success()->send();
    }

    /** @return array<int, Forms\Components\Component> */
    private function p24Schema(): array
    {
        return [
            Forms\Components\TextInput::make('p24.merchant_id')->label('Merchant ID')->required(),
            Forms\Components\TextInput::make('p24.pos_id')->label('POS ID')->required(),
            Forms\Components\TextInput::make('p24.crc_key')->label('CRC key')->password()->revealable()->required(),
            Forms\Components\TextInput::make('p24.api_key')->label('API key')->password()->revealable()->required(),
            Forms\Components\Toggle::make('p24.sandbox')->label('Sandbox (test)')->default(true),
        ];
    }

    /** @return array<int, Forms\Components\Component> */
    private function payuSchema(): array
    {
        return [
            Forms\Components\TextInput::make('payu.pos_id')->label('POS ID')->required(),
            Forms\Components\TextInput::make('payu.client_id')->label('OAuth client_id')->required(),
            Forms\Components\TextInput::make('payu.client_secret')->label('OAuth client_secret')->password()->revealable()->required(),
            Forms\Components\TextInput::make('payu.md5_key')->label('Klucz drugi (MD5)')->password()->revealable()->required(),
            Forms\Components\Toggle::make('payu.sandbox')->label('Sandbox (test)')->default(true),
        ];
    }

    /** @return array<int, Forms\Components\Component> */
    private function stripeSchema(): array
    {
        return [
            Forms\Components\TextInput::make('stripe.publishable_key')->label('Publishable key (pk_...)'),
            Forms\Components\TextInput::make('stripe.secret_key')->label('Secret key (sk_...)')->password()->revealable()->required(),
            Forms\Components\TextInput::make('stripe.webhook_secret')->label('Webhook secret (whsec_...)')->password()->revealable()->required()
                ->helperText('Skopiuj ze Stripe Dashboard → Developers → Webhooks → endpoint → Signing secret.'),
            Forms\Components\CheckboxList::make('stripe.enabled_methods')
                ->label('Pokazywane metody płatności')
                ->helperText('Wybierz, które opcje klient zobaczy w Stripe Checkout. Domyślnie tylko karty.')
                ->options(StripePaymentProvider::methodOptions())
                ->columns(2)
                ->default(['card']),
        ];
    }

    /** @return array<int, Forms\Components\Component> */
    private function mollieSchema(): array
    {
        return [
            Forms\Components\TextInput::make('mollie.api_key')->label('API key (live_... lub test_...)')->password()->revealable()->required()
                ->helperText('Pobierz z Mollie Dashboard → Developers → API keys.'),
            Forms\Components\CheckboxList::make('mollie.enabled_methods')
                ->label('Pokazywane metody płatności')
                ->helperText('Pusta lista = Mollie pokaże wszystkie metody aktywne na Twoim koncie. Pojedyncza metoda = klient idzie od razu do tej metody (np. od razu BLIK).')
                ->options(MolliePaymentProvider::methodOptions())
                ->columns(2),
        ];
    }
}
