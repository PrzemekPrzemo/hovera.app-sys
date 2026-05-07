<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\CalendarEntryStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\CalendarEntry;
use App\Notifications\BookingCancelledClientNotification;
use App\Services\Calendar\PassUseManager;
use App\Services\Portal\ClientMessageJournal;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * Public-flow cancellation. Reached from the cancel link in
 * confirmation emails. The signature embeds the tenant slug + entry
 * id and is valid until the booking start time.
 */
class BookingCancellationController extends Controller
{
    public function __construct(
        private readonly TenantManager $tenants,
        private readonly PassUseManager $passes,
        private readonly TenantAuditLogger $audit,
        private readonly ClientMessageJournal $journal,
    ) {}

    public function show(Request $request, string $slug, string $entryId): View|RedirectResponse
    {
        $tenant = $this->resolveAndActivate($slug);
        $entry = CalendarEntry::query()->find($entryId);

        if (! $entry) {
            return view('public.booking.cancel-invalid', [
                'tenant' => $tenant,
                'reason' => 'not_found',
                'primary_color' => data_get($tenant->branding, 'primary_color', '#10b981'),
            ]);
        }

        // Already cancelled or completed — no-op friendly page
        if (in_array($entry->status, [CalendarEntryStatus::Cancelled, CalendarEntryStatus::Completed], true)) {
            return view('public.booking.cancel-invalid', [
                'tenant' => $tenant,
                'reason' => $entry->status === CalendarEntryStatus::Cancelled ? 'already_cancelled' : 'already_completed',
                'primary_color' => data_get($tenant->branding, 'primary_color', '#10b981'),
            ]);
        }

        if (! $request->hasValidSignature()) {
            return view('public.booking.cancel-invalid', [
                'tenant' => $tenant,
                'reason' => 'expired',
                'primary_color' => data_get($tenant->branding, 'primary_color', '#10b981'),
            ]);
        }

        $wouldRestore = $this->passes->wouldRestoreOnCancel($entry);

        return view('public.booking.cancel-form', [
            'tenant' => $tenant,
            'entry' => $entry,
            'would_restore_pass' => $wouldRestore,
            'primary_color' => data_get($tenant->branding, 'primary_color', '#10b981'),
        ]);
    }

    public function submit(Request $request, string $slug, string $entryId): View|RedirectResponse
    {
        $tenant = $this->resolveAndActivate($slug);

        if (! $request->hasValidSignature()) {
            abort(403);
        }

        $entry = CalendarEntry::query()->findOrFail($entryId);
        if (in_array($entry->status, [CalendarEntryStatus::Cancelled, CalendarEntryStatus::Completed], true)) {
            return redirect()->to(URL::previous());
        }

        $passRestored = $this->passes->restoreFor($entry, 'client_cancelled');
        $entry->forceFill(['status' => CalendarEntryStatus::Cancelled->value])->save();

        $this->audit->record(
            'public_booking.cancelled_by_client',
            'CalendarEntry',
            (string) $entry->getKey(),
            ['pass_restored' => $passRestored, 'starts_at' => $entry->starts_at->toIso8601String()],
        );

        $client = $entry->client;
        if ($client?->email) {
            Notification::route('mail', $client->email)->notify(new BookingCancelledClientNotification(
                tenantName: $tenant->name,
                startsAt: $entry->starts_at,
                instructorName: $entry->instructor?->name ?? '—',
                cancelledBy: 'client',
                passRestored: $passRestored,
            ));
            $this->journal->record(
                $client,
                'booking.cancelled',
                "Rezerwacja odwołana — {$tenant->name}",
                ['starts_at' => $entry->starts_at->toIso8601String(), 'cancelled_by' => 'client', 'pass_restored' => $passRestored],
                'CalendarEntry',
                (string) $entry->id,
            );
        }

        return view('public.booking.cancel-thanks', [
            'tenant' => $tenant,
            'pass_restored' => $passRestored,
            'primary_color' => data_get($tenant->branding, 'primary_color', '#10b981'),
        ]);
    }

    private function resolveAndActivate(string $slug): Tenant
    {
        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $slug)) {
            abort(404);
        }

        $tenant = Cache::remember(
            "public_booking_tenant:{$slug}",
            now()->addMinute(),
            fn () => Tenant::query()
                ->where('slug', $slug)
                ->whereIn('status', ['trialing', 'active', 'past_due'])
                ->first(),
        );

        if (! $tenant) {
            abort(404);
        }

        // Skip re-activation if the manager already points at this tenant
        // (tests pre-configure the connection; production starts empty).
        if ($this->tenants->current()?->id !== $tenant->id) {
            $this->tenants->setCurrent($tenant);
        }

        return $tenant;
    }
}
