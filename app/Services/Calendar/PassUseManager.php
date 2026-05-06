<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\Tenant\CalendarEntry;
use App\Models\Tenant\Pass;
use App\Models\Tenant\PassUse;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\DB;

/**
 * Owns the lifecycle of pass usage in response to calendar events.
 *
 *   create lesson      → applyTo()        consumes one use from the
 *                                          oldest-expiring usable pass
 *                                          for the booking's client
 *   cancel in time     → restoreFor()     restores the use; pass's
 *                                          remaining_uses goes back up
 *   cancel late        → no-op            (use stays consumed)
 *   un-cancel          → applyTo() again  fresh use consumed
 *
 * "In time" is defined per pass via `cancellation_policy_hours`; if
 * NULL, falls back to the active tenant's
 * `settings.cancellation_policy.hours` (default 12).
 */
class PassUseManager
{
    public function __construct(private readonly TenantManager $tenants) {}

    /**
     * Try to consume one use from the client's first usable pass.
     * Returns the created PassUse (or null if no usable pass exists —
     * the booking still proceeds, the user just paid out of pocket).
     */
    public function applyTo(CalendarEntry $entry): ?PassUse
    {
        if (! $entry->client_id) {
            return null;
        }

        // Already has an active use? Don't double-consume.
        if (PassUse::query()
            ->where('calendar_entry_id', $entry->id)
            ->whereNull('restored_at')
            ->exists()
        ) {
            return null;
        }

        return DB::connection('tenant')->transaction(function () use ($entry) {
            $pass = $this->pickUsablePassForClient($entry->client_id);
            if (! $pass) {
                return null;
            }

            $use = PassUse::create([
                'pass_id' => $pass->id,
                'calendar_entry_id' => $entry->id,
                'consumed_at' => now(),
            ]);

            $pass->recomputeFromUses();

            return $use;
        });
    }

    /**
     * Restore the active use(s) attached to this entry IF the cancellation
     * lands within the configured window. Returns true if restored, false
     * if the use stays consumed (late cancel) or there was nothing to
     * restore.
     */
    public function restoreFor(CalendarEntry $entry, string $reason = 'cancellation'): bool
    {
        return DB::connection('tenant')->transaction(function () use ($entry, $reason) {
            $uses = PassUse::query()
                ->where('calendar_entry_id', $entry->id)
                ->whereNull('restored_at')
                ->with('pass')
                ->get();

            if ($uses->isEmpty()) {
                return false;
            }

            $tenantDefault = $this->tenantDefaultCancellationHours();
            $restoredAny = false;

            foreach ($uses as $use) {
                $pass = $use->pass;
                if (! $pass) {
                    continue;
                }

                if (! $pass->isWithinCancellationWindow($entry->starts_at, $tenantDefault)) {
                    // Late cancel — pass stays consumed.
                    continue;
                }

                $use->forceFill([
                    'restored_at' => now(),
                    'restored_reason' => $reason,
                ])->save();

                $pass->recomputeFromUses();
                $restoredAny = true;
            }

            return $restoredAny;
        });
    }

    /**
     * Like restoreFor() but doesn't change anything — just reports
     * whether a restore would happen now. Used by the UI to surface
     * "if you cancel now, the pass will / won't be restored".
     */
    public function wouldRestoreOnCancel(CalendarEntry $entry): bool
    {
        $use = PassUse::query()
            ->where('calendar_entry_id', $entry->id)
            ->whereNull('restored_at')
            ->with('pass')
            ->first();

        if (! $use || ! $use->pass) {
            return false;
        }

        return $use->pass->isWithinCancellationWindow(
            $entry->starts_at,
            $this->tenantDefaultCancellationHours(),
        );
    }

    /**
     * FIFO by valid_until (soonest expiring first). Passes without an
     * expiry date sort last — they're "evergreen" and we prefer to
     * burn time-limited passes before they're wasted.
     */
    public function pickUsablePassForClient(string $clientId): ?Pass
    {
        return Pass::query()
            ->where('client_id', $clientId)
            ->usable()
            ->orderByRaw('CASE WHEN valid_until IS NULL THEN 1 ELSE 0 END, valid_until ASC')
            ->orderBy('created_at')
            ->first();
    }

    private function tenantDefaultCancellationHours(): int
    {
        $tenant = $this->tenants->current();
        if (! $tenant) {
            return 12;
        }

        return (int) (data_get($tenant->settings, 'cancellation_policy.hours') ?? 12);
    }
}
