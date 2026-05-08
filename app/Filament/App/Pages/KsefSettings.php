<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Services\Ksef\KsefCertificateService;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

/**
 * Per-stable konfiguracja KSeF: wybór środowiska, NIP kontekstu, upload
 * certyfikatu (PFX lub para .crt + .key).
 *
 * Cert + hasło / klucz prywatny szyfrowane via Laravel Crypt — to samo
 * AES-256-CBC + HMAC co dla db_password tenanta. Wyświetlamy meta-info
 * (subject, fingerprint, daty ważności) z parsedPemCert żeby owner
 * widział czy upload się powiódł.
 *
 * KSeF env (test / demo / production) + context_nip ustawia owner
 * stajni, master-admin nie ma do tego dostępu (KSeF jest osobistym
 * podpisem stajni jako podatnika).
 */
class KsefSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?int $navigationSort = 40;

    public static function getNavigationLabel(): string
    {
        return __('pages.ksef_settings.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.settings');
    }

    public function getTitle(): string|Htmlable
    {
        return __('pages.ksef_settings.title');
    }

    protected static string $view = 'filament.pages.ksef-settings';

    /** @var array<string,mixed> */
    public array $data = [];

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
        $ksef = (array) (data_get($tenant->settings, 'ksef') ?? []);

        $this->form->fill([
            'env' => $ksef['env'] ?? 'test',
            'context_nip' => $ksef['context_nip'] ?? $tenant->tax_id ?? null,
            'identifier_type' => $ksef['identifier_type'] ?? 'certificateSubject',
        ]);
    }

    public function form(Form $form): Form
    {
        $tenant = app(TenantManager::class)->current();
        $cert = (array) (data_get($tenant?->settings, 'ksef.cert_metadata') ?? []);
        $hasCert = ! empty($cert);

        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make('Środowisko KSeF')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Radio::make('env')
                            ->label('Środowisko')
                            ->options([
                                'test' => 'Test (ksef-test.mf.gov.pl)',
                                'demo' => 'Demo (ksef-demo.mf.gov.pl)',
                                'prod' => 'Produkcyjne (ksef.mf.gov.pl)',
                            ])
                            ->default('test')
                            ->required(),
                        Forms\Components\TextInput::make('context_nip')
                            ->label('NIP stajni (kontekst)')
                            ->required()
                            ->maxLength(16)
                            ->helperText('NIP używany przy uwierzytelnianiu w KSeF — ten sam co na fakturach.'),
                        Forms\Components\Radio::make('identifier_type')
                            ->label('Typ identyfikatora podpisującego')
                            ->options([
                                'certificateSubject' => 'Subject certyfikatu (zwykle dla PFX)',
                                'certificateFingerprint' => 'Fingerprint (dla certyfikatów KSeF)',
                            ])
                            ->default('certificateSubject')
                            ->required(),
                    ]),

                Forms\Components\Section::make('Certyfikat — upload')
                    ->description('Jednorazowo wgrywasz certyfikat. Klucz prywatny + hasło są zaszyfrowane na poziomie aplikacji (Laravel Crypt + AES-256).')
                    ->schema([
                        Forms\Components\Tabs::make('cert_tabs')
                            ->tabs([
                                Forms\Components\Tabs\Tab::make('PFX / P12')
                                    ->schema([
                                        Forms\Components\FileUpload::make('cert_pfx')
                                            ->label('Plik certyfikatu (.pfx / .p12)')
                                            ->acceptedFileTypes(['application/x-pkcs12', 'application/octet-stream', 'application/pkcs12'])
                                            ->maxSize(50)
                                            ->disk('local')
                                            ->visibility('private')
                                            ->storeFiles(false), // przekażemy raw bytes do save()
                                        Forms\Components\TextInput::make('cert_pfx_password')
                                            ->label('Hasło PFX')
                                            ->password()
                                            ->revealable()
                                            ->helperText('Hasło używane TYLKO przy parsowaniu — NIE jest zapisywane w plain text.'),
                                    ]),
                                Forms\Components\Tabs\Tab::make('PEM (.crt + .key)')
                                    ->schema([
                                        Forms\Components\FileUpload::make('cert_pem_crt')
                                            ->label('Certyfikat (.crt / .pem)')
                                            ->acceptedFileTypes(['application/x-x509-ca-cert', 'application/octet-stream', 'text/plain'])
                                            ->maxSize(50)
                                            ->disk('local')
                                            ->visibility('private')
                                            ->storeFiles(false),
                                        Forms\Components\FileUpload::make('cert_pem_key')
                                            ->label('Klucz prywatny (.key / .pem)')
                                            ->acceptedFileTypes(['application/octet-stream', 'application/x-pem-file', 'text/plain'])
                                            ->maxSize(50)
                                            ->disk('local')
                                            ->visibility('private')
                                            ->storeFiles(false),
                                        Forms\Components\TextInput::make('cert_pem_password')
                                            ->label('Hasło klucza (jeśli zaszyfrowany)')
                                            ->password()
                                            ->revealable(),
                                    ]),
                            ]),
                    ]),

                Forms\Components\Section::make('Aktualnie zapisany certyfikat')
                    ->visible($hasCert)
                    ->schema([
                        Forms\Components\Placeholder::make('cert_subject_cn')
                            ->label('Podmiot')
                            ->content(fn () => $cert['subject_cn'] ?? '—'),
                        Forms\Components\Placeholder::make('cert_subject_nip')
                            ->label('NIP w certyfikacie')
                            ->content(fn () => $cert['subject_nip'] ?? '—'),
                        Forms\Components\Placeholder::make('cert_issuer')
                            ->label('Wystawca')
                            ->content(fn () => $cert['issuer'] ?? '—'),
                        Forms\Components\Placeholder::make('cert_fingerprint')
                            ->label('Fingerprint SHA-256')
                            ->content(fn () => $cert['fingerprint'] ?? '—'),
                        Forms\Components\Placeholder::make('cert_valid_to')
                            ->label('Ważny do')
                            ->content(fn () => $cert['valid_to'] ?? '—'),
                        Forms\Components\Placeholder::make('cert_type')
                            ->label('Typ')
                            ->content(fn () => match ($cert['cert_type'] ?? null) {
                                'personal' => 'Podpis kwalifikowany (osobowy)',
                                'seal' => 'Pieczęć elektroniczna',
                                'ksef' => 'Certyfikat KSeF',
                                default => '—',
                            }),
                    ]),
            ]);
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);

        $tenant = app(TenantManager::class)->tenantOrFail();
        $form = $this->form->getState();
        $settings = (array) ($tenant->settings ?? []);
        $ksef = (array) ($settings['ksef'] ?? []);

        $ksef['env'] = (string) ($form['env'] ?? 'test');
        $ksef['context_nip'] = (string) ($form['context_nip'] ?? '');
        $ksef['identifier_type'] = (string) ($form['identifier_type'] ?? 'certificateSubject');

        // Upload PFX (jeśli wgrane w tej sesji)
        $pfxFile = $form['cert_pfx'] ?? null;
        $pfxPassword = (string) ($form['cert_pfx_password'] ?? '');
        if ($pfxFile && $pfxPassword !== '') {
            try {
                $pfxBytes = $this->readUploadedBytes($pfxFile);
                $meta = KsefCertificateService::parsePfx($pfxBytes, $pfxPassword);

                $ksef['cert_format'] = 'pfx';
                $ksef['cert_pfx_encrypted'] = Crypt::encryptString(base64_encode($pfxBytes));
                $ksef['cert_password_encrypted'] = Crypt::encryptString($pfxPassword);
                $ksef['cert_metadata'] = $meta;

                Notification::make()->title('Certyfikat PFX zapisany.')->success()->send();
            } catch (\Throwable $e) {
                Notification::make()->title('Błąd certyfikatu PFX')->body($e->getMessage())->danger()->send();

                return;
            }
        }

        // Upload PEM (.crt + .key)
        $crtFile = $form['cert_pem_crt'] ?? null;
        $keyFile = $form['cert_pem_key'] ?? null;
        $pemPassword = (string) ($form['cert_pem_password'] ?? '');
        if ($crtFile && $keyFile) {
            try {
                $crtBytes = $this->readUploadedBytes($crtFile);
                $keyBytes = $this->readUploadedBytes($keyFile);
                $meta = KsefCertificateService::parsePemPair($crtBytes, $keyBytes, $pemPassword ?: null);

                $ksef['cert_format'] = 'pem';
                $ksef['cert_crt_encrypted'] = Crypt::encryptString($crtBytes);
                $ksef['cert_key_encrypted'] = Crypt::encryptString($keyBytes);
                $ksef['cert_password_encrypted'] = $pemPassword !== '' ? Crypt::encryptString($pemPassword) : null;
                $ksef['cert_metadata'] = $meta;

                Notification::make()->title('Certyfikat PEM zapisany.')->success()->send();
            } catch (\Throwable $e) {
                Notification::make()->title('Błąd certyfikatu PEM')->body($e->getMessage())->danger()->send();

                return;
            }
        }

        $settings['ksef'] = $ksef;
        $tenant->forceFill(['settings' => $settings])->save();

        app(TenantAuditLogger::class)->record('ksef.settings_updated', 'Tenant', (string) $tenant->id, [
            'env' => $ksef['env'],
            'has_cert' => isset($ksef['cert_format']),
        ]);

        Notification::make()->title('Zapisano ustawienia KSeF')->success()->send();
    }

    /**
     * Filament FileUpload z `storeFiles(false)` przekazuje
     * Symfony\Component\HttpFoundation\File\UploadedFile lub ścieżkę.
     * Czytamy bezpiecznie w obu przypadkach.
     */
    private function readUploadedBytes(mixed $file): string
    {
        if ($file instanceof UploadedFile) {
            return (string) file_get_contents($file->getRealPath());
        }
        if ($file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            return (string) file_get_contents($file->getRealPath());
        }
        if (is_string($file) && is_file($file)) {
            return (string) file_get_contents($file);
        }
        // Filament czasem zwraca array { path, ... }
        if (is_array($file) && isset($file['path']) && is_file($file['path'])) {
            return (string) file_get_contents($file['path']);
        }

        throw new \RuntimeException('Nie można odczytać przesłanego pliku certyfikatu.');
    }
}
