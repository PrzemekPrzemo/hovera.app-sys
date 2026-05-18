<?php

declare(strict_types=1);

namespace App\Filament\Transport\Resources\TransportReviewResource\Pages;

use App\Filament\Transport\Resources\TransportReviewResource;
use App\Models\Central\TransportReview;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewTransportReview extends ViewRecord
{
    protected static string $resource = TransportReviewResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make(__('transport/reviews.view.section_review'))
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('rating')
                            ->label(__('transport/reviews.table.column.rating'))
                            ->formatStateUsing(fn ($state) => str_repeat('★', (int) $state).str_repeat('☆', max(0, 5 - (int) $state))),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn ($state) => __('transport/reviews.status.'.$state)),
                        Infolists\Components\TextEntry::make('customer_name')
                            ->label(__('transport/reviews.table.column.customer'))
                            ->formatStateUsing(fn ($state) => TransportReview::redactCustomerName($state)),
                        Infolists\Components\TextEntry::make('submitted_at')
                            ->label(__('transport/reviews.table.column.submitted_at'))
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('comment')
                            ->label(__('transport/reviews.table.column.comment'))
                            ->columnSpanFull()
                            ->placeholder('—'),
                    ]),
                Infolists\Components\Section::make(__('transport/reviews.view.section_response'))
                    ->schema([
                        Infolists\Components\TextEntry::make('transporter_response')
                            ->label(__('transport/reviews.form.response_label'))
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('transporter_responded_at')
                            ->dateTime()
                            ->placeholder('—'),
                    ]),
                Infolists\Components\Section::make(__('transport/reviews.view.section_moderation'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Infolists\Components\TextEntry::make('flagged_reason')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('flagged_by_tenant_at')
                            ->dateTime()
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('moderation_notes')
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
