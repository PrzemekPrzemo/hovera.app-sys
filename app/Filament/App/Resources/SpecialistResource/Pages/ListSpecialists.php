<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\SpecialistResource\Pages;

use App\Domain\Specialists\SpecialistInviteResult;
use App\Domain\Specialists\SpecialistInviteService;
use App\Filament\App\Resources\SpecialistResource;
use App\Tenancy\TenantManager;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListSpecialists extends ListRecords
{
    protected static string $resource = SpecialistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->inviteExternalSpecialistAction(),
            Actions\CreateAction::make()
                ->label(__('app/specialist.action.create.label')),
        ];
    }

    /**
     * "Zaproś weterynarza" — wywołuje `SpecialistInviteService` (PR O5
     * Channel B). Stable wpisuje email + nazwisko, system generuje
     * 7d magic link i wysyła mail z linkiem do `/specialist/setup`.
     *
     * Tworzy `ExternalSpecialist` w central DB (cross-tenant identity)
     * — osobne od tenant-level `Specialist` (per-stable contact). Stable
     * po pomyślnej rejestracji vet'a może dodać go do per-stable kontaktów
     * przez standardowy Create — osobnym PR pojawi się autolink.
     */
    private function inviteExternalSpecialistAction(): Actions\Action
    {
        return Actions\Action::make('invite_external_specialist')
            ->label(__('app/specialist.action.invite.label'))
            ->icon('heroicon-o-envelope')
            ->color('primary')
            ->form([
                Forms\Components\TextInput::make('email')
                    ->label(__('app/specialist.action.invite.email'))
                    ->email()
                    ->required()
                    ->placeholder('weterynarz@example.com'),
                Forms\Components\TextInput::make('display_name')
                    ->label(__('app/specialist.action.invite.display_name'))
                    ->required()
                    ->minLength(2)
                    ->maxLength(200)
                    ->placeholder(__('app/specialist.action.invite.display_name_placeholder')),
                Forms\Components\Select::make('specialty')
                    ->label(__('app/specialist.action.invite.specialty'))
                    ->options([
                        'vet' => __('app/specialist.specialty.vet'),
                        'farrier' => __('app/specialist.specialty.farrier'),
                        'groomer' => __('app/specialist.specialty.groomer'),
                        'dietetyk' => __('app/specialist.specialty.dietetyk'),
                        'other' => __('app/specialist.specialty.other'),
                    ])
                    ->default('vet'),
            ])
            ->modalHeading(__('app/specialist.action.invite.modal_heading'))
            ->modalDescription(__('app/specialist.action.invite.modal_description'))
            ->modalSubmitActionLabel(__('app/specialist.action.invite.submit'))
            ->action(function (array $data): void {
                $tenant = app(TenantManager::class)->current();
                $user = Auth::user();

                if ($tenant === null || $user === null) {
                    Notification::make()
                        ->danger()
                        ->title(__('app/specialist.action.invite.no_tenant'))
                        ->send();

                    return;
                }

                $result = app(SpecialistInviteService::class)->invite(
                    email: (string) $data['email'],
                    displayName: (string) $data['display_name'],
                    invitingTenant: $tenant,
                    invitingUser: $user,
                    extra: ['specialty' => $data['specialty'] ?? null],
                );

                match ($result->status) {
                    SpecialistInviteResult::STATUS_CREATED => Notification::make()
                        ->success()
                        ->title(__('app/specialist.action.invite.notify.created_title'))
                        ->body(__('app/specialist.action.invite.notify.created_body', ['email' => $result->specialist->email]))
                        ->send(),
                    SpecialistInviteResult::STATUS_REISSUED => Notification::make()
                        ->success()
                        ->title(__('app/specialist.action.invite.notify.reissued_title'))
                        ->body(__('app/specialist.action.invite.notify.reissued_body', ['email' => $result->specialist->email]))
                        ->send(),
                    SpecialistInviteResult::STATUS_EXISTING_ALREADY_SETUP => Notification::make()
                        ->warning()
                        ->title(__('app/specialist.action.invite.notify.already_setup_title'))
                        ->body(__('app/specialist.action.invite.notify.already_setup_body', ['email' => $result->specialist->email]))
                        ->send(),
                };
            });
    }
}
