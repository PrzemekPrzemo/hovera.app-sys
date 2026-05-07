<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Tenants\CreateTenant;
use App\Models\Central\Tenant;
use App\Tenancy\TenantManager;
use Database\Seeders\Demo\HoveraDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class DemoSeedCommand extends Command
{
    protected $signature = 'hovera:demo:seed
        {--slug=demo : Tenant slug to seed (auto-utworzony jeśli nie istnieje)}
        {--name=Stadnina Demo : Display name dla nowego tenanta}
        {--owner-email=demo@hovera.app : Owner email (zaproszenie zostanie wysłane do logu)}
        {--owner-name=Demo Owner : Owner display name}
        {--fresh : Drop wszystkie tabele tenanta przed seedingiem (clean state)}';

    protected $description = 'Generuje spójny zestaw demo dla pojedynczego tenanta — klienci, konie, boxy, kalendarz, faktury.';

    public function handle(CreateTenant $createTenant, TenantManager $tenants, HoveraDemoSeeder $seeder): int
    {
        $slug = (string) $this->option('slug');
        $tenant = Tenant::query()->where('slug', $slug)->first();

        if (! $tenant) {
            $this->info("Tenant '{$slug}' nie istnieje — tworzę przez CreateTenant action…");
            try {
                $tenant = $createTenant->execute([
                    'slug' => $slug,
                    'name' => (string) $this->option('name'),
                    'country' => 'PL',
                    'locale' => 'pl',
                    'timezone' => 'Europe/Warsaw',
                    'currency' => 'PLN',
                    'owner_email' => (string) $this->option('owner-email'),
                    'owner_name' => (string) $this->option('owner-name'),
                ]);
            } catch (\Throwable $e) {
                $this->error('Nie udało się utworzyć tenanta: '.$e->getMessage());
                $this->line('Sprawdź czy provisioner DB ma uprawnienia (CREATE/DROP DATABASE, CREATE USER, GRANT OPTION).');

                return self::FAILURE;
            }
            $this->info("✓ Tenant utworzony (DB: {$tenant->db_name})");
        } else {
            $this->info("Używam istniejącego tenanta '{$slug}'.");
        }

        // Switch context — tenant connection wskaże na demo DB
        $tenants->setCurrent($tenant);

        if ($this->option('fresh')) {
            $this->warn("Czyszczę bazę tenanta '{$slug}' (migrate:fresh)…");
            Artisan::call('migrate:fresh', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--realpath' => false,
                '--force' => true,
            ], $this->getOutput());
        } else {
            // Sprawdź czy migracje są wgrane (ważne dla świeżych tenantów)
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--realpath' => false,
                '--force' => true,
            ], $this->getOutput());
        }

        $this->info('Generuję demo dane…');
        $seeder->run();

        // Ustaw branding + public profile dla demo tenant'a — żeby /s/{slug}
        // miało pełną treść (logo, opis, kontakt, godziny, lista instruktorów).
        $tenant->forceFill([
            'branding' => [
                'primary_color' => '#A8956B', // Hovera ochre
                'logo_url' => null,
            ],
            'settings' => array_merge((array) $tenant->settings, [
                'public_profile' => [
                    'tagline' => 'Stajnia z duszą — pensjonat, lekcje, rekreacja',
                    'description' => 'Kameralna stajnia z 12 boksami położona w pięknej okolicy. '.
                        'Prowadzimy pensjonat dla koni, lekcje jazdy konnej dla początkujących i zaawansowanych, '.
                        "treningi przygotowujące do zawodów, organizujemy obozy jeździeckie i imprezy okolicznościowe.\n\n".
                        'Indywidualne podejście do każdego konia. Codzienna opieka weterynaryjna, '.
                        'stała współpraca z kowalem i fizjoterapeutą.',
                    'email' => 'kontakt@demo-stajnia.pl',
                    'phone' => '+48 600 100 200',
                    'address' => 'ul. Jeździecka 1, 00-001 Warszawa',
                    'website' => 'https://hovera.app',
                    'opening_hours' => 'Pn–Pt: 9:00–20:00 · Sob–Nd: 8:00–18:00',
                    'show_box_availability' => true,
                    'show_instructors' => true,
                ],
            ]),
        ])->save();

        // Cache resetuję (`/s/{slug}` ma cache 5 min)
        Cache::forget("public_site:{$tenant->slug}");
        Cache::forget("public_box_availability:{$tenant->slug}");

        $this->newLine();
        $this->info('✓ Demo gotowe.');
        $this->line('');
        $this->line('  Panel stajni:    '.config('app.url').'/app  (login: '.$this->option('owner-email').')');
        $this->line('  Public site:     '.config('app.url').'/'.config('hovera.public_site.prefix', 's').'/'.$tenant->slug);
        $this->line('  Tenant slug:     '.$tenant->slug);
        $this->line('  DB:              '.$tenant->db_name);
        $this->newLine();
        $this->line('  Zaproszenie ownera poszło do logu (storage/logs/laravel-*.log) — szukaj URL "/invite/...".');
        $this->line('  W trybie produkcyjnym mailer wysyłałby link na '.$this->option('owner-email').'.');

        return self::SUCCESS;
    }
}
