<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Actions\Invoicing\CreateInvoiceCorrection;
use App\Actions\Invoicing\IssueInvoice;
use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Filament\App\Resources\InvoiceResource\Pages;
use App\Filament\Components\PriceInput;
use App\Models\Tenant\Client;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\InvoiceItem;
use App\Notifications\InvoiceIssuedClientNotification;
use App\Services\Invoicing\InvoicePublicLink;
use App\Services\Ksef\KsefClient;
use App\Services\Portal\ClientMessageJournal;
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
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Finanse';

    protected static ?string $navigationLabel = 'Faktury';

    protected static ?string $modelLabel = 'faktura';

    protected static ?string $pluralModelLabel = 'Faktury';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Dane faktury')
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('kind')
                        ->label('Rodzaj')
                        ->options(InvoiceKind::options())
                        ->default(InvoiceKind::Fv->value)
                        ->required()
                        ->disabledOn('edit'),
                    Forms\Components\TextInput::make('number')
                        ->label('Numer')
                        ->placeholder('— nadawany przy wystawieniu —')
                        ->disabled(),
                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options(InvoiceStatus::options())
                        ->default(InvoiceStatus::Draft->value)
                        ->disabled(),
                ]),

            Forms\Components\Section::make('Nabywca')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('client_id')
                        ->label('Klient')
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
                    Forms\Components\TextInput::make('buyer_name')->label('Nazwa / imię i nazwisko')->required(),
                    Forms\Components\TextInput::make('buyer_nip')->label('NIP (opcjonalnie dla os. fizycznych)'),
                    Forms\Components\TextInput::make('buyer_address')->label('Adres'),
                    Forms\Components\TextInput::make('buyer_postal_code')->label('Kod'),
                    Forms\Components\TextInput::make('buyer_city')->label('Miasto'),
                    Forms\Components\TextInput::make('buyer_country')->label('Kraj')->default('PL')->maxLength(2),
                ]),

            Forms\Components\Section::make('Sprzedawca (snapshot)')
                ->columns(2)
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('seller_name')->label('Nazwa')->required(),
                    Forms\Components\TextInput::make('seller_nip')->label('NIP'),
                    Forms\Components\TextInput::make('seller_address')->label('Adres'),
                    Forms\Components\TextInput::make('seller_postal_code')->label('Kod'),
                    Forms\Components\TextInput::make('seller_city')->label('Miasto'),
                    Forms\Components\TextInput::make('seller_country')->label('Kraj')->default('PL')->maxLength(2),
                ]),

            Forms\Components\Section::make('Daty')
                ->columns(3)
                ->schema([
                    Forms\Components\DatePicker::make('issued_at')->label('Wystawiona')->disabled(),
                    Forms\Components\DatePicker::make('sale_date')->label('Data sprzedaży'),
                    Forms\Components\DatePicker::make('due_at')->label('Termin płatności')
                        ->default(fn () => now()->addDays(7)->toDateString()),
                ]),

            Forms\Components\Section::make('Pozycje')
                ->schema([
                    Forms\Components\Repeater::make('items')
                        ->relationship()
                        ->columns(6)
                        ->orderColumn('position')
                        ->defaultItems(1)
                        ->reorderable()
                        ->schema([
                            Forms\Components\TextInput::make('name')->label('Nazwa')->required()->columnSpan(2),
                            Forms\Components\TextInput::make('quantity')->label('Ilość')->numeric()->default(1)->required(),
                            Forms\Components\TextInput::make('unit')->label('Jedn.')->default('szt.'),
                            PriceInput::make('unit_price_cents', 'Cena j. netto')->required(),
                            Forms\Components\Select::make('vat_rate')->label('VAT')
                                ->options(InvoiceItem::vatRateOptions())
                                ->default('23')
                                ->required(),
                        ]),
                ]),

            Forms\Components\Section::make('Notatki')
                ->collapsed()
                ->schema([
                    Forms\Components\Textarea::make('notes')->label('Uwagi')->rows(3),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Numer')
                    ->placeholder('—')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\BadgeColumn::make('kind')
                    ->label('Rodzaj')
                    ->formatStateUsing(fn (InvoiceKind $state) => $state->shortLabel())
                    ->colors([
                        'primary' => InvoiceKind::Fv->value,
                        'gray' => InvoiceKind::FvProforma->value,
                        'warning' => InvoiceKind::FvKorekta->value,
                    ]),
                Tables\Columns\TextColumn::make('issued_at')->label('Wystawiona')->date()->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('client.name')->label('Nabywca')->searchable(),
                Tables\Columns\TextColumn::make('total_cents')
                    ->label('Brutto')
                    ->formatStateUsing(fn (?int $state, Invoice $record) => $record->totalFormatted())
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
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
                Tables\Columns\TextColumn::make('due_at')->label('Termin')->date()->placeholder('—')->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('kind')->options(InvoiceKind::options()),
                Tables\Filters\SelectFilter::make('status')->options(InvoiceStatus::options()),
                Tables\Filters\Filter::make('overdue')
                    ->label('Po terminie')
                    ->query(fn ($query) => $query->overdue()),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('issue')
                    ->label('Wystaw')
                    ->icon('heroicon-m-paper-airplane')
                    ->visible(fn (Invoice $r) => $r->status === InvoiceStatus::Draft)
                    ->requiresConfirmation()
                    ->action(function (Invoice $record) {
                        try {
                            app(IssueInvoice::class)->execute($record);
                            Notification::make()->title('Faktura wystawiona')->success()->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Nie można wystawić faktury')
                                ->body(implode("\n", Arr::flatten($e->errors())))
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('correct')
                    ->label('Korekta')
                    ->icon('heroicon-m-arrow-path')
                    ->visible(fn (Invoice $r) => $r->kind !== InvoiceKind::FvKorekta && $r->status->isPosted())
                    ->action(function (Invoice $record) {
                        try {
                            $korekta = app(CreateInvoiceCorrection::class)->execute($record);
                            Notification::make()->title('Korekta utworzona')->success()
                                ->body('Otwórz draft '.($korekta->id).' i edytuj pozycje.')
                                ->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Błąd')
                                ->body(implode("\n", Arr::flatten($e->errors())))
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('ksef')
                    ->label('Wyślij do KSeF')
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
                    ->modalDescription('Faktura zostanie podpisana certyfikatem stajni i wysłana do KSeF.')
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
                                ->title('KSeF: uwierzytelnienie udane')
                                ->body('Wysyłka treści faktury w przygotowaniu (PR 4b).')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            $record->forceFill(['ksef_status' => 'rejected'])->save();
                            Notification::make()
                                ->title('KSeF: błąd')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('email')
                    ->label('Wyślij na e-mail')
                    ->icon('heroicon-m-envelope')
                    ->color('primary')
                    ->visible(fn (Invoice $r) => $r->status->isPosted())
                    ->requiresConfirmation()
                    ->modalDescription('Wyślemy link do faktury na e-mail klienta. Link działa do 90 dni (lub 14 dni po terminie płatności).')
                    ->action(function (Invoice $record) {
                        $tenant = app(TenantManager::class)->current();
                        if (! $tenant) {
                            return;
                        }
                        $client = $record->client;
                        if (! $client?->email) {
                            Notification::make()->title('Brak e-maila klienta')->danger()->send();

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

                        Notification::make()->title('Wysłano fakturę na e-mail klienta')->success()->send();
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
