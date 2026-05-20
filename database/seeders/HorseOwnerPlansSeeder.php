<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\TenantType;
use App\Models\Central\Plan;
use Illuminate\Database\Seeder;

/**
 * Plan dla owner'ów — FREE forever. Brak limit'ów monetarnych ale
 * limity strukturalne (max liczba koni, max otwartych zamówień)
 * żeby chronić infra przed nadużyciem.
 *
 * Monetyzacja Hovera leży po stronie stable/transporter subscriptions —
 * owner = user-acquisition (consumer side marketplace'u).
 */
class HorseOwnerPlansSeeder extends Seeder
{
    public function run(): void
    {
        Plan::updateOrCreate(
            ['code' => 'owner_free'],
            [
                'audience' => TenantType::HorseOwner->value,
                'name' => 'Owner Free',
                'currency' => 'PLN',
                'price_monthly_cents' => 0,
                'price_yearly_cents' => 0,
                'limits' => [
                    'max_horses' => 10,
                    'max_open_orders' => 5,
                    // Owner nie wystawia faktur, nie obsługuje pensjonariuszy
                    'max_clients' => 0,
                    'max_seats' => 1,
                ],
                'features' => [
                    'order_transport' => true,
                    'view_quotes' => true,
                    'horse_documents' => true,
                    'vet_records' => true,
                    // Premium feature'y wyłączone — owner free tier
                    'invoicing' => false,
                    'ksef' => false,
                    'multi_user' => false,
                ],
                'sort_order' => 100,
                'is_active' => true,
                'is_public' => true,
            ],
        );
    }
}
