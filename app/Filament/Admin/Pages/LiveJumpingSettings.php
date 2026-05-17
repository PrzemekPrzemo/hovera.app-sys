<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Models\Central\SystemSetting;
use App\Services\Integrations\LiveJumping\LiveJumpingClient;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

/**
 * Master-admin: konfiguracja partnerskiej integracji LiveJumping.com
 * (wyniki sportowe + kalendarz zawodów).
 *
 * Workflow:
 *   1. Master admin podaje API URL + API token
 *   2. Klika „Testuj połączenie" — sprawdzamy ping endpoint
 *   3. Klika „Start współpracy" — zapisuje credsy + zapala flagę
 *      `livejumping.enabled`
 *
 * Dopóki flaga jest OFF, nic w panelach stajni się nie zmienia.
 * Po ON: pojawia się sekcja Sport w kartach koni/jeźdźców, widget
 * „Nadchodzące starty" na dashboardzie, pasek z zawodami w kalendarzu.
 *
 * URL trzymany plaintekstem (niewrażliwy, sam URL); token szyfrowany
 * przez SystemSetting::setSecret (AES via Laravel Crypt).
 */
class LiveJumpingSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static ?int $navigationSort = 20;

    protected static string $view = 'filament.admin.pages.livejumping-settings';

    /** @var array<string,mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->is_master_admin;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin/livejumping.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.configuration');
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin/livejumping.title');
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);

        $this->form->fill([
            'enabled' => (bool) SystemSetting::getValue('livejumping.enabled', false),
            'api_url' => (string) SystemSetting::getValue('livejumping.api_url', ''),
            'api_token_set' => SystemSetting::getSecret('livejumping.api_token') !== null,
            'api_token' => null,
            'connected_at' => SystemSetting::getValue('livejumping.connected_at'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('admin/livejumping.section.status'))
                    ->description(__('admin/livejumping.section.status_help'))
                    ->schema([
                        Forms\Components\Placeholder::make('status_indicator')
                            ->label(__('admin/livejumping.field.status'))
                            ->content(fn () => ($this->data['enabled'] ?? false)
                                ? '🟢 '.__('admin/livejumping.status.active')
                                : '⚫ '.__('admin/livejumping.status.inactive')),
                        Forms\Components\Placeholder::make('connected_at_display')
                            ->label(__('admin/livejumping.field.connected_at'))
                            ->content(fn () => $this->data['connected_at']
                                ? \Illuminate\Support\Carbon::parse($this->data['connected_at'])->format('Y-m-d H:i')
                                : '—')
                            ->visible(fn () => filled($this->data['connected_at'] ?? null)),
                    ]),

                Forms\Components\Section::make(__('admin/livejumping.section.credentials'))
                    ->description(__('admin/livejumping.section.credentials_help'))
                    ->schema([
                        Forms\Components\TextInput::make('api_url')
                            ->label(__('admin/livejumping.field.api_url'))
                            ->url()
                            ->required()
                            ->maxLength(500)
                            ->placeholder('https://api.livejumping.com')
                            ->helperText(__('admin/livejumping.field.api_url_help')),

                        Forms\Components\Placeholder::make('api_token_status')
                            ->label(__('admin/livejumping.field.api_token_status'))
                            ->content(fn () => ($this->data['api_token_set'] ?? false)
                                ? '✓ '.__('admin/livejumping.status.configured')
                                : '✗ '.__('admin/livejumping.status.not_configured')),
                        Forms\Components\TextInput::make('api_token')
                            ->label(__('admin/livejumping.field.api_token'))
                            ->password()
                            ->revealable()
                            ->maxLength(500)
                            ->placeholder('lj_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxx')
                            ->helperText(__('admin/livejumping.field.api_token_help'))
                            ->dehydrated(fn ($state) => filled($state))
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('testConnection')
                                    ->label(__('admin/livejumping.action.test'))
                                    ->icon('heroicon-o-bolt')
                                    ->action(fn () => $this->testConnection()),
                            ),
                    ]),

                Forms\Components\Section::make(__('admin/livejumping.section.partnership'))
                    ->description(__('admin/livejumping.section.partnership_help'))
                    ->schema([
                        Forms\Components\Toggle::make('enabled')
                            ->label(__('admin/livejumping.field.enabled'))
                            ->helperText(__('admin/livejumping.field.enabled_help'))
                            ->onIcon('heroicon-o-check')
                            ->offIcon('heroicon-o-x-mark'),
                    ]),
            ])
            ->statePath('data');
    }

    public function testConnection(): void
    {
        abort_unless(self::canAccess(), 403);

        $url = (string) ($this->data['api_url'] ?? '');
        $token = (string) ($this->data['api_token'] ?? '')
            ?: (string) SystemSetting::getSecret('livejumping.api_token', '');

        if ($url === '' || $token === '') {
            Notification::make()
                ->title(__('admin/livejumping.action.test_missing_creds'))
                ->warning()
                ->send();

            return;
        }

        $result = app(LiveJumpingClient::class)->testConnection($url, $token);

        if ($result['ok']) {
            Notification::make()
                ->title(__('admin/livejumping.action.test_ok'))
                ->body($result['message'])
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title(__('admin/livejumping.action.test_failed'))
                ->body($result['message'].(isset($result['raw']) ? "\n\n".$result['raw'] : ''))
                ->danger()
                ->persistent()
                ->send();
        }
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);

        $payload = $this->form->getState();
        $previouslyEnabled = (bool) SystemSetting::getValue('livejumping.enabled', false);
        $newEnabled = (bool) ($payload['enabled'] ?? false);

        SystemSetting::setValue('livejumping.api_url', trim((string) ($payload['api_url'] ?? '')));

        if (filled($payload['api_token'] ?? null)) {
            SystemSetting::setSecret('livejumping.api_token', (string) $payload['api_token']);
        }

        // Twardy warunek: nie pozwól włączyć integracji bez tokenu — UI
        // mogło być przekonane że jest skonfigurowany, ale ktoś mógł
        // wyczyścić DB w międzyczasie.
        if ($newEnabled && SystemSetting::getSecret('livejumping.api_token') === null) {
            $newEnabled = false;
            Notification::make()
                ->title(__('admin/livejumping.action.cannot_enable_without_token'))
                ->warning()
                ->send();
        }

        SystemSetting::setValue('livejumping.enabled', $newEnabled);

        if ($newEnabled && ! $previouslyEnabled) {
            SystemSetting::setValue('livejumping.connected_at', now()->toIso8601String());
        }

        Notification::make()
            ->title(__('admin/livejumping.action.saved'))
            ->success()
            ->send();

        // Refresh state w UI bez przeładowania
        $this->data['api_token'] = null;
        $this->data['api_token_set'] = SystemSetting::getSecret('livejumping.api_token') !== null;
        $this->data['enabled'] = $newEnabled;
        $this->data['connected_at'] = SystemSetting::getValue('livejumping.connected_at');
    }
}
