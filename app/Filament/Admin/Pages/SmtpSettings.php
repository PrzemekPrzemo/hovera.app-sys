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
use Illuminate\Support\HtmlString;

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
            'default_skip_tls_verify' => (bool) SystemSetting::getValue('mail.default.skip_tls_verify', false),
            'default_from_address' => SystemSetting::getValue('mail.default.from_address', config('mail.from.address')),
            'default_from_name' => SystemSetting::getValue('mail.default.from_name', config('mail.from.name')),
            // Global reply-to (Mail::alwaysReplyTo) — działa dla wszystkich mailerów.
            'reply_to_address' => SystemSetting::getValue('mail.default.reply_to_address', ''),
            'reply_to_name' => SystemSetting::getValue('mail.default.reply_to_name', ''),
            // Mailgun (alternatywa do SMTP — gdy `secret` jest ustawiony, wygrywa).
            'mailgun_domain' => SystemSetting::getSecret('mail.mailgun.domain', '') ?? '',
            'mailgun_secret' => '', // leave blank to keep
            'mailgun_endpoint' => SystemSetting::getValue('mail.mailgun.endpoint', 'api.eu.mailgun.net'),
            'mailgun_from_address' => SystemSetting::getValue('mail.mailgun.from_address', ''),
            'mailgun_from_name' => SystemSetting::getValue('mail.mailgun.from_name', ''),
            // Transport mailer
            'transport_host' => SystemSetting::getSecret('mail.transport.host', '') ?? '',
            'transport_port' => SystemSetting::getValue('mail.transport.port', 587),
            'transport_username' => SystemSetting::getSecret('mail.transport.username', '') ?? '',
            'transport_password' => '',
            'transport_encryption' => SystemSetting::getValue('mail.transport.encryption', 'tls'),
            'transport_skip_tls_verify' => (bool) SystemSetting::getValue('mail.transport.skip_tls_verify', false),
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
                Forms\Components\Section::make(__('admin/smtp.form.section.diagnostics'))
                    ->description(__('admin/smtp.form.section.diagnostics_description'))
                    ->icon('heroicon-o-magnifying-glass')
                    ->schema([
                        Forms\Components\Placeholder::make('effective_mailer')
                            ->label(__('admin/smtp.form.label.effective_mailer'))
                            ->content(fn () => $this->renderEffectiveMailerDiagnostic()),
                    ]),

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
                        Forms\Components\Toggle::make('default_skip_tls_verify')
                            ->label(__('admin/smtp.form.label.skip_tls_verify'))
                            ->helperText(__('admin/smtp.form.helper.skip_tls_verify'))
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('default_from_address')
                            ->label(__('admin/smtp.form.label.from_address'))
                            ->email()
                            ->placeholder('noreply@hovera.app'),
                        Forms\Components\TextInput::make('default_from_name')
                            ->label(__('admin/smtp.form.label.from_name'))
                            ->placeholder('Hovera'),
                        Forms\Components\TextInput::make('reply_to_address')
                            ->label(__('admin/smtp.form.label.reply_to_address'))
                            ->email()
                            ->placeholder('kontakt@hovera.pl')
                            ->helperText(__('admin/smtp.form.helper.reply_to_address')),
                        Forms\Components\TextInput::make('reply_to_name')
                            ->label(__('admin/smtp.form.label.reply_to_name'))
                            ->placeholder('Hovera — Wsparcie'),
                    ]),

                Forms\Components\Section::make(__('admin/smtp.form.section.mailgun'))
                    ->description(__('admin/smtp.form.section.mailgun_description'))
                    ->icon('heroicon-o-paper-airplane')
                    ->collapsed(fn () => SystemSetting::getSecret('mail.mailgun.secret') === null)
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('mailgun_domain')
                            ->label(__('admin/smtp.form.label.mailgun_domain'))
                            ->placeholder('hovera.pl')
                            ->helperText(__('admin/smtp.form.helper.mailgun_domain')),
                        Forms\Components\Select::make('mailgun_endpoint')
                            ->label(__('admin/smtp.form.label.mailgun_endpoint'))
                            ->options([
                                'api.eu.mailgun.net' => 'EU — api.eu.mailgun.net',
                                'api.mailgun.net' => 'US — api.mailgun.net',
                            ])
                            ->default('api.eu.mailgun.net')
                            ->helperText(__('admin/smtp.form.helper.mailgun_endpoint')),
                        Forms\Components\TextInput::make('mailgun_secret')
                            ->label(__('admin/smtp.form.label.mailgun_secret'))
                            ->password()
                            ->revealable()
                            ->placeholder('key-***')
                            ->helperText(__('admin/smtp.form.helper.password_leave_blank'))
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('mailgun_from_address')
                            ->label(__('admin/smtp.form.label.mailgun_from_address'))
                            ->email()
                            ->placeholder('noreply@hovera.pl')
                            ->helperText(__('admin/smtp.form.helper.mailgun_from_address')),
                        Forms\Components\TextInput::make('mailgun_from_name')
                            ->label(__('admin/smtp.form.label.mailgun_from_name'))
                            ->placeholder('Hovera'),
                        Forms\Components\Placeholder::make('mailgun_status')
                            ->label(__('admin/smtp.form.label.status'))
                            ->content(fn () => SystemSetting::getSecret('mail.mailgun.secret')
                                ? __('admin/smtp.form.status.mailgun_active')
                                : __('admin/smtp.form.status.mailgun_inactive'))
                            ->columnSpanFull(),
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
                        Forms\Components\Toggle::make('transport_skip_tls_verify')
                            ->label(__('admin/smtp.form.label.skip_tls_verify'))
                            ->helperText(__('admin/smtp.form.helper.skip_tls_verify'))
                            ->columnSpanFull(),
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
            // Boolean toggles — zapisujemy zawsze (false też ma znaczenie, w przeciwieństwie do empty stringa).
            SystemSetting::setValue(
                "mail.{$mailer}.skip_tls_verify",
                (bool) ($form["{$mailer}_skip_tls_verify"] ?? false),
            );
        }

        // Mailgun (API transport — alternatywa do SMTP). `secret` jest super-secret
        // (API key), zapisujemy zawsze gdy wpisany; pusty = zachowaj poprzedni.
        $mailgunDomain = trim((string) ($form['mailgun_domain'] ?? ''));
        if ($mailgunDomain !== '') {
            SystemSetting::setSecret('mail.mailgun.domain', $mailgunDomain);
        }
        $mailgunSecret = trim((string) ($form['mailgun_secret'] ?? ''));
        if ($mailgunSecret !== '') {
            SystemSetting::setSecret('mail.mailgun.secret', $mailgunSecret);
        }
        $mailgunEndpoint = trim((string) ($form['mailgun_endpoint'] ?? ''));
        if ($mailgunEndpoint !== '') {
            SystemSetting::setValue('mail.mailgun.endpoint', $mailgunEndpoint);
        }
        // Mailgun-specific From — MUSI być na verified domain z Mailgun panel'a,
        // inaczej API zwraca 401 Forbidden. Puste pole = fallback do default.from.
        foreach (['from_address', 'from_name'] as $field) {
            $value = trim((string) ($form["mailgun_{$field}"] ?? ''));
            if ($value !== '') {
                SystemSetting::setValue("mail.mailgun.{$field}", $value);
            }
        }

        // Global Reply-To — Laravel `Mail::alwaysReplyTo` applied w
        // AppServiceProvider, działa dla SMTP + Mailgun + transport mailera.
        // Puste = nie ustawiamy global hook'a (mailables mogą mieć własne replyTo).
        foreach (['reply_to_address', 'reply_to_name'] as $field) {
            $value = trim((string) ($form[$field] ?? ''));
            if ($value !== '') {
                SystemSetting::setValue("mail.default.{$field}", $value);
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

        // Preflight: jeśli config('mail.default') = log/array, ostrzegamy
        // od razu — Mail::raw zwróci OK ale email faktycznie nie wyjdzie.
        $effectiveMailer = (string) config('mail.default');
        if (in_array($effectiveMailer, ['log', 'array'], true)) {
            Notification::make()
                ->title(__('admin/smtp.action.test_failed'))
                ->body(__('admin/smtp.diagnostics.log_mailer_warning', ['mailer' => $effectiveMailer]))
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        try {
            Mail::raw(__('admin/smtp.test_email.body'), function ($m) use ($email) {
                $m->to($email)->subject(__('admin/smtp.test_email.subject'));
            });

            Notification::make()
                ->title(__('admin/smtp.action.test_sent', ['email' => $email]))
                ->body(__('admin/smtp.action.test_sent_body', ['mailer' => $effectiveMailer]))
                ->success()
                ->send();
        } catch (\Throwable $e) {
            // 401 Forbidden z Mailguna ma typowo 3 przyczyny — pomagamy adminowi
            // zdiagnozować zamiast pokazywać surowy "Forbidden" bez kontekstu.
            $hint = $this->guessMailerErrorHint($e->getMessage(), $effectiveMailer);
            Notification::make()
                ->title(__('admin/smtp.action.test_failed'))
                ->body($e->getMessage().($hint !== '' ? "\n\n".$hint : ''))
                ->danger()
                ->persistent()
                ->send();
        }
    }

    /**
     * Heurystyka diagnostyczna dla typowych błędów wysyłki — pomaga master
     * adminowi zlokalizować problem zamiast googlować surowy komunikat.
     */
    private function guessMailerErrorHint(string $errorMessage, string $effectiveMailer): string
    {
        $lower = strtolower($errorMessage);
        if ($effectiveMailer !== 'mailgun') {
            return '';
        }
        // 401 / 403 z Mailguna — 3 typowe przyczyny.
        if (str_contains($lower, '401') || str_contains($lower, '403')
            || str_contains($lower, 'forbidden') || str_contains($lower, 'unauthorized')) {
            $fromDomain = $this->fromDomainOrNull();
            $verifiedDomain = (string) (config('services.mailgun.domain') ?? '');
            $details = [];
            if ($fromDomain && $verifiedDomain && $fromDomain !== $verifiedDomain) {
                $details[] = __('admin/smtp.diagnostics.mailgun_401_from_mismatch', [
                    'from_domain' => $fromDomain,
                    'verified_domain' => $verifiedDomain,
                ]);
            }
            $details[] = __('admin/smtp.diagnostics.mailgun_401_hint');

            return implode("\n", $details);
        }

        return '';
    }

    private function fromDomainOrNull(): ?string
    {
        $fromAddress = (string) (config('mail.from.address') ?? '');
        if ($fromAddress === '' || ! str_contains($fromAddress, '@')) {
            return null;
        }

        return strtolower(trim(substr(strrchr($fromAddress, '@'), 1)));
    }

    /**
     * Diagnostic banner — pokazuje master admin'owi jaki mailer jest
     * aktywny i czy SystemSetting override zadziałał. Wcześniej user
     * zapisywał SMTP config ale `mail.default = env('MAIL_MAILER', 'log')`
     * pozostawał 'log' → maile szły do logu. AppServiceProvider teraz
     * override'uje też `mail.default = 'smtp'` gdy SystemSetting ma host —
     * ten banner pomaga zweryfikować że to działa.
     */
    private function renderEffectiveMailerDiagnostic(): HtmlString
    {
        $effectiveMailer = (string) config('mail.default');
        $envMailer = (string) env('MAIL_MAILER', 'log');
        $effectiveHost = (string) (config('mail.mailers.smtp.host') ?? '');
        $hasSystemSettingHost = SystemSetting::getSecret('mail.default.host') !== null;
        $effectiveFromAddress = (string) (config('mail.from.address') ?? '');
        $effectiveFromName = (string) (config('mail.from.name') ?? '');

        $mailgunDomain = (string) (config('services.mailgun.domain') ?? '');
        $mailgunEndpoint = (string) (config('services.mailgun.endpoint') ?? '');

        $statusBadge = match (true) {
            in_array($effectiveMailer, ['log', 'array'], true) => '<span class="inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-800">'.e($effectiveMailer).' — '.e(__('admin/smtp.diagnostics.not_sending')).'</span>',
            $effectiveMailer === 'mailgun' && $mailgunDomain !== '' => '<span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">mailgun → '.e($mailgunDomain).' ('.e($mailgunEndpoint).')</span>',
            $effectiveMailer === 'smtp' && $effectiveHost !== '' => '<span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">smtp → '.e($effectiveHost).'</span>',
            default => '<span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">'.e($effectiveMailer).' — '.e(__('admin/smtp.diagnostics.no_host')).'</span>',
        };

        $rows = [
            [__('admin/smtp.diagnostics.effective_mailer'), $statusBadge],
            [__('admin/smtp.diagnostics.env_mailer'), '<code class="text-xs">'.e($envMailer).'</code>'],
            [__('admin/smtp.diagnostics.override_active'), ($hasSystemSettingHost || SystemSetting::getSecret('mail.mailgun.secret') !== null)
                ? '<span class="text-emerald-700">✓ '.e(__('admin/smtp.diagnostics.override_yes')).'</span>'
                : '<span class="text-gray-500">'.e(__('admin/smtp.diagnostics.override_no')).'</span>'],
            [__('admin/smtp.diagnostics.from'), $effectiveFromAddress !== ''
                ? '<code class="text-xs">'.e($effectiveFromName !== '' ? "{$effectiveFromName} <{$effectiveFromAddress}>" : $effectiveFromAddress).'</code>'
                : '<span class="text-rose-600">— '.e(__('admin/smtp.diagnostics.from_missing')).'</span>'],
        ];

        $html = '<dl class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">';
        foreach ($rows as [$label, $value]) {
            $html .= '<div class="flex items-start gap-2">';
            $html .= '<dt class="font-medium text-gray-700 dark:text-gray-300">'.e($label).':</dt>';
            $html .= '<dd>'.$value.'</dd>';
            $html .= '</div>';
        }
        $html .= '</dl>';

        if (in_array($effectiveMailer, ['log', 'array'], true)) {
            $html .= '<div class="mt-3 rounded-md bg-rose-50 p-3 text-sm text-rose-800 dark:bg-rose-900/30 dark:text-rose-200">'
                .e(__('admin/smtp.diagnostics.log_mailer_explanation'))
                .'</div>';
        }

        // Mailgun aktywny + From domain != verified domain → bardzo
        // częsta przyczyna 401 Forbidden. Pokazujemy zanim user kliknie test.
        if ($effectiveMailer === 'mailgun') {
            $fromDomain = $this->fromDomainOrNull();
            if ($fromDomain && $mailgunDomain !== '' && $fromDomain !== strtolower($mailgunDomain)) {
                $html .= '<div class="mt-3 rounded-md bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-900/30 dark:text-amber-200">'
                    .e(__('admin/smtp.diagnostics.mailgun_from_mismatch_warning', [
                        'from_domain' => $fromDomain,
                        'verified_domain' => $mailgunDomain,
                    ]))
                    .'</div>';
            }
        }

        return new HtmlString($html);
    }
}
