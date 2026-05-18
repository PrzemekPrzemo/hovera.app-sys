<?php

declare(strict_types=1);

namespace App\Domain\Transport\Leads;

use App\Domain\Transport\Notifications\LeadReceivedNotification;
use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\TransportLead;
use App\Models\Central\TransportLeadDispatch;
use App\Models\Central\TransportServiceArea;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Str;

/**
 * Roześle TransportLead do właściwych transporterów. Patrz docs/TRANSPORT.md §5.
 *
 * Tryby:
 *   DIRECT    — lead.targeted_transporter_ids (1-3 ulids), wysyłka tylko do nich.
 *               Każdy może odpowiedzieć ofertą; zamawiający wybiera 1.
 *   BROADCAST — szukamy verified transporterów spełniających:
 *               • aktywna subskrypcja (status active/trialing/past_due)
 *               • verification_status = verified
 *               • service_area zawiera pickup_voivodeship LUB
 *                 dropoff_voivodeship LUB adjacent (z config)
 *               Wszyscy znalezieni dostają lead; nadawca wybiera.
 *
 * Idempotent: dla każdej pary (lead, transporter) tworzymy
 * transport_lead_dispatch (unique constraint zapobiega duplikatom).
 *
 * Wywoływane bezpośrednio z TransportInquiryController.submit() (sync —
 * w przyszłości można queue'ować gdy lista transporterów rośnie >50).
 */
class LeadDispatcher
{
    /**
     * @return array{notified: int, transporter_ids: list<string>}
     */
    public function dispatch(TransportLead $lead): array
    {
        $transporters = $this->resolveTargets($lead);

        if ($transporters->isEmpty()) {
            return ['notified' => 0, 'transporter_ids' => []];
        }

        $notifiedIds = [];

        foreach ($transporters as $transporter) {
            $this->recordDispatch($lead, $transporter);
            $this->notifyOwner($lead, $transporter, $notifiedIds);
        }

        return [
            'notified' => count($notifiedIds),
            'transporter_ids' => $notifiedIds,
        ];
    }

    /** @return Collection<int, Tenant> */
    private function resolveTargets(TransportLead $lead): Collection
    {
        if ($lead->mode === 'direct' && ! empty($lead->targeted_transporter_ids)) {
            return Tenant::query()
                ->whereIn('id', $lead->targeted_transporter_ids)
                ->where('type', TenantType::Transporter->value)
                ->where('verification_status', VerificationStatus::Verified->value)
                ->whereIn('status', ['active', 'trialing', 'past_due'])
                ->get();
        }

        // BROADCAST: voivodeships do dopasowania = pickup + dropoff + adjacent
        $voivodeships = $this->expandWithAdjacency([
            $lead->pickup_voivodeship,
            $lead->dropoff_voivodeship,
        ]);

        if (empty($voivodeships)) {
            return collect();
        }

        $transporterIds = TransportServiceArea::query()
            ->whereIn('voivodeship', $voivodeships)
            ->pluck('transporter_tenant_id')
            ->unique()
            ->values()
            ->all();

        if (empty($transporterIds)) {
            return collect();
        }

        return Tenant::query()
            ->whereIn('id', $transporterIds)
            ->where('type', TenantType::Transporter->value)
            ->where('verification_status', VerificationStatus::Verified->value)
            ->whereIn('status', ['active', 'trialing', 'past_due'])
            ->get();
    }

    /**
     * @param  list<?string>  $voivodeships
     * @return list<string>
     */
    private function expandWithAdjacency(array $voivodeships): array
    {
        $adjacency = (array) config('transport.voivodeship_adjacency', []);
        $expanded = [];

        foreach ($voivodeships as $v) {
            if (! $v) {
                continue;
            }
            $expanded[] = $v;
            foreach ((array) ($adjacency[$v] ?? []) as $neighbour) {
                $expanded[] = $neighbour;
            }
        }

        return array_values(array_unique($expanded));
    }

    private function recordDispatch(TransportLead $lead, Tenant $transporter): TransportLeadDispatch
    {
        // updateOrCreate na unique (lead_id, transporter_tenant_id) — gdyby
        // dispatcher był wywołany dwa razy (retry po awarii notyfikacji),
        // nie duplikujemy rekordów.
        return TransportLeadDispatch::query()->updateOrCreate(
            [
                'lead_id' => $lead->id,
                'transporter_tenant_id' => $transporter->id,
            ],
            [
                'notified_email' => false,
                'notified_at' => null,
                'view_status' => 'unseen',
            ],
        );
    }

    /**
     * @param  array<int, string>  $notifiedIds  reference do listy ID transporterów
     *                            którym wysłano email (do raportu)
     */
    private function notifyOwner(TransportLead $lead, Tenant $transporter, array &$notifiedIds): void
    {
        $email = $this->resolveOwnerEmail($transporter);
        if ($email === null) {
            return;
        }

        try {
            NotificationFacade::route('mail', $email)
                ->notify(new LeadReceivedNotification($lead, $transporter));
        } catch (\Throwable $e) {
            report($e);

            return;
        }

        TransportLeadDispatch::query()
            ->where('lead_id', $lead->id)
            ->where('transporter_tenant_id', $transporter->id)
            ->update([
                'notified_email' => true,
                'notified_at' => Carbon::now(),
            ]);

        $notifiedIds[] = $transporter->id;
    }

    private function resolveOwnerEmail(Tenant $tenant): ?string
    {
        $row = DB::connection('central')
            ->table('tenant_memberships')
            ->join('users', 'tenant_memberships.user_id', '=', 'users.id')
            ->where('tenant_memberships.tenant_id', $tenant->id)
            ->where('tenant_memberships.role', 'owner')
            ->whereNull('tenant_memberships.revoked_at')
            ->select('users.email')
            ->first();

        return $row?->email;
    }
}
