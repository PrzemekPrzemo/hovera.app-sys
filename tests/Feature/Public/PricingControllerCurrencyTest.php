<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use Database\Seeders\TransportAddonsSeeder;
use Database\Seeders\TransportPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /pricing/transport — currency toggle + Enterprise contact CTA.
 */
class PricingControllerCurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TransportPlansSeeder::seed();
        TransportAddonsSeeder::seed();
    }

    public function test_default_currency_pln_for_pl_locale(): void
    {
        $this->app->setLocale('pl');

        $resp = $this->get('/pricing/transport');
        $resp->assertOk();
        // Start plan = 250 PLN, rendered as "250"
        $resp->assertSee('250');
        $resp->assertSee('PLN / mc', false);
    }

    public function test_query_param_overrides_locale_default(): void
    {
        $resp = $this->get('/pricing/transport?currency=EUR');
        $resp->assertOk();
        // Pro plan = 129 EUR
        $resp->assertSee('129');
        $resp->assertSee('EUR /', false);
    }

    public function test_invalid_currency_falls_back_to_locale_default(): void
    {
        $this->app->setLocale('pl');
        $resp = $this->get('/pricing/transport?currency=JPY');
        $resp->assertOk();
        // JPY rejected → PLN (locale default)
        $resp->assertSee('PLN');
    }

    public function test_enterprise_shows_contact_cta(): void
    {
        $resp = $this->get('/pricing/transport');
        $resp->assertOk();
        $resp->assertSee('mailto:sales@hovera.app', false);
    }

    public function test_lock_in_and_promo_notices_present(): void
    {
        $resp = $this->get('/pricing/transport?lang=pl');
        $resp->assertOk();
        // Lock-in note + promo note pulled from lang/pl/transport/plans.php
        $resp->assertSee('12 mc', false);
        $resp->assertSee('31.07.2026', false);
    }

    public function test_addons_table_rendered(): void
    {
        // Addons stored with PL `name` field (no i18n on name itself);
        // assertions check raw DB-derived text.
        $resp = $this->get('/pricing/transport?lang=pl');
        $resp->assertOk();
        $resp->assertSee('Migracja danych z Excela', false);
        $resp->assertSee('Onboarding live 1:1', false);
        $resp->assertSee('Dodatkowy kierowca', false);
        $resp->assertSee('Dodatkowy pojazd', false);
    }

    public function test_all_four_transporter_plans_appear(): void
    {
        $resp = $this->get('/pricing/transport');
        $resp->assertOk();
        $resp->assertSee('Transport Start');
        $resp->assertSee('Transport Pro');
        $resp->assertSee('Transport Business');
        $resp->assertSee('Transport Enterprise');
    }
}
