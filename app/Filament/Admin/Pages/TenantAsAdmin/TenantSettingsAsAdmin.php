<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages\TenantAsAdmin;

use App\Filament\Components\GusLookupAction;
use App\Models\Central\Tenant;
use App\Services\MasterAuditLogger;
use App\Tenancy\TenantManager;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Master-admin counterpart of App\Filament\App\Pages\TenantSettings.
 *
 * Renders the same branding / public profile / online booking form for
 * a tenant identified by a route param, so master admin can fix
 * client mistakes without impersonation. The save path wraps the
 * `Tenant` model write inside TenantManager::execute() so any
 * downstream tenant-DB writes (cache invalidation, audit log) hit the
 * correct DB. The `tenants` row itself lives on `central` and is
 * always written there.
 */
class TenantSettingsAsAdmin extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.admin.pages.tenant-as-admin.tenant-settings';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'tenants/{tenantId}/settings';

    public string $tenantId = '';

    /** @var array<string,mixed> */
    public array $data = [];

    public function getTitle(): string|Htmlable
    {
        return __('admin/back-office.settings.title', ['name' => $this->tenant()->name]);
    }

    public function getBreadcrumbs(): array
    {
        return [
            url('/admin/tenants') => __('navigation.tenants'),
            url('/admin/tenants/'.$this->tenant()->id.'/edit') => $this->tenant()->name,
            __('admin/back-office.settings.breadcrumb') => '',
        ];
    }

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->is_master_admin;
    }

    public function mount(string $tenantId): void
    {
        abort_unless(self::canAccess(), 403);

        $this->tenantId = $tenantId;
        $tenant = $this->tenant();

        $publicProfile = (array) (data_get($tenant->settings, 'public_profile') ?? []);
        $publicBooking = (array) (data_get($tenant->settings, 'public_booking') ?? []);

        $this->form->fill([
            'name' => $tenant->name,
            'legal_name' => $tenant->legal_name,
            'tax_id' => $tenant->tax_id,
            'primary_color' => $tenant->branding['primary_color'] ?? '#A8956B',
            'logo_path' => $tenant->branding['logo_path'] ?? null,
            'hero_image_path' => $tenant->branding['hero_image_path'] ?? null,
            'pp_tagline' => $publicProfile['tagline'] ?? null,
            'pp_description' => $publicProfile['description'] ?? null,
            'pp_email' => $publicProfile['email'] ?? null,
            'pp_phone' => $publicProfile['phone'] ?? null,
            'pp_address' => $publicProfile['address'] ?? null,
            'pp_website' => $publicProfile['website'] ?? null,
            'pp_opening_hours' => $publicProfile['opening_hours'] ?? null,
            'pb_enabled' => (bool) ($publicBooking['enabled'] ?? false),
            'pb_lesson_duration_minutes' => $publicBooking['lesson_duration_minutes'] ?? 60,
            'pb_working_hours_start' => $publicBooking['working_hours_start'] ?? '09:00',
            'pb_working_hours_end' => $publicBooking['working_hours_end'] ?? '19:00',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('admin/back-office.settings.section.identification'))
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('admin/back-office.settings.label.name'))
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('legal_name')
                            ->label(__('admin/back-office.settings.label.legal_name'))
                            ->maxLength(255),
                        Forms\Components\TextInput::make('tax_id')
                            ->label(__('admin/back-office.settings.label.tax_id'))
                            ->maxLength(32)
                            ->suffixAction(GusLookupAction::make(['name' => 'legal_name'])),
                    ]),

                Forms\Components\Section::make(__('admin/back-office.settings.section.branding'))
                    ->description(__('admin/back-office.settings.section.branding_description'))
                    ->columns(2)
                    ->schema([
                        Forms\Components\ColorPicker::make('primary_color')
                            ->label(__('admin/back-office.settings.label.primary_color')),
                        Forms\Components\FileUpload::make('logo_path')
                            ->label(__('admin/back-office.settings.label.logo_path'))
                            ->image()
                            ->maxSize(2048)
                            ->disk('public')
                            ->directory('branding')
                            ->visibility('public'),
                        Forms\Components\FileUpload::make('hero_image_path')
                            ->label(__('admin/back-office.settings.label.hero_image_path'))
                            ->image()
                            ->maxSize(5120)
                            ->disk('public')
                            ->directory('branding')
                            ->visibility('public')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make(__('admin/back-office.settings.section.public_profile'))
                    ->description(__('admin/back-office.settings.section.public_profile_description'))
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('pp_tagline')
                            ->label(__('admin/back-office.settings.label.pp_tagline'))
                            ->maxLength(120),
                        Forms\Components\TextInput::make('pp_opening_hours')
                            ->label(__('admin/back-office.settings.label.pp_opening_hours'))
                            ->maxLength(120),
                        Forms\Components\Textarea::make('pp_description')
                            ->label(__('admin/back-office.settings.label.pp_description'))
                            ->rows(4)
                            ->columnSpanFull()
                            ->maxLength(2000),
                        Forms\Components\TextInput::make('pp_email')
                            ->label(__('admin/back-office.settings.label.pp_email'))
                            ->email()->maxLength(255),
                        Forms\Components\TextInput::make('pp_phone')
                            ->label(__('admin/back-office.settings.label.pp_phone'))
                            ->tel()->maxLength(40),
                        Forms\Components\TextInput::make('pp_address')
                            ->label(__('admin/back-office.settings.label.pp_address'))
                            ->maxLength(255),
                        Forms\Components\TextInput::make('pp_website')
                            ->label(__('admin/back-office.settings.label.pp_website'))
                            ->url()->maxLength(255),
                    ]),

                Forms\Components\Section::make(__('admin/back-office.settings.section.online_booking'))
                    ->columns(3)
                    ->schema([
                        Forms\Components\Toggle::make('pb_enabled')
                            ->label(__('admin/back-office.settings.label.pb_enabled'))
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('pb_lesson_duration_minutes')
                            ->label(__('admin/back-office.settings.label.pb_lesson_duration_minutes'))
                            ->numeric()->minValue(15)->maxValue(240)->default(60),
                        Forms\Components\TimePicker::make('pb_working_hours_start')
                            ->label(__('admin/back-office.settings.label.pb_working_hours_start'))
                            ->seconds(false)->default('09:00'),
                        Forms\Components\TimePicker::make('pb_working_hours_end')
                            ->label(__('admin/back-office.settings.label.pb_working_hours_end'))
                            ->seconds(false)->default('19:00'),
                    ]),
            ])
            ->statePath('data')
            ->columns(1);
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);

        $data = $this->form->getState();
        $tenant = $this->tenant();

        $branding = (array) ($tenant->branding ?? []);
        $branding['primary_color'] = $data['primary_color'] ?? null;
        $branding['logo_path'] = $data['logo_path'] ?? null;
        $branding['hero_image_path'] = $data['hero_image_path'] ?? null;
        $branding['logo_url'] = $branding['logo_path']
            ? Storage::disk('public')->url($branding['logo_path'])
            : null;
        $branding['hero_image_url'] = $branding['hero_image_path']
            ? Storage::disk('public')->url($branding['hero_image_path'])
            : null;

        $settings = (array) ($tenant->settings ?? []);
        $publicProfile = (array) ($settings['public_profile'] ?? []);
        $publicProfile['tagline'] = $data['pp_tagline'] ?? null;
        $publicProfile['description'] = $data['pp_description'] ?? null;
        $publicProfile['email'] = $data['pp_email'] ?? null;
        $publicProfile['phone'] = $data['pp_phone'] ?? null;
        $publicProfile['address'] = $data['pp_address'] ?? null;
        $publicProfile['website'] = $data['pp_website'] ?? null;
        $publicProfile['opening_hours'] = $data['pp_opening_hours'] ?? null;
        $settings['public_profile'] = $publicProfile;

        $publicBooking = (array) ($settings['public_booking'] ?? []);
        $publicBooking['enabled'] = (bool) ($data['pb_enabled'] ?? false);
        $publicBooking['lesson_duration_minutes'] = (int) ($data['pb_lesson_duration_minutes'] ?? 60);
        $publicBooking['working_hours_start'] = substr((string) ($data['pb_working_hours_start'] ?? '09:00'), 0, 5);
        $publicBooking['working_hours_end'] = substr((string) ($data['pb_working_hours_end'] ?? '19:00'), 0, 5);
        $settings['public_booking'] = $publicBooking;

        $tenant->forceFill([
            'name' => $data['name'],
            'legal_name' => $data['legal_name'] ?? null,
            'tax_id' => $data['tax_id'] ?? null,
            'branding' => $branding,
            'settings' => $settings,
        ])->save();

        // Public-site cache lives keyed on slug; flush so back-office
        // edits hit /s/{slug} immediately (same keys as TenantSettings).
        Cache::forget("public_site:{$tenant->slug}");
        Cache::forget("public_box_availability:{$tenant->slug}");
        Cache::forget("public_instructors:{$tenant->slug}");
        Cache::forget("public_pricing:{$tenant->slug}");

        app(MasterAuditLogger::class)->record(
            'tenant.settings.update_as_admin',
            'Tenant',
            $tenant->id,
            $tenant->id,
            ['fields' => ['branding', 'public_profile', 'public_booking', 'name']],
        );

        Notification::make()
            ->success()
            ->title(__('admin/back-office.settings.saved_title'))
            ->body(__('admin/back-office.settings.saved_body', ['slug' => $tenant->slug]))
            ->send();
    }

    private function tenant(): Tenant
    {
        // Tenants live on `central` and are always loaded directly; the
        // TenantManager::execute() wrap on save() exists for tenant-DB
        // side-effects (audit log, future hooks).
        return Tenant::query()
            ->withTrashed()
            ->findOrFail($this->tenantId);
    }

    /**
     * Convenience helper for tests / extensions that need to run a
     * callback under the page's tenant context.
     */
    public function withTenantContext(callable $callback): mixed
    {
        return app(TenantManager::class)->execute($this->tenant(), $callback);
    }
}
