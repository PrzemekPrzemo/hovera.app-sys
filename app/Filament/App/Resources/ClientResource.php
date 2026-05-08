<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\ClientResource\Pages;
use App\Models\Tenant\Client;
use App\Notifications\ClientPortalMagicLinkNotification;
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

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.stable');
    }

    public static function getNavigationLabel(): string
    {
        return __('navigation.clients');
    }

    public static function getModelLabel(): string
    {
        return __('models.client');
    }

    public static function getPluralModelLabel(): string
    {
        return __('models.clients');
    }

    protected static ?int $navigationSort = 20;

    /** @return array<string,string> */
    private static function typeOptions(): array
    {
        return [
            'individual' => __('app/client.types.individual'),
            'family' => __('app/client.types.family'),
            'organisation' => __('app/client.types.organisation'),
        ];
    }

    /** @return array<string,string> */
    private static function typeOptionsShort(): array
    {
        return [
            'individual' => __('app/client.types_short.individual'),
            'family' => __('app/client.types_short.family'),
            'organisation' => __('app/client.types_short.organisation'),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('app/client.form.section.data'))
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('type')
                        ->label(__('app/client.form.label.type'))
                        ->options(self::typeOptions())
                        ->default('individual')
                        ->required(),
                    Forms\Components\TextInput::make('name')
                        ->label(__('app/client.form.label.name'))
                        ->required(),
                    Forms\Components\TextInput::make('email')->email()->maxLength(255),
                    Forms\Components\TextInput::make('phone')
                        ->label(__('app/client.form.label.phone'))
                        ->tel()->maxLength(40),
                    Forms\Components\TextInput::make('tax_id')
                        ->label(__('app/client.form.label.tax_id'))
                        ->maxLength(32)
                        ->suffixAction(
                            Forms\Components\Actions\Action::make('lookupGus')
                                ->label(__('app/client.form.gus.lookup_label'))
                                ->icon('heroicon-m-magnifying-glass')
                                ->visible(fn () => app(GusApiService::class)->isConfigured())
                                ->action(function (Forms\Get $get, Forms\Set $set) {
                                    $nip = (string) $get('tax_id');
                                    if (! CompanyLookupService::isValidNip($nip)) {
                                        Notification::make()
                                            ->title(__('app/client.form.gus.invalid_nip'))
                                            ->danger()->send();

                                        return;
                                    }

                                    $data = app(CompanyLookupService::class)->lookupByNip($nip);
                                    if ($data === null) {
                                        Notification::make()
                                            ->title(__('app/client.form.gus.not_found'))
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

                                    Notification::make()
                                        ->title(__('app/client.form.gus.success'))
                                        ->success()->send();
                                }),
                        ),
                ]),

            Forms\Components\Section::make(__('app/client.form.section.armir'))
                ->description(__('app/client.form.section.armir_description'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('armir_producer_id')
                        ->label(__('app/client.form.label.armir_producer_id'))
                        ->placeholder(__('app/client.form.label.armir_producer_id_placeholder'))
                        ->maxLength(32)
                        ->helperText(__('app/client.form.helper.armir_producer_id')),
                    Forms\Components\TextInput::make('pesel')
                        ->label(__('app/client.form.label.pesel'))
                        ->maxLength(11)
                        ->placeholder('00000000000')
                        ->helperText(__('app/client.form.helper.pesel')),
                ]),

            Forms\Components\Section::make(__('app/client.form.section.address'))
                ->collapsed()
                ->columns(4)
                ->schema([
                    Forms\Components\TextInput::make('street')
                        ->label(__('app/client.form.label.street'))->columnSpan(2),
                    Forms\Components\TextInput::make('postal_code')
                        ->label(__('app/client.form.label.postal_code'))->maxLength(20),
                    Forms\Components\TextInput::make('city')
                        ->label(__('app/client.form.label.city')),
                    Forms\Components\TextInput::make('country')
                        ->label(__('app/client.form.label.country'))->default('PL')->maxLength(2),
                ]),

            Forms\Components\Section::make(__('app/client.form.section.rodo'))
                ->collapsed()
                ->columns(2)
                ->schema([
                    Forms\Components\DateTimePicker::make('rodo_consent_at')
                        ->label(__('app/client.form.label.rodo_consent_at')),
                    Forms\Components\TextInput::make('rodo_consent_source')
                        ->label(__('app/client.form.label.rodo_consent_source'))->maxLength(60),
                ]),

            Forms\Components\Section::make(__('app/client.form.section.notes'))
                ->collapsed()
                ->schema([
                    Forms\Components\Textarea::make('notes')
                        ->label(__('app/client.form.label.notes'))->rows(4),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('app/client.table.column.name'))
                    ->searchable()->sortable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->label(__('app/client.table.column.type'))
                    ->colors([
                        'gray' => 'individual',
                        'primary' => 'family',
                        'success' => 'organisation',
                    ])
                    ->formatStateUsing(fn (string $state) => self::typeOptionsShort()[$state] ?? $state),
                Tables\Columns\TextColumn::make('email')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label(__('app/client.table.column.phone'))->toggleable(),
                Tables\Columns\TextColumn::make('horses_count')
                    ->counts('horses')
                    ->label(__('app/client.table.column.horses_count'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('rodo_consent_at')
                    ->label(__('app/client.table.column.rodo'))
                    ->date()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('app/client.table.column.created_at'))
                    ->date()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options(self::typeOptionsShort()),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->after(self::auditCallback('client.update')),
                Tables\Actions\Action::make('issue_portal_link')
                    ->label(__('app/client.action.issue_portal_link.label'))
                    ->icon('heroicon-o-link')
                    ->color('primary')
                    ->visible(fn (Client $r) => ! $r->trashed())
                    ->requiresConfirmation()
                    ->modalHeading(fn (Client $r) => __('app/client.action.issue_portal_link.modal_heading', ['name' => $r->name]))
                    ->modalDescription(__('app/client.action.issue_portal_link.modal_description'))
                    ->action(function (Client $record, ClientPortalAuth $auth, TenantManager $tm) {
                        $tenant = $tm->tenantOrFail();
                        $url = $auth->issueMagicLink($record, $tenant->slug);

                        app(TenantAuditLogger::class)->record('client.portal_link_issued', 'Client', $record->id, [
                            'name' => $record->name,
                        ]);

                        Notification::make()
                            ->success()
                            ->title(__('app/client.action.issue_portal_link.success_title'))
                            ->body($url)
                            ->persistent()
                            ->send();
                    }),
                Tables\Actions\Action::make('email_portal_link')
                    ->label(__('app/client.action.email_portal_link.label'))
                    ->icon('heroicon-o-envelope')
                    ->color('success')
                    ->visible(fn (Client $r) => ! $r->trashed() && ! empty($r->email))
                    ->requiresConfirmation()
                    ->modalHeading(fn (Client $r) => __('app/client.action.email_portal_link.modal_heading', ['name' => $r->name]))
                    ->modalDescription(fn (Client $r) => __('app/client.action.email_portal_link.modal_description', ['email' => $r->email]))
                    ->action(function (Client $record, ClientPortalAuth $auth, TenantManager $tm) {
                        $tenant = $tm->tenantOrFail();
                        if (empty($record->email)) {
                            Notification::make()->danger()
                                ->title(__('app/client.action.email_portal_link.no_email'))
                                ->send();

                            return;
                        }

                        $url = $auth->issueMagicLink($record, $tenant->slug);

                        \Illuminate\Support\Facades\Notification::route('mail', $record->email)
                            ->notify(new ClientPortalMagicLinkNotification(
                                tenantName: $tenant->name,
                                magicLinkUrl: $url,
                                ttlMinutes: 30,
                            ));

                        app(TenantAuditLogger::class)->record('client.portal_link_emailed', 'Client', $record->id, [
                            'name' => $record->name,
                            'email' => $record->email,
                        ]);

                        Notification::make()
                            ->success()
                            ->title(__('app/client.action.email_portal_link.success_title'))
                            ->body(__('app/client.action.email_portal_link.success_body', ['email' => $record->email]))
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

    public static function getRelations(): array
    {
        return [
            ClientResource\RelationManagers\HorsesRelationManager::class,
        ];
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
