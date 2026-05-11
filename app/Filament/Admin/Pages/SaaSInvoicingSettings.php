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

/**
 * Master-admin: numeracja + szablony faktur SaaS wystawianych stajniom
 * (faktury w `central.invoices`).
 *
 * Szablon numerowy: tokeny {YYYY}, {YY}, {MM}, {NNNN}, {NN}, {SEQ}.
 * Cykl resetu: monthly (numer resetuje się na początku miesiąca) /
 * yearly (na początku roku) / never (ciągła sekwencja od początku).
 *
 * Następny numer override — jeśli wpisany, kolejna FV użyje tej wartości
 * jako SEQ. Przydatne po imporcie z innego systemu albo gdy chcemy wystartować
 * od konkretnej liczby (np. HVR/2026/02/0042).
 *
 * Wszystko w central.system_settings pod kluczem `saas_invoicing.*`.
 */
class SaaSInvoicingSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 14;

    protected static string $view = 'filament.admin.pages.saas-invoicing-settings';

    /** @var array<string,mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->is_master_admin;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin/saas_invoicing.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.configuration');
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin/saas_invoicing.title');
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);

        $this->form->fill([
            'number_template' => SystemSetting::getValue('saas_invoicing.number_template', 'HVR/{YYYY}/{MM}/{NNNN}'),
            'reset_cycle' => SystemSetting::getValue('saas_invoicing.reset_cycle', 'monthly'),
            'next_sequence' => SystemSetting::getValue('saas_invoicing.next_sequence_override', null),
            'vat_rate' => (int) SystemSetting::getValue('saas_invoicing.vat_rate', 23),
            'due_days' => (int) SystemSetting::getValue('saas_invoicing.due_days', 14),
            'currency' => SystemSetting::getValue('saas_invoicing.currency', 'PLN'),
            'footer_note' => SystemSetting::getValue('saas_invoicing.footer_note'),
            'payment_terms' => SystemSetting::getValue('saas_invoicing.payment_terms'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('admin/saas_invoicing.section.numbering'))
                    ->description(__('admin/saas_invoicing.section.numbering_help'))
                    ->schema([
                        Forms\Components\TextInput::make('number_template')
                            ->label(__('admin/saas_invoicing.field.number_template'))
                            ->required()
                            ->maxLength(120)
                            ->placeholder('HVR/{YYYY}/{MM}/{NNNN}')
                            ->helperText(__('admin/saas_invoicing.field.number_template_help')),
                        Forms\Components\Radio::make('reset_cycle')
                            ->label(__('admin/saas_invoicing.field.reset_cycle'))
                            ->options([
                                'monthly' => __('admin/saas_invoicing.cycle.monthly'),
                                'yearly' => __('admin/saas_invoicing.cycle.yearly'),
                                'never' => __('admin/saas_invoicing.cycle.never'),
                            ])
                            ->default('monthly')
                            ->required()
                            ->inline()
                            ->inlineLabel(false),
                        Forms\Components\TextInput::make('next_sequence')
                            ->label(__('admin/saas_invoicing.field.next_sequence'))
                            ->numeric()
                            ->minValue(1)
                            ->helperText(__('admin/saas_invoicing.field.next_sequence_help'))
                            ->placeholder(__('admin/saas_invoicing.field.next_sequence_placeholder')),
                    ]),

                Forms\Components\Section::make(__('admin/saas_invoicing.section.defaults'))
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('currency')
                            ->label(__('admin/saas_invoicing.field.currency'))
                            ->options(['PLN' => 'PLN', 'EUR' => 'EUR', 'USD' => 'USD'])
                            ->default('PLN')
                            ->required(),
                        Forms\Components\TextInput::make('vat_rate')
                            ->label(__('admin/saas_invoicing.field.vat_rate'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(50)
                            ->suffix('%')
                            ->default(23)
                            ->required(),
                        Forms\Components\TextInput::make('due_days')
                            ->label(__('admin/saas_invoicing.field.due_days'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(60)
                            ->suffix(__('admin/saas_invoicing.field.due_days_suffix'))
                            ->default(14)
                            ->required(),
                    ]),

                Forms\Components\Section::make(__('admin/saas_invoicing.section.text'))
                    ->description(__('admin/saas_invoicing.section.text_help'))
                    ->schema([
                        Forms\Components\Textarea::make('payment_terms')
                            ->label(__('admin/saas_invoicing.field.payment_terms'))
                            ->rows(3)
                            ->maxLength(500)
                            ->placeholder(__('admin/saas_invoicing.field.payment_terms_placeholder')),
                        Forms\Components\Textarea::make('footer_note')
                            ->label(__('admin/saas_invoicing.field.footer_note'))
                            ->rows(3)
                            ->maxLength(500)
                            ->helperText(__('admin/saas_invoicing.field.footer_note_help'))
                            ->placeholder(__('admin/saas_invoicing.field.footer_note_placeholder')),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);

        $payload = $this->form->getState();

        SystemSetting::setValue('saas_invoicing.number_template', (string) $payload['number_template']);
        SystemSetting::setValue('saas_invoicing.reset_cycle', (string) $payload['reset_cycle']);
        SystemSetting::setValue('saas_invoicing.vat_rate', (int) $payload['vat_rate']);
        SystemSetting::setValue('saas_invoicing.due_days', (int) $payload['due_days']);
        SystemSetting::setValue('saas_invoicing.currency', (string) $payload['currency']);
        SystemSetting::setValue('saas_invoicing.footer_note', $payload['footer_note'] ?: null);
        SystemSetting::setValue('saas_invoicing.payment_terms', $payload['payment_terms'] ?: null);

        // Override następnego numeru tylko gdy explicit value — pusty field zostawia
        // bieżącą sekwencję bez zmian (numerator dalej inkrementuje normalnie).
        if (filled($payload['next_sequence'] ?? null)) {
            SystemSetting::setValue('saas_invoicing.next_sequence_override', (int) $payload['next_sequence']);
        }

        Notification::make()
            ->title(__('admin/saas_invoicing.action.saved'))
            ->success()
            ->send();

        $this->data['next_sequence'] = null;
    }
}
