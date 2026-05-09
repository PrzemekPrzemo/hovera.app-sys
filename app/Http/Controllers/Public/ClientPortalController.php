<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Actions\Calendar\CreateClientPortalBooking;
use App\Actions\Calendar\RescheduleBookingByClient;
use App\Actions\Stable\SendHorseMessage;
use App\Enums\CalendarEntryStatus;
use App\Enums\HorseDocumentKind;
use App\Enums\InvoiceStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\CalendarEntry;
use App\Models\Tenant\Client;
use App\Models\Tenant\ClientMessage;
use App\Models\Tenant\HealthRecord;
use App\Models\Tenant\Horse;
use App\Models\Tenant\HorseDocument;
use App\Models\Tenant\HorseMessage;
use App\Models\Tenant\Instructor;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\Pass;
use App\Models\Tenant\PassUse;
use App\Models\Tenant\StableActivity;
use App\Notifications\BookingRescheduledClientNotification;
use App\Notifications\ClientPortalMagicLinkNotification;
use App\Services\Calendar\BookingCancellationLink;
use App\Services\Calendar\PublicBookingAvailability;
use App\Services\Invoicing\InvoicePublicLink;
use App\Services\Portal\ClientMessageJournal;
use App\Services\Portal\ClientPortalAuth;
use App\Services\Stable\HorseDocumentService;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientPortalController extends Controller
{
    public function __construct(
        private readonly TenantManager $tenants,
        private readonly ClientPortalAuth $auth,
        private readonly BookingCancellationLink $cancelLinks,
        private readonly TenantAuditLogger $audit,
        private readonly PublicBookingAvailability $availability,
        private readonly RescheduleBookingByClient $reschedule,
        private readonly ClientMessageJournal $journal,
    ) {}

    public function showLogin(Request $request, string $slug): View|RedirectResponse
    {
        $tenant = $this->resolveAndActivate($slug);

        if ($this->auth->current($request, $slug)) {
            return redirect()->route('client_portal.dashboard', ['slug' => $slug]);
        }

        return view('public.portal.login', [
            'tenant' => $tenant,
            'primary_color' => data_get($tenant->branding, 'primary_color', '#10b981'),
        ]);
    }

    /**
     * Always responds with the same "we sent a link if the email is
     * registered" page — never disclose whether an email is known.
     */
    public function submitLogin(Request $request, string $slug): View
    {
        $tenant = $this->resolveAndActivate($slug);

        $data = $request->validate([
            'email' => ['required', 'email:rfc,strict'],
        ]);

        $client = Client::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($data['email'])])
            ->first();

        if ($client) {
            $url = $this->auth->issueMagicLink($client, $slug);

            Notification::route('mail', $client->email)->notify(
                new ClientPortalMagicLinkNotification(
                    tenantName: $tenant->name,
                    magicLinkUrl: $url,
                    ttlMinutes: ClientPortalAuth::TOKEN_TTL_MINUTES,
                ),
            );

            $this->audit->record('client_portal.magic_link_sent', 'Client', (string) $client->id);
            $this->journal->record(
                $client,
                'portal.magic_link',
                "Logowanie do panelu — {$tenant->name}",
                ['ttl_minutes' => ClientPortalAuth::TOKEN_TTL_MINUTES],
            );
        }

        return view('public.portal.login-sent', [
            'tenant' => $tenant,
            'email' => $data['email'],
            'primary_color' => data_get($tenant->branding, 'primary_color', '#10b981'),
        ]);
    }

    public function consumeLogin(Request $request, string $slug, string $clientId): View|RedirectResponse
    {
        $tenant = $this->resolveAndActivate($slug);

        $token = (string) $request->query('token', '');
        $client = Client::query()->find($clientId);

        $valid = $client !== null
            && $token !== ''
            && $this->auth->consume($request, $client, $token, $slug);

        if (! $valid) {
            return view('public.portal.login-invalid', [
                'tenant' => $tenant,
                'primary_color' => data_get($tenant->branding, 'primary_color', '#10b981'),
            ]);
        }

        $this->audit->record('client_portal.logged_in', 'Client', (string) $client->id);

        return redirect()->route('client_portal.dashboard', ['slug' => $slug]);
    }

    public function logout(Request $request, string $slug): RedirectResponse
    {
        $this->resolveAndActivate($slug);
        $this->auth->logout($request, $slug);

        return redirect()->route('client_portal.login.show', ['slug' => $slug]);
    }

    public function dashboard(Request $request, string $slug): View|RedirectResponse
    {
        $tenant = $this->resolveAndActivate($slug);
        $client = $this->auth->current($request, $slug);

        if (! $client) {
            return redirect()->route('client_portal.login.show', ['slug' => $slug]);
        }

        $now = now();

        $upcoming = CalendarEntry::query()
            ->with(['instructor', 'horse', 'arena'])
            ->where('client_id', $client->id)
            ->whereIn('status', [
                CalendarEntryStatus::Requested->value,
                CalendarEntryStatus::Confirmed->value,
            ])
            ->where('starts_at', '>=', $now)
            ->orderBy('starts_at')
            ->limit(50)
            ->get();

        $past = CalendarEntry::query()
            ->with(['instructor', 'horse', 'arena'])
            ->where('client_id', $client->id)
            ->where('starts_at', '<', $now)
            ->orderByDesc('starts_at')
            ->limit(20)
            ->get();

        $cancelLinks = $upcoming
            ->filter(fn (CalendarEntry $e) => $e->status === CalendarEntryStatus::Confirmed)
            ->mapWithKeys(fn (CalendarEntry $e) => [
                $e->id => $this->cancelLinks->for($e, $tenant->slug),
            ]);

        $passes = Pass::query()
            ->where('client_id', $client->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $recentUses = PassUse::query()
            ->with('calendarEntry')
            ->whereIn('pass_id', $passes->pluck('id'))
            ->whereNull('restored_at')
            ->orderByDesc('consumed_at')
            ->limit(5)
            ->get();

        $horses = Horse::query()
            ->where('owner_client_id', $client->id)
            ->orderBy('name')
            ->get();

        // Pre-aggregate per-horse counts so the dashboard view doesn't
        // run N queries inside @foreach.
        $horseAlerts = collect();
        if ($horses->isNotEmpty()) {
            $horseAlerts = HealthRecord::query()
                ->selectRaw('horse_id, SUM(CASE WHEN next_due_at < ? THEN 1 ELSE 0 END) as overdue, '
                    .'SUM(CASE WHEN next_due_at >= ? AND next_due_at <= ? THEN 1 ELSE 0 END) as upcoming', [
                        now()->toDateString(),
                        now()->toDateString(),
                        now()->addDays(30)->toDateString(),
                    ])
                ->whereIn('horse_id', $horses->pluck('id'))
                ->whereNotNull('next_due_at')
                ->groupBy('horse_id')
                ->get()
                ->keyBy('horse_id');
        }

        $recentMessages = ClientMessage::query()
            ->where('client_id', $client->id)
            ->orderByDesc('sent_at')
            ->limit(5)
            ->get();

        $unpaidInvoices = Invoice::query()
            ->where('client_id', $client->id)
            ->where('status', InvoiceStatus::Issued->value)
            ->orderBy('due_at')
            ->limit(10)
            ->get();

        // Nieprzeczytane wiadomości stajnia → klient (sumaryczne, per wszystkie konie klienta)
        $unreadHorseMessages = $horses->isNotEmpty()
            ? (int) HorseMessage::query()
                ->whereIn('horse_id', $horses->pluck('id'))
                ->where('client_id', $client->id)
                ->unreadByClient()
                ->count()
            : 0;

        $invoiceLinks = $unpaidInvoices->mapWithKeys(fn (Invoice $i) => [
            $i->id => app(InvoicePublicLink::class)->for($i, $tenant->slug),
        ]);

        return view('public.portal.dashboard', [
            'tenant' => $tenant,
            'client' => $client,
            'upcoming' => $upcoming,
            'past' => $past,
            'passes' => $passes,
            'recent_uses' => $recentUses,
            'horses' => $horses,
            'horse_alerts' => $horseAlerts,
            'recent_messages' => $recentMessages,
            'unpaid_invoices' => $unpaidInvoices,
            'invoice_links' => $invoiceLinks,
            'unread_horse_messages' => $unreadHorseMessages,
            'cancel_links' => $cancelLinks,
            'primary_color' => data_get($tenant->branding, 'primary_color', '#10b981'),
        ]);
    }

    public function showMessages(Request $request, string $slug): View|RedirectResponse
    {
        $tenant = $this->resolveAndActivate($slug);
        $client = $this->auth->current($request, $slug);
        if (! $client) {
            return redirect()->route('client_portal.login.show', ['slug' => $slug]);
        }

        $messages = ClientMessage::query()
            ->where('client_id', $client->id)
            ->orderByDesc('sent_at')
            ->paginate(30);

        return view('public.portal.messages', [
            'tenant' => $tenant,
            'client' => $client,
            'messages' => $messages,
            'primary_color' => data_get($tenant->branding, 'primary_color', '#10b981'),
        ]);
    }

    public function showBooking(Request $request, string $slug): View|RedirectResponse
    {
        $tenant = $this->resolveAndActivate($slug);
        $client = $this->auth->current($request, $slug);
        if (! $client) {
            return redirect()->route('client_portal.login.show', ['slug' => $slug]);
        }

        $cfg = $this->availability->settingsFor($tenant);
        if (! $cfg['enabled']) {
            return redirect()->route('client_portal.dashboard', ['slug' => $slug])
                ->with('booking_disabled', true);
        }

        $horses = Horse::query()
            ->where('owner_client_id', $client->id)
            ->orderBy('name')
            ->get();

        $instructors = Instructor::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $instructorId = (string) $request->query('instructor');
        $date = (string) $request->query('date', now()->toDateString());

        $slots = collect();
        $datesWithSlots = collect();
        $selectedInstructor = null;
        if ($instructorId !== '') {
            $selectedInstructor = $instructors->firstWhere('id', $instructorId);
            if ($selectedInstructor) {
                try {
                    $cursor = Carbon::parse($date);
                } catch (\Throwable) {
                    $cursor = now()->startOfDay();
                }
                $slots = $this->availability->slotsFor($tenant, $selectedInstructor, $cursor);
                $datesWithSlots = $this->availability->datesWithSlots($tenant, $selectedInstructor);
            }
        }

        return view('public.portal.booking', [
            'tenant' => $tenant,
            'client' => $client,
            'horses' => $horses,
            'instructors' => $instructors,
            'selected_instructor' => $selectedInstructor,
            'date' => $date,
            'slots' => $slots,
            'dates_with_slots' => $datesWithSlots,
            'config' => $cfg,
            'primary_color' => data_get($tenant->branding, 'primary_color', '#10b981'),
        ]);
    }

    public function submitBooking(Request $request, string $slug, CreateClientPortalBooking $action): RedirectResponse
    {
        $tenant = $this->resolveAndActivate($slug);
        $client = $this->auth->current($request, $slug);
        if (! $client) {
            return redirect()->route('client_portal.login.show', ['slug' => $slug]);
        }

        $data = $request->validate([
            'horse_id' => ['required', 'string'],
            'instructor_id' => ['required', 'string'],
            'starts_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $action->execute(
                tenant: $tenant,
                client: $client,
                horseId: $data['horse_id'],
                instructorId: $data['instructor_id'],
                startsAtIso: $data['starts_at'],
                notes: $data['notes'] ?? null,
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('client_portal.dashboard', ['slug' => $slug])
            ->with('booking_requested', true);
    }

    public function showHelp(Request $request, string $slug): View|RedirectResponse
    {
        $tenant = $this->resolveAndActivate($slug);
        $client = $this->auth->current($request, $slug);
        if (! $client) {
            return redirect()->route('client_portal.login.show', ['slug' => $slug]);
        }

        $locale = app()->getLocale();
        $supported = ['pl', 'en', 'de', 'fr'];
        $useLocale = in_array($locale, $supported, true) ? $locale : 'pl';

        $path = resource_path("help/{$useLocale}/client.md");
        if (! file_exists($path)) {
            $path = resource_path('help/pl/client.md');
        }

        return view('public.portal.help', [
            'tenant' => $tenant,
            'client' => $client,
            'help_html' => Str::markdown((string) file_get_contents($path), ['html_input' => 'allow']),
            'primary_color' => data_get($tenant->branding, 'primary_color', '#10b981'),
        ]);
    }

    public function showHorse(Request $request, string $slug, string $horseId): View|RedirectResponse
    {
        $tenant = $this->resolveAndActivate($slug);
        $client = $this->auth->current($request, $slug);
        if (! $client) {
            return redirect()->route('client_portal.login.show', ['slug' => $slug]);
        }

        $horse = Horse::query()
            ->with(['box', 'boardingServices'])
            ->where('owner_client_id', $client->id)
            ->find($horseId);
        if (! $horse) {
            abort(404);
        }

        $records = HealthRecord::query()
            ->where('horse_id', $horse->id)
            ->with('specialist:id,name,type')
            ->orderByDesc('performed_at')
            ->limit(100)
            ->get();

        $activities = StableActivity::query()
            ->where('horse_id', $horse->id)
            ->with('specialist:id,name,type')
            ->orderByDesc('performed_at')
            ->limit(50)
            ->get();

        $messages = HorseMessage::query()
            ->where('horse_id', $horse->id)
            ->orderByDesc('sent_at')
            ->limit(50)
            ->get();

        // Mark stable→client messages jako odczytane przy otwarciu strony
        HorseMessage::query()
            ->where('horse_id', $horse->id)
            ->where('direction', 'from_stable')
            ->whereNull('read_by_client_at')
            ->update(['read_by_client_at' => now()]);

        $documents = HorseDocument::query()
            ->where('horse_id', $horse->id)
            ->orderBy('kind')
            ->orderByDesc('created_at')
            ->get();

        return view('public.portal.horse', [
            'tenant' => $tenant,
            'client' => $client,
            'horse' => $horse,
            'records' => $records,
            'activities' => $activities,
            'messages' => $messages,
            'documents' => $documents,
            'estimated_monthly_cents' => $horse->estimatedMonthlyCostCents(),
            'primary_color' => data_get($tenant->branding, 'primary_color', '#10b981'),
        ]);
    }

    public function sendHorseMessage(Request $request, string $slug, string $horseId): RedirectResponse
    {
        $tenant = $this->resolveAndActivate($slug);
        $client = $this->auth->current($request, $slug);
        if (! $client) {
            return redirect()->route('client_portal.login.show', ['slug' => $slug]);
        }

        $horse = Horse::query()
            ->where('owner_client_id', $client->id)
            ->find($horseId);
        if (! $horse) {
            abort(404);
        }

        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240'], // 10 MB
        ]);

        try {
            app(SendHorseMessage::class)->fromClient(
                tenant: $tenant,
                horse: $horse,
                clientId: $client->id,
                body: (string) $data['body'],
                subject: ($data['subject'] ?? '') !== '' ? (string) $data['subject'] : null,
                attachments: $request->file('attachments') ?? [],
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('client_portal.horses.show', ['slug' => $slug, 'horse' => $horseId])
            ->with('horse_message_sent', true);
    }

    /**
     * Pobranie załącznika z wiadomości — auth check że klient ma dostęp
     * (musi owner-ować konia, którego wiadomość dotyczy).
     */
    public function downloadAttachment(Request $request, string $slug, string $horseId, string $messageId, int $index): StreamedResponse|RedirectResponse
    {
        $tenant = $this->resolveAndActivate($slug);
        $client = $this->auth->current($request, $slug);
        if (! $client) {
            return redirect()->route('client_portal.login.show', ['slug' => $slug]);
        }

        $horse = Horse::query()
            ->where('owner_client_id', $client->id)
            ->find($horseId);
        if (! $horse) {
            abort(404);
        }

        $message = HorseMessage::query()
            ->where('horse_id', $horse->id)
            ->find($messageId);
        if (! $message) {
            abort(404);
        }

        $attachments = (array) ($message->attachments ?? []);
        $a = $attachments[$index] ?? null;
        if (! $a || ! Storage::disk('local')->exists((string) $a['path'])) {
            abort(404);
        }

        return Storage::disk('local')->download(
            (string) $a['path'],
            (string) ($a['original_name'] ?? 'attachment'),
        );
    }

    public function uploadHorseDocument(Request $request, string $slug, string $horseId): RedirectResponse
    {
        $tenant = $this->resolveAndActivate($slug);
        $client = $this->auth->current($request, $slug);
        if (! $client) {
            return redirect()->route('client_portal.login.show', ['slug' => $slug]);
        }

        $horse = Horse::query()
            ->where('owner_client_id', $client->id)
            ->find($horseId);
        if (! $horse) {
            abort(404);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'kind' => ['required', 'string'],
            'description' => ['nullable', 'string', 'max:500'],
            'file' => ['required', 'file', 'max:25600'], // 25 MB
        ]);

        try {
            app(HorseDocumentService::class)->uploadByClient(
                tenant: $tenant,
                horse: $horse,
                clientId: $client->id,
                file: $request->file('file'),
                name: (string) $data['name'],
                kind: HorseDocumentKind::from((string) $data['kind']),
                description: ($data['description'] ?? '') !== '' ? (string) $data['description'] : null,
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('client_portal.horses.show', ['slug' => $slug, 'horse' => $horseId])
            ->with('horse_document_uploaded', true);
    }

    public function downloadHorseDocument(Request $request, string $slug, string $horseId, string $documentId): StreamedResponse|RedirectResponse
    {
        $tenant = $this->resolveAndActivate($slug);
        $client = $this->auth->current($request, $slug);
        if (! $client) {
            return redirect()->route('client_portal.login.show', ['slug' => $slug]);
        }

        $horse = Horse::query()
            ->where('owner_client_id', $client->id)
            ->find($horseId);
        if (! $horse) {
            abort(404);
        }

        $doc = HorseDocument::query()
            ->where('horse_id', $horse->id)
            ->find($documentId);
        if (! $doc || ! Storage::disk('local')->exists($doc->file_path)) {
            abort(404);
        }

        return Storage::disk('local')->download($doc->file_path, $doc->original_name);
    }

    public function deleteHorseDocument(Request $request, string $slug, string $horseId, string $documentId): RedirectResponse
    {
        $tenant = $this->resolveAndActivate($slug);
        $client = $this->auth->current($request, $slug);
        if (! $client) {
            return redirect()->route('client_portal.login.show', ['slug' => $slug]);
        }

        $horse = Horse::query()
            ->where('owner_client_id', $client->id)
            ->find($horseId);
        if (! $horse) {
            abort(404);
        }

        $doc = HorseDocument::query()
            ->where('horse_id', $horse->id)
            ->find($documentId);
        if (! $doc) {
            abort(404);
        }

        try {
            app(HorseDocumentService::class)->delete($doc, byClientId: $client->id);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('client_portal.horses.show', ['slug' => $slug, 'horse' => $horseId])
            ->with('horse_document_deleted', true);
    }

    public function showReschedule(Request $request, string $slug, string $entryId): View|RedirectResponse
    {
        $tenant = $this->resolveAndActivate($slug);
        $client = $this->auth->current($request, $slug);
        if (! $client) {
            return redirect()->route('client_portal.login.show', ['slug' => $slug]);
        }

        $entry = CalendarEntry::query()
            ->with(['instructor'])
            ->where('client_id', $client->id)
            ->find($entryId);
        if (! $entry || $entry->status !== CalendarEntryStatus::Confirmed) {
            abort(404);
        }

        $instructor = $entry->instructor;
        $datesWithSlots = $instructor && $instructor->is_active
            ? $this->availability->datesWithSlots($tenant, $instructor)
            : collect();

        $selectedDate = $request->query('date');
        $slots = collect();
        if ($selectedDate && $instructor && $instructor->is_active) {
            try {
                $date = Carbon::parse((string) $selectedDate)->startOfDay();
                $slots = $this->availability->slotsFor($tenant, $instructor, $date);
            } catch (\Throwable) {
                $slots = collect();
            }
        }

        return view('public.portal.reschedule', [
            'tenant' => $tenant,
            'client' => $client,
            'entry' => $entry,
            'dates_with_slots' => $datesWithSlots,
            'selected_date' => $selectedDate,
            'slots' => $slots,
            'primary_color' => data_get($tenant->branding, 'primary_color', '#10b981'),
        ]);
    }

    public function submitReschedule(Request $request, string $slug, string $entryId): View|RedirectResponse
    {
        $tenant = $this->resolveAndActivate($slug);
        $client = $this->auth->current($request, $slug);
        if (! $client) {
            return redirect()->route('client_portal.login.show', ['slug' => $slug]);
        }

        $entry = CalendarEntry::query()->find($entryId);
        if (! $entry) {
            abort(404);
        }

        $data = $request->validate([
            'starts_at' => ['required', 'date'],
        ]);
        $newStart = Carbon::parse($data['starts_at']);
        $oldStart = $entry->starts_at->copy();

        try {
            $entry = $this->reschedule->execute($tenant, $entry, $client, $newStart);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $this->audit->record('client_portal.reschedule', 'CalendarEntry', (string) $entry->id, [
            'from' => $oldStart->toIso8601String(),
            'to' => $newStart->toIso8601String(),
        ]);

        if ($client->email) {
            Notification::route('mail', $client->email)->notify(new BookingRescheduledClientNotification(
                tenantName: $tenant->name,
                oldStartsAt: $oldStart,
                newStartsAt: $entry->starts_at,
                durationMinutes: (int) $entry->starts_at->diffInMinutes($entry->ends_at),
                instructorName: $entry->instructor?->name ?? '—',
                cancelUrl: $this->cancelLinks->for($entry, $tenant->slug),
                portalUrl: route('client_portal.login.show', ['slug' => $tenant->slug]),
            ));
            $this->journal->record(
                $client,
                'booking.rescheduled',
                "Rezerwacja przesunięta — {$tenant->name}",
                ['from' => $oldStart->toIso8601String(), 'to' => $entry->starts_at->toIso8601String()],
                'CalendarEntry',
                (string) $entry->id,
            );
        }

        return redirect()
            ->route('client_portal.dashboard', ['slug' => $slug])
            ->with('reschedule_success', $entry->id);
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
