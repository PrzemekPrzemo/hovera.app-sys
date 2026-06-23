<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\SpecialistThreadResource\Pages;
use App\Filament\Concerns\RestrictedByTenantRole;
use App\Models\Central\ExternalSpecialist;
use App\Models\Central\SpecialistThread;
use App\Models\Tenant\Horse;
use App\Models\Tenant\Specialist;
use App\Services\Tenancy\TenantRoleGate;
use App\Tenancy\TenantManager;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Channel B w panelu stajni (PR O5 epic 1.4) — lista wątków z external
 * specjalistami + zakładanie nowego wątku.
 *
 * Wątki żyją w central DB, więc `getEloquentQuery()` filtruje po bieżącym
 * tenancie (NIE przez TenantManager::execute — to standardowy central query).
 */
class SpecialistThreadResource extends Resource
{
    use RestrictedByTenantRole;

    /** @return list<string> */
    protected static function allowedRoles(): array
    {
        return TenantRoleGate::SPECIALIST_STAFF;
    }

    protected static ?string $model = SpecialistThread::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?int $navigationSort = 27;

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.stable');
    }

    public static function getNavigationLabel(): string
    {
        return __('app/specialist_thread.nav');
    }

    public static function getModelLabel(): string
    {
        return __('app/specialist_thread.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app/specialist_thread.model_plural');
    }

    public static function getEloquentQuery(): Builder
    {
        $tenant = app(TenantManager::class)->current();

        return SpecialistThread::query()
            ->when($tenant !== null, fn (Builder $q) => $q->forTenant($tenant->id))
            ->when($tenant === null, fn (Builder $q) => $q->whereRaw('1 = 0'))
            ->with('specialist');
    }

    /**
     * Specjaliści zaproszeni przez tę stajnię (lokalny Specialist z
     * external_specialist_id) — kandydaci do nowego wątku.
     *
     * @return array<string,string>
     */
    public static function specialistOptions(): array
    {
        $linkedIds = Specialist::query()
            ->whereNotNull('external_specialist_id')
            ->pluck('external_specialist_id')
            ->all();

        if ($linkedIds === []) {
            return [];
        }

        return ExternalSpecialist::query()
            ->whereIn('id', $linkedIds)
            ->get(['id', 'display_name', 'specialty'])
            ->mapWithKeys(fn (ExternalSpecialist $s) => [$s->id => $s->display_name])
            ->all();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('specialist_id')
                ->label(__('app/specialist_thread.form.specialist'))
                ->options(fn () => self::specialistOptions())
                ->searchable()
                ->required()
                ->helperText(__('app/specialist_thread.form.specialist_hint')),
            Forms\Components\Select::make('horse_id')
                ->label(__('app/specialist_thread.form.horse'))
                ->options(fn () => Horse::query()->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->placeholder(__('app/specialist_thread.form.horse_placeholder')),
            Forms\Components\TextInput::make('subject')
                ->label(__('app/specialist_thread.form.subject'))
                ->required()
                ->maxLength(200),
            Forms\Components\Textarea::make('body')
                ->label(__('app/specialist_thread.form.body'))
                ->required()
                ->rows(4)
                ->dehydrated(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('subject')
                    ->label(__('app/specialist_thread.table.subject'))
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('specialist.display_name')
                    ->label(__('app/specialist_thread.table.specialist'))
                    ->badge()
                    ->color(fn (SpecialistThread $r) => $r->specialist?->is_verified ? 'success' : 'warning')
                    ->formatStateUsing(function (SpecialistThread $r) {
                        $name = $r->specialist?->display_name ?? '—';

                        return $r->specialist?->is_verified
                            ? $name
                            : $name.' · '.__('app/specialist_thread.unverified');
                    }),
                Tables\Columns\TextColumn::make('last_message_at')
                    ->label(__('app/specialist_thread.table.last_message'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->defaultSort('last_message_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make()->label(__('app/specialist_thread.action.open')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSpecialistThreads::route('/'),
            'create' => Pages\CreateSpecialistThread::route('/create'),
            'view' => Pages\ViewSpecialistThread::route('/{record}'),
        ];
    }
}
