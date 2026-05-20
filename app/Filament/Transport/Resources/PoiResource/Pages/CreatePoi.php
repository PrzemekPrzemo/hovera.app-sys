<?php

declare(strict_types=1);

namespace App\Filament\Transport\Resources\PoiResource\Pages;

use App\Domain\Transport\Geocoding\Exceptions\GeocodingException;
use App\Domain\Transport\Geocoding\MapboxGeocoder;
use App\Filament\Transport\Resources\PoiResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreatePoi extends CreateRecord
{
    protected static string $resource = PoiResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->geocodeAddress($data);
    }

    /**
     * Geokoduje address → lat/lng przy save'ie. Soft-fail: gdy
     * geocoder padnie, zostawiamy lat/lng = 0 (user edytuje POI
     * i może retry'ować po naprawie adresu). Patrz analogiczny
     * pattern w CreateQuote::autoCalculatePricing.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function geocodeAddress(array $data): array
    {
        $address = trim((string) ($data['address'] ?? ''));
        if ($address === '') {
            return $data;
        }

        try {
            $geo = app(MapboxGeocoder::class)->geocode($address);
            $data['lat'] = $geo->coords->lat;
            $data['lng'] = $geo->coords->lng;
            // Display name zwykle pełniejszy niż user-typed — używamy go
            // do dokumentacji w POI library.
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
            Log::warning('POI geocoding failed', ['error' => $e->getMessage()]);
        }

        return $data;
    }
}
