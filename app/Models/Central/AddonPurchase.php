<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Add-on purchase — jednorazowy zakup add-onu transportowego (np.
 * migrate_excel, invoice_setup, onboarding_live) przez transportera
 * tenanta. Hovera jest tu merchant of record — pieniądze wpływają na
 * konto P24 Hovery (`services.przelewy24.*`), nie na konto transportera.
 *
 * Tworzony przez master admina w panelu /admin/addon-purchases (action
 * "Wyślij link do płatności") → status=pending + p24_payment_url.
 * Webhook P24 (`webhooks.p24.addon`) flipuje status=paid i ewentualnie
 * uruchamia side-effect (np. dla extra_driver podnosi `max_drivers`).
 *
 * Patrz docs/TRANSPORT.md §13 (master admin add-on purchases).
 */
class AddonPurchase extends Model
{
    use HasUlids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $connection = 'central';

    protected $table = 'addon_purchases';

    protected $fillable = [
        'tenant_id', 'plan_addon_id',
        'addon_code', 'addon_name',
        'currency', 'amount_cents',
        'status',
        'paid_at', 'cancelled_at', 'cancellation_reason',
        'p24_session_id', 'p24_payment_url', 'p24_order_id', 'p24_paid_at',
        'side_effect_metadata', 'side_effect_applied_at',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'p24_paid_at' => 'datetime',
            'side_effect_applied_at' => 'datetime',
            'side_effect_metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function planAddon(): BelongsTo
    {
        return $this->belongsTo(PlanAddon::class);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID || $this->paid_at !== null;
    }

    public function isTerminal(): bool
    {
        return in_array(
            $this->status,
            [self::STATUS_PAID, self::STATUS_FAILED, self::STATUS_CANCELLED],
            true,
        );
    }

    public function amountFormatted(): string
    {
        return number_format($this->amount_cents / 100, 2, ',', ' ').' '.$this->currency;
    }

    /**
     * Czy purchase to sponsored placement (wyróżnienie 30/60/90 dni).
     * Patrz docs/TRANSPORT.md §16.
     */
    public function isSponsored(): bool
    {
        return str_starts_with((string) $this->addon_code, 'sponsored_');
    }

    /**
     * Wyciąga liczbę dni z `side_effect_metadata.featured_days`, z fallbackiem
     * na regex parse `addon_code` (sponsored_30d → 30). Defaultuje na 0
     * — caller (SponsoredPlacementService) musi sprawdzić isSponsored().
     */
    public function featuredDays(): int
    {
        $metadata = (array) ($this->side_effect_metadata ?? []);
        if (isset($metadata['featured_days']) && (int) $metadata['featured_days'] > 0) {
            return (int) $metadata['featured_days'];
        }

        // Fallback: parse z code'u (np. `sponsored_60d` → 60).
        if (preg_match('/^sponsored_(\d+)d$/', (string) $this->addon_code, $m)) {
            return (int) $m[1];
        }

        return 0;
    }

    public function scopeSponsored(Builder $query): Builder
    {
        return $query->where('addon_code', 'LIKE', 'sponsored_%');
    }
}
