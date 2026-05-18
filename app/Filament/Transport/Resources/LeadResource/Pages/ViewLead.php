<?php

declare(strict_types=1);

namespace App\Filament\Transport\Resources\LeadResource\Pages;

use App\Filament\Transport\Resources\LeadResource;
use App\Models\Central\TransportLead;
use App\Models\Central\TransportLeadDispatch;
use App\Models\Central\TransportLeadResponse;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/**
 * Widok pojedynczego leada w inboxie transportera. Otwarcie strony flipuje
 * view_status z 'unseen' → 'seen' (badge w sidebarze maleje). Akcja
 * "Odpowiedz ofertą" tworzy TransportLeadResponse + redirect na QuoteResource
 * create z pre-fill.
 */
class ViewLead extends ViewRecord
{
    protected static string $resource = LeadResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->markAsSeen();
    }

    public function infolist(Infolist $infolist): Infolist
    {
        /** @var TransportLead $lead */
        return $infolist
            ->schema([
                Section::make(__('transport/lead.section.customer'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('originator_name')->label(__('transport/lead.label.name')),
                        TextEntry::make('originator_email')->label(__('transport/lead.label.email')),
                        TextEntry::make('originator_phone')->label(__('transport/lead.label.phone')),
                    ]),

                Section::make(__('transport/lead.section.route'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('pickup_address')->label(__('transport/lead.label.from')),
                        TextEntry::make('dropoff_address')->label(__('transport/lead.label.to')),
                        TextEntry::make('pickup_voivodeship')->label(__('transport/lead.label.pickup_voivodeship')),
                        TextEntry::make('dropoff_voivodeship')->label(__('transport/lead.label.dropoff_voivodeship')),
                        TextEntry::make('preferred_date')->label(__('transport/lead.label.preferred_date'))->date(),
                        TextEntry::make('preferred_time')->label(__('transport/lead.label.preferred_time')),
                    ]),

                Section::make(__('transport/lead.section.cargo'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('horse_count')->label(__('transport/lead.label.horse_count')),
                        TextEntry::make('flexible_date')
                            ->label(__('transport/lead.label.flexible_date'))
                            ->formatStateUsing(fn ($state) => $state ? __('common.yes') : __('common.no')),
                        TextEntry::make('notes')->label(__('transport/lead.label.notes'))->columnSpanFull(),
                    ]),

                Section::make(__('transport/lead.section.lifecycle'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('status')->label(__('transport/lead.label.status'))->badge(),
                        TextEntry::make('mode')->label(__('transport/lead.label.mode'))->badge(),
                        TextEntry::make('expires_at')->label(__('transport/lead.label.expires_at'))->dateTime(),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('respond')
                ->label(__('transport/lead.action.respond'))
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->visible(fn () => $this->canRespond())
                ->action(fn () => $this->respondToLead()),
        ];
    }

    private function canRespond(): bool
    {
        /** @var TransportLead $lead */
        $lead = $this->record;
        if (! in_array($lead->status, ['open', 'quoted'], true)) {
            return false;
        }
        if ($lead->expires_at && $lead->expires_at->isPast()) {
            return false;
        }

        $tenant = app(TenantManager::class)->current();
        if (! $tenant) {
            return false;
        }

        // Już odpowiadaliśmy?
        return ! TransportLeadResponse::query()
            ->where('lead_id', $lead->id)
            ->where('transporter_tenant_id', $tenant->id)
            ->exists();
    }

    private function respondToLead(): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        /** @var TransportLead $lead */
        $lead = $this->record;
        $tenant = app(TenantManager::class)->tenantOrFail();

        // Wstępna pusta odpowiedź — flagujemy intencję; rzeczywiste pricing
        // wpadnie po wystawieniu Quote (akcja "save as quote" z calculatora
        // lub bezpośrednie utworzenie offerty).
        // Status 'pending' — gdy Quote zostanie wysłane i klient zaakceptuje,
        // QuoteAcceptanceService (krok 6) flipnie odpowiedni response.
        TransportLeadResponse::query()->firstOrCreate(
            [
                'lead_id' => $lead->id,
                'transporter_tenant_id' => $tenant->id,
            ],
            [
                'price_net' => 0,
                'price_gross' => 0,
                'currency' => 'PLN',
                'proposed_date' => $lead->preferred_date,
                'status' => 'pending',
            ],
        );

        // Pre-fill dla QuoteResource::create przez session — wzorzec
        // identyczny jak Calculator → Quote.
        session()->put('transport.calc.pending', [
            'customer_name' => $lead->originator_name,
            'customer_email' => $lead->originator_email,
            'customer_phone' => $lead->originator_phone,
            'pickup_address' => $lead->pickup_address,
            'pickup_lat' => $lead->pickup_lat,
            'pickup_lng' => $lead->pickup_lng,
            'dropoff_address' => $lead->dropoff_address,
            'dropoff_lat' => $lead->dropoff_lat,
            'dropoff_lng' => $lead->dropoff_lng,
            'preferred_date' => $lead->preferred_date->toDateString(),
            'preferred_time' => $lead->preferred_time,
            'lead_id' => $lead->id,        // backlink — QuoteResource zapisuje
            'currency' => 'PLN',
            'status' => 'draft',
        ]);

        app(TenantAuditLogger::class)->record(
            'transport_lead.respond_intent',
            'TransportLead',
            (string) $lead->id,
        );

        Notification::make()
            ->success()
            ->title(__('transport/lead.notify.respond_started'))
            ->body(__('transport/lead.notify.respond_started_body'))
            ->send();

        return redirect()->to(\App\Filament\Transport\Resources\QuoteResource::getUrl('create'));
    }

    private function markAsSeen(): void
    {
        $tenant = app(TenantManager::class)->current();
        if (! $tenant) {
            return;
        }

        TransportLeadDispatch::query()
            ->where('lead_id', $this->record->id)
            ->where('transporter_tenant_id', $tenant->id)
            ->where('view_status', 'unseen')
            ->update([
                'view_status' => 'seen',
                'seen_at' => now(),
            ]);
    }
}
