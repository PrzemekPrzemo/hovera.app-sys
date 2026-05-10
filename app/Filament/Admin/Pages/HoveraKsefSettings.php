<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Models\Central\SystemSetting;
use App\Services\Ksef\KsefCertificateService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\UploadedFile;

/**
 * Master-admin: konfiguracja KSeF dla hovery jako podatnika VAT.
 *
 * Niewiele różni się od per-tenant KsefSettings (App\Filament\App\Pages),
 * ale zapisuje cert + NIP + env w central.system_settings (singleton dla
 * całego systemu), a nie w settings JSON tenanta. Master-admin używa
 * tej konfiguracji do wystawiania FV SaaS-owych stajniom (subskrypcje).
 *
 * Sensitive dane (cert bytes + hasło) szyfrowane via SystemSetting::setSecret.
 */
class HoveraKsefSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?int $navigationSort = 20;

    protected static string $view = 'filament.admin.pages.hovera-ksef-settings';

    /** @var array<string,mixed> */
    public array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('admin/ksef_central.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.configuration');
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin/ksef_central.title');
    }

    public function mount(): void
    {
        $this->form->fill([
            'env' => SystemSetting::getValue('ksef_central.env', 'test'),
            'context_nip' => SystemSetting::getValue('ksef_central.context_nip', '') ?? '',
            'identifier_type' => SystemSetting::getValue('ksef_central.identifier_type', 'certificateSubject'),
        ]);
    }

    public function form(Form $form): Form
    {
        $meta = (array) (SystemSetting::getValue('ksef_central.cert_metadata') ?? []);
        $hasCert = ! empty($meta);

        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make(__('admin/ksef_central.form.section.env'))
                    ->columns(2)
                    ->schema([
                        Forms\Components\Radio::make('env')
                            ->label(__('admin/ksef_central.form.label.env'))
                            ->options([
                                'test' => __('admin/ksef_central.form.env_options.test'),
                                'demo' => __('admin/ksef_central.form.env_options.demo'),
                                'production' => __('admin/ksef_central.form.env_options.production'),
                            ])
                            ->default('test')
                            ->required(),
                        Forms\Components\TextInput::make('context_nip')
                            ->label(__('admin/ksef_central.form.label.context_nip'))
                            ->required()
                            ->maxLength(16)
                            ->helperText(__('admin/ksef_central.form.label.context_nip_helper')),
                        Forms\Components\Radio::make('identifier_type')
                            ->label(__('admin/ksef_central.form.label.identifier_type'))
                            ->options([
                                'certificateSubject' => __('admin/ksef_central.form.identifier_options.subject'),
                                'certificateFingerprint' => __('admin/ksef_central.form.identifier_options.fingerprint'),
                            ])
                            ->default('certificateSubject')
                            ->required(),
                    ]),

                Forms\Components\Section::make(__('admin/ksef_central.form.section.cert_upload'))
                    ->description(__('admin/ksef_central.form.section.cert_upload_description'))
                    ->schema([
                        Forms\Components\Tabs::make('cert_tabs')
                            ->tabs([
                                Forms\Components\Tabs\Tab::make(__('admin/ksef_central.form.label.tab_pfx'))
                                    ->schema([
                                        Forms\Components\FileUpload::make('cert_pfx')
                                            ->label(__('admin/ksef_central.form.label.cert_pfx_file'))
                                            ->acceptedFileTypes(['application/x-pkcs12', 'application/octet-stream', 'application/pkcs12'])
                                            ->maxSize(50)
                                            ->disk('local')
                                            ->visibility('private')
                                            ->storeFiles(false),
                                        Forms\Components\TextInput::make('cert_pfx_password')
                                            ->label(__('admin/ksef_central.form.label.cert_pfx_password'))
                                            ->password()
                                            ->revealable()
                                            ->helperText(__('admin/ksef_central.form.label.cert_pfx_password_helper')),
                                    ]),
                                Forms\Components\Tabs\Tab::make(__('admin/ksef_central.form.label.tab_pem'))
                                    ->schema([
                                        Forms\Components\FileUpload::make('cert_pem_crt')
                                            ->label(__('admin/ksef_central.form.label.cert_pem_crt'))
                                            ->acceptedFileTypes(['application/x-x509-ca-cert', 'application/octet-stream', 'text/plain'])
                                            ->maxSize(50)
                                            ->disk('local')
                                            ->visibility('private')
                                            ->storeFiles(false),
                                        Forms\Components\FileUpload::make('cert_pem_key')
                                            ->label(__('admin/ksef_central.form.label.cert_pem_key'))
                                            ->acceptedFileTypes(['application/octet-stream', 'application/x-pem-file', 'text/plain'])
                                            ->maxSize(50)
                                            ->disk('local')
                                            ->visibility('private')
                                            ->storeFiles(false),
                                        Forms\Components\TextInput::make('cert_pem_password')
                                            ->label(__('admin/ksef_central.form.label.cert_pem_password'))
                                            ->password()
                                            ->revealable(),
                                    ]),
                            ]),
                    ]),

                Forms\Components\Section::make(__('admin/ksef_central.form.section.cert_current'))
                    ->visible($hasCert)
                    ->schema([
                        Forms\Components\Placeholder::make('cert_subject_cn')
                            ->label(__('admin/ksef_central.form.label.cert_subject_cn'))
                            ->content(fn () => $meta['subject_cn'] ?? '—'),
                        Forms\Components\Placeholder::make('cert_subject_nip')
                            ->label(__('admin/ksef_central.form.label.cert_subject_nip'))
                            ->content(fn () => $meta['subject_nip'] ?? '—'),
                        Forms\Components\Placeholder::make('cert_issuer')
                            ->label(__('admin/ksef_central.form.label.cert_issuer'))
                            ->content(fn () => $meta['issuer'] ?? '—'),
                        Forms\Components\Placeholder::make('cert_fingerprint')
                            ->label(__('admin/ksef_central.form.label.cert_fingerprint'))
                            ->content(fn () => $meta['fingerprint'] ?? '—'),
                        Forms\Components\Placeholder::make('cert_valid_to')
                            ->label(__('admin/ksef_central.form.label.cert_valid_to'))
                            ->content(fn () => $meta['valid_to'] ?? '—'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $form = $this->form->getState();

        SystemSetting::setValue('ksef_central.env', (string) ($form['env'] ?? 'test'));
        SystemSetting::setValue('ksef_central.context_nip', (string) ($form['context_nip'] ?? ''));
        SystemSetting::setValue('ksef_central.identifier_type', (string) ($form['identifier_type'] ?? 'certificateSubject'));

        // PFX upload
        $pfxFile = $form['cert_pfx'] ?? null;
        $pfxPassword = (string) ($form['cert_pfx_password'] ?? '');
        if ($pfxFile && $pfxPassword !== '') {
            try {
                $pfxBytes = $this->readUploadedBytes($pfxFile);
                $meta = KsefCertificateService::parsePfx($pfxBytes, $pfxPassword);

                SystemSetting::setValue('ksef_central.cert_format', 'pfx');
                SystemSetting::setSecret('ksef_central.cert_pfx', base64_encode($pfxBytes));
                SystemSetting::setSecret('ksef_central.cert_password', $pfxPassword);
                SystemSetting::setValue('ksef_central.cert_metadata', $meta);

                Notification::make()->title(__('admin/ksef_central.action.pfx_saved'))->success()->send();
            } catch (\Throwable $e) {
                Notification::make()->title(__('admin/ksef_central.action.pfx_error_title'))->body($e->getMessage())->danger()->send();

                return;
            }
        }

        // PEM (.crt + .key) upload
        $crtFile = $form['cert_pem_crt'] ?? null;
        $keyFile = $form['cert_pem_key'] ?? null;
        $pemPassword = (string) ($form['cert_pem_password'] ?? '');
        if ($crtFile && $keyFile) {
            try {
                $crtBytes = $this->readUploadedBytes($crtFile);
                $keyBytes = $this->readUploadedBytes($keyFile);
                $meta = KsefCertificateService::parsePemPair($crtBytes, $keyBytes, $pemPassword ?: null);

                SystemSetting::setValue('ksef_central.cert_format', 'pem');
                SystemSetting::setSecret('ksef_central.cert_crt', $crtBytes);
                SystemSetting::setSecret('ksef_central.cert_key', $keyBytes);
                if ($pemPassword !== '') {
                    SystemSetting::setSecret('ksef_central.cert_password', $pemPassword);
                }
                SystemSetting::setValue('ksef_central.cert_metadata', $meta);

                Notification::make()->title(__('admin/ksef_central.action.pem_saved'))->success()->send();
            } catch (\Throwable $e) {
                Notification::make()->title(__('admin/ksef_central.action.pem_error_title'))->body($e->getMessage())->danger()->send();

                return;
            }
        }

        Notification::make()->title(__('admin/ksef_central.action.saved'))->success()->send();
    }

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
        if (is_array($file) && isset($file['path']) && is_file($file['path'])) {
            return (string) file_get_contents($file['path']);
        }

        throw new \RuntimeException(__('admin/ksef_central.action.cant_read_file'));
    }
}
