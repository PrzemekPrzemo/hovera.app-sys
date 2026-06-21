<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\InvoiceResource\Pages;
use App\Models\Central\Invoice;
use App\Models\Central\Tenant;
use App\Services\Billing\Przelewy24Service;
use App\Services\Invoicing\InvoicePdfGenerator;
use App\Services\Ksef\CentralKsefService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Master-admin: FV SaaS-owe wystawiane stajniom za subskrypcję hovery.
 *
 * Główne actiony:
 *   - "Wyślij link P24" — generuje token + URL, oznacza FV jako pending
 *     payment. Tenant dostaje link mailem (TODO: notification w
 *     follow-up; tutaj kopiowanie URL).
 *   - "Wyślij do KSeF" — push FA(3) XML do KSeF (central cert).
 *   - "Pobierz PDF" — stub; pełna generacja PDF wymaga dompdf/snappy
 *     setup poza scope tego PR.
 */
class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 50;

    public static function getNavigationLabel(): string
    {
        return __('admin/invoice.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.finances');
    }

    public static function getModelLabel(): string
    {
        return __('admin/invoice.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/invoice.model_plural');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('admin/invoice.form.section.basics'))
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('tenant_id')
                        ->label(__('admin/invoice.form.label.tenant'))
                        ->relationship('tenant', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\TextInput::make('number')
                        ->label(__('admin/invoice.form.label.number'))
                        ->required()
                        ->maxLength(64),
                    Forms\Components\Select::make('kind')
                        ->label(__('admin/invoice.form.label.kind'))
                        ->options([
                            'regular' => __('admin/invoice.kind.regular'),
                            'proforma' => __('admin/invoice.kind.proforma'),
                            'correction' => __('admin/invoice.kind.correction'),
                        ])
                        ->default('regular')
                        ->required(),
                    Forms\Components\TextInput::make('plan_code')->maxLength(32),
                    Forms\Components\Select::make('period')
                        ->options(['monthly' => 'monthly', 'yearly' => 'yearly'])
                        ->nullable(),
                    Forms\Components\TextInput::make('currency')
                        ->default('PLN')
                        ->maxLength(3)
                        ->required(),
                ]),
            Forms\Components\Section::make(__('admin/invoice.form.section.amounts'))
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('subtotal_cents')
                        ->label(__('admin/invoice.form.label.subtotal'))
                        ->numeric()
                        ->required(),
                    Forms\Components\TextInput::make('vat_rate')
                        ->label(__('admin/invoice.form.label.vat_rate'))
                        ->numeric()
                        ->default(23)
                        ->required(),
                    Forms\Components\TextInput::make('vat_cents')->numeric()->required(),
                    Forms\Components\TextInput::make('total_cents')->numeric()->required(),
                ]),
            Forms\Components\Section::make(__('admin/invoice.form.section.dates'))
                ->columns(3)
                ->schema([
                    Forms\Components\DatePicker::make('issued_at')->default(now()),
                    Forms\Components\DatePicker::make('due_at')->default(now()->addDays(14)),
                    Forms\Components\DateTimePicker::make('paid_at')->nullable(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label(__('admin/invoice.table.column.number'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label(__('admin/invoice.table.column.tenant'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('issued_at')
                    ->label(__('admin/invoice.table.column.issued_at'))
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_cents')
                    ->label(__('admin/invoice.table.column.total'))
                    ->formatStateUsing(fn (?int $state, Invoice $r): string => $state === null
                        ? '—'
                        : number_format($state / 100, 2, ',', ' ').' '.$r->currency),
                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('admin/invoice.table.column.status'))
                    ->colors([
                        'success' => 'paid',
                        'warning' => 'open',
                        'gray' => 'draft',
                        'danger' => 'void',
                    ]),
                Tables\Columns\BadgeColumn::make('ksef_status')
                    ->label(__('admin/invoice.table.column.ksef_status'))
                    ->colors([
                        'success' => CentralKsefService::STATUS_ACCEPTED,
                        'warning' => CentralKsefService::STATUS_PENDING,
                        'info' => CentralKsefService::STATUS_SENT,
                        'danger' => CentralKsefService::STATUS_REJECTED,
                    ])
                    ->default('—'),
            ])
            ->defaultSort('issued_at', 'desc')
            ->actions([
                // PR I1 — pobierz PDF FV od Hovery (Sendormeco Holding sp. z o.o.)
                // z config('hovera.legal'). Visible dla wszystkich Invoice'ów
                // central — draft/open/paid/void wszystkie mają snapshot tenanta.
                Tables\Actions\Action::make('download_pdf')
                    ->label(__('admin/invoice.action.download_pdf'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(function (Invoice $record) {
                        $pdfBytes = app(InvoicePdfGenerator::class)
                            ->generateForCentral($record);
                        $filename = 'Hovera_'.preg_replace('/[^A-Za-z0-9_-]/', '_', $record->number).'.pdf';

                        return response()->streamDownload(
                            fn () => print $pdfBytes,
                            $filename,
                            ['Content-Type' => 'application/pdf'],
                        );
                    }),
                Tables\Actions\Action::make('send_p24_link')
                    ->label(__('admin/invoice.action.send_p24_link'))
                    ->icon('heroicon-o-link')
                    ->color('primary')
                    ->visible(fn (Invoice $r) => ! $r->isPaid())
                    ->requiresConfirmation()
                    ->action(function (Invoice $record, Przelewy24Service $p24) {
                        try {
                            $url = $p24->createPayment($record);
                            Notification::make()
                                ->title(__('admin/invoice.action.p24_link_generated'))
                                ->body($url)
                                ->success()
                                ->persistent()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title(__('admin/invoice.action.p24_link_failed'))
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('send_to_ksef')
                    ->label(__('admin/invoice.action.send_to_ksef'))
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->visible(fn (Invoice $r) => $r->ksef_status === null || $r->ksef_status === CentralKsefService::STATUS_REJECTED)
                    ->requiresConfirmation()
                    ->action(function (Invoice $record, CentralKsefService $ksef) {
                        try {
                            $reference = $ksef->pushInvoice($record);
                            Notification::make()
                                ->title(__('admin/invoice.action.ksef_sent'))
                                ->body(__('admin/invoice.action.ksef_reference').': '.$reference)
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title(__('admin/invoice.action.ksef_failed'))
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('download_pdf')
                    ->label(__('admin/invoice.action.download_pdf'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(function (Invoice $record) {
                        // STUB: generacja PDF wymaga dompdf/snappy setupu.
                        // Wypuszczamy notice że jest queued.
                        Notification::make()
                            ->title(__('admin/invoice.action.pdf_stub_title'))
                            ->body(__('admin/invoice.action.pdf_stub_body'))
                            ->warning()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }
}
