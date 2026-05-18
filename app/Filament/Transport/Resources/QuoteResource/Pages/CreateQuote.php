<?php

declare(strict_types=1);

namespace App\Filament\Transport\Resources\QuoteResource\Pages;

use App\Domain\Transport\Quotes\QuoteNumberGenerator;
use App\Filament\Transport\Resources\QuoteResource;
use App\Services\TenantAuditLogger;
use Filament\Resources\Pages\CreateRecord;

class CreateQuote extends CreateRecord
{
    protected static string $resource = QuoteResource::class;

    /**
     * Backlinki do central marketplace (lead_id, response_id) — przekazane
     * z session pre-fill (Calculator → Quote albo Lead inbox → Quote).
     * Nie są w form schema (klient nie powinien móc zmienić), więc trzymamy
     * je tu i wstrzykujemy w mutateFormDataBeforeCreate.
     */
    public ?string $pendingLeadId = null;

    public ?string $pendingResponseId = null;

    /**
     * Pre-fill from session — używane gdy user kliknie "Save as quote"
     * w `/transport/calculator` lub "Odpowiedz ofertą" w LeadResource.
     * Calculator/Lead zapisują pre-fill do session['transport.calc.pending'],
     * my je tu konsumujemy (i czyścimy żeby kolejny direct create dał
     * czysty form).
     */
    protected function fillForm(): void
    {
        $pending = session('transport.calc.pending');

        if (! is_array($pending)) {
            parent::fillForm();

            return;
        }

        session()->forget('transport.calc.pending');

        // Wyciągamy backlinki przed wlaniem do form'a (form schema ich nie ma)
        $this->pendingLeadId = isset($pending['lead_id']) ? (string) $pending['lead_id'] : null;
        $this->pendingResponseId = isset($pending['response_id']) ? (string) $pending['response_id'] : null;
        unset($pending['lead_id'], $pending['response_id']);

        $this->form->fill($pending);
    }

    /**
     * Numer generujemy w mutateFormDataBeforeCreate — żeby liczyć counter
     * dopiero po validacji formularza, ale przed insertem. Atomic transakcja
     * w QuoteNumberGenerator gwarantuje brak race condition'u. Tu też
     * wstrzykujemy backlinki lead_id / response_id z pre-fill'u.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['number'] = app(QuoteNumberGenerator::class)->next();

        if ($this->pendingLeadId) {
            $data['lead_id'] = $this->pendingLeadId;
        }
        if ($this->pendingResponseId) {
            $data['response_id'] = $this->pendingResponseId;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        app(TenantAuditLogger::class)->record(
            'quote.create',
            'Quote',
            (string) $this->record->getKey(),
            ['number' => $this->record->number],
        );
    }
}
