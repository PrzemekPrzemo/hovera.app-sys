<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\Tenant\CalendarEntry;
use Illuminate\Support\Facades\URL;

/**
 * Builds the customer-facing cancel link for a booking.
 *
 * Signed Laravel URL — no DB token needed. The signature is valid until
 * the booking start time, so a stale link can't be replayed after the
 * lesson should have happened.
 *
 * Even after expiry the controller will render an explanatory page
 * rather than throwing — Laravel's signature middleware throws 403
 * which is unfriendly UX, so we don't put it on the route and verify
 * `URL::hasValidSignature()` ourselves in the controller.
 */
class BookingCancellationLink
{
    public function for(CalendarEntry $entry, string $tenantSlug): string
    {
        return URL::temporarySignedRoute(
            name: 'public.booking.cancel.show',
            expiration: $entry->starts_at,
            parameters: [
                'slug' => $tenantSlug,
                'entry' => $entry->id,
            ],
        );
    }
}
