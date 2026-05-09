<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Actions\Invoicing\CreateInvoiceCorrection;
use App\Actions\Invoicing\IssueInvoice;
use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Filament\App\Resources\InvoiceResource\Pages;
use App\Filament\Components\PriceInput;
use App\Filament\Concerns\RestrictedByTenantRole;
use App\Models\Tenant\Client;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\InvoiceItem;
use App\Notifications\InvoiceIssuedClientNotification;
use App\Services\Invoicing\InvoicePublicLink;
use App\Services\Ksef\KsefClient;
use App\Services\Portal\ClientMessageJournal;
use App\Services\Tenancy\TenantRoleGate;
use App\Tenancy\TenantManager;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Notification as MailFacade;
use Illuminate\Validation\ValidationException;

class InvoiceResource extends Resource
{
    use RestrictedByTenantRole;

    /** @return list<string> */
    protected static function allowedRoles(): array
    {
        return TenantRoleGate::FINANCE_STAFF;
    }

    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.finances');
    }

    public static function getNavigationLabel(): string
    {
        return __('navigation.invoices');
    }

    public static function getModelLabel(): string
    {
        return __('models.invoice');
    }

    public static function getPluralModelLabel(): string
    {
        return __('models.invoices');
    }

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('app/invoice.form.section.invoice_data'))
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('kind')
                        ->label(__('app/invoice.form.label.kind'))
                        ->options(InvoiceKind::options())
                        ->default(InvoiceKind::Fv->value)
                        ->required()
                        ->disabledOn('edit'),
                    Forms\Components\TextInput::make('number')
                        ->label(__('app/invoice.form.label.number'))
                        ->placeholder(__('app/invoice.form.label.number_placeholder'))
                        ->disabled(),
                    Forms\Components\Select::make('status')
                        ->label(__('app/invoice.form.label.status'))
                        ->options(InvoiceStatus::options())
                        ->default(InvoiceStatus::Draft->value)
                        ->disabled(),
                ]),

            Forms\Components\Section::make(__('app/invoice.form.section.buyer'))
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('client_id')
                        ->label(__('app/invoice.form.label.client'))
                        ->options(fn () => Client::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                            if (! $state) {
                                return;
                            }
                            $client = Client::query()->find($state);
                            if (! $client) {
                                return;
                            }
                            $set('buyer_name', $client->name);
                            $set('buyer_nip', $client->tax_id);
                            $set('buyer_address', $client->street);
                            $set('buyer_postal_code', $client->postal_code);
                            $set('buyer_city', $client->city);
                            $set('buyer_country', $client->country ?? 'PL');
                        }),
                    Forms\Components\TextInput::make('buyer_name')
                        ->label(__('app/invoice.form.label.buyer_name'))->required(),
                    Forms\Components\TextInput::make('buyer_nip')
                        ->label(__('app/invoice.form.label.buyer_nip')),
                    Forms\Components\TextInput::make('buyer_address')
                        ->label(__('app/invoice.form.label.buyer_address')),
                    Forms\Components\TextInput::make('buyer_postal_code')
                        ->label(__('app/invoice.form.label.buyer_postal_code')),
                    Forms\Components\TextInput::make('buyer_city')
                        ->label(__('app/invoice.form.label.buyer_city')),
                    Forms\Components\TextInput::make('buyer_country')
                        ->label(__('app/invoice.form.label.buyer_country'))->default('PL')->maxLength(2),
                ]),

            Forms\Components\Section::make(__('app/invoice.form.section.seller'))
                ->columns(2)
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('seller_name')
                        ->label(__('app/invoice.form.label.seller_name'))->required(),
                    Forms\Components\TextInput::make('seller_nip')
                        ->label(__('app/invoice.form.label.seller_nip')),
                    Forms\Components\TextInput::make('seller_address')
                        ->label(__('app/invoice.form.label.seller_address')),
                    Forms\Components\TextInput::make('seller_postal_code')
                        ->label(__('app/invoice.form.label.seller_postal_code')),
                    Forms\Components\TextInput::make('seller_city')
                        ->label(__('app/invoice.form.label.seller_city')),
                    Forms\Components\TextInput::make('seller_country')
                        ->label(__('app/invoice.form.label.seller_country'))->default('PL')->maxLength(2),
                ]),

            Forms\Components\Section::make(__('app/invoice.form.section.dates'))
                ->columns(3)
                ->schema([
                    Forms\Components\DatePicker::make('issued_at')
                        ->label(__('app/invoice.form.label.issued_at'))->disabled(),
                    Forms\Components\DatePicker::make('sale_date')
                        ->label(__('app/invoice.form.label.sale_date')),
                    Forms\Components\DatePicker::make('due_at')
                        ->label(__('app/invoice.form.label.due_at'))
                        ->default(fn () => now()->addDays(7)->toDateString()),
                ]),

            Forms\Components\Section::make(__('app/invoice.form.section.items'))
                ->schema([
                    Forms\Components\Repeater::make('items')
                        ->relationship()
                        ->columns(6)
                        ->orderColumn('position')
                        ->defaultItems(1)
                        ->reorderable()
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label(__('app/invoice.form.label.item_name'))->required()->columnSpan(2),
                            Forms\Components\TextInput::make('quantity')
                                ->label(__('app/invoice.form.label.item_quantity'))->numeric()->default(1)->required(),
                            Forms\Components\TextInput::make('unit')
                                ->label(__('app/invoice.form.label.item_unit'))->default('szt.'),
                            PriceInput::make('unit_price_cents', __('app/invoice.form.label.item_unit_price'))->required(),
                            Forms\Components\Select::make('vat_rate')
                                ->label(__('app/invoice.form.label.item_vat'))
                                ->options(InvoiceItem::vatRateOptions())
                                ->default('23')
                                ->required(),
                        ]),
                ]),

            Forms\Components\Section::make(__('app/invoice.form.section.notes'))
                ->collapsed()
                ->schema([
                    Forms\Components\Textarea::make('notes')
                        ->label(__('app/invoice.form.label.notes_label'))->rows(3),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label(__('app/invoice.table.column.number'))
                    ->placeholder('—')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\BadgeColumn::make('kind')
                    ->label(__('app/invoice.table.column.kind'))
                    ->formatStateUsing(fn (InvoiceKind $state) => $state->shortLabel())
                    ->colors([
                        'primary' => InvoiceKind::Fv->value,
                        'gray' => InvoiceKind::FvProforma->value,
                        'warning' => InvoiceKind::FvKorekta->value,
                    ]),
                Tables\Columns\TextColumn::make('issued_at')
                    ->label(__('app/invoice.table.column.issued_at'))->date()->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('client.name')
                    ->label(__('app/invoice.table.column.client'))->searchable(),
                Tables\Columns\TextColumn::make('total_cents')
                    ->label(__('app/invoice.table.column.total'))
                    ->formatStateUsing(fn (?int $state, Invoice $record) => $record->totalFormatted())
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('app/invoice.table.column.status'))
                    ->formatStateUsing(fn (InvoiceStatus $state) => $state->label())
                    ->colors([
                        'gray' => InvoiceStatus::Draft->value,
                        'primary' => InvoiceStatus::Issued->value,
                        'success' => InvoiceStatus::Paid->value,
                        'warning' => InvoiceStatus::Overdue->value,
                        'danger' => fn ($state) => in_array(
                            $state instanceof InvoiceStatus ? $state->value : $state,
                            [InvoiceStatus::Void->value, InvoiceStatus::Cancelled->value],
                            true,
                        ),
                    ]),
                Tables\Columns\TextColumn::make('due_at')
                    ->label(__('app/invoice.table.column.due_at'))->date()->placeholder('—')->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('kind')->options(InvoiceKind::options()),
                Tables\Filters\SelectFilter::make('status')->options(InvoiceStatus::options()),
                Tables\Filters\Filter::make('overdue')
                    ->label(__('app/invoice.table.filter.overdue'))
                    ->query(fn ($query) => $query->overdue()),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('issue')
                    ->label(__('app/invoice.action.issue.label'))
                    ->icon('heroicon-m-paper-airplane')
                    ->visible(fn (Invoice $r) => $r->status === InvoiceStatus::Draft)
                    ->requiresConfirmation()
                    ->action(function (Invoice $record) {
                        try {
                            app(IssueInvoice::class)->execute($record);
                            Notification::make()->title(__('app/invoice.action.issue.success'))->success()->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title(__('app/invoice.action.issue.failure_title'))
                                ->body(implode("\n", Arr::flatten($e->errors())))
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('correct')
                    ->label(__('app/invoice.action.correct.label'))
                    ->icon('heroicon-m-arrow-path')
                    ->visible(fn (Invoice $r) => $r->kind !== InvoiceKind::FvKorekta && $r->status->isPosted())
                    ->action(function (Invoice $record) {
                        try {
                            $korekta = app(CreateInvoiceCorrection::class)->execute($record);
                            Notification::make()->title(__('app/invoice.action.correct.success_title'))->success()
                                ->body(__('app/invoice.action.correct.success_body', ['id' => $korekta->id]))
                                ->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title(__('app/invoice.action.correct.failure_title'))
                                ->body(implode("\n", Arr::flatten($e->errors())))
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('ksef')
                    ->label(__('app/invoice.action.ksef.label'))
                    ->icon('heroicon-m-shield-check')
                    ->color('success')
                    ->visible(function (Invoice $r) {
                        $tenant = app(TenantManager::class)->current();
                        if (! $tenant || ! app(KsefClient::class)->isReady($tenant)) {
                            return false;
                        }

                        return $r->status->isPosted() && $r->ksef_status === null;
                    })
                    ->requiresConfirmation()
                    ->modalDescription(__('app/invoice.action.ksef.modal_description'))
                    ->action(function (Invoice $record) {
                        $tenant = app(TenantManager::class)->current();
                        try {
                            // PR 4: zatwierdzamy auth + budowanie XML.
                            // Pełen invoice send (RSA-OAEP wrap + AES-256-CBC
                            // + multi-doc batch) trafi w PR 4b.
                            app(KsefClient::class)->authenticate($tenant);
                            $record->forceFill([
                                'ksef_status' => 'sent',
                                'ksef_sent_at' => now(),
                            ])->save();
                            Notification::make()
                                ->title(__('app/invoice.action.ksef.auth_success_title'))
                                ->body(__('app/invoice.action.ksef.auth_success_body'))
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            $record->forceFill(['ksef_status' => 'rejected'])->save();
                            Notification::make()
                                ->title(__('app/invoice.action.ksef.failure_title'))
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('email')
                    ->label(__('app/invoice.action.email.label'))
                    ->icon('heroicon-m-envelope')
                    ->color('primary')
                    ->visible(fn (Invoice $r) => $r->status->isPosted())
                    ->requiresConfirmation()
                    ->modalDescription(__('app/invoice.action.email.modal_description'))
                    ->action(function (Invoice $record) {
                        $tenant = app(TenantManager::class)->current();
                        if (! $tenant) {
                            return;
                        }
                        $client = $record->client;
                        if (! $client?->email) {
                            Notification::make()->title(__('app/invoice.action.email.no_email'))->danger()->send();

                            return;
                        }

                        $url = app(InvoicePublicLink::class)->for($record, $tenant->slug);
                        $canPay = ((string) (data_get($tenant->settings, 'payments.default_provider') ?? 'none')) !== 'none';

                        MailFacade::route('mail', $client->email)->notify(new InvoiceIssuedClientNotification(
                            tenantName: $tenant->name,
                            invoiceNumber: (string) $record->number,
                            kindLabel: $record->kind->label(),
                            totalFormatted: $record->totalFormatted(),
                            issuedAt: $record->issued_at,
                            dueAt: $record->due_at,
                            publicUrl: $url,
                            canPayOnline: $canPay && $record->status === InvoiceStatus::Issued,
                        ));

                        // Journal entry — pojawi się w portalu klienta "Wiadomości"
                        app(ClientMessageJournal::class)->record(
                            $client,
                            'invoice.issued',
                            $record->kind->label().' '.$record->number,
                            ['amount' => $record->totalFormatted()],
                            'Invoice',
                            (string) $record->id,
                        );

                        Notification::make()->title(__('app/invoice.action.email.success'))->success()->send();
                    }),
                Tables\Actions\EditAction::make()
                    ->visible(fn (Invoice $r) => $r->status === InvoiceStatus::Draft),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Invoice $r) => $r->status === InvoiceStatus::Draft),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
            'view' => Pages\ViewInvoice::route('/{record}'),
        ];
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        // Snapshot sprzedawcy z aktualnej stajni przy tworzeniu draftu
        $tenant = app(TenantManager::class)->current();
        if ($tenant && empty($data['seller_name'])) {
            $invoicing = (array) (data_get($tenant->settings, 'invoicing') ?? []);
            $data['seller_name'] = (string) ($invoicing['seller_name'] ?? $tenant->legal_name ?? $tenant->name);
            $data['seller_nip'] = (string) ($invoicing['seller_nip'] ?? $tenant->tax_id ?? '') ?: null;
            $data['seller_address'] = (string) ($invoicing['seller_address'] ?? '') ?: null;
            $data['seller_postal_code'] = (string) ($invoicing['seller_postal_code'] ?? '') ?: null;
            $data['seller_city'] = (string) ($invoicing['seller_city'] ?? '') ?: null;
            $data['seller_country'] = $tenant->country ?? 'PL';
        }
        $data['status'] = InvoiceStatus::Draft->value;
        $data['currency'] = $data['currency'] ?? 'PLN';

        return $data;
    }
}
