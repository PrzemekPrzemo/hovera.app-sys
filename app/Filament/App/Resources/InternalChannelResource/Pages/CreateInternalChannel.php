<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\InternalChannelResource\Pages;

use App\Filament\App\Resources\InternalChannelResource;
use App\Models\Tenant\InternalChannel;
use App\Services\Internal\InternalChannelService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CreateInternalChannel extends CreateRecord
{
    protected static string $resource = InternalChannelResource::class;

    /**
     * Admin tworzy kanał: generujemy unikalny slug, ustawiamy autora i
     * dopisujemy wszystkich aktywnych członków stajni.
     *
     * @param array<string,mixed> $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $channel = InternalChannel::create([
            'slug' => $this->uniqueSlug((string) $data['name']),
            'name' => (string) $data['name'],
            'description' => $data['description'] ?? null,
            'is_default' => false,
            'created_by_user_id' => Auth::id(),
        ]);

        app(InternalChannelService::class)->addAllActiveMembers($channel);

        return $channel;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'kanal';
        $slug = $base;
        $i = 2;

        while (InternalChannel::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
