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
     * Pre-fill from session — używane gdy user kliknie "Save as quote"
     * w `/transport/calculator`. Calculator zapisuje Quotation + display
     * names do session['transport.calc.pending'], my je tu konsumujemy
     * (i czyścimy, żeby kolejny direct create dał czysty form).
     */
    protected function fillForm(): void
    {
        $pending = session('transport.calc.pending');

        if (! is_array($pending)) {
            parent::fillForm();

            return;
        }

        session()->forget('transport.calc.pending');
        $this->form->fill($pending);
    }

    /**
     * Numer generujemy w mutateFormDataBeforeCreate — żeby liczyć counter
     * dopiero po validacji formularza, ale przed insertem. Atomic transakcja
     * w QuoteNumberGenerator gwarantuje brak race condition'u.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['number'] = app(QuoteNumberGenerator::class)->next();

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
