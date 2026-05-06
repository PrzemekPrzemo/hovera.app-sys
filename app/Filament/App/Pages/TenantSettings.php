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

    protected static ?string $navigationGroup = 'Stajnia';

    protected static ?int $navigationSort = 99;

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

        $this->form->fill([
            'name' => $tenant->name,
            'legal_name' => $tenant->legal_name,
            'tax_id' => $tenant->tax_id,
            'country' => $tenant->country,
            'locale' => $tenant->locale,
            'timezone' => $tenant->timezone,
            'currency' => $tenant->currency,
            'primary_color' => $tenant->branding['primary_color'] ?? '#10b981',
            'logo_url' => $tenant->branding['logo_url'] ?? null,
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
                    ->columns(2)
                    ->schema([
                        Forms\Components\ColorPicker::make('primary_color')->label('Kolor wiodący'),
                        Forms\Components\TextInput::make('logo_url')->label('URL logo')
                            ->url()->maxLength(500)
                            ->helperText('Tymczasowe — własne uploady dorzucimy w kolejnej iteracji.'),
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
        $branding['logo_url'] = $data['logo_url'] ?? null;

        $changes = [
            'name' => $data['name'],
            'legal_name' => $data['legal_name'] ?? null,
            'tax_id' => $data['tax_id'] ?? null,
            'country' => $data['country'],
            'locale' => $data['locale'],
            'timezone' => $data['timezone'],
            'currency' => $data['currency'],
            'branding' => $branding,
        ];

        // Reload via Tenant model so JSON casts apply to `branding`.
        Tenant::findOrFail($tenant->id)
            ->forceFill($changes)
            ->save();

        app(TenantAuditLogger::class)->record(
            'tenant.settings.update',
            'Tenant',
            $tenant->id,
            ['fields' => array_keys($changes)],
        );

        Notification::make()
            ->success()
            ->title('Ustawienia zapisane')
            ->send();
    }
}
