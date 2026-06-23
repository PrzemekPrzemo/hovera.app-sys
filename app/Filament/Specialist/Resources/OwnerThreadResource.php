<?php

declare(strict_types=1);

namespace App\Filament\Specialist\Resources;

use App\Filament\Specialist\Resources\OwnerThreadResource\Pages;
use App\Models\Central\OwnerSpecialistThread;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Channel D w panelu specjalisty (PR O5 epic 3) — wątki od właścicieli koni
 * (osobno od wątków stajni). Scoped do zalogowanego specjalisty.
 */
class OwnerThreadResource extends Resource
{
    protected static ?string $model = OwnerSpecialistThread::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('specialist/owner_inbox.nav');
    }

    public static function getModelLabel(): string
    {
        return __('specialist/owner_inbox.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('specialist/owner_inbox.model_plural');
    }

    public static function getEloquentQuery(): Builder
    {
        $specialistId = Auth::guard('specialist')->id();

        return OwnerSpecialistThread::query()
            ->when($specialistId !== null, fn (Builder $q) => $q->forSpecialist((string) $specialistId))
            ->when($specialistId === null, fn (Builder $q) => $q->whereRaw('1 = 0'))
            ->with('owner');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('subject')
                    ->label(__('specialist/owner_inbox.table.subject'))
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('owner.name')
                    ->label(__('specialist/owner_inbox.table.owner'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('last_message_at')
                    ->label(__('specialist/owner_inbox.table.last_message'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->defaultSort('last_message_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make()->label(__('specialist/owner_inbox.action.open')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOwnerThreads::route('/'),
            'view' => Pages\ViewOwnerThread::route('/{record}'),
        ];
    }
}
