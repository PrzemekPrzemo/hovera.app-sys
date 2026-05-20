<?php

declare(strict_types=1);

namespace App\Filament\Transport\Resources;

use App\Domain\Customers\Exceptions\CompanyLookupException;
use App\Domain\Customers\PolishRegistryService;
use App\Filament\Concerns\RestrictedByTenantRole;
use App\Filament\Transport\Resources\CustomerResource\Pages;
use App\Models\Tenant\Customer;
use App\Services\Tenancy\TenantRoleGate;
use App\Services\TenantAuditLogger;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Klienci transportera — własna baza, używana przy tworzeniu ofert.
 * Funkcje weryfikacji wpinają się przez `App\Domain\Customers\PolishRegistryService`
 * (MF Biała Lista po NIP, KRS po numerze KRS).
 *
 * Patrz user feedback "powinna być możliwość dodawania własnych klientów
 * i weryfikacji ich danych w gus, krs, ceidg".
 */
class CustomerResource extends Resource
{
    use RestrictedByTenantRole;

    /** @return list<string> */
    protected static function allowedRoles(): array
    {
        return TenantRoleGate::TRANSPORT_OPERATORS;
    }

    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.dispatch');
    }

    public static function getNavigationLabel(): string
    {
        return __('transport/customer.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('transport/customer.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('transport/customer.plural_label');
    }

    protected static ?int $navigationSort = 25;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('transport/customer.section.identification'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('transport/customer.form.label.name'))
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('company')
                        ->label(__('transport/customer.form.label.company'))
                        ->maxLength(255),
                    Forms\Components\TextInput::make('email')
                        ->label(__('transport/customer.form.label.email'))
                        ->email()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('phone')
                        ->label(__('transport/customer.form.label.phone'))
                        ->tel()
                        ->maxLength(40),
                ]),

            Forms\Components\Section::make(__('transport/customer.section.registry'))
                ->description(__('transport/customer.section.registry_description'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('tax_id')
                        ->label(__('transport/customer.form.label.tax_id'))
                        ->placeholder('1234567890')
                        ->maxLength(32)
                        ->suffixAction(
                            Forms\Components\Actions\Action::make('lookup_nip')
                                ->icon('heroicon-o-magnifying-glass')
                                ->tooltip(__('transport/customer.action.lookup_nip_tooltip'))
                                ->action(function (Get $get, Set $set) {
                                    self::handleLookup('nip', (string) $get('tax_id'), $set);
                                }),
                        ),
                    Forms\Components\TextInput::make('krs_number')
                        ->label(__('transport/customer.form.label.krs_number'))
                        ->placeholder('0000123456')
                        ->maxLength(16)
                        ->suffixAction(
                            Forms\Components\Actions\Action::make('lookup_krs')
                                ->icon('heroicon-o-magnifying-glass')
                                ->tooltip(__('transport/customer.action.lookup_krs_tooltip'))
                                ->action(function (Get $get, Set $set) {
                                    self::handleLookup('krs', (string) $get('krs_number'), $set);
                                }),
                        ),
                    Forms\Components\Textarea::make('address')
                        ->label(__('transport/customer.form.label.address'))
                        ->rows(2)
                        ->columnSpanFull(),
                    Forms\Components\Placeholder::make('last_verified_at')
                        ->label(__('transport/customer.form.label.last_verified'))
                        ->content(fn (?Customer $record) => $record?->last_verified_at
                            ? $record->last_verified_at->format('Y-m-d H:i').' ('.$record->verification_source.')'
                            : __('transport/customer.form.value.not_verified')),
                ]),

            Forms\Components\Section::make(__('transport/customer.section.notes'))
                ->collapsed()
                ->schema([
                    Forms\Components\Textarea::make('notes')
                        ->label(__('transport/customer.form.label.notes'))
                        ->rows(3),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('transport/customer.table.column.name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('company')
                    ->label(__('transport/customer.table.column.company'))
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('tax_id')
                    ->label(__('transport/customer.table.column.tax_id'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label(__('transport/customer.table.column.email'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label(__('transport/customer.table.column.phone'))
                    ->toggleable(),
                Tables\Columns\IconColumn::make('last_verified_at')
                    ->label(__('transport/customer.table.column.verified'))
                    ->boolean()
                    ->getStateUsing(fn (Customer $c) => $c->last_verified_at !== null),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('transport/customer.table.column.created_at'))
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->after(self::auditCallback('customer.update')),
                Tables\Actions\DeleteAction::make()->after(self::auditCallback('customer.delete')),
                Tables\Actions\RestoreAction::make()->after(self::auditCallback('customer.restore')),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }

    private static function auditCallback(string $action): callable
    {
        return function (Model $record) use ($action) {
            app(TenantAuditLogger::class)->record($action, 'Customer', (string) $record->getKey(), [
                'name' => $record->name,
            ]);
        };
    }

    private static function handleLookup(string $kind, string $identifier, Set $set): void
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            Notification::make()
                ->warning()
                ->title(__('transport/customer.notify.lookup_empty_identifier'))
                ->send();

            return;
        }

        try {
            $service = app(PolishRegistryService::class);
            $result = $kind === 'nip'
                ? $service->lookupByNip($identifier)
                : $service->lookupByKrs($identifier);

            if ($result->name !== null && $result->name !== '') {
                $set('company', $result->name);
            }
            if ($result->taxId !== null && $result->taxId !== '') {
                $set('tax_id', $result->taxId);
            }
            if ($result->krsNumber !== null && $result->krsNumber !== '') {
                $set('krs_number', $result->krsNumber);
            }
            if ($result->address !== null && $result->address !== '') {
                $set('address', $result->address);
            }
            $set('last_verified_at', now());
            $set('verification_source', $result->source);
            $set('verification_data', $result->raw);

            Notification::make()
                ->success()
                ->title(__('transport/customer.notify.lookup_success', ['source' => strtoupper($result->source)]))
                ->body($result->name ?? '')
                ->send();
        } catch (CompanyLookupException $e) {
            Notification::make()
                ->danger()
                ->title(__('transport/customer.notify.lookup_failed'))
                ->body($e->getMessage())
                ->persistent()
                ->send();
        }
    }
}
