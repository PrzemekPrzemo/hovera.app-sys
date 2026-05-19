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
 * Master-admin: konfiguracja PayU dla hovery (Hovera-as-merchant dla SaaS
 * billing — FV za subskrypcje + add-ony). Patrz docs/TRANSPORT.md §16.
 *
 * Pełen parity z `Przelewy24Settings` — wartości lądują w `system_settings`
 * pod kluczami `payu.*` (analogicznie do `przelewy24.*`). Secrets
 * (`oauth_client_secret`, `md5_key`, `second_key`) szyfrowane przez
 * SystemSetting::setSecret.
 *
 * Public fields (pos_id, oauth_client_id, env) trzymane plaintekstem.
 *
 * UWAGA: PayUService w obecnym stanie czyta wciąż z `config('services.payu')`
 * (env vars). Ten UI zapisuje do SystemSetting jako forward-compat — przyszły
 * PR doda boot-override (analogicznie wymaga refactor Przelewy24Service).
 */
class PayUSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?int $navigationSort = 16;

    protected static string $view = 'filament.admin.pages.payu-settings';

    /** @var array<string,mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->is_master_admin;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin/payu.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.configuration');
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin/payu.title');
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);

        $this->form->fill([
            'pos_id' => SystemSetting::getValue('payu.pos_id', (string) config('services.payu.pos_id', '')),
            'oauth_client_id' => SystemSetting::getValue('payu.oauth_client_id', (string) config('services.payu.oauth_client_id', '')),
            'env' => SystemSetting::getValue('payu.env', (string) config('services.payu.env', 'sandbox')),
            // Secret fields — nie pokazujemy plaintextu, tylko status ✓ jeśli ustawione.
            'oauth_client_secret_set' => SystemSetting::getSecret('payu.oauth_client_secret') !== null,
            'md5_key_set' => SystemSetting::getSecret('payu.md5_key') !== null,
            'second_key_set' => SystemSetting::getSecret('payu.second_key') !== null,
            'oauth_client_secret' => null,
            'md5_key' => null,
            'second_key' => null,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('admin/payu.section.account'))
                    ->description(__('admin/payu.section.account_help'))
                    ->schema([
                        Forms\Components\Radio::make('env')
                            ->label(__('admin/payu.field.env'))
                            ->options([
                                'sandbox' => __('admin/payu.env.sandbox'),
                                'production' => __('admin/payu.env.production'),
                            ])
                            ->required()
                            ->default('sandbox')
                            ->inline()
                            ->inlineLabel(false),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('pos_id')
                                    ->label(__('admin/payu.field.pos_id'))
                                    ->required()
                                    ->numeric()
                                    ->helperText(__('admin/payu.field.pos_id_help')),
                                Forms\Components\TextInput::make('oauth_client_id')
                                    ->label(__('admin/payu.field.oauth_client_id'))
                                    ->required()
                                    ->maxLength(64)
                                    ->helperText(__('admin/payu.field.oauth_client_id_help')),
                            ]),
                    ]),

                Forms\Components\Section::make(__('admin/payu.section.secrets'))
                    ->description(__('admin/payu.section.secrets_help'))
                    ->schema([
                        Forms\Components\Placeholder::make('oauth_client_secret_status')
                            ->label(__('admin/payu.field.oauth_client_secret_status'))
                            ->content(fn () => $this->data['oauth_client_secret_set'] ?? false
                                ? '✓ '.__('admin/payu.status.configured')
                                : '✗ '.__('admin/payu.status.not_configured')),
                        Forms\Components\TextInput::make('oauth_client_secret')
                            ->label(__('admin/payu.field.oauth_client_secret'))
                            ->password()
                            ->revealable()
                            ->maxLength(200)
                            ->helperText(__('admin/payu.field.oauth_client_secret_help'))
                            ->dehydrated(fn ($state) => filled($state)),

                        Forms\Components\Placeholder::make('md5_key_status')
                            ->label(__('admin/payu.field.md5_key_status'))
                            ->content(fn () => $this->data['md5_key_set'] ?? false
                                ? '✓ '.__('admin/payu.status.configured')
                                : '✗ '.__('admin/payu.status.not_configured')),
                        Forms\Components\TextInput::make('md5_key')
                            ->label(__('admin/payu.field.md5_key'))
                            ->password()
                            ->revealable()
                            ->maxLength(200)
                            ->helperText(__('admin/payu.field.md5_key_help'))
                            ->dehydrated(fn ($state) => filled($state)),

                        Forms\Components\Placeholder::make('second_key_status')
                            ->label(__('admin/payu.field.second_key_status'))
                            ->content(fn () => $this->data['second_key_set'] ?? false
                                ? '✓ '.__('admin/payu.status.configured')
                                : '✗ '.__('admin/payu.status.not_configured')),
                        Forms\Components\TextInput::make('second_key')
                            ->label(__('admin/payu.field.second_key'))
                            ->password()
                            ->revealable()
                            ->maxLength(200)
                            ->helperText(__('admin/payu.field.second_key_help'))
                            ->dehydrated(fn ($state) => filled($state)),
                    ]),

                Forms\Components\Section::make(__('admin/payu.section.webhook'))
                    ->description(__('admin/payu.section.webhook_help'))
                    ->schema([
                        Forms\Components\Placeholder::make('webhook_url')
                            ->label(__('admin/payu.field.webhook_url'))
                            ->content(fn () => url('/webhooks/payu')),
                        Forms\Components\Placeholder::make('return_url')
                            ->label(__('admin/payu.field.return_url'))
                            ->content(fn () => url('/payments/payu/return/{invoice_id}')),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);

        $payload = $this->form->getState();

        SystemSetting::setValue('payu.pos_id', (string) ($payload['pos_id'] ?? ''));
        SystemSetting::setValue('payu.oauth_client_id', (string) ($payload['oauth_client_id'] ?? ''));
        SystemSetting::setValue('payu.env', (string) ($payload['env'] ?? 'sandbox'));

        if (filled($payload['oauth_client_secret'] ?? null)) {
            SystemSetting::setSecret('payu.oauth_client_secret', (string) $payload['oauth_client_secret']);
        }
        if (filled($payload['md5_key'] ?? null)) {
            SystemSetting::setSecret('payu.md5_key', (string) $payload['md5_key']);
        }
        if (filled($payload['second_key'] ?? null)) {
            SystemSetting::setSecret('payu.second_key', (string) $payload['second_key']);
        }

        Notification::make()
            ->title(__('admin/payu.action.saved'))
            ->success()
            ->send();

        // Reset password fields w UI, status zostaje ✓ jeśli było zapisane.
        $this->data['oauth_client_secret'] = null;
        $this->data['md5_key'] = null;
        $this->data['second_key'] = null;
        $this->data['oauth_client_secret_set'] = SystemSetting::getSecret('payu.oauth_client_secret') !== null;
        $this->data['md5_key_set'] = SystemSetting::getSecret('payu.md5_key') !== null;
        $this->data['second_key_set'] = SystemSetting::getSecret('payu.second_key') !== null;
    }
}
