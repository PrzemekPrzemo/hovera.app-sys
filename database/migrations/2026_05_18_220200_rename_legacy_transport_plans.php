<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Marketing spec sync: 3 stare plany (Solo/Pro/Fleet) zastępujemy
 * 4 nowymi (Start/Pro/Business/Enterprise). Kolizja występuje na kodzie
 * `transport_pro` — stary 349 PLN, nowy 549 PLN. Żeby nie nadepnąć
 * istniejącym subskrypcjom Stripe i pozwolić master adminowi ręcznie
 * przemigrować tenantów, robimy soft-rename:
 *
 *   transport_solo  → transport_solo_legacy
 *   transport_pro   → transport_pro_legacy
 *   transport_fleet → transport_fleet_legacy
 *
 * Oraz `is_active=false, is_public=false` żeby zniknęły z /pricing
 * i z domyślnych dropdownów. Plan dalej istnieje (tenants nadal mogą
 * być powiązani) — admin powinien przepiąć ich przez Filament UI po
 * uruchomieniu nowej oferty.
 *
 * Patrz docs/TRANSPORT.md §2 D2 + §15.4.
 */
return new class extends Migration
{
    public function up(): void
    {
        $legacyCodes = ['transport_solo', 'transport_pro', 'transport_fleet'];

        foreach ($legacyCodes as $code) {
            DB::connection('central')->table('plans')
                ->where('code', $code)
                ->update([
                    'code' => $code.'_legacy',
                    'is_active' => false,
                    'is_public' => false,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        $map = [
            'transport_solo_legacy' => 'transport_solo',
            'transport_pro_legacy' => 'transport_pro',
            'transport_fleet_legacy' => 'transport_fleet',
        ];

        foreach ($map as $from => $to) {
            // Tylko jeśli docelowego kodu jeszcze nie ma — inaczej nowy
            // `transport_pro` (Pro 549 PLN) byłby nadpisany przez stary.
            $exists = DB::connection('central')->table('plans')
                ->where('code', $to)->exists();
            if ($exists) {
                continue;
            }
            DB::connection('central')->table('plans')
                ->where('code', $from)
                ->update([
                    'code' => $to,
                    'is_active' => true,
                    'is_public' => true,
                    'updated_at' => now(),
                ]);
        }
    }
};
