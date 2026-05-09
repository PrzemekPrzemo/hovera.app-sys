<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Models\Tenant\Arena;
use App\Models\Tenant\BoardingService;
use App\Models\Tenant\Box;
use App\Models\Tenant\BoxAssignment;
use App\Models\Tenant\CalendarEntry;
use App\Models\Tenant\CalendarEntryParticipant;
use App\Models\Tenant\Client;
use App\Models\Tenant\FeedItem;
use App\Models\Tenant\FeedStockMovement;
use App\Models\Tenant\Horse;
use App\Models\Tenant\HorseDocument;
use App\Models\Tenant\HorseFeedingPlanItem;
use App\Models\Tenant\HorseMessage;
use App\Models\Tenant\HorsePhoto;
use App\Models\Tenant\HorseWeightMeasurement;
use App\Models\Tenant\Instructor;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\InvoiceItem;
use App\Models\Tenant\Pass;
use App\Models\Tenant\PassUse;
use App\Models\Tenant\Payment;
use App\Models\Tenant\TreatmentTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Generator demo danych dla pojedynczego tenanta. Wywoływany przez
 * `php artisan hovera:demo:seed`. Zakłada że context tenanta jest już
 * ustawiony (TenantManager::use(...)) i schemat zmigrowany.
 *
 * Idempotentność: NIE czyści istniejących danych — flaga `--fresh`
 * w komendzie odpala migrate:fresh przed seedem.
 */
class HoveraDemoSeeder
{
    private const HORSE_NAMES = [
        'Bucefał', 'Kasztanka', 'Pegaz', 'Iskra', 'Ferdynand',
        'Łaska', 'Tornado', 'Smok', 'Wichura', 'Jutrzenka',
        'Aladyn', 'Rusałka', 'Zefir', 'Mocarz',
    ];

    private const BREEDS = [
        'Małopolska', 'Wielkopolska', 'Konik polski', 'Półkrwi angielski',
        'Hanowerska', 'KWPN', 'Śląska', 'Oldenburg',
    ];

    private const COLORS = [
        'Kasztanowata', 'Gniada', 'Siwa', 'Ciemnogniada',
        'Tarantowata', 'Dereszowata', 'Kara', 'Bułana',
    ];

    /** @var array<int, array{name: string, email: string, phone: string, is_company?: bool, nip?: string, city?: string}> */
    private const CLIENTS = [
        ['name' => 'Anna Kowalska', 'email' => 'anna.kowalska@example.com', 'phone' => '+48 600 100 200', 'city' => 'Warszawa'],
        ['name' => 'Marek Nowak', 'email' => 'marek.nowak@example.com', 'phone' => '+48 600 200 300', 'city' => 'Kraków'],
        ['name' => 'Stadnina Wisła sp. z o.o.', 'email' => 'biuro@stadnina-wisla.pl', 'phone' => '+48 22 555 11 22', 'is_company' => true, 'nip' => '5260250274', 'city' => 'Warszawa'],
        ['name' => 'Katarzyna Wójcik', 'email' => 'k.wojcik@example.com', 'phone' => '+48 600 400 500', 'city' => 'Poznań'],
        ['name' => 'Piotr Lewandowski', 'email' => 'piotr.l@example.com', 'phone' => '+48 600 500 600', 'city' => 'Wrocław'],
        ['name' => 'Magdalena Kowalczyk', 'email' => 'm.kowalczyk@example.com', 'phone' => '+48 600 600 700', 'city' => 'Gdańsk'],
    ];

    /** @var array<int, array{name: string, color: string, hourly_cents: int}> */
    private const INSTRUCTORS = [
        ['name' => 'Tomasz Jeździec', 'color' => '#3D2E22', 'hourly_cents' => 12000],
        ['name' => 'Karolina Trener', 'color' => '#A8956B', 'hourly_cents' => 15000],
        ['name' => 'Adam Stajenny', 'color' => '#8F8576', 'hourly_cents' => 10000],
    ];

    public function run(): void
    {
        DB::connection('tenant')->transaction(function (): void {
            $instructors = $this->seedInstructors();
            $arenas = $this->seedArenas();
            $boxes = $this->seedBoxes();
            $services = $this->seedBoardingServices();
            $clients = $this->seedClients();
            $horses = $this->seedHorses($clients, $boxes, $services);
            $this->seedCalendarAndPasses($horses, $clients, $instructors, $arenas);
            $this->seedHealthAndActivities($horses);
            $this->seedDocumentsAndMessages($horses, $clients);
            $this->seedInvoicesAndPayments($clients, $horses);

            // Modules added in PR rounds 1+2 — without these the demo
            // panel feels incomplete and most "what's new" sections look empty.
            $this->seedFeedingPlans($horses);
            $this->seedFeedInventory();
            $this->seedHorseWeights($horses);
            $this->seedHorsePhotos($horses);
            $this->seedExtraTreatmentTemplates();
            $this->seedGroupLessons($horses, $clients, $instructors, $arenas);
        });
    }

    /** @return array<int, Instructor> */
    private function seedInstructors(): array
    {
        $out = [];
        foreach (self::INSTRUCTORS as $i) {
            $out[] = Instructor::create([
                'name' => $i['name'],
                'email' => Str::slug($i['name']).'@hovera-demo.app',
                'hourly_rate_cents' => $i['hourly_cents'],
                'color' => $i['color'],
                'is_active' => true,
            ]);
        }

        return $out;
    }

    /** @return array<int, Arena> */
    private function seedArenas(): array
    {
        return [
            Arena::create(['name' => 'Hala kryta', 'type' => 'indoor', 'color' => '#3D2E22', 'is_active' => true, 'sort_order' => 1]),
            Arena::create(['name' => 'Plac otwarty', 'type' => 'outdoor', 'color' => '#A8956B', 'is_active' => true, 'sort_order' => 2]),
        ];
    }

    /** @return array<int, Box> */
    private function seedBoxes(): array
    {
        $boxes = [];
        // Box.type enum: indoor / paddock / outdoor / quarantine
        for ($i = 1; $i <= 12; $i++) {
            $boxes[] = Box::create([
                'name' => sprintf('B-%02d', $i),
                'label' => 'Box '.$i,
                'type' => $i <= 10 ? 'indoor' : ($i === 11 ? 'paddock' : 'quarantine'),
                'size_m2' => $i <= 8 ? 12 : 16,
                'capacity' => 1,
                'monthly_rate_cents' => $i <= 8 ? 80000 : 95000,
                'is_active' => $i !== 12, // ostatni do remontu
                'sort_order' => $i,
                'notes' => $i === 12 ? 'Box w remoncie — niedostępny do końca miesiąca.' : null,
            ]);
        }

        return $boxes;
    }

    /** @return array<int, BoardingService> */
    private function seedBoardingServices(): array
    {
        $defs = [
            ['name' => 'Pensjonat pełny — siano + owies', 'unit' => 'szt.', 'frequency' => 'monthly', 'price_cents' => 150000, 'vat_rate' => 23, 'sort_order' => 1],
            ['name' => 'Wybieg dzienny (padok)', 'unit' => 'dzień', 'frequency' => 'daily', 'price_cents' => 1500, 'vat_rate' => 23, 'sort_order' => 2],
            ['name' => 'Ściółka — trociny, codziennie', 'unit' => 'm³', 'frequency' => 'monthly', 'price_cents' => 30000, 'vat_rate' => 8, 'sort_order' => 3],
            ['name' => 'Dodatkowy owies (porcja)', 'unit' => 'porcja', 'frequency' => 'daily', 'price_cents' => 800, 'vat_rate' => 8, 'sort_order' => 4],
            ['name' => 'Korekcja kopyt + kucie', 'unit' => 'wizyta', 'frequency' => 'per_use', 'price_cents' => 25000, 'vat_rate' => 23, 'sort_order' => 5],
            ['name' => 'Solarium 15 min', 'unit' => 'wizyta', 'frequency' => 'per_use', 'price_cents' => 3000, 'vat_rate' => 23, 'sort_order' => 6],
            ['name' => 'Trening lonżowanie', 'unit' => 'sesja', 'frequency' => 'per_use', 'price_cents' => 8000, 'vat_rate' => 23, 'sort_order' => 7],
        ];

        $out = [];
        foreach ($defs as $d) {
            $out[] = BoardingService::create([...$d, 'is_active' => true, 'description' => null]);
        }

        return $out;
    }

    /** @return array<int, Client> */
    private function seedClients(): array
    {
        $out = [];
        // Client.type enum: individual / family / organisation
        foreach (self::CLIENTS as $c) {
            $out[] = Client::create([
                'type' => ($c['is_company'] ?? false) ? 'organisation' : 'individual',
                'name' => $c['name'],
                'email' => $c['email'],
                'phone' => $c['phone'],
                'tax_id' => $c['nip'] ?? null,
                'city' => $c['city'] ?? null,
                'country' => 'PL',
                'rodo_consent_at' => now()->subMonths(rand(1, 12)),
                'rodo_consent_source' => 'demo_seeder',
            ]);
        }

        return $out;
    }

    /**
     * @param  array<int, Client>  $clients
     * @param  array<int, Box>  $boxes
     * @param  array<int, BoardingService>  $services
     * @return array<int, Horse>
     */
    private function seedHorses(array $clients, array $boxes, array $services): array
    {
        $sexes = ['mare', 'stallion', 'gelding'];
        $out = [];
        $horseCount = count(self::HORSE_NAMES);
        for ($i = 0; $i < $horseCount; $i++) {
            $owner = $clients[$i % count($clients)]; // round-robin
            $horse = Horse::create([
                'name' => self::HORSE_NAMES[$i],
                'breed' => self::BREEDS[$i % count(self::BREEDS)],
                'sex' => $sexes[$i % 3],
                'color' => self::COLORS[$i % count(self::COLORS)],
                'birth_date' => now()->subYears(rand(4, 18))->subMonths(rand(0, 11))->toDateString(),
                'owner_client_id' => $owner->id,
                'box_id' => $i < 11 ? $boxes[$i]->id : null, // 11 koni z boxami, kilka na padoku
                'microchip' => '985'.str_pad((string) rand(100000000000, 999999999999), 12, '0'),
                'passport_number' => 'PL-PASS-'.str_pad((string) ($i + 1), 6, '0', STR_PAD_LEFT),
                'ueln' => '616'.str_pad((string) (1000000 + $i), 12, '0', STR_PAD_LEFT),
            ]);

            // Box assignment history (jak istnieje box)
            if ($horse->box_id) {
                BoxAssignment::create([
                    'horse_id' => $horse->id,
                    'box_id' => $horse->box_id,
                    'assigned_at' => now()->subMonths(rand(1, 6)),
                    'reason' => 'Pierwsze przypisanie',
                ]);
            }

            // Pin 2-3 boarding services
            $picked = [$services[0]->id => ['quantity' => 1.0]]; // pensjonat pełny dla każdego
            if ($i % 2 === 0) {
                $picked[$services[1]->id] = ['quantity' => 1.0]; // padok
            }
            if ($i % 3 === 0) {
                $picked[$services[2]->id] = ['quantity' => 0.5]; // ściółka
            }
            $horse->boardingServices()->attach($picked);

            $out[] = $horse;
        }

        return $out;
    }

    /**
     * @param  array<int, Horse>  $horses
     * @param  array<int, Client>  $clients
     * @param  array<int, Instructor>  $instructors
     * @param  array<int, Arena>  $arenas
     */
    private function seedCalendarAndPasses(array $horses, array $clients, array $instructors, array $arenas): void
    {
        // Karnety dla 3 wybranych klientów
        $passClients = array_slice($clients, 0, 3);
        $passes = [];
        foreach ($passClients as $idx => $client) {
            $passes[$client->id] = Pass::create([
                'client_id' => $client->id,
                'name' => 'Karnet '.[8, 12, 4][$idx].'-wstępów',
                'total_uses' => [8, 12, 4][$idx],
                'remaining_uses' => [5, 8, 1][$idx], // część zużyta
                'valid_from' => now()->subMonth()->toDateString(),
                'valid_until' => now()->addMonths(2)->toDateString(),
                'price_cents' => [40000, 55000, 24000][$idx],
                'status' => 'active',
                'cancellation_policy_hours' => 12,
            ]);
        }

        // Przeszłe rezerwacje (8 sztuk, ostatnie 2 tygodnie)
        for ($d = 14; $d >= 1; $d -= 2) {
            $horse = $horses[array_rand($horses)];
            $instructor = $instructors[array_rand($instructors)];
            $arena = $arenas[array_rand($arenas)];
            $start = now()->subDays($d)->setTime(rand(9, 18), 0);
            $entry = CalendarEntry::create([
                'type' => 'lesson_individual',
                'starts_at' => $start,
                'ends_at' => $start->copy()->addMinutes(60),
                'horse_id' => $horse->id,
                'instructor_id' => $instructor->id,
                'arena_id' => $arena->id,
                'client_id' => $horse->owner_client_id,
                'status' => 'completed',
                'price_cents' => 12000,
                'title' => 'Lekcja '.$horse->name,
            ]);

            // 50% past entries skonsumowało karnet (jeśli właściciel ma)
            if (rand(0, 1) === 0 && isset($passes[$horse->owner_client_id])) {
                PassUse::create([
                    'pass_id' => $passes[$horse->owner_client_id]->id,
                    'calendar_entry_id' => $entry->id,
                    'consumed_at' => $start,
                ]);
            }
        }

        // Przyszłe rezerwacje (12 sztuk, do 4 tygodni do przodu)
        $types = ['lesson_individual', 'lesson_individual', 'training', 'lesson_group', 'care'];
        for ($d = 1; $d <= 28; $d += 2) {
            if (rand(0, 1) === 0) {
                continue;
            }
            $horse = $horses[array_rand($horses)];
            $start = now()->addDays($d)->setTime(rand(9, 18), 0);
            CalendarEntry::create([
                'type' => $types[array_rand($types)],
                'starts_at' => $start,
                'ends_at' => $start->copy()->addMinutes(60),
                'horse_id' => $horse->id,
                'instructor_id' => $instructors[array_rand($instructors)]->id,
                'arena_id' => $arenas[array_rand($arenas)]->id,
                'client_id' => $horse->owner_client_id,
                'status' => $d === 1 ? 'requested' : 'confirmed',
                'price_cents' => 12000,
                'title' => 'Lekcja '.$horse->name,
            ]);
        }
    }

    /**
     * @param  array<int, Horse>  $horses
     *
     * Bulk insert dla performance — single INSERT zamiast 70+ create()
     * skraca seed o ~30s na slow MySQL. Pomijamy Eloquent observers
     * (w demo seeded data nie potrzeba).
     */
    private function seedHealthAndActivities(array $horses): void
    {
        $now = now();
        $healthRows = [];
        $activityRows = [];

        $activityTypes = ['feeding', 'grooming', 'turnout', 'exercise', 'box_cleaning'];
        $activitySummaries = ['Karmienie poranne', 'Czesanie i mycie', 'Wybieg na padok', 'Lonża 30 min', 'Sprzątanie boksu'];

        foreach ($horses as $i => $horse) {
            // Health: szczepienie + kucie + (co 3-ty) dentysta
            $healthRows[] = [
                'id' => (string) Str::ulid(),
                'horse_id' => $horse->id,
                'type' => 'vaccination',
                'performed_at' => $now->copy()->subMonths(2)->subDays(rand(0, 28)),
                'performed_by' => 'lek. wet. M. Kowalski',
                'summary' => 'Szczepienie na grypę + tężec (przypomnienie)',
                'next_due_at' => $now->copy()->addMonths(10)->toDateString(),
                'cost_cents' => 18000,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $healthRows[] = [
                'id' => (string) Str::ulid(),
                'horse_id' => $horse->id,
                'type' => 'farrier',
                'performed_at' => $now->copy()->subWeeks(rand(2, 6)),
                'performed_by' => 'A. Kowal — kowal z 15-letnim stażem',
                'summary' => 'Korekcja + kucie na 4 nogi',
                'next_due_at' => $now->copy()->addWeeks(8)->toDateString(),
                'cost_cents' => 25000,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if ($i % 3 === 0) {
                $healthRows[] = [
                    'id' => (string) Str::ulid(),
                    'horse_id' => $horse->id,
                    'type' => 'dentist',
                    'performed_at' => $now->copy()->subMonths(rand(4, 8)),
                    'performed_by' => 'dr P. Stomatolog',
                    'summary' => 'Korekcja zębów rocznych',
                    'cost_cents' => 35000,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Activity log — 5 wpisów / koń = 70 dla 14 koni
            for ($a = 0; $a < 5; $a++) {
                $activityRows[] = [
                    'id' => (string) Str::ulid(),
                    'horse_id' => $horse->id,
                    'type' => $activityTypes[$a],
                    'performed_at' => $now->copy()->subDays($a)->setTime(rand(7, 19), rand(0, 59)),
                    'performed_by' => 'A. Stajenny',
                    'summary' => $activitySummaries[$a],
                    'cost_cents' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Bulk insert — chunked po 50 żeby nie hit MySQL max packet
        foreach (array_chunk($healthRows, 50) as $chunk) {
            DB::connection('tenant')->table('health_records')->insert($chunk);
        }
        foreach (array_chunk($activityRows, 50) as $chunk) {
            DB::connection('tenant')->table('stable_activities')->insert($chunk);
        }
    }

    /**
     * @param  array<int, Horse>  $horses
     * @param  array<int, Client>  $clients
     */
    private function seedDocumentsAndMessages(array $horses, array $clients): void
    {
        // Dokumenty — fake pliki na local disk
        Storage::disk('local')->makeDirectory('horse-documents/demo');
        foreach ($horses as $i => $horse) {
            $kinds = ['passport', 'insurance'];
            if ($i % 2 === 0) {
                $kinds[] = 'contract';
            }
            foreach ($kinds as $kind) {
                $fileName = $horse->id.'_'.$kind.'.txt';
                $path = 'horse-documents/demo/'.$fileName;
                Storage::disk('local')->put($path, "DEMO — {$kind} dla konia {$horse->name}");

                HorseDocument::create([
                    'horse_id' => $horse->id,
                    'name' => match ($kind) {
                        'passport' => 'Paszport '.$horse->name,
                        'insurance' => 'Polisa OC ZK',
                        'contract' => 'Umowa pensjonatu',
                        default => ucfirst($kind),
                    },
                    'kind' => $kind,
                    'file_path' => $path,
                    'original_name' => $fileName,
                    'mime' => 'text/plain',
                    'size_bytes' => Storage::disk('local')->size($path),
                    'uploaded_by_role' => 'stable',
                    'valid_from' => $kind === 'insurance' ? now()->subMonths(2)->toDateString() : null,
                    'valid_until' => $kind === 'insurance' ? now()->addMonths(10)->toDateString() : null,
                ]);
            }
        }

        // Wiadomości — 1 thread per pierwsze 3 konie
        foreach (array_slice($horses, 0, 3) as $horse) {
            HorseMessage::create([
                'horse_id' => $horse->id,
                'direction' => 'from_stable',
                'sender_user_id' => null,
                'client_id' => $horse->owner_client_id,
                'subject' => 'Stan zdrowia '.$horse->name,
                'body' => 'Dzień dobry, '.$horse->name." dziś przyjął karmę bardzo dobrze, jest spokojny i aktywny na padoku.\n\nPozdrawiamy,\nzespół stajni",
                'sent_at' => now()->subDays(3),
            ]);
            HorseMessage::create([
                'horse_id' => $horse->id,
                'direction' => 'from_client',
                'sender_user_id' => null,
                'client_id' => $horse->owner_client_id,
                'subject' => 'Re: Stan zdrowia '.$horse->name,
                'body' => 'Dziękuję za informację! Czy mogę przyjść w sobotę go odwiedzić?',
                'sent_at' => now()->subDays(2),
            ]);
        }
    }

    /**
     * @param  array<int, Client>  $clients
     * @param  array<int, Horse>  $horses
     */
    private function seedInvoicesAndPayments(array $clients, array $horses): void
    {
        // 5 faktur za pensjonat: 3 paid, 1 overdue, 1 issued (czeka)
        $statuses = ['paid', 'paid', 'paid', 'overdue', 'issued'];
        $monthsBack = [3, 2, 1, 1, 0];
        $clientsForInvoice = array_slice($clients, 0, 5);

        foreach ($clientsForInvoice as $i => $client) {
            $issuedAt = now()->subMonths($monthsBack[$i])->startOfMonth();
            $dueAt = $issuedAt->copy()->addDays(14);
            $net = 150000;
            $vat = (int) round($net * 0.23);
            $total = $net + $vat;

            $invoice = Invoice::create([
                'number' => sprintf('FV/%d/%02d/%04d', $i + 1, $issuedAt->month, $issuedAt->year),
                'kind' => 'fv',
                'status' => $statuses[$i],
                'client_id' => $client->id,
                'seller_name' => 'Stadnina Demo Hovera',
                'seller_nip' => '5252287513',
                'seller_address' => 'ul. Demo 1',
                'seller_postal_code' => '00-001',
                'seller_city' => 'Warszawa',
                'seller_country' => 'PL',
                'buyer_name' => $client->name,
                'buyer_nip' => $client->tax_id,
                'buyer_city' => $client->city,
                'buyer_country' => 'PL',
                'issued_at' => $issuedAt->toDateString(),
                'sale_date' => $issuedAt->toDateString(),
                'due_at' => $dueAt->toDateString(),
                'paid_at' => $statuses[$i] === 'paid' ? $issuedAt->copy()->addDays(rand(2, 14)) : null,
                'currency' => 'PLN',
                'subtotal_cents' => $net,
                'vat_cents' => $vat,
                'total_cents' => $total,
                'ksef_status' => 'pending',
            ]);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'position' => 1,
                'name' => 'Pensjonat '.$issuedAt->translatedFormat('F Y'),
                'description' => null,
                'quantity' => 1,
                'unit' => 'szt.',
                'vat_rate' => 23,
                'unit_price_cents' => $net,
                'net_cents' => $net,
                'vat_cents' => $vat,
                'total_cents' => $total,
            ]);

            if ($statuses[$i] === 'paid') {
                // PaymentProvider enum: none / stub / p24 / payu / stripe / mollie
                // PaymentStatus enum: pending / processing / succeeded / failed / refunded
                // 'none' + 'succeeded' = przelew bankowy ręczny rozliczony poza systemem
                Payment::create([
                    'client_id' => $client->id,
                    'invoice_id' => $invoice->id,
                    'amount_cents' => $total,
                    'currency' => 'PLN',
                    'provider' => 'none',
                    'provider_ref' => 'DEMO-'.Str::upper(Str::random(8)),
                    'status' => 'succeeded',
                    'paid_at' => $invoice->paid_at,
                ]);
            }
        }
    }

    /**
     * @param  array<int, Horse>  $horses
     */
    private function seedFeedingPlans(array $horses): void
    {
        // Pierwszych 8 koni dostaje pełny plan, reszta ma minimalny
        // (rano + wieczór). Przykład tego, jak owner może dostosować
        // dietę per koń.
        $fullPlan = [
            ['meal' => 'breakfast', 'feed' => 'Owies', 'amount' => 2.0, 'unit' => 'kg', 'notes' => null],
            ['meal' => 'breakfast', 'feed' => 'Siano łąkowe', 'amount' => 5.0, 'unit' => 'kg', 'notes' => 'porcja w siatce'],
            ['meal' => 'midday', 'feed' => 'Marchew', 'amount' => 1.0, 'unit' => 'kg', 'notes' => null],
            ['meal' => 'evening', 'feed' => 'Mash Cool Mix', 'amount' => 1.5, 'unit' => 'kg', 'notes' => 'namoczyć 30 min przed podaniem'],
            ['meal' => 'evening', 'feed' => 'Siano łąkowe', 'amount' => 6.0, 'unit' => 'kg', 'notes' => null],
        ];
        $minimalPlan = [
            ['meal' => 'breakfast', 'feed' => 'Siano łąkowe', 'amount' => 4.0, 'unit' => 'kg', 'notes' => null],
            ['meal' => 'evening', 'feed' => 'Siano łąkowe', 'amount' => 5.0, 'unit' => 'kg', 'notes' => null],
        ];

        foreach ($horses as $i => $horse) {
            $plan = $i < 8 ? $fullPlan : $minimalPlan;
            foreach ($plan as $sort => $row) {
                HorseFeedingPlanItem::create([
                    'id' => (string) Str::ulid(),
                    'horse_id' => $horse->id,
                    'meal' => $row['meal'],
                    'feed_type' => $row['feed'],
                    'amount_kg' => $row['amount'],
                    'unit' => $row['unit'],
                    'notes' => $row['notes'],
                    'is_active' => true,
                    'sort_order' => $sort,
                ]);
            }
        }
    }

    private function seedFeedInventory(): void
    {
        // 6 pozycji magazynu — 2 z nich celowo poniżej threshold żeby
        // owner zobaczył sidebar badge "Magazyn paszy 2".
        $items = [
            // [name, unit, threshold, current_stock_kg]
            ['Owies', 'kg', 100.0, 320.0],          // OK
            ['Siano łąkowe', 'bel', 20.0, 12.0],    // ⚠ poniżej progu
            ['Mash Cool Mix', 'kg', 30.0, 75.0],    // OK
            ['Marchew', 'kg', 50.0, 18.0],          // ⚠ poniżej progu
            ['Buraki cukrowe', 'kg', 40.0, 60.0],   // OK
            ['Lizawka mineralna', 'szt.', 5.0, 14.0], // OK
        ];

        foreach ($items as $sort => [$name, $unit, $threshold, $stock]) {
            $item = FeedItem::create([
                'id' => (string) Str::ulid(),
                'name' => $name,
                'unit' => $unit,
                'low_stock_threshold' => $threshold,
                'is_active' => true,
                'sort_order' => $sort,
            ]);

            // Jedna duża dostawa miesiąc temu — buduje stan początkowy.
            FeedStockMovement::create([
                'id' => (string) Str::ulid(),
                'feed_item_id' => $item->id,
                'delta' => $stock + 30,
                'kind' => 'purchase',
                'movement_date' => now()->subDays(30)->toDateString(),
                'notes' => 'Dostawa od dostawcy Y',
            ]);
            // Konsumpcja w ostatnich tygodniach — spadek do current.
            FeedStockMovement::create([
                'id' => (string) Str::ulid(),
                'feed_item_id' => $item->id,
                'delta' => -30,
                'kind' => 'consumption',
                'movement_date' => now()->subDays(7)->toDateString(),
                'notes' => null,
            ]);
        }
    }

    /**
     * @param  array<int, Horse>  $horses
     */
    private function seedHorseWeights(array $horses): void
    {
        // 8 koni × 6 miesięcznych pomiarów. Trend per koń:
        //  - 0,3,6:  stabilnie ±2 kg
        //  - 1,4,7:  przyrost +5 kg/mc (treningowy program)
        //  - 2,5:    spadek -3 kg/mc (jakaś kontuzja, owner widzi alert)
        foreach (array_slice($horses, 0, 8) as $idx => $horse) {
            $base = 520 + ($idx * 8);

            for ($monthAgo = 6; $monthAgo >= 0; $monthAgo--) {
                $delta = match ($idx % 3) {
                    0 => rand(-2, 2),
                    1 => (6 - $monthAgo) * 5,
                    2 => -((6 - $monthAgo) * 3),
                };
                HorseWeightMeasurement::create([
                    'id' => (string) Str::ulid(),
                    'horse_id' => $horse->id,
                    'measured_at' => now()->subMonths($monthAgo)->toDateString(),
                    'weight_kg' => round($base + $delta, 1),
                    'girth_cm' => round(180 + (($base + $delta - 520) * 0.05), 1),
                    'notes' => $monthAgo === 0 && ($idx % 3 === 2)
                        ? 'Spadek wagi — skonsultować z weterynarzem'
                        : null,
                ]);
            }
        }
    }

    /**
     * @param  array<int, Horse>  $horses
     */
    private function seedHorsePhotos(array $horses): void
    {
        // Demo bez prawdziwych zdjęć — generujemy placeholdery przez GD
        // (1×1 px PNG z hex kolorem stajni). Owner po wgraniu prawdziwych
        // zobaczy galerię zamiast szarych kafli.
        if (! function_exists('imagecreate')) {
            return; // GD niedostępne, pomijamy
        }

        foreach (array_slice($horses, 0, 6) as $horse) {
            for ($p = 1; $p <= 3; $p++) {
                $captionOptions = ['Zdjęcie portretowe', 'Trening dżeppingu', 'Padok', 'Po lekcji', 'Dzień zawodów'];
                $caption = $captionOptions[($p - 1) % count($captionOptions)];

                $img = imagecreate(400, 400);
                $bg = imagecolorallocate($img, rand(120, 200), rand(140, 180), rand(110, 160));
                imagefilledrectangle($img, 0, 0, 400, 400, $bg);
                $textColor = imagecolorallocate($img, 255, 255, 255);
                imagestring($img, 5, 130, 180, substr($horse->name, 0, 12), $textColor);

                ob_start();
                imagepng($img);
                $blob = (string) ob_get_clean();
                imagedestroy($img);

                $path = "tenants/demo/horses/{$horse->id}/photos/demo-{$p}.png";
                Storage::disk('local')->put($path, $blob);

                HorsePhoto::create([
                    'id' => (string) Str::ulid(),
                    'horse_id' => $horse->id,
                    'file_path' => $path,
                    'original_name' => "{$horse->name}-{$p}.png",
                    'mime' => 'image/png',
                    'size_bytes' => strlen($blob),
                    'caption' => $caption,
                    'sort_order' => $p,
                    'uploaded_by_role' => 'stable',
                ]);
            }
        }
    }

    private function seedExtraTreatmentTemplates(): void
    {
        // Dorzucamy 2 customowe szablony oprócz 6 standardowych z migracji,
        // pokazując ownerowi że można edytować/dodać własne.
        $extras = [
            ['name' => 'Przegląd po sezonie', 'type' => 'check_up', 'interval' => 90, 'summary' => 'Sezonowy przegląd po intensywnym treningu', 'notes' => 'Skupić się na stawach, plecach, palpacja kręgosłupa.'],
            ['name' => 'Iniekcja stawowa', 'type' => 'medication', 'interval' => 180, 'summary' => 'Iniekcja kwasu hialuronowego', 'notes' => 'Po konsultacji z weterynarzem; nie wcześniej niż 6 mies. od poprzedniej.'],
        ];

        foreach ($extras as $sort => $tpl) {
            TreatmentTemplate::updateOrCreate(
                ['name' => $tpl['name']],
                [
                    'id' => (string) Str::ulid(),
                    'name' => $tpl['name'],
                    'type' => $tpl['type'],
                    'interval_days' => $tpl['interval'],
                    'default_summary' => $tpl['summary'],
                    'default_notes' => $tpl['notes'],
                    'is_active' => true,
                    'sort_order' => 100 + $sort,
                ]
            );
        }
    }

    /**
     * @param  array<int, Horse>  $horses
     * @param  array<int, Client>  $clients
     * @param  array<int, Instructor>  $instructors
     * @param  array<int, Arena>  $arenas
     */
    private function seedGroupLessons(array $horses, array $clients, array $instructors, array $arenas): void
    {
        // 3 lekcje grupowe: zeszłotygodniowa (mieszane attendance), jutrzejsza
        // (wszyscy expected) i za 2 tygodnie. Pokazuje frekwencję, multi-uczestnik UI.
        $lessons = [
            ['starts' => now()->subDays(2)->setTime(17, 0), 'mixed' => true, 'title' => 'Lekcja grupowa — początkujący'],
            ['starts' => now()->addDay()->setTime(17, 0), 'mixed' => false, 'title' => 'Lekcja grupowa — średnio-zaawansowani'],
            ['starts' => now()->addDays(14)->setTime(18, 0), 'mixed' => false, 'title' => 'Lekcja grupowa — początkujący'],
        ];

        foreach ($lessons as $lesson) {
            $entry = CalendarEntry::create([
                'id' => (string) Str::ulid(),
                'type' => 'lesson_group',
                'title' => $lesson['title'],
                'starts_at' => $lesson['starts'],
                'ends_at' => $lesson['starts']->copy()->addMinutes(60),
                'instructor_id' => $instructors[1]->id,
                'arena_id' => $arenas[0]->id,
                'horse_id' => null,
                'client_id' => null,
                'status' => $lesson['starts']->isPast() ? 'completed' : 'confirmed',
            ]);

            // 4 uczestników na lekcję.
            $participantClients = array_slice($clients, 0, 4);
            $participantHorses = array_slice($horses, 0, 4);
            foreach ($participantClients as $idx => $client) {
                $attendance = match (true) {
                    ! $lesson['mixed'] => 'expected',
                    $idx === 0 => 'present',
                    $idx === 1 => 'present',
                    $idx === 2 => 'late',
                    default => 'absent',
                };
                CalendarEntryParticipant::create([
                    'id' => (string) Str::ulid(),
                    'calendar_entry_id' => $entry->id,
                    'client_id' => $client->id,
                    'horse_id' => $participantHorses[$idx]->id ?? null,
                    'attendance_status' => $attendance,
                    'notes' => $idx === 3 && $lesson['mixed']
                        ? 'Nieobecny — zwolnienie lekarskie'
                        : null,
                ]);
            }
        }
    }
}
