<?php

declare(strict_types=1);

namespace App\Filament\Components;

use App\Services\CompanyLookup\CompanyLookupService;
use App\Services\CompanyLookup\GusApiService;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;

/**
 * Reusable Filament suffix Action dla pól NIP — fetchuje dane firmy
 * z GUS/CEIDG/KRS przez `CompanyLookupService` i wypełnia mapowane pola
 * formularza (name, street, city, postal_code, country itd.).
 *
 * Założenie: form ma pole `tax_id` (lub aliased) jako NIP source, plus
 * dowolne z: name/legal_name, street, city, postal_code. Wszystkie
 * dostępne — caller customizuje przez `mapFields()` jeśli inne nazwy.
 *
 * Użycie w schema:
 *   TextInput::make('tax_id')
 *     ->label('NIP')
 *     ->suffixAction(GusLookupAction::make())
 *
 * Z custom field mapping (np. dla Tenant.legal_name + Tenant.address):
 *   ->suffixAction(GusLookupAction::make(['name' => 'legal_name', 'street' => 'address']))
 *
 * Gdy NIP siedzi pod innym kluczem niż `tax_id` (np. `buyer_nip`,
 * `customer_tax_id`), podaj `sourceField`:
 *   ->suffixAction(GusLookupAction::make([...], sourceField: 'buyer_nip'))
 *
 * Visibility: action automatycznie ukrywa się gdy GUS nie jest
 * skonfigurowany (master admin nie wpisał creds w /admin/...).
 */
class GusLookupAction
{
    /**
     * @param  array<string,string>  $mapFields  Override default 1:1 mapping (e.g. ['name' => 'legal_name']).
     * @param  string  $sourceField  Form-state key that holds the NIP. Defaults to 'tax_id'.
     */
    public static function make(array $mapFields = [], string $sourceField = 'tax_id'): Action
    {
        // Default mapping: 1:1 dla popularnych nazw pól.
        $defaultMap = [
            'name' => 'name',
            'street' => 'street',
            'city' => 'city',
            'postal_code' => 'postal_code',
            'country' => 'country',
        ];
        $effective = array_merge($defaultMap, $mapFields);

        return Action::make('lookupGus')
            ->label(__('common.gus_lookup.label'))
            ->icon('heroicon-m-magnifying-glass')
            ->visible(fn () => app(GusApiService::class)->isConfigured())
            ->action(function (Get $get, Set $set) use ($effective, $sourceField) {
                $nip = (string) $get($sourceField);
                if (! CompanyLookupService::isValidNip($nip)) {
                    Notification::make()
                        ->title(__('common.gus_lookup.invalid_nip'))
                        ->danger()
                        ->send();

                    return;
                }

                $data = app(CompanyLookupService::class)->lookupByNip($nip);
                if ($data === null) {
                    Notification::make()
                        ->title(__('common.gus_lookup.not_found'))
                        ->warning()
                        ->send();

                    return;
                }

                if (isset($effective['name'])) {
                    $set($effective['name'], (string) ($data['name'] ?? ''));
                }
                if (isset($effective['street'])) {
                    $set($effective['street'], trim(
                        ($data['street'] ?? '').' '
                        .($data['building'] ?? '')
                        .($data['apartment'] ? '/'.$data['apartment'] : '')
                    ));
                }
                if (isset($effective['city'])) {
                    $set($effective['city'], (string) ($data['city'] ?? ''));
                }
                if (isset($effective['postal_code'])) {
                    $set($effective['postal_code'], (string) ($data['postal_code'] ?? ''));
                }
                if (isset($effective['country'])) {
                    $set($effective['country'], 'PL');
                }

                $sources = is_array($data['sources'] ?? null) ? implode(', ', $data['sources']) : 'GUS';
                Notification::make()
                    ->title(__('common.gus_lookup.success'))
                    ->body(__('common.gus_lookup.success_body', ['sources' => strtoupper($sources)]))
                    ->success()
                    ->send();
            });
    }
}
