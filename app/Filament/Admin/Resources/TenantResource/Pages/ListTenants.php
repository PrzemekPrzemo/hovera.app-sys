<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TenantResource\Pages;

use App\Actions\Tenants\CreateTenant;
use App\Filament\Admin\Resources\TenantResource;
use App\Services\MasterAuditLogger;
use App\Tenancy\TenantManager;
use Database\Seeders\Demo\HoveraDemoSeeder;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;

class ListTenants extends ListRecords
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('create_demo_tenant')
                ->label('Utwórz demo stajnię')
                ->icon('heroicon-o-sparkles')
                ->color('warning')
                ->modalHeading('Utwórz demo stajnię z gotowymi danymi pokazowymi')
                ->modalDescription('Stajnia zostanie utworzona przez CreateTenant action (DB + user MySQL), zmigrowana, a następnie zaserwowana gotowym zestawem demo (14 koni, 6 klientów, kalendarz, faktury).')
                ->form([
                    Forms\Components\TextInput::make('slug')
                        ->label('Slug stajni')
                        ->default('demo')
                        ->required()
                        ->regex('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/')
                        ->helperText('Lowercase, dozwolone -. Na bazie slug-a będzie URL: app.hovera.app/s/{slug}'),
                    Forms\Components\TextInput::make('name')
                        ->label('Nazwa wyświetlana')
                        ->default('Stadnina Demo')
                        ->required(),
                    Forms\Components\TextInput::make('owner_email')
                        ->label('Email właściciela')
                        ->email()
                        ->default('demo@hovera.app')
                        ->required()
                        ->helperText('Owner dostanie zaproszenie mailem (lub w logu w trybie MAIL_MAILER=log).'),
                    Forms\Components\TextInput::make('owner_name')
                        ->label('Imię i nazwisko ownera')
                        ->default('Demo Owner')
                        ->required(),
                ])
                ->action(function (array $data, CreateTenant $createTenant, TenantManager $tm, HoveraDemoSeeder $seeder, MasterAuditLogger $audit) {
                    try {
                        $tenant = $createTenant->execute([
                            'slug' => $data['slug'],
                            'name' => $data['name'],
                            'country' => 'PL',
                            'locale' => 'pl',
                            'timezone' => 'Europe/Warsaw',
                            'currency' => 'PLN',
                            'owner_email' => $data['owner_email'],
                            'owner_name' => $data['owner_name'],
                        ]);
                        $tm->setCurrent($tenant);
                        Artisan::call('migrate', [
                            '--database' => 'tenant',
                            '--path' => 'database/migrations/tenant',
                            '--force' => true,
                        ]);
                        $seeder->run();
                        $audit->record('tenant.demo_created', 'Tenant', $tenant->id, $tenant->id, [
                            'slug' => $tenant->slug,
                        ]);
                        Notification::make()->success()
                            ->title('Demo stajnia utworzona')
                            ->body("Slug: {$tenant->slug}. Owner ({$data['owner_email']}) dostał link zaproszenia.")
                            ->persistent()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()->danger()
                            ->title('Nie udało się utworzyć demo stajni')
                            ->body($e->getMessage().' (sprawdź uprawnienia provisionera DB)')
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }
}
