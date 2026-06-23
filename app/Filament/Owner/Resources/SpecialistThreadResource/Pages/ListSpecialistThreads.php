<?php

declare(strict_types=1);

namespace App\Filament\Owner\Resources\SpecialistThreadResource\Pages;

use App\Domain\Specialists\SpecialistInviteResult;
use App\Domain\Specialists\SpecialistInviteService;
use App\Filament\Owner\Resources\SpecialistThreadResource;
use App\Tenancy\TenantManager;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListSpecialistThreads extends ListRecords
{
    protected static string $resource = SpecialistThreadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->inviteSpecialistAction(),
            Actions\CreateAction::make()->label(__('owner/specialist_thread.action.new')),
        ];
    }

    /**
     * "Zaproś specjalistę" — reuse SpecialistInviteService z invitingUser =
     * właściciel (PR O5 epic 3). Tworzy ExternalSpecialist + 7d magic link.
     */
    private function inviteSpecialistAction(): Actions\Action
    {
        return Actions\Action::make('invite_specialist')
            ->label(__('owner/specialist_thread.invite.label'))
            ->icon('heroicon-o-envelope')
            ->color('primary')
            ->form([
                Forms\Components\TextInput::make('email')
                    ->label(__('owner/specialist_thread.invite.email'))
                    ->email()->required()->placeholder('weterynarz@example.com'),
                Forms\Components\TextInput::make('display_name')
                    ->label(__('owner/specialist_thread.invite.display_name'))
                    ->required()->minLength(2)->maxLength(200),
                Forms\Components\Select::make('specialty')
                    ->label(__('owner/specialist_thread.invite.specialty'))
                    ->options([
                        'vet' => __('app/specialist.specialty.vet'),
                        'farrier' => __('app/specialist.specialty.farrier'),
                        'dietetyk' => __('app/specialist.specialty.dietetyk'),
                        'other' => __('app/specialist.specialty.other'),
                    ])
                    ->default('vet'),
            ])
            ->modalHeading(__('owner/specialist_thread.invite.modal_heading'))
            ->modalDescription(__('owner/specialist_thread.invite.modal_description'))
            ->modalSubmitActionLabel(__('owner/specialist_thread.invite.submit'))
            ->action(function (array $data): void {
                $tenant = app(TenantManager::class)->current();
                $user = Auth::user();

                if ($tenant === null || $user === null) {
                    Notification::make()->danger()
                        ->title(__('owner/specialist_thread.invite.no_context'))->send();

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
                    SpecialistInviteResult::STATUS_CREATED,
                    SpecialistInviteResult::STATUS_REISSUED => Notification::make()->success()
                        ->title(__('owner/specialist_thread.invite.sent_title'))
                        ->body(__('owner/specialist_thread.invite.sent_body', ['email' => $result->specialist->email]))
                        ->send(),
                    SpecialistInviteResult::STATUS_EXISTING_ALREADY_SETUP => Notification::make()->success()
                        ->title(__('owner/specialist_thread.invite.exists_title'))
                        ->body(__('owner/specialist_thread.invite.exists_body', ['email' => $result->specialist->email]))
                        ->send(),
                };
            });
    }
}
