<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TransportReviewResource\Pages;

use App\Filament\Admin\Resources\TransportReviewResource;
use App\Models\Central\TransportReview;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewAdminTransportReview extends ViewRecord
{
    protected static string $resource = TransportReviewResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make(__('admin/transport_reviews.view.section_review'))
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('transporter.name')
                            ->label(__('admin/transport_reviews.table.column.transporter')),
                        Infolists\Components\TextEntry::make('rating')
                            ->formatStateUsing(fn ($state) => str_repeat('★', (int) $state).str_repeat('☆', max(0, 5 - (int) $state))),
                        Infolists\Components\TextEntry::make('customer_name')
                            ->formatStateUsing(fn ($state) => TransportReview::redactCustomerName($state)),
                        Infolists\Components\TextEntry::make('customer_email_redacted'),
                        Infolists\Components\TextEntry::make('comment')->columnSpanFull(),
                        Infolists\Components\TextEntry::make('status')->badge(),
                        Infolists\Components\TextEntry::make('submitted_at')->dateTime(),
                    ]),
                Infolists\Components\Section::make(__('admin/transport_reviews.view.section_moderation'))
                    ->schema([
                        Infolists\Components\TextEntry::make('flagged_reason')->placeholder('—'),
                        Infolists\Components\TextEntry::make('flagged_by_tenant_at')->dateTime()->placeholder('—'),
                        Infolists\Components\TextEntry::make('moderation_notes')->placeholder('—'),
                        Infolists\Components\TextEntry::make('moderated_at')->dateTime()->placeholder('—'),
                    ]),
            ]);
    }
}
