<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Central\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'free',
                'name' => 'Free',
                'price_monthly_cents' => 0,
                'price_yearly_cents' => 0,
                'limits' => ['max_horses' => 5, 'max_clients' => 10, 'max_seats' => 1, 'online_booking' => false],
                'features' => ['ksef' => false, 'mobile' => true, 'ai_copilot' => false],
                'sort_order' => 0,
            ],
            [
                'code' => 'solo',
                'name' => 'Solo',
                'price_monthly_cents' => 4900,
                'price_yearly_cents' => 47000,
                'limits' => ['max_horses' => 10, 'max_clients' => 30, 'max_seats' => 1, 'online_booking' => true],
                'features' => ['ksef' => false, 'mobile' => true, 'ai_copilot' => false],
                'sort_order' => 10,
            ],
            [
                'code' => 'stable',
                'name' => 'Stable',
                'price_monthly_cents' => 14900,
                'price_yearly_cents' => 143000,
                'limits' => ['max_horses' => 30, 'max_clients' => 100, 'max_seats' => 5, 'online_booking' => true],
                'features' => ['ksef' => true, 'mobile' => true, 'ai_copilot' => false, 'passes' => true],
                'sort_order' => 20,
            ],
            [
                'code' => 'pro',
                'name' => 'Pro',
                'price_monthly_cents' => 34900,
                'price_yearly_cents' => 335000,
                'limits' => ['max_horses' => 100, 'max_clients' => null, 'max_seats' => 15, 'online_booking' => true],
                'features' => ['ksef' => true, 'mobile' => true, 'ai_copilot' => false, 'livery' => true, 'passes' => true, 'vanity_domain' => true],
                'sort_order' => 30,
            ],
            [
                'code' => 'enterprise',
                'name' => 'Enterprise',
                'price_monthly_cents' => 99900,
                'price_yearly_cents' => 959000,
                'limits' => ['max_horses' => null, 'max_clients' => null, 'max_seats' => null, 'online_booking' => true],
                'features' => ['ksef' => true, 'peppol' => true, 'mobile' => true, 'ai_copilot' => true,
                    'livery' => true, 'passes' => true, 'white_label' => true, 'sso' => true, 'vanity_domain' => true],
                'sort_order' => 40,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['code' => $plan['code']], $plan);
        }
    }
}
