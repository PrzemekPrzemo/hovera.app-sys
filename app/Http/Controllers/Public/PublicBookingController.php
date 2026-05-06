<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Actions\Calendar\RequestPublicBooking;
use App\Models\Central\Tenant;
use App\Models\Tenant\Instructor;
use App\Services\Calendar\PublicBookingAvailability;
use App\Tenancy\TenantManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Public-flow booking — no auth, no tenant middleware.
 *
 *   GET  /s/{slug}/book                      step 1: pick instructor
 *   GET  /s/{slug}/book/{instructor}         step 2: pick day + slot
 *   GET  /s/{slug}/book/{instructor}/confirm step 3: contact form
 *   POST /s/{slug}/book/{instructor}         submit
 *
 * Tenants resolve identically to PublicSiteController and the manager
 * is wired so per-tenant queries (Instructor, CalendarEntry) target
 * the right database.
 */
class PublicBookingController extends Controller
{
    public function __construct(
        private readonly PublicBookingAvailability $availability,
        private readonly TenantManager $tenants,
    ) {}

    public function chooseInstructor(string $slug): View|RedirectResponse
    {
        $tenant = $this->resolveAndActivate($slug);
        $cfg = $this->availability->settingsFor($tenant);

        if (! $cfg['enabled']) {
            return redirect('/s/'.$tenant->slug);
        }

        $instructors = Instructor::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('public.booking.instructors', [
            'tenant' => $tenant,
            'instructors' => $instructors,
            'config' => $cfg,
            'primary_color' => data_get($tenant->branding, 'primary_color', '#10b981'),
        ]);
    }

    public function pickSlot(string $slug, string $instructorId, Request $request): View|RedirectResponse
    {
        $tenant = $this->resolveAndActivate($slug);
        $cfg = $this->availability->settingsFor($tenant);
        if (! $cfg['enabled']) {
            return redirect('/s/'.$tenant->slug);
        }

        $instructor = Instructor::query()
            ->where('id', $instructorId)
            ->where('is_active', true)
            ->firstOrFail();

        $date = Carbon::parse($request->query('date', now()->toDateString()));
        $slots = $this->availability->slotsFor($tenant, $instructor, $date);
        $datesWithSlots = $this->availability->datesWithSlots($tenant, $instructor);

        return view('public.booking.slots', [
            'tenant' => $tenant,
            'instructor' => $instructor,
            'date' => $date,
            'slots' => $slots,
            'dates_with_slots' => $datesWithSlots,
            'config' => $cfg,
            'primary_color' => data_get($tenant->branding, 'primary_color', '#10b981'),
        ]);
    }

    public function confirmForm(string $slug, string $instructorId, Request $request): View|RedirectResponse
    {
        $tenant = $this->resolveAndActivate($slug);
        $cfg = $this->availability->settingsFor($tenant);
        if (! $cfg['enabled']) {
            return redirect('/s/'.$tenant->slug);
        }

        $instructor = Instructor::query()
            ->where('id', $instructorId)
            ->where('is_active', true)
            ->firstOrFail();

        $startsAt = Carbon::parse($request->query('starts_at'));

        return view('public.booking.confirm', [
            'tenant' => $tenant,
            'instructor' => $instructor,
            'starts_at' => $startsAt,
            'config' => $cfg,
            'primary_color' => data_get($tenant->branding, 'primary_color', '#10b981'),
        ]);
    }

    public function submit(string $slug, string $instructorId, Request $request, RequestPublicBooking $action): RedirectResponse|View
    {
        $tenant = $this->resolveAndActivate($slug);

        try {
            $result = $action->execute($tenant, [
                'instructor_id' => $instructorId,
                'starts_at' => $request->input('starts_at'),
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'phone' => $request->input('phone'),
                'notes' => $request->input('notes'),
            ]);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return view('public.booking.thanks', [
            'tenant' => $tenant,
            'starts_at' => $result['entry']->starts_at,
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

        if ($this->tenants->current()?->id !== $tenant->id) {
            $this->tenants->setCurrent($tenant);
        }

        return $tenant;
    }
}
