<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\BoxResource\Pages;

use App\Filament\App\Resources\BoxResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBox extends CreateRecord
{
    protected static string $resource = BoxResource::class;
}
