<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Models\Central\Tenant;
use App\Models\Tenant\CalendarEntry;
use App\Models\Tenant\Instructor;
use App\Services\Calendar\IcsCalendarBuilder;
use App\Tenancy\TenantManager;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * Serves the public iCalendar (.ics) feed for one instructor.
 * Authenticated by the per-instructor `ics_token` in the URL —
 * calendar apps cannot send cookies, so the token IS the credential.
 */
class InstructorCalendarController extends Controller
{
    public function __construct(
        private readonly TenantManager $tenants,
        private readonly IcsCalendarBuilder $builder,
    ) {}

    public function show(string $slug, string $token): Response
    {
        $tenant = Tenant::query()->where('slug', $slug)->first();
        if (! $tenant) {
            abort(404);
        }

        $this->tenants->execute($tenant, function () use (&$instructor, $token): void {
            $instructor = Instructor::query()
                ->where('ics_token', $token)
                ->where('is_active', true)
                ->first();
        });

        if (! $instructor) {
            abort(404);
        }

        $entries = collect();
        $this->tenants->execute($tenant, function () use (&$entries, $instructor): void {
            $entries = CalendarEntry::query()
                ->with(['horse:id,name', 'arena:id,name', 'client:id,name'])
                ->where('instructor_id', $instructor->id)
                ->whereBetween('starts_at', [
                    $this->builder->windowStart(),
                    $this->builder->windowEnd(),
                ])
                ->orderBy('starts_at')
                ->get();
        });

        $body = $this->builder->build($tenant, $instructor, $entries);

        return response($body, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="instructor-calendar.ics"',
            'Cache-Control' => 'private, max-age=900', // re-poll allowed every 15 min
        ]);
    }
}
