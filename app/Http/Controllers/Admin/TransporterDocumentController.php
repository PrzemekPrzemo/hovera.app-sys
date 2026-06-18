<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Central\Tenant;
use App\Models\Tenant\TransporterDocument;
use App\Services\MasterAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Master admin podgląd / pobranie dokumentu weryfikacyjnego transportera.
 * Strumieniuje plik wprost z `transport.documents.disk` (default local,
 * na proda zwykle S3-compatible) z poprawnym Content-Disposition.
 *
 *   preview()  → `inline` — PDF/JPG/PNG otwiera się w karcie przeglądarki
 *   download() → `attachment` — wymuszamy save-as (do anonimizacji)
 *
 * Dlaczego HTTP endpoint, a nie Filament Action z `response()->download()`:
 *   - Master admin często chce otworzyć kilka dokumentów w osobnych
 *     kartach jednocześnie (porównanie OCP z dowodem rejestracyjnym itd).
 *   - Filament Action wymusza klik w UI + modal lifecycle — to spowalnia
 *     bulk-review pending tenant'ów.
 *
 * Tenant context: `TransporterDocument` żyje w per-tenant DB; route binduje
 * tylko central'owy `Tenant`, dokument szukamy przez `TenantManager::execute`
 * po przełączeniu connection'a. Plik jest na DISKU (nie w DB), więc po
 * znalezieniu rekordu wracamy do central context — `Storage::disk()->response()`
 * nie wymaga tenant connection'a.
 */
class TransporterDocumentController extends Controller
{
    public function __construct(
        private readonly TenantManager $tenants,
        private readonly MasterAuditLogger $audit,
    ) {}

    public function preview(Tenant $tenant, string $document): StreamedResponse|RedirectResponse
    {
        return $this->stream($tenant, $document, inline: true);
    }

    public function download(Tenant $tenant, string $document): StreamedResponse|RedirectResponse
    {
        return $this->stream($tenant, $document, inline: false);
    }

    private function stream(Tenant $tenant, string $documentId, bool $inline): StreamedResponse|RedirectResponse
    {
        if (! $tenant->isTransporter()) {
            abort(404);
        }

        // Skip-if-same-tenant guard (mirror `SendInvoiceToClientJob`) —
        // gdy request już biegnie w tenant context (np. testy feature
        // które presetują `current` przez reflection), nie reconfigurujemy
        // connection'a, żeby test SQLite nie nadpisał się prawdziwym
        // MySQL config'iem tenanta z central.tenants.
        $lookup = fn () => TransporterDocument::query()->find($documentId);
        /** @var TransporterDocument|null $document */
        $document = $this->tenants->current()?->id === $tenant->id
            ? $lookup()
            : $this->tenants->execute($tenant, $lookup);

        if (! $document || ! $document->file_path) {
            abort(404);
        }

        $disk = Storage::disk((string) config('transport.documents.disk', 'local'));

        if (! $disk->exists($document->file_path)) {
            abort(404);
        }

        $userId = Auth::id();
        $this->audit->record(
            action: $inline ? 'transporter_document.preview' : 'transporter_document.download',
            targetType: 'TransporterDocument',
            targetId: (string) $document->id,
            tenantId: (string) $tenant->id,
            payload: [
                'type' => $document->document_type?->value,
                'filename' => $document->original_filename,
                'by_user_id' => $userId,
            ],
        );

        $filename = $this->buildFilename($tenant, $document);
        $disposition = $inline ? 'inline' : 'attachment';
        $mime = $document->file_mime ?: ($disk->mimeType($document->file_path) ?: 'application/octet-stream');

        return $disk->response($document->file_path, $filename, [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition.'; filename="'.addslashes($filename).'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    /**
     * Friendly filename na download: `{slug}_{document_type}_{original}.{ext}`.
     * Anonimizacja po stronie admin'a — chcemy żeby plik na dysku był od razu
     * sensownie nazwany (nie `RoadCarrierLicense-aBcDe12345.pdf`).
     */
    private function buildFilename(Tenant $tenant, TransporterDocument $document): string
    {
        $type = $document->document_type?->value ?? 'document';
        $original = (string) ($document->original_filename ?: '');
        $extension = pathinfo($document->file_path ?? '', PATHINFO_EXTENSION) ?: 'pdf';

        $base = Str::slug($tenant->slug.'_'.$type);
        $originalStem = pathinfo($original, PATHINFO_FILENAME);
        if ($originalStem !== '') {
            $base .= '_'.Str::slug(Str::limit($originalStem, 60, ''));
        }

        return $base.'.'.strtolower($extension);
    }
}
