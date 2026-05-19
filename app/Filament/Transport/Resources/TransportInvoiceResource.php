<?php

declare(strict_types=1);

namespace App\Filament\Transport\Resources;

use App\Domain\Transport\Invoices\TransportInvoicePdfGenerator;
use App\Domain\Transport\Ksef\KsefNotConfiguredException;
use App\Domain\Transport\Ksef\TransporterKsefService;
use App\Domain\Transport\Notifications\TransportInvoiceSentNotification;
use App\Enums\TransportInvoiceKind;
use App\Enums\TransportInvoiceStatus;
use App\Enums\TransportKsefStatus;
use App\Filament\Concerns\RestrictedByTenantRole;
use App\Filament\Transport\Resources\TransportInvoiceResource\Pages;
use App\Models\Tenant\TransportInvoice;
use App\Services\Tenancy\TenantRoleGate;
use App\Services\TenantAuditLogger;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Faktury transportowe (Filament) — list/view + akcje (download PDF,
 * send email, mark paid). Tworzenie wyłącznie z Quote (akcja w
 * QuoteResource). Edytowanie zablokowane po issued (snapshot immutable).
 *
 * Patrz docs/TRANSPORT.md §9 faza 3 (krok C2).
 */
class TransportInvoiceResource extends Resource
{
    use RestrictedByTenantRole;

    /** @return list<string> */
    protected static function allowedRoles(): array
    {
        return TenantRoleGate::FULL_ADMINS_AND_MANAGERS;
    }

    protected static ?string $model = TransportInvoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.finances');
    }

    public static function getNavigationLabel(): string
    {
        return __('transport/invoice_resource.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('models.transport_invoice');
    }

    public static function getPluralModelLabel(): string
    {
        return __('models.transport_invoices');
    }

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('transport/invoice_resource.section.header'))
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('number')->disabled(),
                    Forms\Components\Select::make('kind')
                        ->options(TransportInvoiceKind::options())
                        // Enabled na create (KOR wymagany przy korekcie),
                        // disabled na edit (zmiana kind po submit do KSeF
                        // wprowadziłaby niespójność). Patrz docs/TRANSPORT.md §16.
                        ->disabledOn('edit')
                        ->required()
                        ->default(TransportInvoiceKind::Fv->value)
                        ->live()
                        ->helperText(__('transport/invoice_resource.form.helper.kind')),
                    Forms\Components\Select::make('status')
                        ->options(TransportInvoiceStatus::options())
                        ->disabled(),
                ]),

            // Sekcja korekty — wymagana gdy kind=Korekta dla zgodności z FA(3).
            Forms\Components\Section::make(__('transport/invoice_resource.section.correction'))
                ->description(__('transport/invoice_resource.section.correction_help'))
                ->visible(fn (Get $get) => $get('kind') === TransportInvoiceKind::Korekta->value)
                ->schema([
                    Forms\Components\Select::make('corrects_invoice_id')
                        ->label(__('transport/invoice_resource.form.label.corrects_invoice'))
                        ->helperText(__('transport/invoice_resource.form.helper.corrects_invoice'))
                        ->options(fn () => TransportInvoice::query()
                            ->where('kind', TransportInvoiceKind::Fv->value)
                            ->whereNotIn('status', ['draft', 'cancelled'])
                            ->orderByDesc('issued_at')
                            ->limit(200)
                            ->pluck('number', 'id')
                            ->all())
                        ->searchable()
                        ->required(fn (Get $get) => $get('kind') === TransportInvoiceKind::Korekta->value),
                ]),

            Forms\Components\Section::make(__('transport/invoice_resource.section.parties'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('seller_name')->disabled()
                        ->label(__('transport/invoice_resource.form.label.seller')),
                    Forms\Components\TextInput::make('seller_nip')->disabled(),
                    Forms\Components\TextInput::make('buyer_name')->disabled()
                        ->label(__('transport/invoice_resource.form.label.buyer')),
                    Forms\Components\TextInput::make('buyer_email')->disabled(),
                    Forms\Components\TextInput::make('buyer_nip')->disabled(),
                ]),

            Forms\Components\Section::make(__('transport/invoice_resource.section.amounts'))
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('subtotal_cents')
                        ->label(__('transport/invoice_resource.form.label.net_total'))
                        ->disabled()
                        ->formatStateUsing(fn ($state) => number_format(((int) $state) / 100, 2, ',', ' '))
                        ->suffix(fn ($record) => $record?->currency ?? 'PLN'),
                    Forms\Components\TextInput::make('vat_cents')
                        ->label(__('transport/invoice_resource.form.label.vat_total'))
                        ->disabled()
                        ->formatStateUsing(fn ($state) => number_format(((int) $state) / 100, 2, ',', ' '))
                        ->suffix(fn ($record) => $record?->currency ?? 'PLN'),
                    Forms\Components\TextInput::make('total_cents')
                        ->label(__('transport/invoice_resource.form.label.gross_total'))
                        ->disabled()
                        ->formatStateUsing(fn ($state) => number_format(((int) $state) / 100, 2, ',', ' '))
                        ->suffix(fn ($record) => $record?->currency ?? 'PLN'),
                ]),

            Forms\Components\Section::make(__('transport/invoice_resource.section.dates'))
                ->columns(4)
                ->schema([
                    Forms\Components\DatePicker::make('issued_at')->disabled(),
                    Forms\Components\DatePicker::make('sale_date')->disabled(),
                    Forms\Components\DatePicker::make('due_at')->disabled(),
                    Forms\Components\DateTimePicker::make('paid_at')->disabled(),
                ]),

            Forms\Components\Section::make(__('transport/invoice_resource.section.route'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('pickup_address')->disabled(),
                    Forms\Components\TextInput::make('dropoff_address')->disabled(),
                    Forms\Components\DatePicker::make('service_date')->disabled(),
                    Forms\Components\TextInput::make('distance_km')->disabled()->suffix('km'),
                ]),

            Forms\Components\Section::make(__('transport/invoice_resource.section.notes'))
                ->schema([
                    Forms\Components\Textarea::make('notes')->rows(3),
                ]),

            Forms\Components\Section::make(__('transport/ksef.section.invoice_title'))
                ->description(__('transport/ksef.section.invoice_description'))
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('ksef_status')
                        ->label(__('transport/ksef.form.label.invoice_status'))
                        ->disabled()
                        ->formatStateUsing(fn ($state) => $state instanceof TransportKsefStatus
                            ? $state->label()
                            : (TransportKsefStatus::tryFrom((string) $state)?->label()
                                ?? __('transport/ksef.status.not_submitted'))),
                    Forms\Components\TextInput::make('ksef_reference_number')
                        ->label(__('transport/ksef.form.label.reference_number'))
                        ->disabled(),
                    Forms\Components\DateTimePicker::make('ksef_submitted_at')
                        ->label(__('transport/ksef.form.label.submitted_at'))
                        ->disabled(),
                ])
                ->visible(fn ($record) => $record !== null),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label(__('transport/invoice_resource.table.column.number'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('kind')
                    ->label(__('transport/invoice_resource.table.column.kind'))
                    ->badge()
                    ->formatStateUsing(fn (TransportInvoiceKind $state) => $state->label())
                    ->toggleable(),
                Tables\Columns\TextColumn::make('buyer_name')
                    ->label(__('transport/invoice_resource.table.column.buyer'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('issued_at')
                    ->label(__('transport/invoice_resource.table.column.issued_at'))
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('due_at')
                    ->label(__('transport/invoice_resource.table.column.due_at'))
                    ->date()
                    ->sortable()
                    ->color(fn (TransportInvoice $i) => $i->due_at && $i->due_at->isPast() && $i->status !== TransportInvoiceStatus::Paid ? 'danger' : null),
                Tables\Columns\TextColumn::make('total_cents')
                    ->label(__('transport/invoice_resource.table.column.total'))
                    ->formatStateUsing(fn ($state, $record) => number_format($state / 100, 2, ',', ' ').' '.$record->currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('transport/invoice_resource.table.column.status'))
                    ->badge()
                    ->formatStateUsing(fn (TransportInvoiceStatus $state) => $state->label())
                    ->color(fn (TransportInvoiceStatus $state) => $state->color()),
                Tables\Columns\TextColumn::make('ksef_status')
                    ->label(__('transport/ksef.table.column.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof TransportKsefStatus
                        ? $state->label()
                        : (TransportKsefStatus::tryFrom((string) ($state ?? 'not_submitted'))
                            ?? TransportKsefStatus::NotSubmitted)->label())
                    ->color(fn ($state) => $state instanceof TransportKsefStatus
                        ? $state->color()
                        : (TransportKsefStatus::tryFrom((string) ($state ?? 'not_submitted'))
                            ?? TransportKsefStatus::NotSubmitted)->color())
                    ->toggleable(),
            ])
            ->defaultSort('issued_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(TransportInvoiceStatus::options()),
                Tables\Filters\SelectFilter::make('kind')
                    ->options(TransportInvoiceKind::options()),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('downloadPdf')
                    ->label(__('transport/invoice_resource.action.download_pdf'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn (TransportInvoice $i) => self::downloadPdf($i)),
                Tables\Actions\Action::make('sendEmail')
                    ->label(__('transport/invoice_resource.action.send_email'))
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->visible(fn (TransportInvoice $i) => $i->buyer_email && ! $i->status->isFinal())
                    ->requiresConfirmation()
                    ->action(fn (TransportInvoice $i) => self::sendInvoiceEmail($i)),
                Tables\Actions\Action::make('markPaid')
                    ->label(__('transport/invoice_resource.action.mark_paid'))
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (TransportInvoice $i) => in_array($i->status, [TransportInvoiceStatus::Issued, TransportInvoiceStatus::Overdue], true))
                    ->requiresConfirmation()
                    ->action(fn (TransportInvoice $i) => self::markPaid($i)),
                Tables\Actions\Action::make('ksefSubmit')
                    ->label(__('transport/ksef.action.submit'))
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->color('warning')
                    ->tooltip(__('transport/ksef.action.submit_tooltip'))
                    ->visible(fn (TransportInvoice $i) => self::ksefCanSubmit($i))
                    ->requiresConfirmation()
                    ->modalDescription(__('transport/ksef.action.submit_confirm'))
                    ->action(fn (TransportInvoice $i) => self::ksefSubmit($i)),
                Tables\Actions\Action::make('ksefRefresh')
                    ->label(__('transport/ksef.action.refresh'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->visible(fn (TransportInvoice $i) => self::ksefCanRefresh($i))
                    ->action(fn (TransportInvoice $i) => self::ksefRefresh($i)),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('ksefSubmitBulk')
                    ->label(__('transport/ksef.action.submit_bulk'))
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription(__('transport/ksef.action.submit_bulk_confirm'))
                    ->action(fn ($records) => self::ksefSubmitBulk($records)),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransportInvoices::route('/'),
            'view' => Pages\ViewTransportInvoice::route('/{record}'),
        ];
    }

    public static function downloadPdf(TransportInvoice $invoice): StreamedResponse
    {
        $pdf = app(TransportInvoicePdfGenerator::class)->generate($invoice);

        return response()->streamDownload(
            callback: fn () => print ($pdf),
            name: $invoice->number.'.pdf',
            headers: ['Content-Type' => 'application/pdf'],
        );
    }

    public static function sendInvoiceEmail(TransportInvoice $invoice): void
    {
        if (! $invoice->buyer_email) {
            Notification::make()
                ->warning()
                ->title(__('transport/invoice_resource.notify.no_buyer_email'))
                ->send();

            return;
        }

        try {
            NotificationFacade::route('mail', $invoice->buyer_email)
                ->notify(new TransportInvoiceSentNotification($invoice));
        } catch (\Throwable $e) {
            report($e);
            Notification::make()
                ->danger()
                ->title(__('transport/invoice_resource.notify.email_failed'))
                ->body($e->getMessage())
                ->send();

            return;
        }

        app(TenantAuditLogger::class)->record(
            'transport_invoice.sent',
            'TransportInvoice',
            (string) $invoice->id,
            ['number' => $invoice->number, 'email' => $invoice->buyer_email],
        );

        Notification::make()
            ->success()
            ->title(__('transport/invoice_resource.notify.sent'))
            ->body(__('transport/invoice_resource.notify.sent_body', [
                'number' => $invoice->number,
                'email' => $invoice->buyer_email,
            ]))
            ->send();
    }

    public static function markPaid(TransportInvoice $invoice): void
    {
        $invoice->forceFill([
            'status' => TransportInvoiceStatus::Paid,
            'paid_at' => now(),
        ])->save();

        app(TenantAuditLogger::class)->record(
            'transport_invoice.mark_paid',
            'TransportInvoice',
            (string) $invoice->id,
            ['number' => $invoice->number],
        );

        Notification::make()
            ->success()
            ->title(__('transport/invoice_resource.notify.marked_paid'))
            ->body($invoice->number)
            ->send();
    }

    /**
     * Action visibility: submit jest dostępny tylko gdy FV ma status
     * (issued / overdue / paid — czyli wystawiona) AND KSeF jest jeszcze
     * not_submitted AND transporter ma włączoną integrację. Draft i void
     * nie idą do KSeF (sens biznesowy: KSeF = oficjalne wystawienie).
     */
    public static function ksefCanSubmit(TransportInvoice $invoice): bool
    {
        if (in_array($invoice->status, [TransportInvoiceStatus::Draft, TransportInvoiceStatus::Void, TransportInvoiceStatus::Cancelled], true)) {
            return false;
        }

        $current = self::ksefStatusOf($invoice);
        if ($current !== TransportKsefStatus::NotSubmitted) {
            return false;
        }

        return app(TransporterKsefService::class)->isEnabledForCurrentTransporter();
    }

    public static function ksefCanRefresh(TransportInvoice $invoice): bool
    {
        $current = self::ksefStatusOf($invoice);

        return in_array($current, [TransportKsefStatus::Submitted, TransportKsefStatus::Error], true)
            && app(TransporterKsefService::class)->isEnabledForCurrentTransporter();
    }

    private static function ksefStatusOf(TransportInvoice $invoice): TransportKsefStatus
    {
        $status = $invoice->ksef_status;
        if ($status instanceof TransportKsefStatus) {
            return $status;
        }
        if (is_string($status) && $status !== '') {
            return TransportKsefStatus::tryFrom($status) ?? TransportKsefStatus::NotSubmitted;
        }

        return TransportKsefStatus::NotSubmitted;
    }

    public static function ksefSubmit(TransportInvoice $invoice): void
    {
        try {
            $result = app(TransporterKsefService::class)->submit($invoice);
        } catch (KsefNotConfiguredException $e) {
            Notification::make()
                ->warning()
                ->title(__('transport/ksef.notify.not_configured'))
                ->body($e->getMessage())
                ->send();

            return;
        }

        if ($result->isSuccess()) {
            Notification::make()
                ->success()
                ->title(__('transport/ksef.notify.submitted'))
                ->body($invoice->number.' → '.$result->referenceNumber)
                ->send();

            return;
        }

        Notification::make()
            ->danger()
            ->title(__('transport/ksef.notify.submit_failed'))
            ->body($result->errorMessage ?: __('transport/ksef.notify.unknown_error'))
            ->persistent()
            ->send();
    }

    public static function ksefRefresh(TransportInvoice $invoice): void
    {
        try {
            $result = app(TransporterKsefService::class)->refreshStatus($invoice);
        } catch (KsefNotConfiguredException $e) {
            Notification::make()
                ->warning()
                ->title(__('transport/ksef.notify.not_configured'))
                ->body($e->getMessage())
                ->send();

            return;
        }

        Notification::make()
            ->title(__('transport/ksef.notify.status_refreshed'))
            ->body($result->status->label())
            ->success()
            ->send();
    }

    public static function ksefSubmitBulk(Collection $records): void
    {
        // Limit 50 — KSeF MF API jest rate-limited, nie chcemy
        // odpalać 500 wywołań z jednego klika.
        $records = $records->take(50);

        $service = app(TransporterKsefService::class);
        $ok = 0;
        $fail = 0;

        foreach ($records as $invoice) {
            if (! self::ksefCanSubmit($invoice)) {
                $fail++;

                continue;
            }
            try {
                $result = $service->submit($invoice);
                $result->isSuccess() ? $ok++ : $fail++;
            } catch (KsefNotConfiguredException) {
                Notification::make()
                    ->warning()
                    ->title(__('transport/ksef.notify.not_configured'))
                    ->send();

                return;
            }
        }

        Notification::make()
            ->title(__('transport/ksef.notify.bulk_done'))
            ->body(__('transport/ksef.notify.bulk_done_body', ['ok' => $ok, 'fail' => $fail]))
            ->success()
            ->send();
    }
}
