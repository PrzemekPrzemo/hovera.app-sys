<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Domain\Transport\Favorites\TransportFavoriteManager;
use App\Filament\Concerns\RestrictedByTenantRole;
use App\Models\Central\Tenant;
use App\Services\Tenancy\TenantRoleGate;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Stajnia oznacza ulubionych transporterów (max 5). Patrz docs/TRANSPORT.md §5.1.
 *
 * Pre-fill'owane w tryb DIRECT przy składaniu zapytania transportowego —
 * stajnia z listy 5 wybiera 1-3 do których faktycznie chce wysłać lead.
 *
 * Lista transporterów = wszyscy verified tenant'y typu transporter
 * (TransportFavoriteManager::availableTransportersQuery).
 */
class TransportFavorites extends Page
{
    use RestrictedByTenantRole;

    /** @return list<string> */
    protected static function allowedRoles(): array
    {
        return TenantRoleGate::FULL_ADMINS_AND_MANAGERS;
    }

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    public static function getNavigationLabel(): string
    {
        return __('app/transport_favorites.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.tools');
    }

    public function getTitle(): string|Htmlable
    {
        return __('app/transport_favorites.title');
    }

    protected static ?int $navigationSort = 50;

    protected static string $view = 'filament.app.pages.transport-favorites';

    public string $search = '';

    public function toggle(string $transporterId): void
    {
        abort_unless(self::canAccess(), 403);

        $stable = app(TenantManager::class)->tenantOrFail();
        $manager = app(TransportFavoriteManager::class);

        if ($manager->isFavorite($stable, $transporterId)) {
            $manager->remove($stable, $transporterId);
            app(TenantAuditLogger::class)->record(
                'transport.favorite_removed',
                'Tenant',
                $transporterId,
            );
            Notification::make()
                ->title(__('app/transport_favorites.notify.removed'))
                ->success()
                ->send();

            return;
        }

        $transporter = Tenant::query()->where('id', $transporterId)->first();
        if (! $transporter) {
            return;
        }

        try {
            $added = $manager->add($stable, $transporter);
        } catch (\DomainException $e) {
            Notification::make()
                ->danger()
                ->title(__('app/transport_favorites.notify.error'))
                ->body($e->getMessage())
                ->send();

            return;
        }

        if (! $added) {
            Notification::make()
                ->warning()
                ->title(__('app/transport_favorites.notify.limit_reached'))
                ->body(__('app/transport_favorites.notify.limit_body', ['limit' => $manager->limit()]))
                ->send();

            return;
        }

        app(TenantAuditLogger::class)->record(
            'transport.favorite_added',
            'Tenant',
            $transporterId,
        );
        Notification::make()
            ->success()
            ->title(__('app/transport_favorites.notify.added'))
            ->send();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $stable = app(TenantManager::class)->tenantOrFail();
        $manager = app(TransportFavoriteManager::class);
        $favoriteIds = $manager->idsFor($stable);

        $query = $manager->availableTransportersQuery();
        if (trim($this->search) !== '') {
            $query->where(function ($q) {
                $q->where('name', 'like', '%'.trim($this->search).'%')
                    ->orWhere('legal_name', 'like', '%'.trim($this->search).'%')
                    ->orWhere('slug', 'like', '%'.trim($this->search).'%');
            });
        }

        $rows = $query->orderBy('name')->limit(100)->get();

        // Ulubieni na górze (sortowanie w PHP — łatwiejsze niż w SQL przy
        // arbitralnej liście ID'ków).
        $rows = $rows->sortBy(fn (Tenant $t) => in_array($t->id, $favoriteIds, true) ? 0 : 1)->values();

        return [
            'rows' => $rows,
            'favoriteIds' => $favoriteIds,
            'limit' => $manager->limit(),
            'currentCount' => count($favoriteIds),
        ];
    }
}
