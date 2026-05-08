<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Actions\Tenants\DeleteTenant;
use App\Filament\Admin\Resources\TenantResource\Pages;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Services\MasterAuditLogger;
use App\Support\ImpersonationDebug;
use App\Tenancy\TenantManager;
use Database\Seeders\Demo\HoveraDemoSeeder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Artisan;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Stajnie';

    protected static ?string $navigationGroup = 'Stajnie';

    protected static ?string $modelLabel = 'Stajnia';

    protected static ?string $pluralModelLabel = 'Stajnie';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identyfikacja')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->maxLength(63)
                        ->regex('/^[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])?$/')
                        ->disabledOn('edit')
                        ->helperText('Niezmienne. Używane w adresach i nazwie bazy.'),
                    Forms\Components\TextInput::make('name')->required()->maxLength(255),
                    Forms\Components\TextInput::make('legal_name')->maxLength(255),
                    Forms\Components\TextInput::make('tax_id')->label('NIP / VAT ID')->maxLength(32),
                ]),

            Forms\Components\Section::make('Lokalizacja')
                ->columns(4)
                ->schema([
                    Forms\Components\TextInput::make('country')->default('PL')->maxLength(2)->required(),
                    Forms\Components\TextInput::make('locale')->default('pl')->maxLength(10)->required(),
                    Forms\Components\TextInput::make('timezone')->default('Europe/Warsaw')->required(),
                    Forms\Components\TextInput::make('currency')->default('PLN')->maxLength(3)->required(),
                ]),

            Forms\Components\Section::make('Subskrypcja')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('plan_id')
                        ->label('Plan')
                        ->options(fn () => Plan::query()->orderBy('sort_order')->pluck('name', 'id'))
                        ->searchable(),
                    Forms\Components\Select::make('status')
                        ->options([
                            'provisioning' => 'provisioning',
                            'trialing' => 'trialing',
                            'active' => 'active',
                            'past_due' => 'past_due',
                            'suspended' => 'suspended',
                            'churned' => 'churned',
                        ])
                        ->disabledOn('create'),
                ]),

            Forms\Components\Section::make('Branding')
                ->description('Używane na publicznej stronie /s/{slug} i w mailach.')
                ->collapsed()
                ->columns(2)
                ->schema([
                    Forms\Components\ColorPicker::make('branding.primary_color')
                        ->label('Kolor wiodący')
                        ->default('#10b981'),
                    Forms\Components\TextInput::make('branding.logo_url')
                        ->label('URL logo')
                        ->url()
                        ->maxLength(500),
                ]),

            Forms\Components\Section::make('Profil publiczny')
                ->description('Dane wyświetlane na publicznej stronie stajni /s/{slug}.')
                ->collapsed()
                ->columns(2)
                ->schema([
                    Forms\Components\Textarea::make('settings.public_profile.description')
                        ->label('Opis stajni')
                        ->rows(3)
                        ->maxLength(2000)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('settings.public_profile.email')
                        ->label('Email kontaktowy (publiczny)')
                        ->email()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('settings.public_profile.phone')
                        ->label('Telefon kontaktowy')
                        ->tel()
                        ->maxLength(40),
                    Forms\Components\TextInput::make('settings.public_profile.address')
                        ->label('Adres')
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('settings.public_profile.website')
                        ->label('Strona WWW')
                        ->url()
                        ->maxLength(500),
                ]),

            Forms\Components\Section::make('Baza danych')
                ->columns(3)
                ->visibleOn('edit')
                ->schema([
                    Forms\Components\TextInput::make('db_name')->disabled(),
                    Forms\Components\TextInput::make('db_username')->disabled(),
                    Forms\Components\TextInput::make('db_host')->disabled(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('slug')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('country')->label('Kraj')->sortable(),
                Tables\Columns\TextColumn::make('plan.name')->label('Plan')->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray' => fn ($state) => in_array($state, ['provisioning', 'churned'], true),
                        'warning' => fn ($state) => in_array($state, ['trialing', 'past_due'], true),
                        'success' => 'active',
                        'danger' => fn ($state) => in_array($state, ['suspended', 'deleted'], true),
                    ]),
                Tables\Columns\TextColumn::make('db_name')->label('Baza')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')->label('Utworzona')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'trialing' => 'trialing',
                        'active' => 'active',
                        'past_due' => 'past_due',
                        'suspended' => 'suspended',
                        'churned' => 'churned',
                    ]),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('suspend')
                    ->label('Suspend')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (Tenant $r) => $r->status !== 'suspended')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('reason')->required()->maxLength(500),
                    ])
                    ->action(function (Tenant $record, array $data, MasterAuditLogger $audit) {
                        $record->forceFill([
                            'status' => 'suspended',
                            'suspended_at' => now(),
                            'suspended_reason' => $data['reason'],
                        ])->save();
                        $audit->record('tenant.suspend', 'Tenant', $record->id, $record->id, $data);
                        Notification::make()->success()->title('Stajnia zawieszona')->send();
                    }),
                Tables\Actions\Action::make('reactivate')
                    ->label('Reactivate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Tenant $r) => $r->status === 'suspended')
                    ->requiresConfirmation()
                    ->action(function (Tenant $record, MasterAuditLogger $audit) {
                        $record->forceFill([
                            'status' => 'active',
                            'suspended_at' => null,
                            'suspended_reason' => null,
                        ])->save();
                        $audit->record('tenant.reactivate', 'Tenant', $record->id, $record->id);
                        Notification::make()->success()->title('Stajnia ponownie aktywna')->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->label('Soft delete')
                    ->after(function (Tenant $record, MasterAuditLogger $audit) {
                        $audit->record('tenant.soft_delete', 'Tenant', $record->id, $record->id);
                    }),
                Tables\Actions\RestoreAction::make()
                    ->after(function (Tenant $record, MasterAuditLogger $audit) {
                        $audit->record('tenant.restore', 'Tenant', $record->id, $record->id);
                    }),
                Tables\Actions\Action::make('login_as_owner')
                    ->label('Zaloguj jako stajnia')
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->visible(fn (Tenant $r) => ! $r->trashed()
                        && $r->status === 'active'
                        && $r->memberships()->whereNull('revoked_at')->whereNotNull('user_id')->exists())
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Powód impersonacji (audit RODO)')
                            ->required()
                            ->minLength(5)
                            ->maxLength(500)
                            ->helperText('Wymagane. Sesja jest wpisana do impersonation_sessions + audit_log_master.'),
                    ])
                    ->action(function (Tenant $record, array $data) {
                        $membership = $record->memberships()
                            ->whereNull('revoked_at')
                            ->whereNotNull('user_id')
                            ->orderBy('created_at')
                            ->with('user')
                            ->first();

                        if (! $membership || ! $membership->user) {
                            Notification::make()->danger()
                                ->title('Brak aktywnego usera dla tej stajni')
                                ->body('Najpierw dodaj członka zespołu lub zaproś ownera.')
                                ->send();

                            return;
                        }

                        // Stash intent — the actual Auth::loginUsingId() runs in
                        // ImpersonationController@start (fresh request), which
                        // avoids fighting AuthenticateSession middleware mid-Livewire.
                        request()->session()->put('impersonation.intent', [
                            'tenant_id' => $record->id,
                            'target_user_id' => $membership->user->id,
                            'reason' => (string) $data['reason'],
                            'issued_at' => now()->timestamp,
                        ]);

                        ImpersonationDebug::snap('1_tenant_action_stashed_intent', [
                            'tenant_id' => $record->id,
                            'target_user_id' => $membership->user->id,
                        ]);
                    })
                    ->successRedirectUrl(fn () => route('impersonation.start'))
                    ->modalSubmitActionLabel('Rozpocznij impersonację'),
                Tables\Actions\Action::make('seed_demo')
                    ->label('Wgraj demo dane')
                    ->icon('heroicon-o-sparkles')
                    ->color('warning')
                    ->visible(fn (Tenant $r) => ! $r->trashed() && $r->status === 'active')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Tenant $r) => "Wgrać demo dane do {$r->name}?")
                    ->modalDescription('Doda 14 koni, 6 klientów, 12 boxów, kalendarz, faktury i resztę zestawu pokazowego. Działa na bazie tenanta.')
                    ->form([
                        Forms\Components\Toggle::make('fresh')
                            ->label('Wyczyść istniejące dane (DROP all tables)')
                            ->default(false)
                            ->helperText('UWAGA: usunie wszystkie obecne dane stajni przed seed.'),
                    ])
                    ->action(function (Tenant $record, array $data, TenantManager $tm, HoveraDemoSeeder $seeder, MasterAuditLogger $audit) {
                        $tm->setCurrent($record);
                        try {
                            if ($data['fresh'] ?? false) {
                                Artisan::call('migrate:fresh', [
                                    '--database' => 'tenant',
                                    '--path' => 'database/migrations/tenant',
                                    '--force' => true,
                                ]);
                            } else {
                                Artisan::call('migrate', [
                                    '--database' => 'tenant',
                                    '--path' => 'database/migrations/tenant',
                                    '--force' => true,
                                ]);
                            }
                            $seeder->run();
                            $audit->record('tenant.demo_seeded', 'Tenant', $record->id, $record->id, [
                                'fresh' => $data['fresh'] ?? false,
                            ]);
                            Notification::make()->success()
                                ->title('Demo dane wgrane')
                                ->body("Stajnia {$record->name} ma teraz pełen zestaw pokazowy.")
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()->danger()
                                ->title('Nie udało się wgrać demo')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('destroy')
                    ->label('Drop database')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (Tenant $r) => $r->trashed())
                    ->requiresConfirmation()
                    ->modalHeading('Trwale usuń stajnię')
                    ->modalDescription(fn (Tenant $r) => "Tej operacji NIE można cofnąć. Bazy {$r->db_name} oraz konto MySQL {$r->db_username} zostaną usunięte fizycznie.")
                    ->form([
                        Forms\Components\TextInput::make('confirm_slug')
                            ->label('Wpisz slug stajni, aby potwierdzić')
                            ->required(),
                    ])
                    ->action(function (Tenant $record, array $data, DeleteTenant $deleter, MasterAuditLogger $audit) {
                        if ($data['confirm_slug'] !== $record->slug) {
                            Notification::make()->danger()->title('Slug się nie zgadza.')->send();

                            return;
                        }
                        $audit->record('tenant.destroy', 'Tenant', $record->id, $record->id, [
                            'db_name' => $record->db_name,
                            'slug' => $record->slug,
                        ]);
                        $deleter->destroy($record);
                        Notification::make()->success()->title('Stajnia trwale usunięta')->send();
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getRelations(): array
    {
        return [
            TenantResource\RelationManagers\MembershipsRelationManager::class,
            TenantResource\RelationManagers\InvitationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
