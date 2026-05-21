<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\HorseResource\Pages;

use App\Domain\Horses\HorseRegistrySyncService;
use App\Filament\App\Resources\HorseResource;
use App\Models\Central\CentralHorseRegistry;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Tenancy\TenantManager;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Log;

class ListHorses extends ListRecords
{
    protected static string $resource = HorseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            $this->importFromRegistryAction(),
        ];
    }

    /**
     * PR 1 z TODO — Stable: search & import horse from central registry.
     *
     * Flow:
     *   1. Stable wpisuje email właściciela
     *   2. Filament reactive form po blur'ze pobiera listę koni user'a z
     *      CentralHorseRegistry (primary_owner_user_id = User.id)
     *   3. Select pokazuje "{name} ({passport_no ?: brak paszportu})"
     *   4. Submit → requestBoarding() → status=pending
     *      Owner zostanie powiadomiony (PR 5 doda HorseBoardingRequestedNotification);
     *      teraz log warning + flash.
     */
    private function importFromRegistryAction(): Actions\Action
    {
        return Actions\Action::make('import_from_registry')
            ->label(__('app/horse.action.import_from_registry.label'))
            ->icon('heroicon-o-magnifying-glass-circle')
            ->color('primary')
            ->modalHeading(__('app/horse.action.import_from_registry.modal_heading'))
            ->modalDescription(__('app/horse.action.import_from_registry.modal_description'))
            ->modalSubmitActionLabel(__('app/horse.action.import_from_registry.submit'))
            ->form([
                Forms\Components\TextInput::make('owner_email')
                    ->label(__('app/horse.action.import_from_registry.owner_email'))
                    ->helperText(__('app/horse.action.import_from_registry.owner_email_helper'))
                    ->email()
                    ->required()
                    ->live(onBlur: true),
                Forms\Components\Select::make('central_horse_id')
                    ->label(__('app/horse.action.import_from_registry.horse'))
                    ->helperText(__('app/horse.action.import_from_registry.horse_helper'))
                    ->options(fn (Forms\Get $get): array => self::resolveHorseOptions((string) ($get('owner_email') ?? '')))
                    ->required()
                    ->visible(fn (Forms\Get $get) => filter_var($get('owner_email'), FILTER_VALIDATE_EMAIL) !== false)
                    ->searchable(),
                Forms\Components\Placeholder::make('lookup_status')
                    ->label('')
                    ->content(fn (Forms\Get $get) => self::renderLookupStatus((string) ($get('owner_email') ?? '')))
                    ->visible(fn (Forms\Get $get) => filter_var($get('owner_email'), FILTER_VALIDATE_EMAIL) !== false),
            ])
            ->action(function (array $data) {
                /** @var Tenant|null $stable */
                $stable = app(TenantManager::class)->current();
                if ($stable === null) {
                    Notification::make()->danger()
                        ->title(__('app/horse.action.import_from_registry.no_tenant'))
                        ->send();

                    return;
                }

                $horse = CentralHorseRegistry::query()->find($data['central_horse_id'] ?? null);
                if ($horse === null) {
                    Notification::make()->danger()
                        ->title(__('app/horse.action.import_from_registry.horse_missing'))
                        ->send();

                    return;
                }

                $assignment = app(HorseRegistrySyncService::class)->requestBoarding($horse, $stable);

                // PR 5 doda HorseBoardingRequestedNotification dispatched do
                // primary_owner_user_id. Na razie logujemy żeby ops mógł
                // ręcznie poinformować właściciela.
                Log::info('Stable requested boarding for horse (pending notif system)', [
                    'stable_tenant_id' => $stable->id,
                    'central_horse_id' => $horse->id,
                    'owner_user_id' => $horse->primary_owner_user_id,
                    'assignment_id' => $assignment->id,
                    'assignment_status' => $assignment->status,
                ]);

                Notification::make()->success()
                    ->title(__('app/horse.action.import_from_registry.success_title'))
                    ->body(__('app/horse.action.import_from_registry.success_body', [
                        'name' => $horse->name,
                        'status' => $assignment->status,
                    ]))
                    ->send();
            });
    }

    /**
     * Lookup koni dla owner'a po email. Zwraca [id => label] dla Select'a.
     * Pusty array gdy nie ma matching User'a / koni — Select pokazuje
     * "no options" message domyślnie.
     *
     * @return array<string,string>
     */
    public static function resolveHorseOptions(string $email): array
    {
        $email = trim($email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [];
        }

        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            return [];
        }

        return CentralHorseRegistry::query()
            ->where('primary_owner_user_id', $user->id)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (CentralHorseRegistry $h) => [
                $h->id => sprintf(
                    '%s (%s)',
                    $h->name,
                    $h->passport_no ?: __('app/horse.action.import_from_registry.no_passport'),
                ),
            ])
            ->all();
    }

    /**
     * Status banner: ile koni znaleziono / sugestie gdy 0.
     */
    public static function renderLookupStatus(string $email): string
    {
        $email = trim($email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return '';
        }

        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            return __('app/horse.action.import_from_registry.lookup.user_not_found');
        }

        $count = CentralHorseRegistry::query()
            ->where('primary_owner_user_id', $user->id)
            ->count();

        if ($count === 0) {
            return __('app/horse.action.import_from_registry.lookup.no_horses', ['email' => $email]);
        }

        return __('app/horse.action.import_from_registry.lookup.found', ['count' => $count]);
    }
}
