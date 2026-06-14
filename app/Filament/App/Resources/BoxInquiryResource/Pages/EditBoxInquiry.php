<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\BoxInquiryResource\Pages;

use App\Filament\App\Resources\BoxInquiryResource;
use App\Models\Tenant\BoxInquiry;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBoxInquiry extends EditRecord
{
    protected static string $resource = BoxInquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    /**
     * Auto-stamp `responded_at` przy pierwszej zmianie statusu z NEW na
     * cokolwiek innego (contacted/closed) — żeby manager nie musiał klikać
     * w datepicker za każdym razem.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (
            $this->record->status === BoxInquiry::STATUS_NEW
            && ($data['status'] ?? null) !== BoxInquiry::STATUS_NEW
            && empty($data['responded_at'])
        ) {
            $data['responded_at'] = now();
        }

        return $data;
    }
}
