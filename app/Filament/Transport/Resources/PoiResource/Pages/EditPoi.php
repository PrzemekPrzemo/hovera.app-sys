<?php

declare(strict_types=1);

namespace App\Filament\Transport\Resources\PoiResource\Pages;

use App\Domain\Transport\Geocoding\Exceptions\GeocodingException;
use App\Domain\Transport\Geocoding\MapboxGeocoder;
use App\Filament\Transport\Resources\PoiResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;
use Throwable;

class EditPoi extends EditRecord
{
    protected static string $resource = PoiResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    /**
     * Re-geokodujemy TYLKO gdy address się zmienił — żeby nie palić
     * Mapbox call'i przy edycji metadata (notes/sort_order/is_active).
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $oldAddress = (string) ($this->record->address ?? '');
        $newAddress = trim((string) ($data['address'] ?? ''));

        if ($newAddress === '' || $newAddress === $oldAddress) {
            return $data;
        }

        try {
            $geo = app(MapboxGeocoder::class)->geocode($newAddress);
            $data['lat'] = $geo->coords->lat;
            $data['lng'] = $geo->coords->lng;
            if ((string) $geo->displayName !== '') {
                $data['address'] = $geo->displayName;
            }
        } catch (GeocodingException $e) {
            Notification::make()
                ->warning()
                ->title(__('transport/poi.notify.geocoding_failed_title'))
                ->body($e->getMessage())
                ->send();
        } catch (Throwable $e) {
            Log::warning('POI geocoding failed on edit', ['poi_id' => $this->record->id, 'error' => $e->getMessage()]);
        }

        return $data;
    }
}
