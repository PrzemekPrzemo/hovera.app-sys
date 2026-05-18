<?php

declare(strict_types=1);

namespace App\Filament\Transport\Pages;

use App\Domain\Transport\ServiceAreas\TransportServiceAreaManager;
use App\Filament\Concerns\RestrictedByTenantRole;
use App\Services\Tenancy\TenantRoleGate;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Wybór województw obsługi — wpływa na to, kogo LeadDispatcher znajdzie
 * w trybie broadcast. Patrz docs/TRANSPORT.md §5.4 + §3.2.
 *
 * Checkbox-grid 16 województw. Przy zapisie pokazujemy info ile dodatkowych
 * województw "przyjmiesz" dzięki adjacency (kazdy sąsiad zwiększa zasięg
 * w mode broadcast).
 */
class ServiceAreas extends Page implements HasForms
{
    use InteractsWithForms;
    use RestrictedByTenantRole;

    /** @return list<string> */
    protected static function allowedRoles(): array
    {
        return TenantRoleGate::FULL_ADMINS;
    }

    protected static ?string $navigationIcon = 'heroicon-o-map';

    public static function getNavigationLabel(): string
    {
        return __('transport/service_areas.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.settings');
    }

    public function getTitle(): string|Htmlable
    {
        return __('transport/service_areas.title');
    }

    protected static ?int $navigationSort = 8;

    protected static string $view = 'filament.transport.pages.service-areas';

    /** @var array<string,mixed> */
    public array $data = [];

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);

        $tenant = app(TenantManager::class)->tenantOrFail();
        $this->form->fill([
            'voivodeships' => app(TransportServiceAreaManager::class)->listFor($tenant),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make(__('transport/service_areas.section.heading'))
                    ->description(__('transport/service_areas.section.description'))
                    ->schema([
                        Forms\Components\CheckboxList::make('voivodeships')
                            ->label(__('transport/service_areas.form.label.voivodeships'))
                            ->options(collect(TransportServiceAreaManager::allVoivodeships())
                                ->mapWithKeys(fn (string $v) => [$v => $v])
                                ->all())
                            ->columns(4)
                            ->bulkToggleable(),
                    ]),
            ]);
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);

        $tenant = app(TenantManager::class)->tenantOrFail();
        $selected = (array) ($this->form->getState()['voivodeships'] ?? []);

        $manager = app(TransportServiceAreaManager::class);
        $manager->sync($tenant, $selected);

        $coverage = $manager->effectiveCoverage($tenant);
        $direct = count($selected);
        $total = count($coverage);

        app(TenantAuditLogger::class)->record(
            'transport.service_areas_updated',
            'Tenant',
            (string) $tenant->id,
            ['direct_count' => $direct, 'effective_count' => $total],
        );

        Notification::make()
            ->success()
            ->title(__('transport/service_areas.notify.saved'))
            ->body(__('transport/service_areas.notify.saved_body', [
                'direct' => $direct,
                'effective' => $total,
            ]))
            ->send();
    }
}
