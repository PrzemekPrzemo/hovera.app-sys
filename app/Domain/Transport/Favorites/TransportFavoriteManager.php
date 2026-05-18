<?php

declare(strict_types=1);

namespace App\Domain\Transport\Favorites;

use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Models\Central\Tenant;
use App\Models\Central\TransportFavorite;
use Illuminate\Database\Eloquent\Builder;

/**
 * Per-stajnia lista ulubionych transporterów. Patrz docs/TRANSPORT.md §5.1 +
 * §4. Limit ustalony jako konfigurowalny — domyślnie 5 (OP3 z planu;
 * decyzja "5 max" wybrana po doświadczeniach z marketplaces innych
 * branż — wystarczy żeby pokryć typowy wybór, mało żeby user nie tworzył
 * "ulubionych" o niskiej istotności).
 *
 * Tabela transport_favorites (PR #192) ma dwie ścieżki: stable_tenant_id
 * (tenant stajnia) LUB user_id (logged-in user bez tenant'a). Tu robimy
 * per-stable, bo cała kadra współdzieli listę.
 */
class TransportFavoriteManager
{
    public const DEFAULT_LIMIT = 5;

    public function limit(): int
    {
        return (int) config('transport.favorites.limit', self::DEFAULT_LIMIT);
    }

    /**
     * @return array<int, string>  list of transporter tenant ids
     */
    public function idsFor(Tenant $stableTenant): array
    {
        return TransportFavorite::query()
            ->where('stable_tenant_id', $stableTenant->id)
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->pluck('transporter_tenant_id')
            ->all();
    }

    public function isFavorite(Tenant $stableTenant, string $transporterTenantId): bool
    {
        return TransportFavorite::query()
            ->where('stable_tenant_id', $stableTenant->id)
            ->where('transporter_tenant_id', $transporterTenantId)
            ->exists();
    }

    /**
     * Dodaje transportera do ulubionych. Idempotent. Zwraca true gdy dodano,
     * false gdy już był na liście lub przekroczono limit.
     *
     * @throws \DomainException gdy próba dodania nie-transportera lub
     *                         nie-verified (defensive — UI powinno
     *                         filtrować, ale bramka tu na pewność).
     */
    public function add(Tenant $stableTenant, Tenant $transporter): bool
    {
        if (! $transporter->isTransporter()) {
            throw new \DomainException('Can only favorite tenants of type=transporter.');
        }
        if (! $transporter->isVerifiedTransporter()) {
            throw new \DomainException('Cannot favorite an unverified transporter.');
        }

        if ($this->isFavorite($stableTenant, $transporter->id)) {
            return false;
        }

        $current = $this->idsFor($stableTenant);
        if (count($current) >= $this->limit()) {
            return false;
        }

        TransportFavorite::create([
            'stable_tenant_id' => $stableTenant->id,
            'transporter_tenant_id' => $transporter->id,
            'sort_order' => count($current),
        ]);

        return true;
    }

    /** Usuwa transportera z ulubionych. Idempotent. */
    public function remove(Tenant $stableTenant, string $transporterTenantId): void
    {
        TransportFavorite::query()
            ->where('stable_tenant_id', $stableTenant->id)
            ->where('transporter_tenant_id', $transporterTenantId)
            ->delete();
    }

    /**
     * Query do listy transporterów dostępnych "do oznaczenia jako ulubieni" —
     * weryfikowani tenanci typu transporter. Stosowane w TransportFavorites
     * Filament Page.
     */
    public function availableTransportersQuery(): Builder
    {
        return Tenant::query()
            ->where('type', TenantType::Transporter->value)
            ->where('verification_status', VerificationStatus::Verified->value);
    }
}
