<?php

declare(strict_types=1);

namespace App\Filament\Specialist\Resources\ThreadResource\Pages;

use App\Filament\Specialist\Resources\ThreadResource;
use App\Models\Central\SpecialistMessage;
use App\Services\Specialist\SpecialistMessagingService;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewThread extends ViewRecord
{
    protected static string $resource = ThreadResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Czytelnik = specjalista → oznacz wiadomości stajni jako przeczytane.
        app(SpecialistMessagingService::class)->markRead($this->getRecord(), SpecialistMessage::SENDER_SPECIALIST);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('reply')
                ->label(__('specialist/inbox.action.reply'))
                ->icon('heroicon-o-paper-airplane')
                ->form([
                    Forms\Components\Textarea::make('body')
                        ->label(__('specialist/inbox.form.body'))
                        ->required()
                        ->rows(4),
                ])
                ->action(function (array $data): void {
                    app(SpecialistMessagingService::class)->reply(
                        thread: $this->getRecord(),
                        senderType: SpecialistMessage::SENDER_SPECIALIST,
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
                        TextEntry::make('subject')->label(__('specialist/inbox.table.subject'))->weight('bold'),
                        TextEntry::make('tenant.name')->label(__('specialist/inbox.table.stable')),
                    ])
                    ->columns(2),
                RepeatableEntry::make('messages')
                    ->label(__('specialist/inbox.messages'))
                    ->schema([
                        TextEntry::make('sender_type')
                            ->label('')
                            ->badge()
                            ->color(fn (string $state) => $state === SpecialistMessage::SENDER_SPECIALIST ? 'success' : 'primary')
                            ->formatStateUsing(fn (string $state) => $state === SpecialistMessage::SENDER_SPECIALIST
                                ? __('specialist/inbox.sender.you')
                                : __('specialist/inbox.sender.stable')),
                        TextEntry::make('body')->label('')->prose(),
                        TextEntry::make('created_at')->label('')->dateTime('d.m.Y H:i')->color('gray')->size('sm'),
                    ])
                    ->columns(1),
            ]);
    }
}
