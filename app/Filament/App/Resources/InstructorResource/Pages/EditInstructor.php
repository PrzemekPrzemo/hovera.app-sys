<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\InstructorResource\Pages;

use App\Filament\App\Resources\InstructorResource;
use Filament\Resources\Pages\EditRecord;

class EditInstructor extends EditRecord
{
    protected static string $resource = InstructorResource::class;
}
