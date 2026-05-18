<?php

declare(strict_types=1);

namespace App\Domain\Transport\Reviews;

use App\Domain\Transport\Notifications\TransportReviewInviteNotification;
use App\Models\Central\Tenant;
use App\Models\Central\TransportReview;
use App\Tenancy\TenantManager;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Str;

/**
 * Generator zaproszeń do recenzji. Cron entrypoint:
 *   $service->dispatchPendingInvites()
 *
 * Algorytm:
 *  1. Znajdź wszystkie `transport_lead_responses.status = accepted`
 *     dla których lead.preferred_date jest sprzed ≥14 dni.
 *  2. Pomiń te, dla których istnieje już TransportReview (po unique
 *     (lead_id, response_id)).
 *  3. Dla każdej: utwórz `TransportReview` w status=invited, wygeneruj
 *     token (Str::random 48) + hash sha256, wyślij invite mail z magic
 *     linkiem przez mailer('transport').
 *
 * Idempotent — re-run nie wyśle drugiego invite (unique key).
 * Bez SMTP brutalu — błędy notyfikacji łapane per-row, nie blokują batch'a.
 *
 * Patrz docs/TRANSPORT.md §12 + §14.
 */
class TransportReviewInviteService
{
    /**
     * Domyślnie 14 dni — zgodnie ze specem. Konfigurowalne na wypadek
     * gdyby UX po pierwszym tygodniu chciał skrócić/wydłużyć.
     */
    public const DELAY_DAYS = 14;

    public const INVITE_TTL_DAYS = 30;

    public function __construct(
        private readonly TenantManager $tenants,
    ) {}

    /**
     * @return int  liczba wysłanych zaproszeń (audytowe)
     */
    public function dispatchPendingInvites(?CarbonInterface $now = null): int
    {
        $now = $now ?? now();
        $cutoff = $now->copy()->subDays(self::DELAY_DAYS)->endOfDay();

        // JOIN przez query builder — Eloquent musiałby przeładować obie
        // collections w PHP, niepotrzebnie. Tu w jednym query mamy parę
        // (response_id, lead_id, transporter_tenant_id, originator_email,
        // originator_name, preferred_date) gotową do dispatchu.
        $candidates = DB::connection('central')
            ->table('transport_lead_responses as r')
            ->join('transport_leads as l', 'l.id', '=', 'r.lead_id')
            ->leftJoin('transport_reviews as v', function ($j) {
                $j->on('v.lead_id', '=', 'r.lead_id')
                    ->on('v.response_id', '=', 'r.id');
            })
            ->where('r.status', 'accepted')
            ->where('l.preferred_date', '<=', $cutoff->toDateString())
            ->whereNull('v.id')
            ->whereNotNull('l.originator_email')
            ->select([
                'r.id as response_id',
                'r.lead_id',
                'r.transporter_tenant_id',
                'r.quote_id',
                'l.originator_email',
                'l.originator_name',
                'l.preferred_date',
            ])
            ->get();

        $sent = 0;
        foreach ($candidates as $row) {
            try {
                $review = $this->createInviteRow($row, $now);
                if ($review === null) {
                    continue;
                }
                $this->sendInvite($review, (string) $row->originator_email);
                $sent++;
            } catch (\Throwable $e) {
                // Pojedynczy invite nie ma blokować całego batch'a — innym
                // klientom też należy się okazja. Loguj i jedź dalej.
                report($e);
                Log::warning('transport_review.invite.failed', [
                    'lead_id' => $row->lead_id ?? null,
                    'response_id' => $row->response_id ?? null,
                    'reason' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    /**
     * Tworzy wiersz TransportReview + zwraca instancję z DOklejonym
     * raw tokenem ($review->raw_token) potrzebnym do magic linka.
     * Raw token NIE jest persisted — tylko hash sha256.
     */
    private function createInviteRow(object $row, CarbonInterface $now): ?TransportReview
    {
        $rawToken = Str::random(48);
        $email = (string) $row->originator_email;

        // Unique (lead_id, response_id) jest naszą ochroną przed race —
        // jeśli inny worker właśnie wstawił, łapiemy QueryException i
        // pomijamy ten row bez wysyłki drugiej kopii.
        try {
            /** @var TransportReview $review */
            $review = TransportReview::create([
                'transporter_tenant_id' => (string) $row->transporter_tenant_id,
                'lead_id' => (string) $row->lead_id,
                'response_id' => (string) $row->response_id,
                'quote_id' => $row->quote_id ? (string) $row->quote_id : null,
                'invite_token_hash' => hash('sha256', $rawToken),
                'invite_sent_at' => $now,
                'invite_expires_at' => $now->copy()->addDays(self::INVITE_TTL_DAYS),
                'customer_name' => $row->originator_name ? mb_substr((string) $row->originator_name, 0, 120) : null,
                'customer_email_hash' => hash('sha256', mb_strtolower($email)),
                'customer_email_redacted' => TransportReview::redactEmail($email),
                'status' => 'invited',
            ]);
        } catch (QueryException $e) {
            // Race: inny worker dodał ten sam (lead, response). OK — pomiń.
            return null;
        }

        $review->setAttribute('raw_token', $rawToken);

        return $review;
    }

    private function sendInvite(TransportReview $review, string $email): void
    {
        $tenant = Tenant::query()->find($review->transporter_tenant_id);
        if (! $tenant) {
            return;
        }

        $link = route('public.transport.review.show', [
            'token' => $review->getAttribute('raw_token'),
        ]);

        NotificationFacade::route('mail', $email)
            ->notify(new TransportReviewInviteNotification(
                transporterName: (string) $tenant->name,
                transporterSlug: (string) $tenant->slug,
                customerName: $review->customer_name,
                reviewLink: $link,
            ));
    }
}
