<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Models\Central\Tenant;
use App\Models\Central\TransportReview;
use App\Models\Central\TransportServiceArea;
use App\Models\Tenant\TransporterDocument;
use App\Models\Tenant\Vehicle;
use App\Tenancy\TenantManager;
use Carbon\CarbonInterface;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Publiczny profil transportera pod /t/{slug}. Marketing landing — pokazuje
 * firmę, flotę i obsługiwane województwa z CTA do /transport/zapytanie.
 *
 * - Bez auth — strona publiczna, indeksowalna (SEO play dla transporterów).
 * - 404 dla niezweryfikowanych / Stable / suspended / soft-deleted tenantów —
 *   nie powinni mieć publicznej obecności dopóki Hovera nie potwierdzi
 *   dokumentów (patrz docs/TRANSPORT.md verification flow).
 * - Cachowane 5 min na poziomie slug→tenant; flota/voivodeships dłużej (10 min).
 * - Flota leci przez TenantManager::setCurrent + try/catch — jeśli per-tenant DB
 *   się nie podłączy (np. w testach feature bez tenant migracji), sekcja
 *   floty po prostu znika zamiast crashować całą stronę.
 */
class TransporterProfileController extends Controller
{
    public function __construct(
        private readonly TenantManager $tenants,
    ) {}

    public function show(string $slug): View|Response
    {
        $tenant = $this->resolveTenant($slug);

        if (! $tenant) {
            abort(404);
        }

        $branding = (array) ($tenant->branding ?? []);
        $publicProfile = (array) (($tenant->settings ?? [])['public_profile'] ?? []);

        $vehicles = Cache::remember(
            "public_transporter_vehicles:{$slug}",
            now()->addMinutes(10),
            fn () => $this->loadVehicles($tenant),
        );

        $serviceAreas = Cache::remember(
            "public_transporter_service_areas:{$slug}",
            now()->addMinutes(10),
            fn () => $this->loadServiceAreas($tenant),
        );

        // Marketplace reviews — sekcja "Opinie klientów". Aggregate cached
        // przez TransportReview::aggregateFor (10 min), busted po nowym
        // submit'cie. Listę last-10 cache'ujemy razem z aggregate'em.
        $reviewAggregate = TransportReview::aggregateFor($tenant);
        $latestReviews = $reviewAggregate['count'] > 0
            ? Cache::remember(
                "public_transporter_reviews:{$slug}",
                now()->addMinutes(10),
                fn () => $this->loadLatestReviews($tenant),
            )
            : [];

        // Zanonimizowane certyfikaty / dokumenty potwierdzające jakość obsługi.
        // Master admin wgrywa wersję bez PII przez `/admin/transporters/.../edit`
        // → relation manager → akcja "Wgraj zanonimizowaną wersję".
        $publicDocuments = Cache::remember(
            "public_transporter_documents:{$slug}",
            now()->addMinutes(10),
            fn () => $this->loadPublicDocuments($tenant),
        );

        return response()->view('public.transport.profile', [
            'tenant' => $tenant,
            'primary_color' => $branding['primary_color'] ?? '#A8956B',
            'logo_url' => $branding['logo_url'] ?? null,
            'hero_image_url' => $branding['hero_image_url'] ?? null,
            'tagline' => $publicProfile['tagline'] ?? null,
            'description' => $publicProfile['description'] ?? null,
            'contact_email' => $publicProfile['email'] ?? null,
            'contact_phone' => $publicProfile['phone'] ?? null,
            'address' => $publicProfile['address'] ?? null,
            'website' => $publicProfile['website'] ?? null,
            'vehicles' => $vehicles,
            'service_areas' => $serviceAreas,
            'review_aggregate' => $reviewAggregate,
            'latest_reviews' => $latestReviews,
            'public_documents' => $publicDocuments,
        ])->header('Cache-Control', 'public, max-age=60, s-maxage=300');
    }

    /**
     * Strumieniowanie zanonimizowanej wersji dokumentu jako podgląd inline.
     * Publiczny endpoint bez auth — kontrole bezpieczeństwa:
     *   1. Tenant musi być isVerifiedTransporter (404 dla pozostałych)
     *   2. Dokument musi mieć `hasPublicVersion()` — status=verified AND
     *      `public_file_path != null`
     *   3. Plik musi istnieć fizycznie na dysku (graceful 404 dla orphan'ów)
     *
     * Cache 1h na CDN — zanonimizowane PDFy nie zmieniają się często, a koszt
     * stream'owania PDFa z storage'u przez PHP nie jest pomijalny przy ruchu.
     */
    public function publicDocument(string $slug, string $document): StreamedResponse|Response
    {
        $tenant = $this->resolveTenant($slug);
        if (! $tenant) {
            abort(404);
        }

        // Skip-if-same-tenant guard — mirror `TransporterDocumentController`.
        // Pozwala testom feature presetować tenant przez reflection (SQLite)
        // bez nadpisywania connection'a prawdziwym MySQL configiem.
        $lookup = fn () => TransporterDocument::query()->find($document);
        /** @var TransporterDocument|null $doc */
        $doc = $this->tenants->current()?->id === $tenant->id
            ? $lookup()
            : $this->tenants->execute($tenant, $lookup);
        if (! $doc || ! $doc->hasPublicVersion()) {
            abort(404);
        }

        $disk = Storage::disk((string) config('transport.documents.disk', 'local'));
        if (! $disk->exists((string) $doc->public_file_path)) {
            abort(404);
        }

        $mime = $doc->public_file_mime ?: ($disk->mimeType($doc->public_file_path) ?: 'application/octet-stream');
        $typeLabel = $doc->document_type?->label() ?? 'document';
        $filename = Str::slug($tenant->slug.'_'.$typeLabel).'.'.(pathinfo($doc->public_file_path ?? '', PATHINFO_EXTENSION) ?: 'pdf');

        return $disk->response($doc->public_file_path, $filename, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.addslashes($filename).'"',
            'Cache-Control' => 'public, max-age=3600, s-maxage=3600',
        ]);
    }

    /**
     * @return array{primary: list<string>, adjacent: list<string>}
     */
    private function loadServiceAreas(Tenant $tenant): array
    {
        $own = TransportServiceArea::query()
            ->where('transporter_tenant_id', $tenant->id)
            ->pluck('voivodeship')
            ->all();

        $adjacency = (array) config('transport.voivodeship_adjacency', []);
        $adjacent = [];
        foreach ($own as $voivodeship) {
            foreach ((array) ($adjacency[$voivodeship] ?? []) as $neighbour) {
                if (! in_array($neighbour, $own, true)) {
                    $adjacent[] = $neighbour;
                }
            }
        }

        return [
            'primary' => $own,
            'adjacent' => array_values(array_unique($adjacent)),
        ];
    }

    /**
     * @return list<array{
     *   name:string, capacity:?int, year:?int,
     *   has_air_suspension:bool, has_camera:bool, has_climate_control:bool,
     *   photo:?string
     * }>
     */
    private function loadVehicles(Tenant $tenant): array
    {
        if ($this->tenants->current()?->id !== $tenant->id) {
            try {
                $this->tenants->setCurrent($tenant);
            } catch (\Throwable) {
                return [];
            }
        }

        try {
            return Vehicle::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->limit(12)
                ->get([
                    'name', 'capacity_horses', 'year_of_manufacture',
                    'has_air_suspension', 'has_camera', 'has_climate_control',
                    'photos',
                ])
                ->map(fn (Vehicle $v) => [
                    'name' => (string) $v->name,
                    'capacity' => $v->capacity_horses,
                    'year' => $v->year_of_manufacture,
                    'has_air_suspension' => (bool) $v->has_air_suspension,
                    'has_camera' => (bool) $v->has_camera,
                    'has_climate_control' => (bool) $v->has_climate_control,
                    'photo' => is_array($v->photos) && ! empty($v->photos)
                        ? (string) ($v->photos[0]['url'] ?? $v->photos[0] ?? '')
                        : null,
                ])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Zanonimizowane dokumenty z `hasPublicVersion() === true` — lista
     * do sekcji "Certyfikaty i licencje" na profilu publicznym. Każdy
     * dokument prezentowany jako kafelek z ikonką typu + linkiem do
     * `public.transporter.document` (inline PDF/JPG/PNG).
     *
     * Defensive try/catch + nullable return — w testach feature bez
     * tenant DB albo dla starych tenantów bez migracji `public_*` kolumn
     * sekcja po prostu znika (nie crashujemy całego profilu).
     *
     * @return list<array{id:string, type_value:string, type_label:string, uploaded_at:?CarbonInterface}>
     */
    private function loadPublicDocuments(Tenant $tenant): array
    {
        if ($this->tenants->current()?->id !== $tenant->id) {
            try {
                $this->tenants->setCurrent($tenant);
            } catch (\Throwable) {
                return [];
            }
        }

        try {
            return TransporterDocument::query()
                ->where('status', TransporterDocument::STATUS_VERIFIED)
                ->whereNotNull('public_file_path')
                ->orderBy('document_type')
                ->get(['id', 'document_type', 'public_uploaded_at'])
                ->map(fn (TransporterDocument $d): array => [
                    'id' => (string) $d->id,
                    'type_value' => $d->document_type?->value ?? '',
                    'type_label' => $d->document_type?->label() ?? '',
                    'uploaded_at' => $d->public_uploaded_at,
                ])
                ->filter(fn (array $row) => $row['type_value'] !== '')
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Ostatnie 10 opublikowanych recenzji do sekcji "Opinie klientów".
     * Redagowane imię/nazwisko + brak emaila — RODO data minimisation.
     *
     * @return list<array{rating:int, comment:?string, customer:string, submitted_at:?CarbonInterface, transporter_response:?string, transporter_responded_at:?CarbonInterface}>
     */
    private function loadLatestReviews(Tenant $tenant): array
    {
        return TransportReview::query()
            ->where('transporter_tenant_id', $tenant->id)
            ->published()
            ->orderByDesc('submitted_at')
            ->limit(10)
            ->get()
            ->map(fn (TransportReview $r): array => [
                'rating' => (int) ($r->rating ?? 0),
                'comment' => $r->comment,
                'customer' => TransportReview::redactCustomerName($r->customer_name),
                'submitted_at' => $r->submitted_at,
                'transporter_response' => $r->transporter_response,
                'transporter_responded_at' => $r->transporter_responded_at,
            ])
            ->all();
    }

    private function resolveTenant(string $slug): ?Tenant
    {
        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $slug)) {
            return null;
        }

        return Cache::remember(
            "public_transporter:{$slug}",
            now()->addMinutes(5),
            function () use ($slug) {
                $tenant = Tenant::query()
                    ->where('slug', $slug)
                    ->whereIn('status', ['trialing', 'active', 'past_due'])
                    ->first();

                if (! $tenant || ! $tenant->isVerifiedTransporter()) {
                    return null;
                }

                return $tenant;
            },
        );
    }
}
