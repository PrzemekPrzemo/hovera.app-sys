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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Per-stable settings, editable by users with role owner/admin.
 * Tenant slug + db credentials are intentionally NOT exposed here —
 * they're master-admin territory.
 */
class TenantSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Ustawienia stajni';

    protected static ?string $title = 'Ustawienia stajni';

    protected static ?string $navigationGroup = 'Ustawienia';

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
            'pp_tagline' => $publicProfile['tagline'] ?? null,
            'pp_description' => $publicProfile['description'] ?? null,
            'pp_email' => $publicProfile['email'] ?? null,
            'pp_phone' => $publicProfile['phone'] ?? null,
            'pp_address' => $publicProfile['address'] ?? null,
            'pp_website' => $publicProfile['website'] ?? null,
            'pp_opening_hours' => $publicProfile['opening_hours'] ?? null,
            'pp_show_box_availability' => (bool) ($publicProfile['show_box_availability'] ?? true),
            'pp_show_instructors' => (bool) ($publicProfile['show_instructors'] ?? false),
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
        return $form
            ->schema([
                Forms\Components\Section::make('Identyfikacja')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')->label('Nazwa stajni')->required()->maxLength(255),
                        Forms\Components\TextInput::make('legal_name')->label('Nazwa prawna (na faktury)')->maxLength(255),
                        Forms\Components\TextInput::make('tax_id')->label('NIP / VAT ID')->maxLength(32),
                    ]),

                Forms\Components\Section::make('Lokalizacja')
                    ->columns(4)
                    ->schema([
                        Forms\Components\TextInput::make('country')->label('Kraj')->required()->maxLength(2),
                        Forms\Components\Select::make('locale')->label('Język domyślny')
                            ->options([
                                'pl' => 'Polski', 'en' => 'English', 'de' => 'Deutsch',
                                'nl' => 'Nederlands', 'fr' => 'Français', 'it' => 'Italiano', 'es' => 'Español',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('timezone')->label('Strefa czasowa')->required()->maxLength(64),
                        Forms\Components\Select::make('currency')->label('Waluta')
                            ->options([
                                'PLN' => 'PLN', 'EUR' => 'EUR', 'CHF' => 'CHF',
                                'CZK' => 'CZK', 'HUF' => 'HUF', 'GBP' => 'GBP', 'USD' => 'USD',
                            ])
                            ->required(),
                    ]),

                Forms\Components\Section::make('Branding')
                    ->description('Logo i kolor wiodący są używane w panelu klienta + na publicznej stronie /s/{slug}.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\ColorPicker::make('primary_color')->label('Kolor wiodący'),
                        Forms\Components\FileUpload::make('logo_path')
                            ->label('Logo stajni')
                            ->image()
                            ->maxSize(2048) // 2 MB
                            ->disk('public')
                            ->directory('branding')
                            ->visibility('public')
                            ->imageResizeMode('contain')
                            ->imageCropAspectRatio(null)
                            ->helperText('PNG / JPG / WebP / SVG, max 2 MB. Najlepiej kwadratowe lub poziome (max ~400×100 px).'),
                    ]),

                Forms\Components\Section::make('Strona publiczna /s/'.app(TenantManager::class)->current()?->slug)
                    ->description('Treść widoczna dla klientów odwiedzających waszą publiczną stronę. Wszystko opcjonalne.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('pp_tagline')->label('Tagline')
                            ->maxLength(120)
                            ->placeholder('np. "Stajnia z duszą — pensjonat, lekcje, rekreacja"'),
                        Forms\Components\TextInput::make('pp_opening_hours')->label('Godziny otwarcia')
                            ->maxLength(120)
                            ->placeholder('np. "Pn–Pt: 9:00–20:00 · Sob–Nd: 8:00–18:00"'),
                        Forms\Components\Textarea::make('pp_description')->label('O stajni')
                            ->rows(4)
                            ->columnSpanFull()
                            ->maxLength(2000),
                        Forms\Components\TextInput::make('pp_email')->label('Email kontaktowy')
                            ->email()->maxLength(255),
                        Forms\Components\TextInput::make('pp_phone')->label('Telefon')
                            ->tel()->maxLength(40),
                        Forms\Components\TextInput::make('pp_address')->label('Adres')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('pp_website')->label('Strona WWW')
                            ->url()->maxLength(255),
                        Forms\Components\Toggle::make('pp_show_box_availability')
                            ->label('Pokaż "Mamy X wolnych boksów"')
                            ->default(true),
                        Forms\Components\Toggle::make('pp_show_instructors')
                            ->label('Pokaż listę instruktorów')
                            ->default(false),
                    ]),

                Forms\Components\Section::make('Online booking')
                    ->description('Klienci rezerwują lekcje przez /s/{slug}/book. Stajnia potwierdza zgłoszenia ręcznie i przydziela konia.')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Toggle::make('pb_enabled')
                            ->label('Włącz online booking')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('pb_lesson_duration_minutes')
                            ->label('Długość lekcji (min)')
                            ->numeric()->minValue(15)->maxValue(240)->default(60),
                        Forms\Components\TimePicker::make('pb_working_hours_start')
                            ->label('Godziny pracy od')->seconds(false)->default('09:00'),
                        Forms\Components\TimePicker::make('pb_working_hours_end')
                            ->label('Godziny pracy do')->seconds(false)->default('19:00'),
                        Forms\Components\TextInput::make('pb_advance_min_hours')
                            ->label('Min. wyprzedzenie (h)')
                            ->numeric()->minValue(0)->maxValue(168)->default(4)
                            ->helperText('Klient nie może rezerwować na czas bliższy niż X godzin.'),
                        Forms\Components\TextInput::make('pb_advance_max_days')
                            ->label('Max horyzont (dni)')
                            ->numeric()->minValue(1)->maxValue(180)->default(30)
                            ->helperText('Klient nie widzi terminów odleglejszych niż X dni.'),
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
        // Public URL — generowany z file path. /storage/branding/...
        $branding['logo_url'] = $branding['logo_path']
            ? Storage::disk('public')->url($branding['logo_path'])
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
            'show_box_availability' => (bool) ($data['pp_show_box_availability'] ?? true),
            'show_instructors' => (bool) ($data['pp_show_instructors'] ?? false),
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

        app(TenantAuditLogger::class)->record(
            'tenant.settings.update',
            'Tenant',
            $tenant->id,
            ['fields' => array_keys($changes)],
        );

        Notification::make()
            ->success()
            ->title('Ustawienia zapisane')
            ->body('Zmiany na publicznej stronie /s/'.$tenant->slug.' są od razu widoczne.')
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
}
