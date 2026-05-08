<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Models\Central\Tenant;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * Per-stable settings, editable by users with role owner/admin.
 * Tenant slug + db credentials are intentionally NOT exposed here —
 * they're master-admin territory.
 */
class TenantSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    public static function getNavigationLabel(): string
    {
        return __('pages.tenant_settings.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.settings');
    }

    public function getTitle(): string|Htmlable
    {
        return __('pages.tenant_settings.title');
    }

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.pages.tenant-settings';

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

        $publicBooking = (array) (data_get($tenant->settings, 'public_booking') ?? []);
        $publicProfile = (array) (data_get($tenant->settings, 'public_profile') ?? []);

        $this->form->fill([
            'name' => $tenant->name,
            'legal_name' => $tenant->legal_name,
            'tax_id' => $tenant->tax_id,
            'country' => $tenant->country,
            'locale' => $tenant->locale,
            'timezone' => $tenant->timezone,
            'currency' => $tenant->currency,
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
            'pp_social_facebook' => $publicProfile['social_facebook'] ?? null,
            'pp_social_instagram' => $publicProfile['social_instagram'] ?? null,
            'pp_social_youtube' => $publicProfile['social_youtube'] ?? null,
            'pp_social_tiktok' => $publicProfile['social_tiktok'] ?? null,
            'pp_show_box_availability' => (bool) ($publicProfile['show_box_availability'] ?? true),
            'pp_show_instructors' => (bool) ($publicProfile['show_instructors'] ?? false),
            'pp_show_pricing' => (bool) ($publicProfile['show_pricing'] ?? false),
            'pb_enabled' => (bool) ($publicBooking['enabled'] ?? false),
            'pb_lesson_duration_minutes' => $publicBooking['lesson_duration_minutes'] ?? 60,
            'pb_working_hours_start' => $publicBooking['working_hours_start'] ?? '09:00',
            'pb_working_hours_end' => $publicBooking['working_hours_end'] ?? '19:00',
            'pb_advance_min_hours' => $publicBooking['advance_min_hours'] ?? 4,
            'pb_advance_max_days' => $publicBooking['advance_max_days'] ?? 30,
        ]);
    }

    public function form(Form $form): Form
    {
        $slug = app(TenantManager::class)->current()?->slug ?? '';

        return $form
            ->schema([
                Forms\Components\Section::make(__('app/tenant_settings.form.section.identification'))
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('app/tenant_settings.form.label.name'))->required()->maxLength(255),
                        Forms\Components\TextInput::make('legal_name')
                            ->label(__('app/tenant_settings.form.label.legal_name'))->maxLength(255),
                        Forms\Components\TextInput::make('tax_id')
                            ->label(__('app/tenant_settings.form.label.tax_id'))->maxLength(32),
                    ]),

                Forms\Components\Section::make(__('app/tenant_settings.form.section.location'))
                    ->columns(4)
                    ->schema([
                        Forms\Components\TextInput::make('country')
                            ->label(__('app/tenant_settings.form.label.country'))->required()->maxLength(2),
                        Forms\Components\Select::make('locale')
                            ->label(__('app/tenant_settings.form.label.locale'))
                            ->options([
                                'pl' => 'Polski', 'en' => 'English', 'de' => 'Deutsch',
                                'nl' => 'Nederlands', 'fr' => 'Français', 'it' => 'Italiano', 'es' => 'Español',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('timezone')
                            ->label(__('app/tenant_settings.form.label.timezone'))->required()->maxLength(64),
                        Forms\Components\Select::make('currency')
                            ->label(__('app/tenant_settings.form.label.currency'))
                            ->options([
                                'PLN' => 'PLN', 'EUR' => 'EUR', 'CHF' => 'CHF',
                                'CZK' => 'CZK', 'HUF' => 'HUF', 'GBP' => 'GBP', 'USD' => 'USD',
                            ])
                            ->required(),
                    ]),

                Forms\Components\Section::make(__('app/tenant_settings.form.section.branding'))
                    ->description(__('app/tenant_settings.form.section.branding_description'))
                    ->columns(2)
                    ->schema([
                        Forms\Components\ColorPicker::make('primary_color')
                            ->label(__('app/tenant_settings.form.label.primary_color')),
                        Forms\Components\FileUpload::make('logo_path')
                            ->label(__('app/tenant_settings.form.label.logo_path'))
                            ->image()
                            ->maxSize(2048)
                            ->disk('public')
                            ->directory('branding')
                            ->visibility('public')
                            ->helperText(__('app/tenant_settings.form.label.logo_helper')),
                        Forms\Components\FileUpload::make('hero_image_path')
                            ->label(__('app/tenant_settings.form.label.hero_image_path'))
                            ->image()
                            ->maxSize(5120)
                            ->disk('public')
                            ->directory('branding')
                            ->visibility('public')
                            ->columnSpanFull()
                            ->helperText(__('app/tenant_settings.form.label.hero_image_helper')),
                    ]),

                Forms\Components\Section::make(__('app/tenant_settings.form.section.public_site', ['slug' => $slug]))
                    ->description(__('app/tenant_settings.form.section.public_site_description'))
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('pp_tagline')
                            ->label(__('app/tenant_settings.form.label.pp_tagline'))
                            ->maxLength(120)
                            ->placeholder(__('app/tenant_settings.form.label.pp_tagline_placeholder')),
                        Forms\Components\TextInput::make('pp_opening_hours')
                            ->label(__('app/tenant_settings.form.label.pp_opening_hours'))
                            ->maxLength(120)
                            ->placeholder(__('app/tenant_settings.form.label.pp_opening_hours_placeholder')),
                        Forms\Components\Textarea::make('pp_description')
                            ->label(__('app/tenant_settings.form.label.pp_description'))
                            ->rows(4)
                            ->columnSpanFull()
                            ->maxLength(2000),
                        Forms\Components\TextInput::make('pp_email')
                            ->label(__('app/tenant_settings.form.label.pp_email'))
                            ->email()->maxLength(255),
                        Forms\Components\TextInput::make('pp_phone')
                            ->label(__('app/tenant_settings.form.label.pp_phone'))
                            ->tel()->maxLength(40),
                        Forms\Components\TextInput::make('pp_address')
                            ->label(__('app/tenant_settings.form.label.pp_address'))
                            ->maxLength(255),
                        Forms\Components\TextInput::make('pp_website')
                            ->label(__('app/tenant_settings.form.label.pp_website'))
                            ->url()->maxLength(255),
                        Forms\Components\Toggle::make('pp_show_box_availability')
                            ->label(__('app/tenant_settings.form.label.pp_show_box_availability'))
                            ->default(true),
                        Forms\Components\Toggle::make('pp_show_instructors')
                            ->label(__('app/tenant_settings.form.label.pp_show_instructors'))
                            ->default(false),
                        Forms\Components\Toggle::make('pp_show_pricing')
                            ->label(__('app/tenant_settings.form.label.pp_show_pricing'))
                            ->default(false)
                            ->helperText(__('app/tenant_settings.form.label.pp_show_pricing_helper')),
                    ]),

                Forms\Components\Section::make(__('app/tenant_settings.form.section.social'))
                    ->description(__('app/tenant_settings.form.section.social_description'))
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('pp_social_facebook')
                            ->label(__('app/tenant_settings.form.label.pp_social_facebook'))->url()->placeholder('https://facebook.com/twoja-stajnia'),
                        Forms\Components\TextInput::make('pp_social_instagram')
                            ->label(__('app/tenant_settings.form.label.pp_social_instagram'))->url()->placeholder('https://instagram.com/twoja-stajnia'),
                        Forms\Components\TextInput::make('pp_social_youtube')
                            ->label(__('app/tenant_settings.form.label.pp_social_youtube'))->url()->placeholder('https://youtube.com/@twoja-stajnia'),
                        Forms\Components\TextInput::make('pp_social_tiktok')
                            ->label(__('app/tenant_settings.form.label.pp_social_tiktok'))->url()->placeholder('https://tiktok.com/@twoja-stajnia'),
                    ]),

                Forms\Components\Section::make(__('app/tenant_settings.form.section.embeds'))
                    ->description(__('app/tenant_settings.form.section.embeds_description'))
                    ->collapsed()
                    ->schema([
                        Forms\Components\Placeholder::make('embed_box_availability')
                            ->label(__('app/tenant_settings.form.label.embed_box_availability'))
                            ->content(fn () => self::embedSnippet('box-availability', 220)),
                        Forms\Components\Placeholder::make('embed_booking')
                            ->label(__('app/tenant_settings.form.label.embed_booking'))
                            ->content(fn () => self::embedSnippet('booking', 280)),
                        Forms\Components\Placeholder::make('embed_pricing')
                            ->label(__('app/tenant_settings.form.label.embed_pricing'))
                            ->content(fn () => self::embedSnippet('pricing', 480)),
                        Forms\Components\Placeholder::make('embed_instructors')
                            ->label(__('app/tenant_settings.form.label.embed_instructors'))
                            ->content(fn () => self::embedSnippet('instructors', 320)),
                    ]),

                Forms\Components\Section::make(__('app/tenant_settings.form.section.online_booking'))
                    ->description(__('app/tenant_settings.form.section.online_booking_description'))
                    ->columns(3)
                    ->schema([
                        Forms\Components\Toggle::make('pb_enabled')
                            ->label(__('app/tenant_settings.form.label.pb_enabled'))
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('pb_lesson_duration_minutes')
                            ->label(__('app/tenant_settings.form.label.pb_lesson_duration_minutes'))
                            ->numeric()->minValue(15)->maxValue(240)->default(60),
                        Forms\Components\TimePicker::make('pb_working_hours_start')
                            ->label(__('app/tenant_settings.form.label.pb_working_hours_start'))->seconds(false)->default('09:00'),
                        Forms\Components\TimePicker::make('pb_working_hours_end')
                            ->label(__('app/tenant_settings.form.label.pb_working_hours_end'))->seconds(false)->default('19:00'),
                        Forms\Components\TextInput::make('pb_advance_min_hours')
                            ->label(__('app/tenant_settings.form.label.pb_advance_min_hours'))
                            ->numeric()->minValue(0)->maxValue(168)->default(4)
                            ->helperText(__('app/tenant_settings.form.label.pb_advance_min_hours_helper')),
                        Forms\Components\TextInput::make('pb_advance_max_days')
                            ->label(__('app/tenant_settings.form.label.pb_advance_max_days'))
                            ->numeric()->minValue(1)->maxValue(180)->default(30)
                            ->helperText(__('app/tenant_settings.form.label.pb_advance_max_days_helper')),
                    ]),
            ])
            ->statePath('data')
            ->columns(1);
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);

        $data = $this->form->getState();
        $tenant = app(TenantManager::class)->tenantOrFail();

        $branding = (array) ($tenant->branding ?? []);
        $branding['primary_color'] = $data['primary_color'] ?? null;
        $branding['logo_path'] = $data['logo_path'] ?? null;
        $branding['hero_image_path'] = $data['hero_image_path'] ?? null;
        // Public URLs — generowane z file paths. /storage/branding/...
        $branding['logo_url'] = $branding['logo_path']
            ? Storage::disk('public')->url($branding['logo_path'])
            : null;
        $branding['hero_image_url'] = $branding['hero_image_path']
            ? Storage::disk('public')->url($branding['hero_image_path'])
            : null;

        $settings = (array) ($tenant->settings ?? []);
        $settings['public_profile'] = [
            'tagline' => $data['pp_tagline'] ?? null,
            'description' => $data['pp_description'] ?? null,
            'email' => $data['pp_email'] ?? null,
            'phone' => $data['pp_phone'] ?? null,
            'address' => $data['pp_address'] ?? null,
            'website' => $data['pp_website'] ?? null,
            'opening_hours' => $data['pp_opening_hours'] ?? null,
            'social_facebook' => $data['pp_social_facebook'] ?? null,
            'social_instagram' => $data['pp_social_instagram'] ?? null,
            'social_youtube' => $data['pp_social_youtube'] ?? null,
            'social_tiktok' => $data['pp_social_tiktok'] ?? null,
            'show_box_availability' => (bool) ($data['pp_show_box_availability'] ?? true),
            'show_instructors' => (bool) ($data['pp_show_instructors'] ?? false),
            'show_pricing' => (bool) ($data['pp_show_pricing'] ?? false),
        ];
        $settings['public_booking'] = [
            'enabled' => (bool) ($data['pb_enabled'] ?? false),
            'lesson_duration_minutes' => (int) ($data['pb_lesson_duration_minutes'] ?? 60),
            'working_hours_start' => $this->normaliseTime($data['pb_working_hours_start'] ?? '09:00'),
            'working_hours_end' => $this->normaliseTime($data['pb_working_hours_end'] ?? '19:00'),
            'advance_min_hours' => (int) ($data['pb_advance_min_hours'] ?? 4),
            'advance_max_days' => (int) ($data['pb_advance_max_days'] ?? 30),
        ];

        $changes = [
            'name' => $data['name'],
            'legal_name' => $data['legal_name'] ?? null,
            'tax_id' => $data['tax_id'] ?? null,
            'country' => $data['country'],
            'locale' => $data['locale'],
            'timezone' => $data['timezone'],
            'currency' => $data['currency'],
            'branding' => $branding,
            'settings' => $settings,
        ];

        // Reload via Tenant model so JSON casts apply to `branding`.
        Tenant::findOrFail($tenant->id)
            ->forceFill($changes)
            ->save();

        // Invalidate public site cache (5 min default w PublicSiteController) —
        // żeby zmiany w brandingu były od razu widoczne na /s/{slug}.
        Cache::forget("public_site:{$tenant->slug}");
        Cache::forget("public_box_availability:{$tenant->slug}");
        Cache::forget("public_instructors:{$tenant->slug}");
        Cache::forget("public_pricing:{$tenant->slug}");

        app(TenantAuditLogger::class)->record(
            'tenant.settings.update',
            'Tenant',
            $tenant->id,
            ['fields' => array_keys($changes)],
        );

        Notification::make()
            ->success()
            ->title(__('app/tenant_settings.action.saved_title'))
            ->body(__('app/tenant_settings.action.saved_body', ['slug' => $tenant->slug]))
            ->send();
    }

    /**
     * Filament TimePicker may emit either "HH:MM" or "HH:MM:SS" depending
     * on `seconds(false)` config; we always store "HH:MM" so the
     * settings JSON stays predictable.
     */
    private function normaliseTime(?string $value): string
    {
        if (! $value) {
            return '09:00';
        }

        return substr($value, 0, 5);
    }

    /**
     * Buduje gotowy do skopiowania snippet HTML z iframe dla danego widgetu.
     * Wstawiany jako Placeholder content w form sekcji "Widgety".
     */
    private static function embedSnippet(string $widget, int $height): HtmlString
    {
        $tenant = app(TenantManager::class)->current();
        if (! $tenant) {
            return new HtmlString('<em>'.e(__('app/tenant_settings.embed.no_tenant')).'</em>');
        }

        $url = url('/'.config('hovera.public_site.prefix', 's').'/'.$tenant->slug.'/embed/'.$widget);
        $iframe = '<iframe src="'.e($url).'" width="100%" height="'.$height.'" frameborder="0" style="border:0; border-radius:16px;"></iframe>';

        $html = '<div style="display:flex; flex-direction:column; gap:.5rem;">'
            .'<textarea readonly onclick="this.select()" rows="2" style="width:100%; padding:.5rem .75rem; font-family:ui-monospace,monospace; font-size:.85rem; border:1px solid #e5e7eb; border-radius:6px; background:#f9fafb; resize:vertical;">'
            .e($iframe)
            .'</textarea>'
            .'<div style="font-size:.8rem; color:#6b7280;">'.e(__('app/tenant_settings.embed.help')).'</div>'
            .'</div>';

        return new HtmlString($html);
    }
}
