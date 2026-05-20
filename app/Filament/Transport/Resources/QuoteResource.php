<?php

declare(strict_types=1);

namespace App\Filament\Transport\Resources;

use App\Domain\Transport\Invoices\IssueTransportInvoiceFromQuote;
use App\Domain\Transport\Notifications\QuoteSentNotification;
use App\Domain\Transport\Quotes\QuoteNumberGenerator;
use App\Domain\Transport\Quotes\QuotePdfGenerator;
use App\Enums\QuoteStatus;
use App\Enums\VehicleType;
use App\Filament\Concerns\RestrictedByTenantRole;
use App\Filament\Transport\Resources\QuoteResource\Pages;
use App\Models\Tenant\Driver;
use App\Models\Tenant\Quote;
use App\Models\Tenant\TransportInvoice;
use App\Models\Tenant\Vehicle;
use App\Services\Tenancy\TenantRoleGate;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuoteResource extends Resource
{
    use RestrictedByTenantRole;

    /** @return list<string> */
    protected static function allowedRoles(): array
    {
        return TenantRoleGate::TRANSPORT_OPERATORS;
    }

    protected static ?string $model = Quote::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.dispatch');
    }

    public static function getNavigationLabel(): string
    {
        return __('navigation.quotes');
    }

    public static function getModelLabel(): string
    {
        return __('models.quote');
    }

    public static function getPluralModelLabel(): string
    {
        return __('models.quotes');
    }

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('transport/quote.section.header'))
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('number')
                        ->label(__('transport/quote.form.label.number'))
                        ->disabled()
                        ->dehydrated(false)
                        ->placeholder(fn () => app(QuoteNumberGenerator::class)->preview()),
                    Forms\Components\Select::make('status')
                        ->label(__('transport/quote.form.label.status'))
                        ->options(QuoteStatus::options())
                        ->default(QuoteStatus::Draft->value)
                        ->required()
                        ->native(false),
                    Forms\Components\DatePicker::make('valid_until')
                        ->label(__('transport/quote.form.label.valid_until'))
                        ->native(false)
                        ->default(now()->addDays(14)),
                ]),

            Forms\Components\Section::make(__('transport/quote.section.customer'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('customer_name')
                        ->label(__('transport/quote.form.label.customer_name'))
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('customer_email')
                        ->label(__('transport/quote.form.label.customer_email'))
                        ->email()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('customer_phone')
                        ->label(__('transport/quote.form.label.customer_phone'))
                        ->tel()
                        ->maxLength(40),
                    Forms\Components\TextInput::make('customer_company')
                        ->label(__('transport/quote.form.label.customer_company'))
                        ->maxLength(255),
                    Forms\Components\TextInput::make('customer_tax_id')
                        ->label(__('transport/quote.form.label.customer_tax_id'))
                        ->maxLength(32),
                    Forms\Components\Textarea::make('customer_address')
                        ->label(__('transport/quote.form.label.customer_address'))
                        ->rows(2)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make(__('transport/quote.section.route'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('pickup_address')
                        ->label(__('transport/quote.form.label.pickup_address'))
                        ->required()
                        ->maxLength(255)
                        ->extraInputAttributes(['data-places-autocomplete' => 'panel', 'autocomplete' => 'off']),
                    Forms\Components\TextInput::make('dropoff_address')
                        ->label(__('transport/quote.form.label.dropoff_address'))
                        ->required()
                        ->maxLength(255)
                        ->extraInputAttributes(['data-places-autocomplete' => 'panel', 'autocomplete' => 'off']),
                    Forms\Components\TextInput::make('pickup_lat')
                        ->label('Pickup lat')
                        ->numeric()
                        ->required(),
                    Forms\Components\TextInput::make('pickup_lng')
                        ->label('Pickup lng')
                        ->numeric()
                        ->required(),
                    Forms\Components\TextInput::make('dropoff_lat')
                        ->label('Dropoff lat')
                        ->numeric()
                        ->required(),
                    Forms\Components\TextInput::make('dropoff_lng')
                        ->label('Dropoff lng')
                        ->numeric()
                        ->required(),
                    Forms\Components\DatePicker::make('preferred_date')
                        ->label(__('transport/quote.form.label.preferred_date'))
                        ->native(false)
                        ->required(),
                    Forms\Components\TimePicker::make('preferred_time')
                        ->label(__('transport/quote.form.label.preferred_time'))
                        ->seconds(false),
                    Forms\Components\Toggle::make('round_trip')
                        ->label(__('transport/quote.form.label.round_trip'))
                        ->inline(false),
                    Forms\Components\Toggle::make('loaded')
                        ->label(__('transport/quote.form.label.loaded'))
                        ->default(true)
                        ->inline(false),
                ]),

            Forms\Components\Section::make(__('transport/quote.section.resources'))
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('vehicle_id')
                        ->label(__('transport/quote.form.label.vehicle'))
                        ->helperText(__('transport/quote.form.helper.vehicle'))
                        ->options(fn () => Vehicle::query()
                            ->where('is_active', true)
                            ->where('vehicle_type', VehicleType::Truck->value)
                            ->pluck('name', 'id')->all())
                        ->searchable()
                        ->native(false),
                    Forms\Components\Select::make('trailer_id')
                        ->label(__('transport/quote.form.label.trailer'))
                        ->helperText(__('transport/quote.form.helper.trailer'))
                        ->options(fn () => Vehicle::query()
                            ->where('is_active', true)
                            ->where('vehicle_type', VehicleType::Trailer->value)
                            ->pluck('name', 'id')->all())
                        ->searchable()
                        ->native(false),
                    Forms\Components\Select::make('driver_id')
                        ->label(__('transport/quote.form.label.driver'))
                        ->options(fn () => Driver::query()->where('is_active', true)
                            ->get()
                            ->mapWithKeys(fn (Driver $d) => [$d->id => $d->full_name])
                            ->all())
                        ->searchable()
                        ->native(false),
                ]),

            Forms\Components\Section::make(__('transport/quote.section.pricing'))
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('distance_km')
                        ->label(__('transport/quote.form.label.distance_km'))
                        ->required()
                        ->numeric()
                        ->suffix('km'),
                    Forms\Components\TextInput::make('rate_per_km')
                        ->label(__('transport/quote.form.label.rate_per_km'))
                        ->required()
                        ->numeric()
                        ->suffix(fn (Forms\Get $get) => (string) ($get('currency') ?? 'PLN').'/km'),
                    Forms\Components\TextInput::make('duration_seconds')
                        ->label(__('transport/quote.form.label.duration_seconds'))
                        ->required()
                        ->numeric()
                        ->suffix('s'),
                    Forms\Components\TextInput::make('base_cost')
                        ->label(__('transport/quote.form.label.base_cost'))
                        ->required()
                        ->numeric(),
                    Forms\Components\TextInput::make('fuel_surcharge')
                        ->label(__('transport/quote.form.label.fuel_surcharge'))
                        ->numeric()
                        ->default(0),
                    Forms\Components\TextInput::make('minimum_adjustment')
                        ->label(__('transport/quote.form.label.minimum_adjustment'))
                        ->numeric()
                        ->default(0),
                    Forms\Components\TextInput::make('net_total')
                        ->label(__('transport/quote.form.label.net_total'))
                        ->required()
                        ->numeric(),
                    Forms\Components\TextInput::make('vat_rate')
                        ->label(__('transport/quote.form.label.vat_rate'))
                        ->required()
                        ->numeric()
                        ->suffix('%'),
                    Forms\Components\TextInput::make('vat_amount')
                        ->label(__('transport/quote.form.label.vat_amount'))
                        ->required()
                        ->numeric(),
                    Forms\Components\TextInput::make('gross_total')
                        ->label(__('transport/quote.form.label.gross_total'))
                        ->required()
                        ->numeric()
                        ->columnSpan(2),
                    Forms\Components\Select::make('currency')
                        ->label(__('transport/quote.form.label.currency'))
                        ->options(['PLN' => 'PLN', 'EUR' => 'EUR', 'CZK' => 'CZK'])
                        ->default('PLN')
                        ->required()
                        ->native(false),
                    Forms\Components\TextInput::make('routing_provider')
                        ->label(__('transport/quote.form.label.routing_provider'))
                        ->default('manual')
                        ->required()
                        ->maxLength(16)
                        ->columnSpan(2),
                ]),

            Forms\Components\Section::make(__('transport/quote.section.terms'))
                ->schema([
                    Forms\Components\Textarea::make('terms')
                        ->label(__('transport/quote.form.label.terms'))
                        ->helperText(__('transport/quote.form.helper.terms'))
                        ->rows(4),
                    Forms\Components\Textarea::make('notes')
                        ->label(__('transport/quote.form.label.notes'))
                        ->helperText(__('transport/quote.form.helper.notes'))
                        ->rows(3),
                ]),

            // Direct-charge payments MVP — patrz docs/TRANSPORT.md §13.
            // Hovera NIE przyjmuje płatności — wklejony URL prowadzi
            // bezpośrednio do bramki transportera (Stripe / P24 / BLIK / ...).
            Forms\Components\Section::make(__('transport/quote.section.payment'))
                ->description(__('transport/quote.section.payment_description'))
                ->collapsed(fn (?Quote $record) => $record === null || $record->payment_url === null)
                ->schema([
                    Forms\Components\TextInput::make('payment_url')
                        ->label(__('transport/quote.form.label.payment_url'))
                        ->helperText(__('transport/quote.form.helper.payment_url'))
                        ->url()
                        ->maxLength(2048)
                        ->placeholder('https://buy.stripe.com/...'),
                    Forms\Components\TextInput::make('payment_method_label')
                        ->label(__('transport/quote.form.label.payment_method_label'))
                        ->helperText(__('transport/quote.form.helper.payment_method_label'))
                        ->maxLength(80)
                        ->placeholder(__('transport/quote.form.placeholder.payment_method_label')),
                    Forms\Components\Textarea::make('payment_notes')
                        ->label(__('transport/quote.form.label.payment_notes'))
                        ->helperText(__('transport/quote.form.helper.payment_notes'))
                        ->rows(2),
                    Forms\Components\Placeholder::make('payment_completed_status')
                        ->label(__('transport/quote.form.label.payment_completed_status'))
                        ->content(fn (?Quote $record) => $record?->payment_completed_at
                            ? __('transport/quote.form.value.payment_completed_at', [
                                'date' => $record->payment_completed_at->format('Y-m-d H:i'),
                            ])
                            : __('transport/quote.form.value.payment_not_completed'))
                        ->visible(fn (?Quote $record) => $record !== null),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label(__('transport/quote.table.column.number'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label(__('transport/quote.table.column.customer'))
                    ->searchable(['customer_name', 'customer_company'])
                    ->description(fn (Quote $q) => $q->customer_company),
                Tables\Columns\TextColumn::make('route_summary')
                    ->label(__('transport/quote.table.column.route'))
                    ->state(fn (Quote $q) => $q->pickup_address.' → '.$q->dropoff_address)
                    ->limit(60),
                Tables\Columns\TextColumn::make('preferred_date')
                    ->label(__('transport/quote.table.column.preferred_date'))
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('gross_total')
                    ->label(__('transport/quote.table.column.gross_total'))
                    ->money(fn (Quote $q) => $q->currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('transport/quote.table.column.status'))
                    ->badge()
                    ->formatStateUsing(fn (QuoteStatus $state) => $state->label())
                    ->color(fn (QuoteStatus $state) => $state->color()),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('transport/quote.table.column.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('transport/quote.table.column.status'))
                    ->options(QuoteStatus::options()),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('markAsPaid')
                    ->label(__('transport/quote.action.mark_as_paid'))
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Quote $q) => $q->status === QuoteStatus::Accepted
                        && $q->payment_completed_at === null)
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label(__('transport/quote.form.label.mark_as_paid_reason'))
                            ->helperText(__('transport/quote.form.helper.mark_as_paid_reason'))
                            ->rows(2),
                    ])
                    ->modalHeading(__('transport/quote.action.mark_as_paid_modal_heading'))
                    ->modalDescription(__('transport/quote.action.mark_as_paid_modal_description'))
                    ->requiresConfirmation()
                    ->action(fn (Quote $q, array $data) => self::markAsPaid($q, (string) ($data['reason'] ?? ''))),
                Tables\Actions\Action::make('issueInvoice')
                    ->label(__('transport/quote.action.issue_invoice'))
                    ->icon('heroicon-o-document-currency-dollar')
                    ->color('success')
                    ->visible(fn (Quote $q) => $q->status === QuoteStatus::Accepted
                        && ! TransportInvoice::query()->where('quote_id', $q->id)->exists())
                    ->requiresConfirmation()
                    ->action(fn (Quote $q) => self::issueInvoice($q)),
                Tables\Actions\Action::make('downloadPdf')
                    ->label(__('transport/quote.action.download_pdf'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn (Quote $q) => self::downloadPdf($q)),
                Tables\Actions\Action::make('send')
                    ->label(__('transport/quote.action.send'))
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->visible(fn (Quote $q) => $q->status === QuoteStatus::Draft)
                    ->requiresConfirmation()
                    ->action(fn (Quote $q) => self::sendQuote($q)),
                Tables\Actions\Action::make('withdraw')
                    ->label(__('transport/quote.action.withdraw'))
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->visible(fn (Quote $q) => $q->status === QuoteStatus::Sent)
                    ->requiresConfirmation()
                    ->action(fn (Quote $q) => self::withdrawQuote($q)),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->after(self::auditCallback('quote.delete')),
                Tables\Actions\RestoreAction::make()->after(self::auditCallback('quote.restore')),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuotes::route('/'),
            'create' => Pages\CreateQuote::route('/create'),
            'edit' => Pages\EditQuote::route('/{record}/edit'),
        ];
    }

    public static function sendQuote(Quote $quote): void
    {
        // Verification gate — patrz docs/TRANSPORT.md (feedback prod).
        // Transporter bez `verified` nie może wysyłać ofert do klientów.
        if (! self::ensureTenantVerified()) {
            return;
        }

        // Resource assignment gate — vehicle + driver muszą być przypisane przed
        // wysłaniem (trailer opcjonalny). Bez tego po acceptacji statystyki
        // per-vehicle/per-driver nie miały by co liczyć — patrz user feedback
        // "do oferty trzeba dodać samochód i przyczepę i kierowce by poźniej po
        // zaakceptowaniu oferty była możliwość liczenia statystyk".
        $missing = [];
        if (! $quote->vehicle_id) {
            $missing[] = __('transport/quote.form.label.vehicle');
        }
        if (! $quote->driver_id) {
            $missing[] = __('transport/quote.form.label.driver');
        }
        if ($missing !== []) {
            Notification::make()
                ->warning()
                ->title(__('transport/quote.notify.resources_required_title'))
                ->body(__('transport/quote.notify.resources_required_body', [
                    'fields' => implode(', ', $missing),
                ]))
                ->persistent()
                ->send();

            return;
        }

        $quote->forceFill([
            'status' => QuoteStatus::Sent,
            'sent_at' => now(),
            'accept_token' => $quote->accept_token ?: Str::random(48),
        ])->save();

        $emailed = false;
        if ($quote->customer_email) {
            try {
                NotificationFacade::route('mail', $quote->customer_email)
                    ->notify(new QuoteSentNotification($quote));
                $emailed = true;
            } catch (\Throwable $e) {
                report($e);
                Notification::make()
                    ->warning()
                    ->title(__('transport/quote.notify.sent_no_email'))
                    ->body($e->getMessage())
                    ->send();
            }
        }

        app(TenantAuditLogger::class)->record('quote.send', 'Quote', (string) $quote->id, [
            'number' => $quote->number,
            'emailed' => $emailed,
        ]);

        if ($emailed) {
            Notification::make()
                ->success()
                ->title(__('transport/quote.notify.sent'))
                ->body(__('transport/quote.notify.sent_body', [
                    'number' => $quote->number,
                    'email' => $quote->customer_email,
                ]))
                ->send();
        } elseif (! $quote->customer_email) {
            Notification::make()
                ->info()
                ->title(__('transport/quote.notify.sent'))
                ->body(__('transport/quote.notify.sent_no_customer_email', ['number' => $quote->number]))
                ->send();
        }
    }

    /**
     * Wystaw FV ze snapshotu accepted Quote — używa IssueTransportInvoiceFromQuote.
     * Gating: verified tenant (FV mogą wystawiać tylko zweryfikowane firmy).
     * Patrz docs/TRANSPORT.md (krok C2).
     */
    public static function issueInvoice(Quote $quote): void
    {
        if (! self::ensureTenantVerified()) {
            return;
        }

        try {
            $invoice = app(IssueTransportInvoiceFromQuote::class)->execute($quote);
        } catch (\DomainException $e) {
            Notification::make()
                ->warning()
                ->title(__('transport/quote.notify.invoice_failed'))
                ->body($e->getMessage())
                ->persistent()
                ->send();

            return;
        }

        app(TenantAuditLogger::class)->record(
            'transport_invoice.issued',
            'TransportInvoice',
            (string) $invoice->id,
            ['number' => $invoice->number, 'quote_number' => $quote->number],
        );

        Notification::make()
            ->success()
            ->title(__('transport/quote.notify.invoice_issued'))
            ->body(__('transport/quote.notify.invoice_issued_body', ['number' => $invoice->number]))
            ->send();
    }

    public static function downloadPdf(Quote $quote): StreamedResponse
    {
        $pdf = app(QuotePdfGenerator::class)->generate($quote);

        return response()->streamDownload(
            callback: fn () => print ($pdf),
            name: $quote->number.'.pdf',
            headers: ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * Ręczne oznaczenie oferty jako opłaconej. Direct-charge MVP — Hovera
     * nie ma webhooków od bramek (transporter sam przyjmuje pieniądze), więc
     * potwierdzenie wpłaty wymaga manualnego kliknięcia. Patrz docs/TRANSPORT.md §13.
     */
    public static function markAsPaid(Quote $quote, string $reason = ''): void
    {
        if ($quote->payment_completed_at !== null) {
            return;
        }

        $quote->forceFill(['payment_completed_at' => now()])->save();

        app(TenantAuditLogger::class)->record('quote.mark_as_paid', 'Quote', (string) $quote->id, [
            'number' => $quote->number,
            'reason' => $reason !== '' ? $reason : null,
        ]);

        Notification::make()
            ->success()
            ->title(__('transport/quote.notify.marked_as_paid'))
            ->body(__('transport/quote.notify.marked_as_paid_body', ['number' => $quote->number]))
            ->send();
    }

    public static function withdrawQuote(Quote $quote): void
    {
        $quote->forceFill([
            'status' => QuoteStatus::Withdrawn,
            'withdrawn_at' => now(),
        ])->save();

        app(TenantAuditLogger::class)->record('quote.withdraw', 'Quote', (string) $quote->id, [
            'number' => $quote->number,
        ]);

        Notification::make()
            ->warning()
            ->title(__('transport/quote.notify.withdrawn'))
            ->body($quote->number)
            ->send();
    }

    private static function auditCallback(string $action): callable
    {
        return function (Model $record) use ($action) {
            app(TenantAuditLogger::class)->record($action, 'Quote', (string) $record->getKey(), [
                'number' => $record->number,
            ]);
        };
    }

    /**
     * Sprawdza czy aktywny tenant ma `verified` status. Jeśli nie —
     * pokazuje friendly notyfikację z CTA do dokumentów weryfikacyjnych
     * i zwraca false (caller ma przerwać akcję). Master admin ze
     * status=null/inny też dostaje informację.
     *
     * Reused: faza C dla TransportInvoiceResource, faza 6 dla LeadDispatcher,
     * faza 7 dla public profile gate.
     */
    public static function ensureTenantVerified(): bool
    {
        $tenant = app(TenantManager::class)->current();
        if (! $tenant || ! $tenant->isTransporter()) {
            return true;        // gate aplikujemy tylko dla transporterów
        }

        if ($tenant->isVerifiedTransporter()) {
            return true;
        }

        Notification::make()
            ->danger()
            ->title(__('transport/quote.notify.verification_required'))
            ->body(__('transport/quote.notify.verification_required_body'))
            ->actions([
                Action::make('openDocuments')
                    ->label(__('transport/quote.notify.open_documents'))
                    ->url(route('filament.transport.pages.transporter-documents')),
            ])
            ->persistent()
            ->send();

        return false;
    }
}
