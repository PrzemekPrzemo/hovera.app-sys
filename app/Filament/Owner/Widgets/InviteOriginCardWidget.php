<?php

declare(strict_types=1);

namespace App\Filament\Owner\Widgets;

use App\Models\Central\HorseBoardingAssignment;
use App\Models\Central\Tenant;
use App\Tenancy\TenantManager;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

/**
 * Card "Stajnia X cię zaprosiła" — pokazuje się na dashboardzie owner'a
 * gdy rejestracja przyszła przez invite link (`/register/horse-owner?
 * stable={ulid}&token={hex}`). HorseOwnerRegistrationController:96
 * zapisuje `invite_origin` w tenant.settings.
 *
 * Cel: retention. User zarejestrował się bo stajnia mu wysłała link —
 * chce go skierować do "Dodaj konia + Połącz ze stajnią" zamiast
 * zostawić bez wskazówki.
 *
 * Widget znika po stworzeniu pierwszego active assignment z TĄ stajnią
 * (defer logic: porównujemy `stable_tenant_id` z invite vs assignments).
 *
 * Sort = -9 → najwyżej, nad OnboardingBannerWidget (-8) bo to specific
 * call-to-action wynikający z faktu rejestracji przez konkretną stajnię.
 */
class InviteOriginCardWidget extends Widget
{
    protected static ?int $sort = -9;

    protected static string $view = 'filament.owner.widgets.invite-origin-card';

    protected int|string|array $columnSpan = 'full';

    public bool $dismissed = false;

    public function mount(): void
    {
        $this->dismissed = (bool) session('owner.invite_origin_dismissed', false);
    }

    public function dismiss(): void
    {
        session()->put('owner.invite_origin_dismissed', true);
        $this->dismissed = true;
    }

    public static function canView(): bool
    {
        $tenant = app(TenantManager::class)->current();
        if ($tenant === null) {
            return false;
        }

        $origin = self::invite($tenant);
        if ($origin === null) {
            return false;
        }

        if ((bool) session('owner.invite_origin_dismissed', false) === true) {
            return false;
        }

        // Card znika gdy juz ZALATWIONE — user ma active assignment z TA
        // stajnia. Wtedy nie ma sensu zachecac dalej, retention zalapana.
        $hasActiveWithThatStable = HorseBoardingAssignment::query()
            ->where('stable_tenant_id', $origin['stable_tenant_id'])
            ->where('status', HorseBoardingAssignment::STATUS_ACTIVE)
            ->where(function ($q) use ($tenant) {
                $q->whereHas('centralHorse', fn ($h) => $h->where('owner_tenant_id', $tenant->id))
                    ->orWhere('owner_user_id', $tenant->memberships()->where('role', 'owner')->value('user_id'));
            })
            ->exists();

        return ! $hasActiveWithThatStable;
    }

    /**
     * @return array{stable_tenant_id:string, stable_name:string, stable_slug:string, received_at:?string}|null
     */
    public function getInvite(): ?array
    {
        $tenant = app(TenantManager::class)->current();

        return $tenant === null ? null : self::invite($tenant);
    }

    public function getReceivedAtRelative(): string
    {
        $invite = $this->getInvite();
        if ($invite === null || empty($invite['received_at'])) {
            return '';
        }
        try {
            return Carbon::parse($invite['received_at'])->diffForHumans();
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * @return array{stable_tenant_id:string, stable_name:string, stable_slug:string, received_at:?string}|null
     */
    private static function invite(Tenant $tenant): ?array
    {
        $origin = data_get($tenant->settings, 'invite_origin');
        if (! is_array($origin) || empty($origin['stable_tenant_id'])) {
            return null;
        }

        return [
            'stable_tenant_id' => (string) $origin['stable_tenant_id'],
            'stable_name' => (string) ($origin['stable_name'] ?? ''),
            'stable_slug' => (string) ($origin['stable_slug'] ?? ''),
            'received_at' => isset($origin['received_at']) ? (string) $origin['received_at'] : null,
        ];
    }
}
