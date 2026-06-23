<?php

declare(strict_types=1);

namespace App\Filament\Specialist\Resources;

use App\Filament\Specialist\Resources\ThreadResource\Pages;
use App\Models\Central\SpecialistThread;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Skrzynka odbiorcza specjalisty (PR O5 epic 1.5) — wątki ze wszystkimi
 * stajniami, które go zaprosiły. Specjalista odpowiada; nowe wątki zakłada
 * stajnia (brak Create).
 */
class ThreadResource extends Resource
{
    protected static ?string $model = SpecialistThread::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox';

    public static function getNavigationLabel(): string
    {
        return __('specialist/inbox.nav');
    }

    public static function getModelLabel(): string
    {
        return __('specialist/inbox.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('specialist/inbox.model_plural');
    }

    public static function getEloquentQuery(): Builder
    {
        $specialistId = Auth::guard('specialist')->id();

        return SpecialistThread::query()
            ->when($specialistId !== null, fn (Builder $q) => $q->forSpecialist((string) $specialistId))
            ->when($specialistId === null, fn (Builder $q) => $q->whereRaw('1 = 0'))
            ->with('tenant');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('subject')
                    ->label(__('specialist/inbox.table.subject'))
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label(__('specialist/inbox.table.stable'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('last_message_at')
                    ->label(__('specialist/inbox.table.last_message'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->defaultSort('last_message_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make()->label(__('specialist/inbox.action.open')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListThreads::route('/'),
            'view' => Pages\ViewThread::route('/{record}'),
        ];
    }
}
