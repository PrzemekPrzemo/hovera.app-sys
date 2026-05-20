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
use Illuminate\Support\Facades\Mail;

/**
 * Master-admin: SMTP configuration UI dla 2 mailerów:
 *
 *   1. **smtp** (default mailer) — password reset, system notifications,
 *      master admin alerts. Konfig keys: `mail.smtp.{host,port,username,...}`
 *   2. **transport** (dedicated) — emails wychodzące z modułu transport
 *      (oferty do klientów, dispatcher do kierowców, recenzje). Osobne creds
 *      + osobny From żeby separacja reputacji domeny. Patrz docs/TRANSPORT.md §6.
 *
 * Provider klasy (Laravel MailManager) czytają z config('mail.*') przy boot —
 * `AppServiceProvider::boot()` override'uje config tymi wartościami z
 * SystemSetting (gdy ustawione). To pozwala master adminowi rotować creds
 * bez SSH do .env.
 *
 * SystemSetting key naming:
 *   - mail.default.host  (encrypted) ← default mailer
 *   - mail.default.port
 *   - mail.default.username (encrypted)
 *   - mail.default.password (encrypted - secret!)
 *   - mail.default.encryption  (tls|ssl|null)
 *   - mail.default.from_address
 *   - mail.default.from_name
 *   - mail.transport.host (encrypted) ← transport mailer
 *   - mail.transport.port
 *   - ... etc.
 *
 * Bez tej strony master admin musiał edytować .env ręcznie + restart FPM.
 */
class SmtpSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    public static function getNavigationLabel(): string
    {
        return __('admin/smtp.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.configuration');
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin/smtp.title');
    }

    protected static ?int $navigationSort = 15;

    protected static string $view = 'filament.admin.pages.smtp-settings';

    /** @var array<string,mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            // Default mailer
            'default_host' => SystemSetting::getSecret('mail.default.host', '') ?? '',
            'default_port' => SystemSetting::getValue('mail.default.port', 587),
            'default_username' => SystemSetting::getSecret('mail.default.username', '') ?? '',
            'default_password' => '',  // intentionally empty — security UX, leave blank to keep
            'default_encryption' => SystemSetting::getValue('mail.default.encryption', 'tls'),
            'default_from_address' => SystemSetting::getValue('mail.default.from_address', config('mail.from.address')),
            'default_from_name' => SystemSetting::getValue('mail.default.from_name', config('mail.from.name')),
            // Transport mailer
            'transport_host' => SystemSetting::getSecret('mail.transport.host', '') ?? '',
            'transport_port' => SystemSetting::getValue('mail.transport.port', 587),
            'transport_username' => SystemSetting::getSecret('mail.transport.username', '') ?? '',
            'transport_password' => '',
            'transport_encryption' => SystemSetting::getValue('mail.transport.encryption', 'tls'),
            'transport_from_address' => SystemSetting::getValue('mail.transport.from_address', config('mail.mailers.transport.from.address')),
            'transport_from_name' => SystemSetting::getValue('mail.transport.from_name', config('mail.mailers.transport.from.name')),
        ]);
    }

    public function form(Form $form): Form
    {
        $encryptionOptions = [
            'tls' => 'TLS (port 587)',
            'ssl' => 'SSL (port 465)',
            'null' => __('admin/smtp.form.encryption.none'),
        ];

        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make(__('admin/smtp.form.section.default'))
                    ->description(__('admin/smtp.form.section.default_description'))
                    ->icon('heroicon-o-cog-6-tooth')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('default_host')
                            ->label(__('admin/smtp.form.label.host'))
                            ->placeholder('smtp.gmail.com')
                            ->helperText(__('admin/smtp.form.helper.host')),
                        Forms\Components\TextInput::make('default_port')
                            ->label(__('admin/smtp.form.label.port'))
                            ->numeric()
                            ->default(587),
                        Forms\Components\TextInput::make('default_username')
                            ->label(__('admin/smtp.form.label.username'))
                            ->placeholder('hello@yourdomain.com'),
                        Forms\Components\TextInput::make('default_password')
                            ->label(__('admin/smtp.form.label.password'))
                            ->password()
                            ->revealable()
                            ->helperText(__('admin/smtp.form.helper.password_leave_blank')),
                        Forms\Components\Select::make('default_encryption')
                            ->label(__('admin/smtp.form.label.encryption'))
                            ->options($encryptionOptions)
                            ->default('tls'),
                        Forms\Components\Placeholder::make('default_status')
                            ->label(__('admin/smtp.form.label.status'))
                            ->content(fn () => SystemSetting::getSecret('mail.default.host')
                                ? __('admin/smtp.form.status.configured')
                                : __('admin/smtp.form.status.using_env')),
                        Forms\Components\TextInput::make('default_from_address')
                            ->label(__('admin/smtp.form.label.from_address'))
                            ->email()
                            ->placeholder('noreply@hovera.app'),
                        Forms\Components\TextInput::make('default_from_name')
                            ->label(__('admin/smtp.form.label.from_name'))
                            ->placeholder('Hovera'),
                    ]),

                Forms\Components\Section::make(__('admin/smtp.form.section.transport'))
                    ->description(__('admin/smtp.form.section.transport_description'))
                    ->icon('heroicon-o-truck')
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('transport_host')
                            ->label(__('admin/smtp.form.label.host')),
                        Forms\Components\TextInput::make('transport_port')
                            ->label(__('admin/smtp.form.label.port'))
                            ->numeric()
                            ->default(587),
                        Forms\Components\TextInput::make('transport_username')
                            ->label(__('admin/smtp.form.label.username')),
                        Forms\Components\TextInput::make('transport_password')
                            ->label(__('admin/smtp.form.label.password'))
                            ->password()
                            ->revealable()
                            ->helperText(__('admin/smtp.form.helper.password_leave_blank')),
                        Forms\Components\Select::make('transport_encryption')
                            ->label(__('admin/smtp.form.label.encryption'))
                            ->options($encryptionOptions)
                            ->default('tls'),
                        Forms\Components\Placeholder::make('transport_status')
                            ->label(__('admin/smtp.form.label.status'))
                            ->content(fn () => SystemSetting::getSecret('mail.transport.host')
                                ? __('admin/smtp.form.status.configured')
                                : __('admin/smtp.form.status.using_env')),
                        Forms\Components\TextInput::make('transport_from_address')
                            ->label(__('admin/smtp.form.label.from_address'))
                            ->email()
                            ->placeholder('transport@hovera.app'),
                        Forms\Components\TextInput::make('transport_from_name')
                            ->label(__('admin/smtp.form.label.from_name'))
                            ->placeholder('Hovera Transport'),
                    ]),

                Forms\Components\Section::make(__('admin/smtp.form.section.test'))
                    ->description(__('admin/smtp.form.section.test_description'))
                    ->icon('heroicon-o-paper-airplane')
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('test_email')
                            ->label(__('admin/smtp.form.label.test_email'))
                            ->email()
                            ->default(fn () => Auth::user()?->email)
                            ->helperText(__('admin/smtp.form.helper.test_email')),
                    ]),
            ]);
    }

    public function save(): void
    {
        $form = $this->form->getState();

        foreach (['default', 'transport'] as $mailer) {
            // Secrets (host/username — sensitive ale nie super secret like password)
            foreach (['host', 'username'] as $secret) {
                $value = trim((string) ($form["{$mailer}_{$secret}"] ?? ''));
                if ($value !== '') {
                    SystemSetting::setSecret("mail.{$mailer}.{$secret}", $value);
                }
            }
            // Password — tylko jeśli wpisany (pusty = zachowaj poprzedni)
            $password = trim((string) ($form["{$mailer}_password"] ?? ''));
            if ($password !== '') {
                SystemSetting::setSecret("mail.{$mailer}.password", $password);
            }
            // Plain values
            foreach (['port', 'encryption', 'from_address', 'from_name'] as $plain) {
                $value = $form["{$mailer}_{$plain}"] ?? null;
                if ($value !== null && $value !== '') {
                    SystemSetting::setValue("mail.{$mailer}.{$plain}", $value);
                }
            }
        }

        Notification::make()
            ->title(__('admin/smtp.action.saved'))
            ->body(__('admin/smtp.action.saved_body'))
            ->success()
            ->send();
    }

    public function sendTestEmail(): void
    {
        $email = trim((string) ($this->form->getState()['test_email'] ?? ''));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Notification::make()
                ->title(__('admin/smtp.action.test_invalid_email'))
                ->danger()
                ->send();

            return;
        }

        try {
            Mail::raw(__('admin/smtp.test_email.body'), function ($m) use ($email) {
                $m->to($email)->subject(__('admin/smtp.test_email.subject'));
            });

            Notification::make()
                ->title(__('admin/smtp.action.test_sent', ['email' => $email]))
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('admin/smtp.action.test_failed'))
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }
}
