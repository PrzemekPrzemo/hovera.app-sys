<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Transport\Fuel\Exceptions\FuelFetchException;
use App\Domain\Transport\Fuel\Fetchers\EPetrolScraper;
use App\Models\Central\FuelPrice;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Nightly scrape — pobiera krajową średnią ceny paliw z e-petrol.pl i
 * zapisuje snapshot do central `fuel_prices`. Idempotentne: unique key
 * (fuel_type, snapshot_date, source) zapobiega duplikatom przy retry.
 *
 * Schedule: codziennie o 06:00 (po publikacji nowych cen, przed start dnia
 * pracy klienta). W razie 503 e-petrol-a — log + retry przy następnym tick'u.
 *
 *   php artisan transport:scrape-fuel
 *   php artisan transport:scrape-fuel --fuel-type=diesel --dry-run
 */
class TransportScrapeFuelCommand extends Command
{
    protected $signature = 'transport:scrape-fuel
        {--fuel-type=diesel : diesel | petrol_95 | petrol_98 | lpg}
        {--dry-run : tylko log, bez zapisu do bazy}';

    protected $description = 'Pobierz dzienną średnią cenę paliwa z e-petrol.pl i zapisz snapshot.';

    public function handle(EPetrolScraper $scraper): int
    {
        $fuelType = (string) $this->option('fuel-type');
        $dryRun = (bool) $this->option('dry-run');

        try {
            ['price' => $price, 'raw' => $raw] = $scraper->fetch($fuelType);
        } catch (FuelFetchException $e) {
            $this->error('Fetch failed: '.$e->getMessage());
            report($e);

            return self::FAILURE;
        }

        $this->info(sprintf('e-petrol [%s] = %.2f PLN/L', $fuelType, $price));

        if ($dryRun) {
            $this->warn('--dry-run: nie zapisuję snapshotu.');

            return self::SUCCESS;
        }

        $today = Carbon::now()->toDateString();
        FuelPrice::query()->updateOrCreate(
            [
                'fuel_type' => $fuelType,
                'snapshot_date' => $today,
                'source' => FuelPrice::SOURCE_EPETROL,
            ],
            [
                'price_pln' => $price,
                'raw_payload' => $raw,
                'created_at' => now(),
            ],
        );

        $this->info('Snapshot zapisany.');

        return self::SUCCESS;
    }
}
