<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\HealthRecordResource\Pages;

use App\Filament\App\Resources\HealthRecordResource;
use Filament\Resources\Pages\EditRecord;

class EditHealthRecord extends EditRecord
{
    protected static string $resource = HealthRecordResource::class;
}
