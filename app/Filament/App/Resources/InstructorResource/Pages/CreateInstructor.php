<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\InstructorResource\Pages;

use App\Filament\App\Resources\InstructorResource;
use App\Services\TenantAuditLogger;
use Filament\Resources\Pages\CreateRecord;

class CreateInstructor extends CreateRecord
{
    protected static string $resource = InstructorResource::class;

    protected function afterCreate(): void
    {
        app(TenantAuditLogger::class)->record(
            'instructor.create',
            'Instructor',
            (string) $this->record->getKey(),
            ['name' => $this->record->name],
        );
    }
}
