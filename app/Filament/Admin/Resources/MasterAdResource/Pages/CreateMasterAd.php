<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\MasterAdResource\Pages;

use App\Filament\Admin\Resources\MasterAdResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateMasterAd extends CreateRecord
{
    protected static string $resource = MasterAdResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] ??= Auth::id();

        return $data;
    }
}
