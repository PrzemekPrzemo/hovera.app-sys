<?php

declare(strict_types=1);

namespace App\Filament\Owner\Resources;

use App\Filament\Owner\Resources\SpecialistThreadResource\Pages;
use App\Models\Central\ExternalSpecialist;
use App\Models\Central\OwnerSpecialistThread;
use App\Models\Tenant\Horse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Channel D w panelu właściciela (PR O5 epic 3) — bezpośrednie wątki
 * z external specjalistami. Lista scoped do zalogowanego właściciela.
 */
class SpecialistThreadResource extends Resource
{
    protected static ?string $model = OwnerSpecialistThread::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?int $navigationSort = 40;

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.owner_horses');
    }

    public static function getNavigationLabel(): string
    {
        return __('owner/specialist_thread.nav');
    }

    public static function getModelLabel(): string
    {
        return __('owner/specialist_thread.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('owner/specialist_thread.model_plural');
    }

    public static function getEloquentQuery(): Builder
    {
        $ownerId = Auth::id();

        return OwnerSpecialistThread::query()
            ->when($ownerId !== null, fn (Builder $q) => $q->forOwner((string) $ownerId))
            ->when($ownerId === null, fn (Builder $q) => $q->whereRaw('1 = 0'))
            ->with('specialist');
    }

    /**
     * Specjaliści zaproszeni przez tego właściciela — kandydaci do wątku.
     *
     * @return array<string,string>
     */
    public static function specialistOptions(): array
    {
        $ownerId = Auth::id();
        if ($ownerId === null) {
            return [];
        }

        return ExternalSpecialist::query()
            ->where('created_by_user_id', $ownerId)
            ->get(['id', 'display_name'])
            ->mapWithKeys(fn (ExternalSpecialist $s) => [$s->id => $s->display_name])
            ->all();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('specialist_id')
                ->label(__('owner/specialist_thread.form.specialist'))
                ->options(fn () => self::specialistOptions())
                ->searchable()
                ->required()
                ->helperText(__('owner/specialist_thread.form.specialist_hint')),
            Forms\Components\Select::make('horse_id')
                ->label(__('owner/specialist_thread.form.horse'))
                ->options(fn () => Horse::query()->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->placeholder(__('owner/specialist_thread.form.horse_placeholder'))
                ->helperText(__('owner/specialist_thread.form.horse_hint')),
            Forms\Components\TextInput::make('subject')
                ->label(__('owner/specialist_thread.form.subject'))
                ->required()
                ->maxLength(200),
            Forms\Components\Textarea::make('body')
                ->label(__('owner/specialist_thread.form.body'))
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
                    ->label(__('owner/specialist_thread.table.subject'))
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('specialist.display_name')
                    ->label(__('owner/specialist_thread.table.specialist'))
                    ->badge()
                    ->color(fn (OwnerSpecialistThread $r) => $r->specialist?->is_verified ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('last_message_at')
                    ->label(__('owner/specialist_thread.table.last_message'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->defaultSort('last_message_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make()->label(__('owner/specialist_thread.action.open')),
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
