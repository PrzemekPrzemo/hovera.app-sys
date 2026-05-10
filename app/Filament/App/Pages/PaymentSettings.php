<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Enums\PaymentProvider;
use App\Filament\Concerns\RestrictedByTenantRole;
use App\Services\Payments\Providers\MolliePaymentProvider;
use App\Services\Payments\Providers\P24PaymentProvider;
use App\Services\Payments\Providers\PayUPaymentProvider;
use App\Services\Payments\Providers\StripePaymentProvider;
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
    use RestrictedByTenantRole;

    protected static function allowedRoles(): array
    {
        return TenantRoleGate::FULL_ADMINS;
    }

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return __('pages.payment_settings.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.settings');
    }

    public function getTitle(): string|Htmlable
    {
        return __('pages.payment_settings.title');
    }

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
                Forms\Components\Section::make(__('app/payment_settings.form.section.default_provider'))
                    ->description(__('app/payment_settings.form.section.default_provider_description'))
                    ->schema([
                        Forms\Components\Select::make('default_provider')
                            ->label(__('app/payment_settings.form.label.default_provider'))
                            ->options(PaymentProvider::tenantOptions())
                            ->required()
                            ->reactive()
                            ->default('none'),
                    ]),

                Forms\Components\Section::make(__('app/payment_settings.form.section.p24'))
                    ->visible(fn (Forms\Get $get) => $get('default_provider') === 'p24')
                    ->collapsed(false)
                    ->schema($this->p24Schema()),

                Forms\Components\Section::make(__('app/payment_settings.form.section.payu'))
                    ->visible(fn (Forms\Get $get) => $get('default_provider') === 'payu')
                    ->collapsed(false)
                    ->schema($this->payuSchema()),

                Forms\Components\Section::make(__('app/payment_settings.form.section.stripe'))
                    ->visible(fn (Forms\Get $get) => $get('default_provider') === 'stripe')
                    ->collapsed(false)
                    ->schema($this->stripeSchema()),

                Forms\Components\Section::make(__('app/payment_settings.form.section.mollie'))
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

        Notification::make()->title(__('app/payment_settings.action.saved'))->success()->send();
    }

    /** @return array<int, Forms\Components\Component> */
    private function p24Schema(): array
    {
        return [
            Forms\Components\TextInput::make('p24.merchant_id')
                ->label(__('app/payment_settings.form.label.p24_merchant_id'))->required(),
            Forms\Components\TextInput::make('p24.pos_id')
                ->label(__('app/payment_settings.form.label.p24_pos_id'))->required(),
            Forms\Components\TextInput::make('p24.crc_key')
                ->label(__('app/payment_settings.form.label.p24_crc_key'))->password()->revealable()->required(),
            Forms\Components\TextInput::make('p24.api_key')
                ->label(__('app/payment_settings.form.label.p24_api_key'))->password()->revealable()->required()
                ->helperText(__('app/payment_settings.form.label.p24_api_key_helper')),
            Forms\Components\Toggle::make('p24.sandbox')
                ->label(__('app/payment_settings.form.label.p24_sandbox'))->default(true),
            Forms\Components\Select::make('p24.force_method')
                ->label(__('app/payment_settings.form.label.p24_force_method'))
                ->helperText(__('app/payment_settings.form.label.p24_force_method_helper'))
                ->options(P24PaymentProvider::methodOptions())
                ->placeholder(__('app/payment_settings.form.label.force_method_placeholder')),
        ];
    }

    /** @return array<int, Forms\Components\Component> */
    private function payuSchema(): array
    {
        return [
            Forms\Components\TextInput::make('payu.pos_id')
                ->label(__('app/payment_settings.form.label.payu_pos_id'))->required(),
            Forms\Components\TextInput::make('payu.client_id')
                ->label(__('app/payment_settings.form.label.payu_client_id'))->required(),
            Forms\Components\TextInput::make('payu.client_secret')
                ->label(__('app/payment_settings.form.label.payu_client_secret'))->password()->revealable()->required(),
            Forms\Components\TextInput::make('payu.md5_key')
                ->label(__('app/payment_settings.form.label.payu_md5_key'))->password()->revealable()->required()
                ->helperText(__('app/payment_settings.form.label.payu_md5_key_helper')),
            Forms\Components\Toggle::make('payu.sandbox')
                ->label(__('app/payment_settings.form.label.payu_sandbox'))->default(true),
            Forms\Components\Select::make('payu.force_method')
                ->label(__('app/payment_settings.form.label.payu_force_method'))
                ->helperText(__('app/payment_settings.form.label.payu_force_method_helper'))
                ->options(PayUPaymentProvider::methodOptions())
                ->placeholder(__('app/payment_settings.form.label.force_method_placeholder')),
        ];
    }

    /** @return array<int, Forms\Components\Component> */
    private function stripeSchema(): array
    {
        return [
            Forms\Components\TextInput::make('stripe.publishable_key')
                ->label(__('app/payment_settings.form.label.stripe_publishable_key')),
            Forms\Components\TextInput::make('stripe.secret_key')
                ->label(__('app/payment_settings.form.label.stripe_secret_key'))->password()->revealable()->required(),
            Forms\Components\TextInput::make('stripe.webhook_secret')
                ->label(__('app/payment_settings.form.label.stripe_webhook_secret'))->password()->revealable()->required()
                ->helperText(__('app/payment_settings.form.label.stripe_webhook_secret_helper')),
            Forms\Components\CheckboxList::make('stripe.enabled_methods')
                ->label(__('app/payment_settings.form.label.stripe_enabled_methods'))
                ->helperText(__('app/payment_settings.form.label.stripe_enabled_methods_helper'))
                ->options(StripePaymentProvider::methodOptions())
                ->columns(2)
                ->default(['card']),
        ];
    }

    /** @return array<int, Forms\Components\Component> */
    private function mollieSchema(): array
    {
        return [
            Forms\Components\TextInput::make('mollie.api_key')
                ->label(__('app/payment_settings.form.label.mollie_api_key'))->password()->revealable()->required()
                ->helperText(__('app/payment_settings.form.label.mollie_api_key_helper')),
            Forms\Components\CheckboxList::make('mollie.enabled_methods')
                ->label(__('app/payment_settings.form.label.mollie_enabled_methods'))
                ->helperText(__('app/payment_settings.form.label.mollie_enabled_methods_helper'))
                ->options(MolliePaymentProvider::methodOptions())
                ->columns(2),
        ];
    }
}
