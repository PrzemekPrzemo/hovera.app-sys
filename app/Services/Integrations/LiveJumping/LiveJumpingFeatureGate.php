<?php

declare(strict_types=1);

namespace App\Services\Integrations\LiveJumping;

use App\Models\Central\SystemSetting;

/**
 * Pojedyncze źródło prawdy dla pytania „czy integracja LiveJumping jest
 * aktywna". Master admin włącza/wyłącza w /admin/live-jumping-settings;
 * gdy OFF — żaden komponent UI związany z LJ nie renderuje się.
 *
 * Trzymane jako osobna klasa (a nie metoda na kliencie) bo:
 * - resource'y/widgety pytają o stan przed instancjonowaniem klienta,
 * - cachowalny check (cache wewnątrz settera w SystemSetting),
 * - testy łatwo mockują przez $this->app->instance().
 */
class LiveJumpingFeatureGate
{
    public function enabled(): bool
    {
        return (bool) SystemSetting::getValue('livejumping.enabled', false)
            && SystemSetting::getSecret('livejumping.api_token') !== null
            && filled(SystemSetting::getValue('livejumping.api_url'));
    }

    public function disabled(): bool
    {
        return ! $this->enabled();
    }
}
