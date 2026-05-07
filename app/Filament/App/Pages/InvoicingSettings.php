<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Enums\InvoiceKind;
use App\Services\Invoicing\InvoiceNumberGenerator;
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
 * Per-stable konfiguracja faktur. Numeracja, dane sprzedawcy snapshot,
 * domyślne terminy płatności. KSeF/billu.pl trafią tu w PR 4.
 */
class InvoicingSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Faktury i rozliczenia';

    protected static ?string $title = 'Faktury i rozliczenia';

    protected static ?string $navigationGroup = 'Ustawienia';

    protected static ?int $navigationSort = 30;

    protected static string $view = 'filament.pages.invoicing-settings';

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
        $invoicing = (array) (data_get($tenant->settings, 'invoicing') ?? []);

        $this->form->fill([
            'template_fv' => $invoicing['template']['fv'] ?? InvoiceNumberGenerator::DEFAULT_TEMPLATES['fv'],
            'template_pro' => $invoicing['template']['fv_proforma'] ?? InvoiceNumberGenerator::DEFAULT_TEMPLATES['fv_proforma'],
            'template_kor' => $invoicing['template']['fv_korekta'] ?? InvoiceNumberGenerator::DEFAULT_TEMPLATES['fv_korekta'],
            'reset_interval' => $invoicing['reset_interval'] ?? 'yearly',
            'prefix' => $invoicing['prefix'] ?? '',
            'default_due_days' => $invoicing['default_due_days'] ?? 7,
            'seller_name' => $invoicing['seller_name'] ?? $tenant->legal_name ?? $tenant->name,
            'seller_nip' => $invoicing['seller_nip'] ?? $tenant->tax_id,
            'seller_address' => $invoicing['seller_address'] ?? null,
            'seller_postal_code' => $invoicing['seller_postal_code'] ?? null,
            'seller_city' => $invoicing['seller_city'] ?? null,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make('Numeracja faktur')
                    ->description('Placeholdery: {seq}, {seq:NN} (np. {seq:4} → 0001), {YYYY}, {YY}, {MM}, {M}, {DD}, {prefix}.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('template_fv')->label('Wzór FV')->required(),
                        Forms\Components\TextInput::make('template_pro')->label('Wzór Proforma')->required(),
                        Forms\Components\TextInput::make('template_kor')->label('Wzór Korekta')->required(),
                        Forms\Components\TextInput::make('prefix')->label('Prefiks (placeholder {prefix})')
                            ->placeholder('np. STW')->maxLength(16),
                        Forms\Components\Radio::make('reset_interval')
                            ->label('Reset numeracji')
                            ->options(InvoiceNumberGenerator::RESET_OPTIONS)
                            ->default('yearly')
                            ->required(),
                        Forms\Components\TextInput::make('default_due_days')
                            ->label('Domyślny termin płatności (dni)')
                            ->numeric()->minValue(0)->maxValue(180)->default(7),
                    ]),

                Forms\Components\Section::make('Dane sprzedawcy (snapshot na fakturach)')
                    ->description('Te dane zostaną zapisane na każdej nowej fakturze w momencie utworzenia. Edycja danych stajni nie zmieni już wystawionych FV.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('seller_name')->label('Nazwa sprzedawcy')->required(),
                        Forms\Components\TextInput::make('seller_nip')->label('NIP sprzedawcy')->maxLength(16),
                        Forms\Components\TextInput::make('seller_address')->label('Adres')->maxLength(255),
                        Forms\Components\TextInput::make('seller_postal_code')->label('Kod pocztowy')->maxLength(16),
                        Forms\Components\TextInput::make('seller_city')->label('Miasto')->maxLength(120),
                    ]),
            ]);
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);

        $tenant = app(TenantManager::class)->tenantOrFail();
        $form = $this->form->getState();

        $settings = (array) ($tenant->settings ?? []);
        $settings['invoicing'] = [
            'template' => [
                'fv' => (string) $form['template_fv'],
                'fv_proforma' => (string) $form['template_pro'],
                'fv_korekta' => (string) $form['template_kor'],
            ],
            'reset_interval' => (string) ($form['reset_interval'] ?? 'yearly'),
            'prefix' => (string) ($form['prefix'] ?? ''),
            'default_due_days' => (int) ($form['default_due_days'] ?? 7),
            'seller_name' => (string) ($form['seller_name'] ?? ''),
            'seller_nip' => (string) ($form['seller_nip'] ?? '') ?: null,
            'seller_address' => (string) ($form['seller_address'] ?? '') ?: null,
            'seller_postal_code' => (string) ($form['seller_postal_code'] ?? '') ?: null,
            'seller_city' => (string) ($form['seller_city'] ?? '') ?: null,
        ];
        $tenant->forceFill(['settings' => $settings])->save();

        app(TenantAuditLogger::class)->record('invoicing.settings_updated', 'Tenant', (string) $tenant->id);

        Notification::make()->title('Zapisano ustawienia faktur')->success()->send();
    }

    /**
     * Live preview kolejnego numeru — pokazujemy 3 ostatnie żeby owner
     * widział jak template się rozwija.
     *
     * @return array<int,string>
     */
    public function previewNumbers(): array
    {
        $tenant = app(TenantManager::class)->tenantOrFail();
        $gen = app(InvoiceNumberGenerator::class);

        return [
            $gen->preview($tenant, InvoiceKind::Fv, 1),
            $gen->preview($tenant, InvoiceKind::Fv, 2),
            $gen->preview($tenant, InvoiceKind::Fv, 99),
        ];
    }
}
