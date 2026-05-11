<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Central\User;
use App\Services\MasterAuditLogger;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Single shared Profile page registered in both /admin and /app panels.
 * Filament treats it as a separate route per panel automatically.
 */
class Profile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?int $navigationSort = 99;

    public static function getNavigationLabel(): string
    {
        return __('pages.profile.navigation');
    }

    public function getTitle(): string|Htmlable
    {
        return __('pages.profile.title');
    }

    protected static string $view = 'filament.pages.profile';

    /** @var array<string,mixed> */
    public array $data = [];

    /** @var array<string,mixed> */
    public array $passwordData = [];

    public function mount(): void
    {
        /** @var User $user */
        $user = Auth::user();
        $this->form->fill([
            'name' => $user->name,
            'email' => $user->email,
            'locale' => $user->locale,
            'timezone' => $user->timezone,
        ]);
        $this->passwordForm->fill();
    }

    protected function getForms(): array
    {
        return [
            'form',
            'passwordForm',
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('email')
                    ->label('Email')
                    ->disabled()
                    ->dehydrated(false),

                Forms\Components\TextInput::make('name')
                    ->label('Imię i nazwisko')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('locale')
                    ->label(__('common.field.locale'))
                    ->options([
                        'pl' => 'Polski',
                        'en' => 'English',
                        'fr' => 'Français',
                        'de' => 'Deutsch',
                        'ru' => 'Русский',
                    ])
                    ->required()
                    ->helperText(__('common.field.locale_help')),

                Forms\Components\Select::make('timezone')
                    ->label('Strefa czasowa')
                    ->options(self::commonTimezones())
                    ->searchable()
                    ->required(),
            ])
            ->statePath('data')
            ->columns(2);
    }

    public function passwordForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('current_password')
                    ->label('Obecne hasło')
                    ->password()
                    ->revealable()
                    ->required(),

                Forms\Components\TextInput::make('password')
                    ->label('Nowe hasło')
                    ->password()
                    ->revealable()
                    ->required()
                    ->minLength(12)
                    ->same('password_confirmation')
                    ->helperText('Minimum 12 znaków.'),

                Forms\Components\TextInput::make('password_confirmation')
                    ->label('Powtórz nowe hasło')
                    ->password()
                    ->revealable()
                    ->required(),
            ])
            ->statePath('passwordData')
            ->columns(1);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        /** @var User $user */
        $user = Auth::user();
        $user->forceFill([
            'name' => $data['name'],
            'locale' => $data['locale'],
            'timezone' => $data['timezone'],
        ])->save();

        $this->logAudit('profile.update', [
            'changed' => array_keys($data),
        ]);

        Notification::make()
            ->success()
            ->title('Profil zaktualizowany')
            ->send();
    }

    public function changePassword(): void
    {
        $data = $this->passwordForm->getState();

        /** @var User $user */
        $user = Auth::user();

        if (! Hash::check($data['current_password'], $user->password)) {
            Notification::make()
                ->danger()
                ->title('Obecne hasło jest nieprawidłowe.')
                ->send();

            return;
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
        ])->save();

        $this->passwordForm->fill();

        $this->logAudit('profile.password_changed');

        Notification::make()
            ->success()
            ->title('Hasło zmienione')
            ->send();
    }

    /**
     * 2FA controls. Exposed as Filament header actions so they sit
     * neatly above the form.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->hasTwoFactorEnabled()) {
            return [
                Action::make('disable2fa')
                    ->label('Wyłącz 2FA')
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('danger')
                    ->visible(fn () => ! $user->is_master_admin)   // master admin must keep 2FA
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\TextInput::make('current_password')
                            ->label('Potwierdź swoim hasłem')
                            ->password()
                            ->required(),
                    ])
                    ->action(function (array $data) use ($user) {
                        if (! Hash::check($data['current_password'], $user->password)) {
                            Notification::make()->danger()->title('Hasło nieprawidłowe.')->send();

                            return;
                        }

                        $user->forceFill([
                            'two_factor_secret' => null,
                            'two_factor_recovery_codes' => null,
                            'two_factor_confirmed_at' => null,
                        ])->save();

                        request()->session()->forget('two_factor_passed');

                        $this->logAudit('profile.two_factor.disabled');

                        Notification::make()->success()->title('2FA wyłączone')->send();
                    }),
            ];
        }

        return [
            Action::make('enable2fa')
                ->label('Włącz 2FA')
                ->icon('heroicon-o-shield-check')
                ->color('success')
                ->url(route('two-factor.setup'), shouldOpenInNewTab: false),
        ];
    }

    private function logAudit(string $action, array $payload = []): void
    {
        /** @var User $user */
        $user = Auth::user();

        // Master admin actions go to the central audit log.
        if ($user->is_master_admin && app(TenantManager::class)->current() === null) {
            app(MasterAuditLogger::class)->record($action, 'User', $user->id, null, $payload);

            return;
        }

        // Tenant user — write to per-tenant audit log if a tenant is active.
        app(TenantAuditLogger::class)->record($action, 'User', $user->id, $payload);
    }

    /**
     * Reasonable shortlist for the timezone selector. Users can still
     * search; this just prefills the dropdown without dumping all 400+
     * Olson zones.
     *
     * @return array<string,string>
     */
    private static function commonTimezones(): array
    {
        $zones = [
            'Europe/Warsaw',
            'Europe/Berlin',
            'Europe/Amsterdam',
            'Europe/Brussels',
            'Europe/Paris',
            'Europe/London',
            'Europe/Madrid',
            'Europe/Rome',
            'Europe/Vienna',
            'Europe/Prague',
            'Europe/Copenhagen',
            'Europe/Stockholm',
            'Europe/Helsinki',
            'Europe/Oslo',
            'Europe/Dublin',
            'UTC',
        ];

        return array_combine($zones, $zones);
    }
}
