<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Enums\InvoiceKind;
use App\Filament\Components\GusLookupAction;
use App\Filament\Concerns\RestrictedByTenantRole;
use App\Services\Invoicing\InvoiceNumberGenerator;
use App\Services\Tenancy\TenantRoleGate;
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

/**
 * Per-stable konfiguracja faktur. Numeracja, dane sprzedawcy snapshot,
 * domyślne terminy płatności. KSeF/billu.pl trafią tu w PR 4.
 */
class InvoicingSettings extends Page implements HasForms
{
    use InteractsWithForms;
    use RestrictedByTenantRole;

    protected static function allowedRoles(): array
    {
        return TenantRoleGate::FINANCE_STAFF;
    }

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public static function getNavigationLabel(): string
    {
        return __('pages.invoicing_settings.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.settings');
    }

    public function getTitle(): string|Htmlable
    {
        return __('pages.invoicing_settings.title');
    }

    protected static ?int $navigationSort = 30;

    protected static string $view = 'filament.pages.invoicing-settings';

    /** @var array<string,mixed> */
    public array $data = [];

    public static function shouldRegisterNavigation(): bool
    {
        return self::canAccess();
    }

    public static function canAccess(): bool
    {
        $tenant = app(TenantManager::class)->current();
        if (! $tenant) {
            return false;
        }
        // InvoicingSettings (numeracja FV + seller snapshot) jest tylko dla
        // podatników VAT. HorseOwner nie wystawia FV — strona ukryta. Patrz
        // docs/ROLE-MATRIX.md.
        if (! $tenant->type?->canIssueInvoices()) {
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
                Forms\Components\Section::make(__('app/invoicing_settings.form.section.numbering'))
                    ->description(__('app/invoicing_settings.form.section.numbering_description'))
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('template_fv')
                            ->label(__('app/invoicing_settings.form.label.template_fv'))->required(),
                        Forms\Components\TextInput::make('template_pro')
                            ->label(__('app/invoicing_settings.form.label.template_pro'))->required(),
                        Forms\Components\TextInput::make('template_kor')
                            ->label(__('app/invoicing_settings.form.label.template_kor'))->required(),
                        Forms\Components\TextInput::make('prefix')
                            ->label(__('app/invoicing_settings.form.label.prefix'))
                            ->placeholder(__('app/invoicing_settings.form.label.prefix_placeholder'))->maxLength(16),
                        Forms\Components\Radio::make('reset_interval')
                            ->label(__('app/invoicing_settings.form.label.reset_interval'))
                            ->options(InvoiceNumberGenerator::resetOptions())
                            ->default('yearly')
                            ->required(),
                        Forms\Components\TextInput::make('default_due_days')
                            ->label(__('app/invoicing_settings.form.label.default_due_days'))
                            ->numeric()->minValue(0)->maxValue(180)->default(7),
                    ]),

                Forms\Components\Section::make(__('app/invoicing_settings.form.section.seller'))
                    ->description(__('app/invoicing_settings.form.section.seller_description'))
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('seller_name')
                            ->label(__('app/invoicing_settings.form.label.seller_name'))->required(),
                        Forms\Components\TextInput::make('seller_nip')
                            ->label(__('app/invoicing_settings.form.label.seller_nip'))->maxLength(16)
                            ->suffixAction(GusLookupAction::make([
                                'name' => 'seller_name',
                                'street' => 'seller_address',
                                'city' => 'seller_city',
                                'postal_code' => 'seller_postal_code',
                            ], sourceField: 'seller_nip')),
                        Forms\Components\TextInput::make('seller_address')
                            ->label(__('app/invoicing_settings.form.label.seller_address'))->maxLength(255),
                        Forms\Components\TextInput::make('seller_postal_code')
                            ->label(__('app/invoicing_settings.form.label.seller_postal_code'))->maxLength(16),
                        Forms\Components\TextInput::make('seller_city')
                            ->label(__('app/invoicing_settings.form.label.seller_city'))->maxLength(120),
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

        Notification::make()->title(__('app/invoicing_settings.action.saved'))->success()->send();
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
