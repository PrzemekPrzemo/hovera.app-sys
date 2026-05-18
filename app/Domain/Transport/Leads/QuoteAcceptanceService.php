<?php

declare(strict_types=1);

namespace App\Domain\Transport\Leads;

use App\Domain\Transport\Notifications\LeadClosedNotification;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\TransportLead;
use App\Models\Central\TransportLeadResponse;
use App\Models\Tenant\Quote;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Domyka lead w marketplace gdy klient akceptuje którąś ofertę. Patrz
 * docs/TRANSPORT.md §5.3.
 *
 * Wywoływane z QuoteAcceptanceController::accept (publiczny landing
 * potwierdzeniem klienta).
 *
 * Logika:
 *   1. Znajdujemy TransportLeadResponse dla (lead, tenant_obecnej_quote)
 *      → flip status na 'accepted'
 *   2. Pozostałe responses dla tego samego lead_id → 'rejected'
 *   3. TransportLead.status = 'accepted', accepted_response_id = wybrana
 *   4. Notyfikacja LeadClosedNotification do każdego z pozostałych
 *      transporterów ("inny dostawca został wybrany, dzięki za udział")
 *
 * Cross-tenant: Quote leży w bazie tenant'a, lead+responses w central.
 * Mamy `quote.lead_id` jako backlink (ustawione przez CreateQuote z session).
 */
class QuoteAcceptanceService
{
    /**
     * @return array{response_id:?string, rejected_count:int, notified_count:int}
     */
    public function onQuoteAccepted(Quote $quote, Tenant $transporterTenant): array
    {
        if (! $quote->lead_id) {
            // Quote nie powstała z lead'a (np. direct calculator → save as quote).
            return ['response_id' => null, 'rejected_count' => 0, 'notified_count' => 0];
        }

        $lead = TransportLead::query()->where('id', $quote->lead_id)->first();
        if (! $lead) {
            return ['response_id' => null, 'rejected_count' => 0, 'notified_count' => 0];
        }

        return DB::connection('central')->transaction(function () use ($lead, $transporterTenant, $quote) {
            // 1. Wybrana oferta — wpis dla tego transportera może już istnieć
            //    (z LeadResource::respondToLead) lub nie. Jeśli nie istnieje,
            //    tworzymy minimalny rekord.
            $accepted = TransportLeadResponse::query()->firstOrCreate(
                [
                    'lead_id' => $lead->id,
                    'transporter_tenant_id' => $transporterTenant->id,
                ],
                [
                    'price_net' => $quote->net_total,
                    'price_gross' => $quote->gross_total,
                    'currency' => $quote->currency,
                    'distance_km' => $quote->distance_km,
                    'proposed_date' => $quote->preferred_date,
                    'quote_id' => $quote->id,
                ],
            );

            $accepted->forceFill([
                'status' => 'accepted',
                'responded_at' => Carbon::now(),
                'quote_id' => $quote->id,
            ])->save();

            // 2. Pozostałe responses → rejected
            $others = TransportLeadResponse::query()
                ->where('lead_id', $lead->id)
                ->where('id', '!=', $accepted->id)
                ->whereIn('status', ['pending', 'withdrawn'])
                ->get();

            foreach ($others as $r) {
                $r->forceFill([
                    'status' => 'rejected',
                    'responded_at' => $r->responded_at ?? Carbon::now(),
                ])->save();
            }

            // 3. Lead → accepted
            $lead->forceFill([
                'status' => 'accepted',
                'accepted_response_id' => $accepted->id,
            ])->save();

            // 4. Notyfikacje "lead zamknięty" do pozostałych transporterów
            $notified = 0;
            foreach ($others as $rejected) {
                if ($this->notifyRejected($lead, $rejected->transporter_tenant_id)) {
                    $notified++;
                }
            }

            return [
                'response_id' => $accepted->id,
                'rejected_count' => $others->count(),
                'notified_count' => $notified,
            ];
        });
    }

    private function notifyRejected(TransportLead $lead, string $transporterTenantId): bool
    {
        $email = $this->resolveOwnerEmail($transporterTenantId);
        if ($email === null) {
            return false;
        }

        try {
            NotificationFacade::route('mail', $email)
                ->notify(new LeadClosedNotification($lead));
        } catch (\Throwable $e) {
            report($e);

            return false;
        }

        return true;
    }

    private function resolveOwnerEmail(string $tenantId): ?string
    {
        $row = DB::connection('central')
            ->table('tenant_memberships')
            ->join('users', 'tenant_memberships.user_id', '=', 'users.id')
            ->where('tenant_memberships.tenant_id', $tenantId)
            ->where('tenant_memberships.role', 'owner')
            ->whereNull('tenant_memberships.revoked_at')
            ->select('users.email')
            ->first();

        return $row?->email;
    }
}
