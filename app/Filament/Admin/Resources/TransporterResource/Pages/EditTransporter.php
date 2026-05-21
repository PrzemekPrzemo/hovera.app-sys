<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TransporterResource\Pages;

use App\Enums\VerificationStatus;
use App\Filament\Admin\Resources\TransporterResource;
use App\Models\Central\Tenant;
use App\Models\Tenant\TransporterDocument;
use App\Tenancy\TenantManager;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EditTransporter extends EditRecord
{
    protected static string $resource = TransporterResource::class;

    /**
     * Lista dokumentów weryfikacyjnych z bazy tenant'a — read-only widok dla
     * master admin'a do podejmowania decyzji verify/reject. Hydraowane w
     * `getViewData` i renderowane przez infolist w pełnym widoku.
     *
     * @var array<int,array<string,mixed>>
     */
    public array $documents = [];

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->loadDocuments();
    }

    public function getSubheading(): string|Htmlable|null
    {
        /** @var Tenant $tenant */
        $tenant = $this->record;
        $status = $tenant->verification_status;
        if (! $status instanceof VerificationStatus) {
            return null;
        }

        return $tenant->name.' · '.$status->label();
    }

    protected function getHeaderActions(): array
    {
        /** @var Tenant $tenant */
        $tenant = $this->record;

        return [
            Actions\Action::make('verify')
                ->label(__('admin/transporter.action.verify'))
                ->icon('heroicon-o-shield-check')
                ->color('success')
                ->visible(fn () => $tenant->verification_status !== VerificationStatus::Verified)
                ->form([
                    Forms\Components\Textarea::make('notes')
                        ->label(__('admin/transporter.form.label.verification_notes'))
                        ->rows(3),
                ])
                ->action(function (array $data) use ($tenant) {
                    TransporterResource::verify($tenant, (string) ($data['notes'] ?? ''));
                    $this->refreshFormData(['verification_status', 'verified_at', 'verification_notes']);
                })
                ->requiresConfirmation(),
            Actions\Action::make('reject')
                ->label(__('admin/transporter.action.reject'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => in_array($tenant->verification_status, [VerificationStatus::Pending, VerificationStatus::UnderReview], true))
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label(__('admin/transporter.form.label.rejection_reason'))
                        ->rows(4)
                        ->required(),
                ])
                ->action(function (array $data) use ($tenant) {
                    TransporterResource::reject($tenant, (string) $data['reason']);
                    $this->refreshFormData(['verification_status', 'verified_at', 'verification_notes']);
                })
                ->requiresConfirmation(),
        ];
    }

    /**
     * Ładuje dokumenty z bazy tenant'a — przepinamy connection do jego DB
     * a po odczycie wracamy do central. Każdy dokument dostaje signed download URL.
     *
     * Defensywnie obsługujemy "table doesn't exist" (kod SQLSTATE 42S02 /
     * 1146) — to znaczy że tenant DB ma stałą lukę migracji (np. tenant
     * sprovisionowany PRZED wprowadzeniem `transporter_documents`). Wtedy
     * pokazujemy pustą listę + flash notification dla master admina żeby
     * mógł odpalić migrations:tenant na tej konkretnej bazie. Lepiej niż
     * 500 — admin może wciąż edytować podstawowe pola tenanta.
     */
    private function loadDocuments(): void
    {
        /** @var Tenant $tenant */
        $tenant = $this->record;
        $tenants = app(TenantManager::class);
        $previous = $tenants->current();

        try {
            $tenants->setCurrent($tenant);

            $this->documents = TransporterDocument::query()
                ->orderBy('document_type')
                ->orderByDesc('id')
                ->get()
                ->map(fn (TransporterDocument $doc) => [
                    'id' => $doc->id,
                    'document_type' => $doc->document_type?->value,
                    'document_type_label' => $doc->document_type?->label(),
                    'status' => $doc->status,
                    'original_filename' => $doc->original_filename,
                    'file_size' => $doc->file_size,
                    'expires_at' => $doc->expires_at?->format('Y-m-d'),
                    'issued_at' => $doc->issued_at?->format('Y-m-d'),
                    'rejection_reason' => $doc->rejection_reason,
                    'is_expired' => $doc->isExpired(),
                    'download_url' => $this->buildDownloadUrl($doc),
                    'created_at' => $doc->created_at?->format('Y-m-d H:i'),
                ])
                ->all();
        } catch (QueryException $e) {
            // SQLSTATE 42S02 = "base table or view not found" (MySQL 1146,
            // PostgreSQL 42P01, SQLite "no such table"). Tenant DB nie ma
            // tej tabeli — luka migracji. Pokazujemy admin'owi pustą listę
            // + ostrzeżenie zamiast 500.
            if ($this->isMissingTableError($e)) {
                $this->documents = [];
                Log::warning(
                    'Tenant DB missing transporter_documents table (migration gap)',
                    ['tenant_id' => $tenant->id, 'db_name' => $tenant->db_name, 'error' => $e->getMessage()],
                );
                Notification::make()
                    ->title(__('admin/transporter.documents.missing_table_title'))
                    ->body(__('admin/transporter.documents.missing_table_body', ['db' => $tenant->db_name]))
                    ->warning()
                    ->persistent()
                    ->send();
            } else {
                throw $e;
            }
        } finally {
            // Wracamy do poprzedniego tenant'a (lub czyścimy)
            if ($previous) {
                $tenants->setCurrent($previous);
            } else {
                $tenants->forget();
            }
        }
    }

    /**
     * Detekcja "tabela nie istnieje" agnostyczna względem driver'a DB.
     * Sprawdzamy oba: SQLSTATE class 42 (syntax / access rules) i fragmenty
     * komunikatów per driver.
     */
    private function isMissingTableError(QueryException $e): bool
    {
        $code = $e->getCode();
        $message = $e->getMessage();

        // SQLSTATE codes
        if (in_array($code, ['42S02', '42P01'], true)) {
            return true;
        }

        // Driver-specific message fragments (gdy SQLSTATE jest 'HY000' lub puste)
        return str_contains($message, "doesn't exist")
            || str_contains($message, 'no such table')
            || str_contains($message, 'does not exist');
    }

    private function buildDownloadUrl(TransporterDocument $doc): ?string
    {
        if (! $doc->file_path) {
            return null;
        }

        $disk = Storage::disk((string) config('transport.documents.disk', 'local'));

        // S3-like disks support temporary signed URLs; local nie. Dla local
        // master admin pobiera plik przez stream'owy controller (TODO faza B+,
        // na razie zwracamy raw path do diagnostyki).
        if (method_exists($disk, 'temporaryUrl')) {
            try {
                return $disk->temporaryUrl($doc->file_path, now()->addMinutes(15));
            } catch (\Throwable) {
                // not supported — fall through
            }
        }

        return null;
    }
}
