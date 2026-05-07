<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\ClientResource\Pages;
use App\Models\Tenant\Client;
use App\Services\CompanyLookup\CompanyLookupService;
use App\Services\CompanyLookup\GusApiService;
use App\Services\Portal\ClientPortalAuth;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Stajnia';

    protected static ?string $navigationLabel = 'Klienci';

    protected static ?string $modelLabel = 'klient';

    protected static ?string $pluralModelLabel = 'Klienci';

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Dane klienta')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('type')
                        ->label('Typ')
                        ->options([
                            'individual' => 'Osoba prywatna',
                            'family' => 'Rodzina',
                            'organisation' => 'Firma / organizacja',
                        ])
                        ->default('individual')
                        ->required(),
                    Forms\Components\TextInput::make('name')->label('Imię i nazwisko / Nazwa')->required(),
                    Forms\Components\TextInput::make('email')->email()->maxLength(255),
                    Forms\Components\TextInput::make('phone')->label('Telefon')->tel()->maxLength(40),
                    Forms\Components\TextInput::make('tax_id')
                        ->label('NIP / VAT ID')
                        ->maxLength(32)
                        ->suffixAction(
                            // "Pobierz z GUS" — fills name + address from GUS BIR
                            // when NIP is valid + master-admin skonfigurował klucz API.
                            Forms\Components\Actions\Action::make('lookupGus')
                                ->label('Pobierz z GUS')
                                ->icon('heroicon-m-magnifying-glass')
                                ->visible(fn () => app(GusApiService::class)->isConfigured())
                                ->action(function (Forms\Get $get, Forms\Set $set) {
                                    $nip = (string) $get('tax_id');
                                    if (! CompanyLookupService::isValidNip($nip)) {
                                        Notification::make()->title('Nieprawidłowy NIP (suma kontrolna).')
                                            ->danger()->send();

                                        return;
                                    }

                                    $data = app(CompanyLookupService::class)->lookupByNip($nip);
                                    if ($data === null) {
                                        Notification::make()->title('Nie znaleziono firmy w GUS.')
                                            ->warning()->send();

                                        return;
                                    }

                                    $set('name', (string) ($data['name'] ?? ''));
                                    $set('street', trim(($data['street'] ?? '').' '
                                        .($data['building'] ?? '')
                                        .($data['apartment'] ? '/'.$data['apartment'] : '')));
                                    $set('city', (string) ($data['city'] ?? ''));
                                    $set('postal_code', (string) ($data['postal_code'] ?? ''));
                                    $set('country', 'PL');
                                    $set('type', 'organisation');

                                    Notification::make()->title('Pobrano dane z GUS.')->success()->send();
                                }),
                        ),
                ]),

            Forms\Components\Section::make('Adres')
                ->collapsed()
                ->columns(4)
                ->schema([
                    Forms\Components\TextInput::make('street')->label('Ulica i numer')->columnSpan(2),
                    Forms\Components\TextInput::make('postal_code')->label('Kod pocztowy')->maxLength(20),
                    Forms\Components\TextInput::make('city')->label('Miasto'),
                    Forms\Components\TextInput::make('country')->label('Kraj')->default('PL')->maxLength(2),
                ]),

            Forms\Components\Section::make('RODO')
                ->collapsed()
                ->columns(2)
                ->schema([
                    Forms\Components\DateTimePicker::make('rodo_consent_at')->label('Zgoda RODO udzielona'),
                    Forms\Components\TextInput::make('rodo_consent_source')->label('Źródło zgody')->maxLength(60),
                ]),

            Forms\Components\Section::make('Notatki')
                ->collapsed()
                ->schema([
                    Forms\Components\Textarea::make('notes')->label('Notatki wewnętrzne')->rows(4),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nazwa')->searchable()->sortable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->label('Typ')
                    ->colors([
                        'gray' => 'individual',
                        'primary' => 'family',
                        'success' => 'organisation',
                    ])
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'individual' => 'Os. prywatna',
                        'family' => 'Rodzina',
                        'organisation' => 'Firma',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('email')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('phone')->label('Telefon')->toggleable(),
                Tables\Columns\TextColumn::make('horses_count')
                    ->counts('horses')
                    ->label('Konie')
                    ->sortable(),
                Tables\Columns\TextColumn::make('rodo_consent_at')->label('RODO')->date()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')->label('Dodany')->date()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options([
                    'individual' => 'Osoba prywatna',
                    'family' => 'Rodzina',
                    'organisation' => 'Firma',
                ]),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->after(self::auditCallback('client.update')),
                Tables\Actions\Action::make('issue_portal_link')
                    ->label('Wygeneruj link portalu')
                    ->icon('heroicon-o-link')
                    ->color('primary')
                    ->visible(fn (Client $r) => ! $r->trashed())
                    ->requiresConfirmation()
                    ->modalHeading(fn (Client $r) => "Wygenerować link logowania dla {$r->name}?")
                    ->modalDescription('Tworzy jednorazowy magic link (TTL 30 min). Możesz go skopiować i wysłać klientowi ręcznie, np. SMS-em lub Messengerem. Nie wymaga maila.')
                    ->action(function (Client $record, ClientPortalAuth $auth, TenantManager $tm) {
                        $tenant = $tm->tenantOrFail();
                        $url = $auth->issueMagicLink($record, $tenant->slug);

                        app(TenantAuditLogger::class)->record('client.portal_link_issued', 'Client', $record->id, [
                            'name' => $record->name,
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Link logowania utworzony')
                            ->body($url)
                            ->persistent()
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make()->after(self::auditCallback('client.delete')),
                Tables\Actions\RestoreAction::make()->after(self::auditCallback('client.restore')),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
        ];
    }

    private static function auditCallback(string $action): callable
    {
        return function (Model $record) use ($action) {
            app(TenantAuditLogger::class)->record($action, 'Client', (string) $record->getKey(), [
                'name' => $record->name,
            ]);
        };
    }
}
