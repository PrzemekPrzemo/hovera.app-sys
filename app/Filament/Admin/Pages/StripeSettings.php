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
use Illuminate\Support\HtmlString;

/**
 * Master-admin: konfiguracja Stripe API (subskrypcje recurring SaaS hovery).
 *
 * Wcześniej tylko env vars (STRIPE_KEY, STRIPE_SECRET, STRIPE_WEBHOOK_SECRET).
 * Teraz UI w panelu — wartości w central.system_settings pod kluczem `stripe.*`.
 * StripeBillingService powinien (follow-up) odczytywać priorytetowo z
 * SystemSetting, fallback config('services.stripe').
 *
 * Klucze są secret → szyfrowane przez SystemSetting::setSecret.
 * env (test/live) trzymany plaintekstem (niewrażliwe).
 */
class StripeSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 12;

    protected static string $view = 'filament.admin.pages.stripe-settings';

    /** @var array<string,mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->is_master_admin;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin/stripe.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.configuration');
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin/stripe.title');
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);

        $this->form->fill([
            'env' => SystemSetting::getValue('stripe.env', 'test'),
            'publishable_key_set' => SystemSetting::getSecret('stripe.publishable_key') !== null,
            'secret_key_set' => SystemSetting::getSecret('stripe.secret_key') !== null,
            'webhook_secret_set' => SystemSetting::getSecret('stripe.webhook_secret') !== null,
            'publishable_key' => null,
            'secret_key' => null,
            'webhook_secret' => null,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('admin/stripe.section.env'))
                    ->description(__('admin/stripe.section.env_help'))
                    ->schema([
                        Forms\Components\Radio::make('env')
                            ->label(__('admin/stripe.field.env'))
                            ->options([
                                'test' => __('admin/stripe.env.test'),
                                'live' => __('admin/stripe.env.live'),
                            ])
                            ->required()
                            ->default('test')
                            ->inline()
                            ->inlineLabel(false),
                    ]),

                Forms\Components\Section::make(__('admin/stripe.section.keys'))
                    ->description(__('admin/stripe.section.keys_help'))
                    ->schema([
                        Forms\Components\Placeholder::make('publishable_key_status')
                            ->label(__('admin/stripe.field.publishable_key_status'))
                            ->content(fn () => $this->data['publishable_key_set'] ?? false
                                ? '✓ '.__('admin/stripe.status.configured')
                                : '✗ '.__('admin/stripe.status.not_configured')),
                        Forms\Components\TextInput::make('publishable_key')
                            ->label(__('admin/stripe.field.publishable_key'))
                            ->password()
                            ->revealable()
                            ->maxLength(200)
                            ->helperText(__('admin/stripe.field.publishable_key_help'))
                            ->placeholder('pk_test_… lub pk_live_…')
                            ->dehydrated(fn ($state) => filled($state)),

                        Forms\Components\Placeholder::make('secret_key_status')
                            ->label(__('admin/stripe.field.secret_key_status'))
                            ->content(fn () => $this->data['secret_key_set'] ?? false
                                ? '✓ '.__('admin/stripe.status.configured')
                                : '✗ '.__('admin/stripe.status.not_configured')),
                        Forms\Components\TextInput::make('secret_key')
                            ->label(__('admin/stripe.field.secret_key'))
                            ->password()
                            ->revealable()
                            ->maxLength(200)
                            ->helperText(__('admin/stripe.field.secret_key_help'))
                            ->placeholder('sk_test_… lub sk_live_…')
                            ->dehydrated(fn ($state) => filled($state)),
                    ]),

                Forms\Components\Section::make(__('admin/stripe.section.webhook'))
                    ->description(__('admin/stripe.section.webhook_help'))
                    ->schema([
                        Forms\Components\Placeholder::make('webhook_url')
                            ->label(__('admin/stripe.field.webhook_url'))
                            ->content(fn () => url('/webhooks/stripe')),
                        Forms\Components\Placeholder::make('webhook_secret_status')
                            ->label(__('admin/stripe.field.webhook_secret_status'))
                            ->content(fn () => $this->data['webhook_secret_set'] ?? false
                                ? '✓ '.__('admin/stripe.status.configured')
                                : '✗ '.__('admin/stripe.status.not_configured')),
                        Forms\Components\TextInput::make('webhook_secret')
                            ->label(__('admin/stripe.field.webhook_secret'))
                            ->password()
                            ->revealable()
                            ->maxLength(200)
                            ->helperText(__('admin/stripe.field.webhook_secret_help'))
                            ->placeholder('whsec_…')
                            ->dehydrated(fn ($state) => filled($state)),
                    ]),

                Forms\Components\Section::make(__('admin/stripe.section.events'))
                    ->description(__('admin/stripe.section.events_help'))
                    ->schema([
                        Forms\Components\Placeholder::make('events_list')
                            ->label('')
                            ->content(new HtmlString(
                                '<ul class="list-disc ml-5 text-sm">'.
                                '<li><code>checkout.session.completed</code></li>'.
                                '<li><code>customer.subscription.updated</code></li>'.
                                '<li><code>customer.subscription.deleted</code></li>'.
                                '<li><code>invoice.payment_failed</code></li>'.
                                '</ul>'
                            )),
                    ]),

                Forms\Components\Section::make(__('admin/stripe.section.plan_prices'))
                    ->description(__('admin/stripe.section.plan_prices_help'))
                    ->schema([
                        Forms\Components\Placeholder::make('plan_prices_link')
                            ->label('')
                            ->content(new HtmlString(
                                '<a href="'.url('/admin/plans').'" class="text-primary-600 underline">'.
                                __('admin/stripe.section.plan_prices_link').
                                '</a>'
                            )),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);

        $payload = $this->form->getState();

        SystemSetting::setValue('stripe.env', (string) ($payload['env'] ?? 'test'));

        if (filled($payload['publishable_key'] ?? null)) {
            SystemSetting::setSecret('stripe.publishable_key', (string) $payload['publishable_key']);
        }
        if (filled($payload['secret_key'] ?? null)) {
            SystemSetting::setSecret('stripe.secret_key', (string) $payload['secret_key']);
        }
        if (filled($payload['webhook_secret'] ?? null)) {
            SystemSetting::setSecret('stripe.webhook_secret', (string) $payload['webhook_secret']);
        }

        Notification::make()
            ->title(__('admin/stripe.action.saved'))
            ->success()
            ->send();

        $this->data['publishable_key'] = null;
        $this->data['secret_key'] = null;
        $this->data['webhook_secret'] = null;
        $this->data['publishable_key_set'] = SystemSetting::getSecret('stripe.publishable_key') !== null;
        $this->data['secret_key_set'] = SystemSetting::getSecret('stripe.secret_key') !== null;
        $this->data['webhook_secret_set'] = SystemSetting::getSecret('stripe.webhook_secret') !== null;
    }
}
