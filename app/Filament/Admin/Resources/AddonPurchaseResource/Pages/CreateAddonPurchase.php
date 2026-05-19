<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AddonPurchaseResource\Pages;

use App\Filament\Admin\Resources\AddonPurchaseResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateAddonPurchase extends CreateRecord
{
    protected static string $resource = AddonPurchaseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Audit kto stworzył purchase — wymagane przez compliance.
        $data['created_by_user_id'] = (string) (Auth::id() ?? '');

        return $data;
    }
}
