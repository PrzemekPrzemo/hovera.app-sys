<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Tenants\CreateTenant;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use App\Tenancy\TenantManager;
use Database\Seeders\Demo\HoveraDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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

        // Plan Pro — odblokowuje vanity domain section + KSeF + bulk
        // invoicing + raporty bez limitów. Demo bez tego wygląda jak free.
        $proPlan = Plan::query()->where('code', 'pro')->first();

        // Ustaw branding + public profile dla demo tenant'a — żeby /s/{slug}
        // miało pełną treść (logo, opis, kontakt, godziny, lista instruktorów).
        // Online booking ON żeby /book i self-booking w portalu działały.
        $tenant->forceFill([
            'plan_id' => $proPlan?->id ?? $tenant->plan_id,
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
                    'show_pricing' => true,
                ],
                'public_booking' => [
                    'enabled' => true,
                    'lesson_duration_minutes' => 60,
                    'working_hours_start' => '09:00',
                    'working_hours_end' => '20:00',
                    'advance_min_hours' => 4,
                    'advance_max_days' => 30,
                ],
            ]),
        ])->save();

        // Multi-role demo users — dzięki temu odwiedzający /demo może
        // zobaczyć panel z perspektywy każdej roli (matryca uprawnień).
        $this->ensureDemoMembers($tenant);

        // Cache resetuję (`/s/{slug}` ma cache 5 min)
        Cache::forget("public_site:{$tenant->slug}");
        Cache::forget("public_box_availability:{$tenant->slug}");

        $this->newLine();
        $this->info('✓ Demo gotowe.');
        $this->line('');
        $this->line('  Panel stajni:    '.config('app.url').'/app  (login: '.$this->option('owner-email').')');
        $this->line('  Demo URL:        '.config('app.url').'/demo  (auto-login bez rejestracji)');
        $this->line('  Public site:     '.config('app.url').'/'.config('hovera.public_site.prefix', 's').'/'.$tenant->slug);
        $this->line('  Tenant slug:     '.$tenant->slug);
        $this->line('  DB:              '.$tenant->db_name);
        $this->newLine();
        $this->line('  Zaproszenie ownera poszło do logu (storage/logs/laravel-*.log) — szukaj URL "/invite/...".');
        $this->line('  W trybie produkcyjnym mailer wysyłałby link na '.$this->option('owner-email').'.');

        return self::SUCCESS;
    }

    /**
     * Tworzy 5 dodatkowych userów (manager / instructor / employee / vet
     * / viewer) z odpowiednimi rolami, żeby demo użytkownik mógł
     * zobaczyć panel z perspektywy każdej roli (różnice w sidebarze).
     *
     * Każdy user ma random password — nie da się ich zalogować przez
     * standardowy login form. Wejście tylko przez /demo (które loguje
     * jako owner) lub przez nawigację demo-roles (osobny flow).
     */
    private function ensureDemoMembers(Tenant $tenant): void
    {
        $members = [
            ['email' => 'manager@hovera.app', 'name' => 'Demo Manager', 'role' => 'manager'],
            ['email' => 'trener@hovera.app', 'name' => 'Demo Trener', 'role' => 'instructor'],
            ['email' => 'pracownik@hovera.app', 'name' => 'Demo Pracownik', 'role' => 'employee'],
            ['email' => 'vet@hovera.app', 'name' => 'Demo Weterynarz', 'role' => 'vet'],
            ['email' => 'viewer@hovera.app', 'name' => 'Demo Viewer', 'role' => 'viewer'],
        ];

        foreach ($members as $m) {
            $user = User::query()->firstOrCreate(
                ['email' => $m['email']],
                [
                    'id' => (string) Str::ulid(),
                    'name' => $m['name'],
                    'password' => Hash::make(Str::random(40)),
                    'is_master_admin' => false,
                ],
            );

            TenantMembership::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'user_id' => $user->id],
                [
                    'id' => (string) Str::ulid(),
                    'role' => $m['role'],
                    'joined_at' => now(),
                ],
            );
        }
    }
}
