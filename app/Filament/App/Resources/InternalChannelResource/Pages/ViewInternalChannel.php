<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\InternalChannelResource\Pages;

use App\Filament\App\Resources\InternalChannelResource;
use App\Models\Central\User;
use App\Services\Internal\InternalChannelService;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewInternalChannel extends ViewRecord
{
    protected static string $resource = InternalChannelResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        app(InternalChannelService::class)->markChannelRead($this->getRecord(), (string) Auth::id());
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('post')
                ->label(__('app/internal_channel.action.post'))
                ->icon('heroicon-o-paper-airplane')
                ->form([
                    Forms\Components\Textarea::make('body')
                        ->label(__('app/internal_channel.form.message'))
                        ->helperText(__('app/internal_channel.form.message_hint'))
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data): void {
                    app(InternalChannelService::class)->postMessage(
                        channel: $this->getRecord(),
                        authorUserId: (string) Auth::id(),
                        body: (string) $data['body'],
                    );

                    $this->refreshFormData(['messages']);
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->getRecord())
            ->schema([
                Section::make()
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('app/internal_channel.table.name'))
                            ->formatStateUsing(fn (string $state) => '#'.$state)
                            ->weight('bold'),
                        TextEntry::make('description')->label(__('app/internal_channel.table.description'))->placeholder('—'),
                    ])
                    ->columns(2),
                RepeatableEntry::make('messages')
                    ->label(__('app/internal_channel.messages'))
                    ->schema([
                        TextEntry::make('author_user_id')
                            ->label('')
                            ->badge()
                            ->color('primary')
                            ->formatStateUsing(fn (string $state) => User::query()->whereKey($state)->value('name') ?? $state),
                        TextEntry::make('body')->label('')->prose(),
                        TextEntry::make('created_at')->label('')->dateTime('d.m.Y H:i')->color('gray')->size('sm'),
                    ])
                    ->columns(1),
            ]);
    }
}
