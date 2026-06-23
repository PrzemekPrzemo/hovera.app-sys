<?php

declare(strict_types=1);

namespace App\Filament\Owner\Resources\SpecialistThreadResource\Pages;

use App\Filament\Owner\Resources\SpecialistThreadResource;
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

class ViewSpecialistThread extends ViewRecord
{
    protected static string $resource = SpecialistThreadResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        app(OwnerSpecialistMessagingService::class)->markRead($this->getRecord(), OwnerSpecialistMessage::SENDER_OWNER);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('reply')
                ->label(__('owner/specialist_thread.action.reply'))
                ->icon('heroicon-o-paper-airplane')
                ->form([
                    Forms\Components\Textarea::make('body')
                        ->label(__('owner/specialist_thread.form.body'))
                        ->required()
                        ->rows(4),
                ])
                ->action(function (array $data): void {
                    app(OwnerSpecialistMessagingService::class)->reply(
                        thread: $this->getRecord(),
                        senderType: OwnerSpecialistMessage::SENDER_OWNER,
                        senderId: (string) Auth::id(),
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
                        TextEntry::make('subject')->label(__('owner/specialist_thread.table.subject'))->weight('bold'),
                        TextEntry::make('specialist.display_name')->label(__('owner/specialist_thread.table.specialist')),
                    ])
                    ->columns(2),
                RepeatableEntry::make('messages')
                    ->label(__('owner/specialist_thread.messages'))
                    ->schema([
                        TextEntry::make('sender_type')
                            ->label('')
                            ->badge()
                            ->color(fn (string $state) => $state === OwnerSpecialistMessage::SENDER_SPECIALIST ? 'success' : 'primary')
                            ->formatStateUsing(fn (string $state) => $state === OwnerSpecialistMessage::SENDER_SPECIALIST
                                ? __('owner/specialist_thread.sender.specialist')
                                : __('owner/specialist_thread.sender.you')),
                        TextEntry::make('body')->label('')->prose(),
                        TextEntry::make('created_at')->label('')->dateTime('d.m.Y H:i')->color('gray')->size('sm'),
                    ])
                    ->columns(1),
            ]);
    }
}
