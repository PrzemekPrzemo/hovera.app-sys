<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\PassResource\Pages;

use App\Filament\App\Resources\PassResource;
use Filament\Resources\Pages\EditRecord;

class EditPass extends EditRecord
{
    protected static string $resource = PassResource::class;
}
