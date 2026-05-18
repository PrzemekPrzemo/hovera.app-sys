<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * Recenzja klienta marketplace'u — patrz docs/TRANSPORT.md §12 + §14.
 *
 * Bramowane przez „real deal" — invite tworzony tylko gdy
 * TransportLeadResponse.status = accepted i minęło ≥14 dni od preferred_date.
 * Klient submituje przez magic link (bez logowania), opinia ląduje od razu
 * w status=published (Hovera = pośrednik, nie moderujemy preventywnie).
 *
 * Transporter może zgłosić review do moderacji → status=flagged → ukryte
 * publicznie aż staff zdecyduje publish/hide.
 */
class TransportReview extends Model
{
    use HasUlids;

    protected $connection = 'central';

    protected $table = 'transport_reviews';

    protected $fillable = [
        'transporter_tenant_id', 'lead_id', 'response_id', 'quote_id',
        'invite_token_hash', 'invite_sent_at', 'invite_expires_at',
        'rating', 'comment', 'customer_name',
        'customer_email_hash', 'customer_email_redacted',
        'status', 'transporter_response', 'transporter_responded_at',
        'flagged_reason', 'flagged_by_tenant_at',
        'moderated_by_user_id', 'moderated_at', 'moderation_notes',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'invite_sent_at' => 'datetime',
            'invite_expires_at' => 'datetime',
            'transporter_responded_at' => 'datetime',
            'flagged_by_tenant_at' => 'datetime',
            'moderated_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function transporter(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'transporter_tenant_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(TransportLead::class, 'lead_id');
    }

    public function response(): BelongsTo
    {
        return $this->belongsTo(TransportLeadResponse::class, 'response_id');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published')->whereNotNull('submitted_at');
    }

    /**
     * Aggregate dla `/t/{slug}` — count, average, histogram.
     * Cache 10 min: tysiąc wyświetleń profilu nie powinno przekładać się
     * na tysiąc COUNT'ów. Cache busted automatycznie przy nowym submit
     * (TransportReviewController::submit → forgetAggregateCache).
     *
     * @return array{count:int, average:float, distribution:array<int,int>}
     */
    public static function aggregateFor(Tenant $transporter): array
    {
        return Cache::remember(
            self::aggregateCacheKey($transporter->id),
            now()->addMinutes(10),
            function () use ($transporter): array {
                $rows = self::query()
                    ->where('transporter_tenant_id', $transporter->id)
                    ->where('status', 'published')
                    ->whereNotNull('rating')
                    ->selectRaw('rating, COUNT(*) as c')
                    ->groupBy('rating')
                    ->pluck('c', 'rating')
                    ->all();

                $distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
                $count = 0;
                $sum = 0;
                foreach ($rows as $rating => $c) {
                    $r = (int) $rating;
                    if ($r >= 1 && $r <= 5) {
                        $distribution[$r] = (int) $c;
                        $count += (int) $c;
                        $sum += $r * (int) $c;
                    }
                }

                return [
                    'count' => $count,
                    'average' => $count > 0 ? round($sum / $count, 2) : 0.0,
                    'distribution' => $distribution,
                ];
            },
        );
    }

    public static function forgetAggregateCache(string $transporterTenantId): void
    {
        Cache::forget(self::aggregateCacheKey($transporterTenantId));
    }

    private static function aggregateCacheKey(string $transporterTenantId): string
    {
        return "transport_review_aggregate:{$transporterTenantId}";
    }

    /**
     * Redaguje email do publicznego widoku: jan@example.com → "j***@example.com".
     * Stałe minimum 1 znak przed *** + całe domain — wystarczy żeby klient
     * rozpoznał własny zostawiony review, a nie ujawnić tożsamości innym.
     */
    public static function redactEmail(string $email): string
    {
        $email = trim($email);
        if (! str_contains($email, '@')) {
            return '***';
        }
        [$local, $domain] = explode('@', $email, 2);
        $first = mb_substr($local, 0, 1);

        return $first.'***@'.$domain;
    }

    /**
     * Anonimizuje nazwisko klienta dla publicznego widoku.
     *   "Jan Kowalski" → "Jan K."
     *   "Anna"         → "Anna"
     *   ""             → __('public/transport_review.anonymous_customer')
     */
    public static function redactCustomerName(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return (string) __('public/transport_review.anonymous_customer');
        }

        $parts = preg_split('/\s+/', $name) ?: [];
        if (count($parts) === 1) {
            return $parts[0];
        }

        $first = array_shift($parts);
        $lastInitial = mb_strtoupper(mb_substr((string) end($parts), 0, 1));

        return $first.' '.$lastInitial.'.';
    }
}
