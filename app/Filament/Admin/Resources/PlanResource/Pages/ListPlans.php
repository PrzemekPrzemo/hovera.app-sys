<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PlanResource\Pages;

use App\Filament\Admin\Resources\PlanResource;
use App\Models\Central\Plan;
use Database\Seeders\PlansSeeder;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListPlans extends ListRecords
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('seed_defaults')
                ->label('Zainstaluj domyślne plany')
                ->icon('heroicon-o-sparkles')
                ->color('warning')
                ->visible(fn () => Plan::query()->count() === 0)
                ->requiresConfirmation()
                ->modalHeading('Zainstalować 5 domyślnych planów?')
                ->modalDescription('Free, Solo, Stable, Pro, Enterprise — z cenami i limitami z marketing site.')
                ->action(function () {
                    PlansSeeder::run();
                    Notification::make()
                        ->success()
                        ->title('Plany zainstalowane.')
                        ->body('5 planów dodanych. Możesz teraz przypisać je do stajni w /admin/tenants.')
                        ->send();
                }),
        ];
    }
}
