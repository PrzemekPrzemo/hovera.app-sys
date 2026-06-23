<?php

declare(strict_types=1);

namespace App\Filament\Specialist\Resources\OwnerThreadResource\Pages;

use App\Filament\Specialist\Resources\OwnerThreadResource;
use App\Models\Central\OwnerSpecialistMessage;
use App\Services\Specialist\OwnerSpecialistMessagingService;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewOwnerThread extends ViewRecord
{
    protected static string $resource = OwnerThreadResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        app(OwnerSpecialistMessagingService::class)->markRead($this->getRecord(), OwnerSpecialistMessage::SENDER_SPECIALIST);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('reply')
                ->label(__('specialist/owner_inbox.action.reply'))
                ->icon('heroicon-o-paper-airplane')
                ->form([
                    Forms\Components\Textarea::make('body')
                        ->label(__('specialist/owner_inbox.form.body'))
                        ->required()
                        ->rows(4),
                ])
                ->action(function (array $data): void {
                    app(OwnerSpecialistMessagingService::class)->reply(
                        thread: $this->getRecord(),
                        senderType: OwnerSpecialistMessage::SENDER_SPECIALIST,
                        senderId: (string) Auth::guard('specialist')->id(),
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
                        TextEntry::make('subject')->label(__('specialist/owner_inbox.table.subject'))->weight('bold'),
                        TextEntry::make('owner.name')->label(__('specialist/owner_inbox.table.owner')),
                    ])
                    ->columns(2),
                RepeatableEntry::make('messages')
                    ->label(__('specialist/owner_inbox.messages'))
                    ->schema([
                        TextEntry::make('sender_type')
                            ->label('')
                            ->badge()
                            ->color(fn (string $state) => $state === OwnerSpecialistMessage::SENDER_SPECIALIST ? 'success' : 'primary')
                            ->formatStateUsing(fn (string $state) => $state === OwnerSpecialistMessage::SENDER_SPECIALIST
                                ? __('specialist/owner_inbox.sender.you')
                                : __('specialist/owner_inbox.sender.owner')),
                        TextEntry::make('body')->label('')->prose(),
                        TextEntry::make('created_at')->label('')->dateTime('d.m.Y H:i')->color('gray')->size('sm'),
                    ])
                    ->columns(1),
            ]);
    }
}
