<?php

declare(strict_types=1);

namespace App\Filament\Transport\Resources;

use App\Domain\Transport\Invoices\TransportInvoicePdfGenerator;
use App\Domain\Transport\Notifications\TransportInvoiceSentNotification;
use App\Enums\TransportInvoiceKind;
use App\Enums\TransportInvoiceStatus;
use App\Filament\Concerns\RestrictedByTenantRole;
use App\Filament\Transport\Resources\TransportInvoiceResource\Pages;
use App\Models\Tenant\TransportInvoice;
use App\Services\Tenancy\TenantRoleGate;
use App\Services\TenantAuditLogger;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Notification as NotificationFacade;

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
                        ->disabled(),
                    Forms\Components\Select::make('status')
                        ->options(TransportInvoiceStatus::options())
                        ->disabled(),
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
                Tables\Actions\ViewAction::make(),
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

    public static function downloadPdf(TransportInvoice $invoice): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $pdf = app(TransportInvoicePdfGenerator::class)->generate($invoice);

        return response()->streamDownload(
            callback: fn () => print($pdf),
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
}
