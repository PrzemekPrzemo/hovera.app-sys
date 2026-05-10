<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Actions\Impersonation\StartImpersonation;
use App\Actions\Tenants\DeleteTenant;
use App\Filament\Admin\Resources\TenantResource\Pages;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Services\Exports\TenantDataExporter;
use App\Services\MasterAuditLogger;
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
use Illuminate\Support\Facades\Auth;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string
    {
        return __('navigation.tenants');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.stables');
    }

    public static function getModelLabel(): string
    {
        return __('models.tenant');
    }

    public static function getPluralModelLabel(): string
    {
        return __('models.tenants');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('admin/tenant.form.section.identification'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->maxLength(63)
                        ->regex('/^[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])?$/')
                        ->disabledOn('edit')
                        ->helperText(__('admin/tenant.form.helper.slug')),
                    Forms\Components\TextInput::make('name')->required()->maxLength(255),
                    Forms\Components\TextInput::make('legal_name')->maxLength(255),
                    Forms\Components\TextInput::make('tax_id')
                        ->label(__('admin/tenant.form.label.tax_id'))
                        ->maxLength(32),
                ]),

            Forms\Components\Section::make(__('admin/tenant.form.section.location'))
                ->columns(4)
                ->schema([
                    Forms\Components\TextInput::make('country')->default('PL')->maxLength(2)->required(),
                    Forms\Components\TextInput::make('locale')->default('pl')->maxLength(10)->required(),
                    Forms\Components\TextInput::make('timezone')->default('Europe/Warsaw')->required(),
                    Forms\Components\TextInput::make('currency')->default('PLN')->maxLength(3)->required(),
                ]),

            Forms\Components\Section::make(__('admin/tenant.form.section.subscription'))
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('plan_id')
                        ->label(__('admin/tenant.form.label.plan'))
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

            Forms\Components\Section::make(__('admin/tenant.form.section.branding'))
                ->description(__('admin/tenant.form.section.branding_description'))
                ->collapsed()
                ->columns(2)
                ->schema([
                    Forms\Components\ColorPicker::make('branding.primary_color')
                        ->label(__('admin/tenant.form.label.primary_color'))
                        ->default('#10b981'),
                    Forms\Components\TextInput::make('branding.logo_url')
                        ->label(__('admin/tenant.form.label.logo_url'))
                        ->url()
                        ->maxLength(500),
                ]),

            Forms\Components\Section::make(__('admin/tenant.form.section.public_profile'))
                ->description(__('admin/tenant.form.section.public_profile_description'))
                ->collapsed()
                ->columns(2)
                ->schema([
                    Forms\Components\Textarea::make('settings.public_profile.description')
                        ->label(__('admin/tenant.form.label.public_description'))
                        ->rows(3)
                        ->maxLength(2000)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('settings.public_profile.email')
                        ->label(__('admin/tenant.form.label.public_email'))
                        ->email()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('settings.public_profile.phone')
                        ->label(__('admin/tenant.form.label.public_phone'))
                        ->tel()
                        ->maxLength(40),
                    Forms\Components\TextInput::make('settings.public_profile.address')
                        ->label(__('admin/tenant.form.label.public_address'))
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('settings.public_profile.website')
                        ->label(__('admin/tenant.form.label.public_website'))
                        ->url()
                        ->maxLength(500),
                ]),

            Forms\Components\Section::make(__('admin/tenant.form.section.database'))
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
                Tables\Columns\TextColumn::make('country')
                    ->label(__('admin/tenant.table.column.country'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('plan.name')
                    ->label(__('admin/tenant.table.column.plan'))
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray' => fn ($state) => in_array($state, ['provisioning', 'churned'], true),
                        'warning' => fn ($state) => in_array($state, ['trialing', 'past_due'], true),
                        'success' => 'active',
                        'danger' => fn ($state) => in_array($state, ['suspended', 'deleted'], true),
                    ]),
                Tables\Columns\TextColumn::make('db_name')
                    ->label(__('admin/tenant.table.column.db_name'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin/tenant.table.column.created_at'))
                    ->dateTime()
                    ->sortable(),
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
                    ->label(__('admin/tenant.action.suspend.label'))
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
                        Notification::make()->success()
                            ->title(__('admin/tenant.action.suspend.notification_title'))
                            ->send();
                    }),
                Tables\Actions\Action::make('reactivate')
                    ->label(__('admin/tenant.action.reactivate.label'))
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
                        Notification::make()->success()
                            ->title(__('admin/tenant.action.reactivate.notification_title'))
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->label(__('admin/tenant.action.soft_delete.label'))
                    ->after(function (Tenant $record, MasterAuditLogger $audit) {
                        $audit->record('tenant.soft_delete', 'Tenant', $record->id, $record->id);
                    }),
                Tables\Actions\RestoreAction::make()
                    ->after(function (Tenant $record, MasterAuditLogger $audit) {
                        $audit->record('tenant.restore', 'Tenant', $record->id, $record->id);
                    }),
                Tables\Actions\Action::make('login_as_owner')
                    ->label(__('admin/tenant.action.login_as_owner.label'))
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->visible(fn (Tenant $r) => ! $r->trashed()
                        && $r->status === 'active'
                        && $r->memberships()->whereNull('revoked_at')->whereNotNull('user_id')->exists())
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label(__('admin/tenant.action.login_as_owner.reason_label'))
                            ->required()
                            ->minLength(5)
                            ->maxLength(500)
                            ->helperText(__('admin/tenant.action.login_as_owner.reason_helper')),
                    ])
                    ->action(function (Tenant $record, array $data, StartImpersonation $impersonate) {
                        $membership = $record->memberships()
                            ->whereNull('revoked_at')
                            ->whereNotNull('user_id')
                            ->orderBy('created_at')
                            ->with('user')
                            ->first();

                        if (! $membership || ! $membership->user) {
                            Notification::make()->danger()
                                ->title(__('admin/tenant.action.login_as_owner.no_user_title'))
                                ->body(__('admin/tenant.action.login_as_owner.no_user_body'))
                                ->send();

                            return;
                        }

                        $impersonate->execute(
                            masterAdmin: Auth::user(),
                            tenant: $record,
                            targetUser: $membership->user,
                            reason: (string) $data['reason'],
                            session: request()->session(),
                        );
                    })
                    ->successRedirectUrl('/app')
                    ->modalSubmitActionLabel(__('admin/tenant.action.login_as_owner.submit')),
                Tables\Actions\Action::make('export_data')
                    ->label(__('admin/tenant-export.action.label'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('warning')
                    ->visible(fn (Tenant $r) => ! $r->trashed() && Auth::user()?->is_master_admin === true)
                    ->requiresConfirmation()
                    ->modalHeading(fn (Tenant $r) => __('admin/tenant-export.action.modal_heading', ['name' => $r->name]))
                    ->modalDescription(__('admin/tenant-export.action.modal_description'))
                    ->action(function (Tenant $record, TenantDataExporter $exporter, MasterAuditLogger $audit) {
                        try {
                            $path = $exporter->export($record);
                        } catch (\Throwable $e) {
                            Notification::make()->danger()
                                ->title(__('admin/tenant-export.toast.failure_title'))
                                ->body($e->getMessage())
                                ->send();

                            return null;
                        }

                        $audit->record('tenant.data_export', 'Tenant', $record->id, $record->id, [
                            'file' => basename($path),
                        ]);

                        Notification::make()->success()
                            ->title(__('admin/tenant-export.toast.success_title'))
                            ->body(__('admin/tenant-export.toast.success_body', ['file' => basename($path)]))
                            ->send();

                        return response()->download($path)->deleteFileAfterSend();
                    }),
                Tables\Actions\Action::make('seed_demo')
                    ->label(__('admin/tenant.action.seed_demo.label'))
                    ->icon('heroicon-o-sparkles')
                    ->color('warning')
                    ->visible(fn (Tenant $r) => ! $r->trashed() && $r->status === 'active')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Tenant $r) => __('admin/tenant.action.seed_demo.modal_heading', ['name' => $r->name]))
                    ->modalDescription(__('admin/tenant.action.seed_demo.modal_description'))
                    ->form([
                        Forms\Components\Toggle::make('fresh')
                            ->label(__('admin/tenant.action.seed_demo.fresh_label'))
                            ->default(false)
                            ->helperText(__('admin/tenant.action.seed_demo.fresh_helper')),
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
                                ->title(__('admin/tenant.action.seed_demo.success_title'))
                                ->body(__('admin/tenant.action.seed_demo.success_body', ['name' => $record->name]))
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()->danger()
                                ->title(__('admin/tenant.action.seed_demo.failure_title'))
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('destroy')
                    ->label(__('admin/tenant.action.destroy.label'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (Tenant $r) => $r->trashed())
                    ->requiresConfirmation()
                    ->modalHeading(__('admin/tenant.action.destroy.modal_heading'))
                    ->modalDescription(fn (Tenant $r) => __('admin/tenant.action.destroy.modal_description', [
                        'db' => $r->db_name,
                        'user' => $r->db_username,
                    ]))
                    ->form([
                        Forms\Components\TextInput::make('confirm_slug')
                            ->label(__('admin/tenant.action.destroy.confirm_slug_label'))
                            ->required(),
                    ])
                    ->action(function (Tenant $record, array $data, DeleteTenant $deleter, MasterAuditLogger $audit) {
                        if ($data['confirm_slug'] !== $record->slug) {
                            Notification::make()->danger()
                                ->title(__('admin/tenant.action.destroy.slug_mismatch'))
                                ->send();

                            return;
                        }
                        $audit->record('tenant.destroy', 'Tenant', $record->id, $record->id, [
                            'db_name' => $record->db_name,
                            'slug' => $record->slug,
                        ]);
                        $deleter->destroy($record);
                        Notification::make()->success()
                            ->title(__('admin/tenant.action.destroy.success_title'))
                            ->send();
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
