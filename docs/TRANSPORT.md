# Hovera — Moduł Transportu Koni

> Stan: maj 2026 · plan szczegółowy, pre-implementation
>
> Ten dokument jest źródłem prawdy dla modułu transportu. Wszystkie decyzje produktowe
> i architektoniczne uzgodnione z właścicielem produktu (PrzemekPrzemo) są tu spisane.
> Faza implementacyjna powinna każdorazowo zaczynać od konsultacji odpowiedniego rozdziału.

---

## 1. Wizja i pozycjonowanie

### 1.1 Co budujemy

Drugą połowę produktu Hovera: **moduł transportu koni**, który zamienia obecny zewnętrzny
kalkulator transportu (Laravel, ~3 lata produkcji, jeden klient płacący 250 zł/mc) w pełny
SaaS dla firm przewozowych — z marketplace'em zapytań i integracją z modułem stajni.

Cel: **dwa rynki naraz, w tym samym ekosystemie.**

- **Stajnia** zamawia transport jednym kliknięciem z karty konia (dane konia + adres stajni
  są już w systemie).
- **Firma transportowa** dostaje zapytania od stajni z hovery + od anonimowych klientów
  z publicznego formularza. Wystawia oferty z PDFkiem, fakturuje, prowadzi
  ewidencję pojazdów i tras.

### 1.2 Dlaczego to ma sens biznesowo

1. **Cross-sell.** Każda stajnia w hoverze co najmniej raz w roku zamawia transport.
   Każdy transporter co najmniej raz w roku rozmawia ze stajnią. Sieć efektów.
2. **Migracja klienta z dnia 1.** Mamy już płacącego klienta (250 zł/mc) na obecnym
   kalkulatorze — przeniesiemy go do hovery z gotowym datasetem.
3. **Marketplace zwiększa LTV transportera.** Sam panel zarządczy to ~150 zł/mc.
   Marketplace z leadami uzasadnia 300–500 zł/mc.
4. **Mała konkurencja w UE.** Brak pionowego SaaS-a dla przewozu zwierząt. Najbliżej:
   logistyczne CRM-y ogólne (TimoCom etc.) — nie obsługują specyfiki koni.

### 1.3 Pozycjonowanie wewnątrz Hovery

| Wymiar | Stajnia (current) | Transporter (new) |
|---|---|---|
| `tenants.type` | `stable` | `transporter` |
| Panel Filament | `/app` | `/transport` |
| Public page | `/s/{slug}` | `/t/{slug}` |
| Plany Stripe | Starter / Pro / Multi | Solo / Pro / Fleet |
| Główny resource | Horse, CalendarEntry | Vehicle, Quote, Lead |

Jedna osoba może mieć **i stajnię, i firmę transportową** — multi-tenancy bez zmian,
istniejący tenant-switcher obsługuje to natywnie.

---

## 2. Decyzje produktowe (zafixowane)

| # | Decyzja | Co to znaczy |
|---|---|---|
| **D1** | **Self-service trial** (jak stajnia) | Transporter rejestruje się sam, ma X dni triala, potem Stripe checkout. Bez kontaktu sprzedaży. |
| **D2** | **3 plany wg liczby pojazdów** | `Solo` (1 pojazd) / `Pro` (do 5) / `Fleet` (unlimited). Wszystkie plany dają marketplace; różnice w limitach pojazdów + zaawansowane funkcje (np. multi-driver routing) na wyższych. |
| **D3** | **Hybrid routing leadu** | Zamawiający wybiera: (a) direct do 1-3 ulubionych transporterów, (b) broadcast do wszystkich z obszaru. Szczegóły → §5. |
| **D4** | **Migracja istniejącego klienta w całości** | Pojazdy, stawki, oferty, leady, płatności — wszystko jest importowane jednorazowym skryptem ze starej bazy MySQL. |
| **D5** | **Publiczna mini-strona transportera** (`/t/{slug}`) | Profil marketingowy: logo, opis, obszar obsługi, pojazdy, kontakt, CTA „Zapytaj o ofertę". SEO-friendly. |
| **D6** | **Osobny SMTP dla notyfikacji transportowych do kierowców** | Nie mieszamy z hoverowym mailerem (no-reply@hovera.app). Osobny mailer wpisany w `config/mail.php` jako `transport`. Konfiguracja per env. Szczegóły → §6. |
| **D7** | **Maps/routing API — plan-gated** | Plan Solo → darmowy OpenRouteService (HGV profile). Plan Pro/Fleet → możliwość podpięcia własnego klucza Google Maps Routes API. Decyzja sprzedażowo-techniczna → §7. |

---

## 3. Architektura — wysokopoziomowo

### 3.1 Tenancy

- Nowa kolumna `tenants.type` ∈ `'stable'` / `'transporter'`.
- Signup flow rozszerzony o krok „**Co prowadzisz?**" (stajnię / firmę przewozową / oba).
- Filament panel `/transport` jako osobny `PanelProvider` (analogicznie do istniejącego
  `AppPanelProvider`). Niezależny sidebar, niezależne resource'y.
- Współdzielone: `User`, `TenantMembership`, billing (Stripe), wybór języka, system
  notyfikacji push (FCM), audyt.

### 3.2 Bazy danych — dwa konteksty

**Central DB** (cross-tenant, public marketplace):

| Tabela | Co trzyma |
|---|---|
| `transport_leads` | każde zapytanie (anonimowe lub od stajni). Status: `open` / `quoted` / `accepted` / `expired` / `cancelled`. Kolumna `mode` ∈ `direct` / `broadcast`. |
| `transport_lead_dispatch` | lead × transporter (kto dostał powiadomienie, kiedy, kanał: email / push / in-app) |
| `transport_lead_responses` | oferty od transporterów (kwota, ETA, warunki, link do PDF, status: `pending` / `accepted` / `rejected` / `withdrawn`) |
| `transport_service_areas` | transporter_tenant_id → voivodeship (multi-row, multi-select w UI) |
| `transport_favorites` | klient (stable_tenant_id LUB user_id dla anonimowych) → transporter_tenant_id, max 5 |
| `transporter_profiles` | publiczne dane profilu (`slug`, `logo_path`, `description`, telefon, email, social), 1:1 z transporter tenant |

**Tenant DB** (per-transporter, izolowana — wzorzec istniejący):

| Tabela | Pochodzenie |
|---|---|
| `vehicles` | port ze starego systemu: parametry, plates, zdjęcia, capacity (liczba koni) |
| `transport_settings` | stawki per-km, paliwo (surcharge logic), opłata minimalna, VAT, waluta, dni roboczych |
| `quotes` | oferty z numeracją `OF/YYYY/MM/NNNN`, status, koszty rozbite (km × stawka + paliwo + opłaty + VAT) |
| `leads_inbox` | denormalizowana kopia leadów które wpadły do tego transportera (łatwiejszy query do UI) |
| `payments` | wpłaty per quote: zaliczka + finalna; integracja Stripe / przelewy24 (faza późniejsza) |
| `fuel_prices` | snapshoty z e-petrol.pl (cron) lub manual override |
| `drivers` | kierowcy (osobny SMTP-recipient pool dla notyfikacji — §6) |

### 3.3 Serwisy (Application layer)

```
app/Domain/Transport/
├── Services/
│   ├── CalculatorService.php          # ortodoksja: km × stawka + paliwo + opłaty + VAT
│   ├── RoutingService.php             # adapter: OpenRouteService | GoogleMapsRoutes
│   ├── FuelPriceService.php           # scraper e-petrol + cache + fallback
│   ├── QuoteNumberGenerator.php       # OF/YYYY/MM/NNNN, per-tenant counter
│   ├── LeadDispatcher.php             # logika §5 — komu wysłać i jak
│   └── QuoteAcceptanceService.php     # akceptacja → rejection pozostałych ofert
├── Notifications/
│   ├── LeadReceivedNotification.php   # do transportera (email osobnym mailerem + push)
│   ├── QuoteSentNotification.php      # do zamawiającego
│   └── QuoteAcceptedNotification.php  # do transportera (+ rejected do reszty)
└── Filament/
    ├── TransportPanelProvider.php
    └── Resources/
        ├── VehicleResource.php
        ├── QuoteResource.php
        ├── LeadResource.php
        ├── DriverResource.php
        └── TransportSettingsPage.php
```

---

## 4. Model danych — szczegóły kluczowych tabel

### 4.1 `transport_leads` (central)

```php
Schema::create('transport_leads', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->ulid('originator_tenant_id')->nullable();        // stajnia która zamawia (nullable dla anonimowych)
    $table->ulid('originator_user_id')->nullable();          // konkretny user
    $table->string('originator_email')->nullable();           // dla anonimowych
    $table->string('originator_phone')->nullable();
    $table->string('originator_name')->nullable();

    $table->enum('mode', ['direct', 'broadcast']);
    $table->json('targeted_transporter_ids')->nullable();     // tylko gdy mode=direct, max 3

    $table->string('pickup_address');
    $table->decimal('pickup_lat', 10, 7);
    $table->decimal('pickup_lng', 10, 7);
    $table->string('pickup_voivodeship', 32);

    $table->string('dropoff_address');
    $table->decimal('dropoff_lat', 10, 7);
    $table->decimal('dropoff_lng', 10, 7);
    $table->string('dropoff_voivodeship', 32);

    $table->date('preferred_date');
    $table->time('preferred_time')->nullable();
    $table->boolean('flexible_date')->default(false);

    $table->unsignedTinyInteger('horse_count')->default(1);
    $table->json('horses')->nullable();                       // [{name, height_cm, weight_kg, papers_ok}]
    $table->text('notes')->nullable();

    $table->enum('status', ['open', 'quoted', 'accepted', 'expired', 'cancelled'])->default('open');
    $table->ulid('accepted_response_id')->nullable();
    $table->timestamp('expires_at');                          // default: +14 dni
    $table->timestamps();

    $table->index(['status', 'expires_at']);
    $table->index(['pickup_voivodeship', 'dropoff_voivodeship']);
});
```

### 4.2 `transport_lead_responses` (central)

```php
Schema::create('transport_lead_responses', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('lead_id')->constrained('transport_leads')->cascadeOnDelete();
    $table->ulid('transporter_tenant_id');

    $table->decimal('price_net', 10, 2);
    $table->decimal('price_gross', 10, 2);
    $table->string('currency', 3)->default('PLN');
    $table->decimal('distance_km', 8, 2);
    $table->date('proposed_date');
    $table->time('proposed_time')->nullable();

    $table->text('terms')->nullable();
    $table->string('pdf_url')->nullable();
    $table->ulid('quote_id')->nullable();                     // FK do quotes w tenant DB

    $table->enum('status', ['pending', 'accepted', 'rejected', 'withdrawn'])->default('pending');
    $table->timestamp('responded_at')->nullable();
    $table->timestamps();

    $table->unique(['lead_id', 'transporter_tenant_id']);     // jeden transporter = jedna oferta na lead
    $table->index(['transporter_tenant_id', 'status']);
});
```

### 4.3 `vehicles` (per tenant)

```php
Schema::create('vehicles', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->string('name');                                   // np. "Volvo FH16 — wóz duży"
    $table->string('registration_plate', 16);
    $table->unsignedTinyInteger('capacity_horses');            // ile koni mieści
    $table->decimal('gross_weight_kg', 8, 0);
    $table->json('photos')->nullable();
    $table->boolean('has_air_suspension')->default(false);
    $table->boolean('has_camera')->default(false);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### 4.4 `transport_settings` (per tenant, singleton)

```php
Schema::create('transport_settings', function (Blueprint $table) {
    $table->id();
    $table->decimal('rate_per_km', 6, 2);                     // np. 4.50 PLN/km
    $table->decimal('rate_per_km_loaded', 6, 2)->nullable();  // jeśli inna stawka za km z koniem
    $table->decimal('minimum_charge', 8, 2);                  // np. 800 PLN
    $table->decimal('fuel_consumption_l_per_100km', 5, 2);    // np. 32.5
    $table->boolean('fuel_surcharge_enabled')->default(true);
    $table->decimal('fuel_base_price_pln', 5, 2)->default(7.00); // baza, powyżej której naliczamy surcharge
    $table->decimal('vat_rate', 4, 2)->default(23.00);
    $table->string('currency', 3)->default('PLN');
    $table->json('routing_provider')->default('{"provider":"ors"}'); // §7
    $table->timestamps();
});
```

---

## 5. Routing leadu — pełna logika

### 5.1 Tryb DIRECT

Zamawiający (zalogowana stajnia lub anonimowy z publicznego formularza) wybiera **1–3
ulubionych transporterów** spośród zapisanych w `transport_favorites` lub przeglądając
listę profili publicznych (`/t/{slug}`).

- Lead trafia **tylko do tych transporterów**.
- Każdy z nich może (ale nie musi) odpowiedzieć ofertą — `transport_lead_responses`.
- Zamawiający widzi wszystkie odpowiedzi i **wybiera jedną**.
- Akceptacja jednej oferty → pozostałe oferty automatycznie zmieniają status na
  `rejected`, lead zmienia status na `accepted`, `accepted_response_id` wskazuje wybraną.
- Transporterzy z `rejected` dostają powiadomienie „Twoja oferta nie została wybrana".

### 5.2 Tryb BROADCAST

Zamawiający nie wskazuje konkretnych firm. Lead jest **broadcastowany** do wszystkich
transporterów spełniających kryteria:

- `transport_service_areas.voivodeship` ∈ {voivodeship startu, voivodeship celu, sąsiednie}
- subskrypcja transportera jest aktywna
- transporter ma co najmniej jeden aktywny pojazd o `capacity_horses ≥ horse_count`

**Każdy z powiadomionych transporterów może złożyć ofertę.** Nie ma „kto pierwszy
ten lepszy" — wszyscy zainteresowani składają swoje propozycje w okresie ważności leadu
(`expires_at`, domyślnie 14 dni; konfigurowalne).

**Decyzja należy do zamawiającego.** Zamawiający przegląda wszystkie nadesłane oferty
(cena, ETA, opinie o transporterze, parametry pojazdu) i wybiera jedną.

**Akceptacja jednej oferty = automatyczne odrzucenie wszystkich pozostałych.** Pozostali
transporterzy dostają powiadomienie „Lead został zamknięty — wybrano innego dostawcę".

Mechanika identyczna jak w trybie DIRECT — różni się **kogo dotykamy notyfikacją na
starcie**, nie sposobem rozstrzygania.

### 5.3 Wspólne reguły

| Zdarzenie | Efekt |
|---|---|
| Transporter wysyła ofertę | `lead.status` → `quoted` (jeśli był `open`) |
| Zamawiający akceptuje ofertę | response → `accepted`, lead → `accepted`, pozostałe response → `rejected`, generowane: notyfikacje, faktura draft (jeśli plan obsługuje), wpis w kalendarzu transportera |
| `expires_at` mija bez akceptacji | lead → `expired`, wszystkie pending → `withdrawn`, zamawiający dostaje propozycję „przedłuż / wyślij ponownie" |
| Zamawiający anuluje | lead → `cancelled`, wszystkie pending → `withdrawn` |
| Transporter wycofuje ofertę | response → `withdrawn`, można wystawić nową |

### 5.4 Adjacency map województw

Zaszywamy w kodzie statyczną mapę (`config/transport.php`):

```php
'voivodeship_adjacency' => [
    'mazowieckie' => ['łódzkie', 'kujawsko-pomorskie', 'warmińsko-mazurskie',
                      'podlaskie', 'lubelskie', 'świętokrzyskie'],
    // ... 16 województw
],
```

Trasy międzynarodowe (DE/CZ/SK/LT) — faza późniejsza, na razie tylko PL.

---

## 6. Notyfikacje — osobny SMTP dla transportu

### 6.1 Dlaczego osobny

1. **Reputacja domeny.** Transportowe maile (oferty, faktury, zlecenia dla kierowców)
   to inna treść niż stajenne (zapomniane szczepienia, zapis na lekcję). Mieszanie
   może obniżyć deliverability obu strumieni.
2. **Granularna kontrola.** Klient transportowy chce widzieć maile z adresem
   `transport@hovera.app` lub własną domeną (`zlecenia@firma-transportowa.pl`),
   nie `no-reply@hovera.app`.
3. **Łatwiejszy debugging.** Osobne dashboardy w Postmark/Resend per kanał.

### 6.2 Implementacja

Dodajemy do `config/mail.php`:

```php
'mailers' => [
    'smtp' => [ /* istniejące */ ],
    'transport' => [
        'transport' => 'smtp',
        'host' => env('TRANSPORT_MAIL_HOST'),
        'port' => env('TRANSPORT_MAIL_PORT', 587),
        'username' => env('TRANSPORT_MAIL_USERNAME'),
        'password' => env('TRANSPORT_MAIL_PASSWORD'),
        'encryption' => env('TRANSPORT_MAIL_ENCRYPTION', 'tls'),
        'from' => [
            'address' => env('TRANSPORT_MAIL_FROM_ADDRESS', 'transport@hovera.app'),
            'name' => env('TRANSPORT_MAIL_FROM_NAME', 'Hovera Transport'),
        ],
    ],
],
```

I w `.env.example`:

```
# Transport module — separate SMTP for driver/customer notifications
TRANSPORT_MAIL_HOST=
TRANSPORT_MAIL_PORT=587
TRANSPORT_MAIL_USERNAME=
TRANSPORT_MAIL_PASSWORD=
TRANSPORT_MAIL_ENCRYPTION=tls
TRANSPORT_MAIL_FROM_ADDRESS=transport@hovera.app
TRANSPORT_MAIL_FROM_NAME="Hovera Transport"
```

### 6.3 Routing notyfikacji

W każdej Notification z `app/Domain/Transport/Notifications/`:

```php
public function toMail(object $notifiable): MailMessage
{
    return (new MailMessage)
        ->mailer('transport')      // <- kluczowe
        ->subject(...)
        ->view(...);
}
```

Plus override per-tenant: jeśli transporter podał własne SMTP creds w
`transport_settings.custom_smtp_*` (faza Pro), używamy ich zamiast globalnego —
przez `Mail::build(...)` z runtime config.

### 6.4 Kanały vs adresaci

| Adresat | Email | Push (FCM) | SMS |
|---|---|---|---|
| Transporter (właściciel) | ✅ transport mailer | ✅ jeśli ma app | ➖ faza 2 |
| Kierowca | ✅ transport mailer | ✅ jeśli ma app | ✅ faza 2 (urgent dispatches) |
| Zamawiający (stajnia) | ✅ stable mailer (domyślny) | ✅ | ➖ |
| Zamawiający (anonimowy) | ✅ transport mailer | ❌ | ➖ |

---

## 7. Maps / routing API — analiza decyzji

### 7.1 Opcje

| Opcja | Koszt | Jakość PL | HGV profile | Limity |
|---|---|---|---|---|
| **OpenRouteService** (self-hosted lub free tier 2000 req/dzień) | 0 | dobra | ✅ | 2000/dzień free, potem dedicated server |
| **OSRM** (self-hosted) | ~30€/mc serwer | bardzo dobra (OSM) | ✅ ale wymaga konfiguracji | własna infra |
| **Google Maps Routes API** | ~$5/1000 req po free $200/mc | najlepsza w PL (Street View precision) | ✅ via TruckRouting | praktycznie nieograniczone |
| **Mapbox Directions** | ~$0.50/1000 req po free 100k/mc | dobra | ✅ via Driving-Traffic | hojny free tier |
| **HERE Routing** | enterprise | bardzo dobra w UE | ✅ dedicated truck profile | enterprise pricing |

### 7.2 Rekomendacja sprzedażowa

**Plan-gated approach** — to jest najmocniejsze sprzedażowo:

| Plan | Routing provider | Argument w sprzedaży |
|---|---|---|
| **Solo** (1 pojazd, ~150 zł/mc) | OpenRouteService (free tier) | „Wszystko dla małej firmy. Trasy oparte o OSM — wystarczające w 95% przypadków." |
| **Pro** (do 5 pojazdów, ~350 zł/mc) | OpenRouteService **lub** podłączenie własnego klucza Mapbox (custom branding map preview) | „Możesz podpiąć własną mapę Mapbox dla profesjonalnego brandingu." |
| **Fleet** (unlimited, ~700 zł/mc + €0.005 per route call) | Domyślnie Google Maps Routes API (z konta hovery), lub własny klucz Google | „Najlepsza dostępna jakość tras. Google Maps z trybem ciężarowym (mosty, zakazy, restrykcje wagowe)." |

**Dlaczego tak:**

1. **Solo nie potrzebuje Google.** Mały transporter wozi 200–500 km miesięcznie.
   Margines błędu 2–5% w trasie OSM jest nieistotny biznesowo, bo i tak doliczają bufor.
2. **Fleet potrzebuje Google.** Duża firma robi 5000+ km/mc, gdzie 3% błędu = 150 km
   = realna strata 600–800 zł. Tu Google się zwraca natychmiast.
3. **Pro to upsell motivator.** „Zapłać 200 zł więcej i miej mapę swojej firmy z
   własnym brandingiem" — to klasyczny driver konwersji na wyższy plan.
4. **Hovera kontroluje koszty.** Jeśli klient przekroczy zwyczajowe użycie API
   Google, fakturujemy nadmiar (€0.005 per call powyżej 5000/mc). Transparentnie.

### 7.3 Adapter wzorzec

```php
interface RoutingProvider {
    public function calculateRoute(Coords $from, Coords $to, RouteOptions $opts): Route;
}

class OpenRouteServiceProvider implements RoutingProvider { /* ... */ }
class GoogleMapsRoutesProvider implements RoutingProvider { /* ... */ }
class MapboxProvider implements RoutingProvider { /* ... */ }

class RoutingService {
    public function for(Tenant $transporter): RoutingProvider
    {
        return match($transporter->plan->routing_provider) {
            'google' => app(GoogleMapsRoutesProvider::class)
                ->withKey($transporter->transport_settings->routing_api_key
                    ?? config('transport.google_default_key')),
            'mapbox' => app(MapboxProvider::class)->withKey($transporter->transport_settings->routing_api_key),
            default => app(OpenRouteServiceProvider::class),
        };
    }
}
```

### 7.4 Cache trasy

Niezależnie od providera, każda obliczona trasa idzie do cache (`route_cache` w
central DB, klucz: hash(from_lat+from_lng+to_lat+to_lng), TTL 30 dni). Trasa Warszawa
→ Poznań nie zmienia się z dnia na dzień, a Google billing tym sposobem spada
gwałtownie dla popularnych par.

---

## 8. Migracja istniejącego klienta

### 8.1 Co przenosimy

| Z legacy MySQL | Do hovery |
|---|---|
| `vehicles` table | `vehicles` (per nowy transporter tenant) |
| `settings.rates_*` | `transport_settings` (singleton row) |
| `quotes` (3 lata historii) | `quotes` + każdy ma `legacy_id` dla referencji |
| `quote_items` | rozbicie kosztów per quote |
| stare PDF-y | `storage/transport/quotes/legacy/{quote_id}.pdf` + kolumna `legacy_pdf_url` |
| `clients` | każdy klient → wiersz w `transport_customers` (per tenant) |
| `users` (admin firmy) | zaproszony przez normalny invite flow |

### 8.2 Skrypt importu

```bash
php artisan transport:import-legacy \
  --legacy-host=... --legacy-user=... --legacy-pass=... --legacy-db=... \
  --target-tenant-slug=firma-przewozowa-xyz \
  --dry-run
```

Etapy:

1. **Walidacja połączeń** (legacy + target tenant istnieje).
2. **Dry-run report** — co będzie zaimportowane, ile rekordów per tabela.
3. **Mapowanie potwierdzane interaktywnie** dla dwuznaczności (np. nietypowe statusy ofert).
4. **Faktyczny import** — w transakcji, z rollback przy błędzie.
5. **Raport końcowy** — `transport_settings.legacy_migrated_at`.

### 8.3 Plan komunikacji z klientem

1. **T-14 dni:** informacja „przenosimy Cię na nowy system, zachowamy wszystkie dane".
2. **T-7 dni:** zaproszenie do hovery (utworzone konto + dane), klient może już testować.
3. **T-0 (dzień migracji):** finalny import (delta) + przekierowanie 301 z starego URL na
   hoverowy panel.
4. **T+30 dni:** stary system w read-only (klient może zerknąć w razie wątpliwości).
5. **T+90 dni:** stary system off.

---

## 9. Plan fazowy

Suma: **9–11 tygodni od startu do GA**.

### Faza 1 — Foundations (tydzień 1–3)

**Cel:** transporter może się zarejestrować, dodać pojazd, ustawić stawki.

- [ ] Migracja `tenants.type` + backfill (`type='stable'` dla istniejących).
- [ ] Signup flow: krok wyboru typu tenanta.
- [ ] `TransportPanelProvider` (Filament) → `/transport`.
- [ ] `VehicleResource` (CRUD).
- [ ] `TransportSettingsPage` (singleton form).
- [ ] `DriverResource` (CRUD, podstawowy).
- [ ] Migracja `transport_*` tabel central + per-tenant.
- [ ] Stripe products: Solo / Pro / Fleet + gating na `vehicles` count.

**Demo na koniec:** rejestrujesz się jako transporter, logujesz, dodajesz pojazd, ustawiasz stawki, masz subskrypcję.

### Faza 2 — Calculator + Routing (tydzień 3–4)

**Cel:** transporter potrafi wycenić trasę z mapą.

- [ ] `RoutingProvider` interfejs + 3 implementacje (ORS, Google, Mapbox).
- [ ] `RoutingService` z plan-aware selection.
- [ ] `route_cache` tabela + invalidation.
- [ ] `FuelPriceService` (scraper e-petrol + cache + manual override).
- [ ] `CalculatorService` (km × stawka + paliwo + opłaty + VAT).
- [ ] UI kalkulatora w panelu (Filament action) z preview mapy.

**Demo:** wpisujesz Warszawa → Kraków, widzisz trasę na mapie, wycenę 1480 zł netto.

### Faza 3 — Quotes + PDF + Email (tydzień 4–5)

**Cel:** transporter wystawia ofertę, klient dostaje PDF mailem.

- [ ] `QuoteResource` (Filament).
- [ ] `QuoteNumberGenerator` (OF/YYYY/MM/NNNN, per tenant).
- [ ] Generator PDF (mPDF, branded template).
- [ ] Notyfikacja `QuoteSentNotification` (osobny mailer `transport`).
- [ ] Workflow: draft → sent → accepted/rejected.

**Demo:** generujesz ofertę, klient dostaje mail z PDF, klika „akceptuję".

### Faza 4 — Migracja istniejącego klienta (tydzień 5–6)

**Cel:** klient od kalkulatora działa w hoverze.

- [ ] `transport:import-legacy` artisan command.
- [ ] Faktyczna migracja (T-0 z planu §8.3).
- [ ] Stary URL → 301 redirect.

**Demo:** klient loguje się w hoverze, widzi swoje stare oferty, kontynuuje pracę.

### Faza 5 — Service areas + Favorites (tydzień 6–7)

**Cel:** mapa obsługi + ulubieni transporterzy.

- [ ] `TransportServiceAreasPage` (klikalna mapa PL, multi-select).
- [ ] `transport_favorites` UI (w panelu stajni: „zobacz transporterów" → gwiazdka).
- [ ] Adjacency config (`config/transport.php`).

**Demo:** stajnia oznacza 3 ulubionych transporterów, każdy z nich ma ustawione 4 województwa.

### Faza 6 — Public form + Lead marketplace (tydzień 7–8)

**Cel:** zapytania trafiają do transporterów.

- [ ] `transport_leads` + `transport_lead_dispatch` + `transport_lead_responses` (central).
- [ ] Public form `/transport/zapytanie` (PL/EN/DE).
- [ ] Logged-in flow w portalu stajni: button na karcie konia „Zamów transport".
- [ ] `LeadDispatcher` (logika direct vs broadcast, §5).
- [ ] `LeadResource` (inbox transportera).
- [ ] `QuoteAcceptanceService` (akceptacja → rejection pozostałych).
- [ ] Anonimowy acceptance token (link w mailu).
- [ ] Notyfikacje: `LeadReceivedNotification`, `QuoteAcceptedNotification` (rejected do reszty).

**Demo:** anonimowy klient z formularza → mail do 8 transporterów w mazowieckim → 3 oferty → klient wybiera jedną → 2 inne dostają „nie wybrano".

### Faza 7 — Public mini-page `/t/{slug}` (tydzień 8–9)

**Cel:** transporter ma stronę-wizytówkę.

- [ ] `transporter_profiles` tabela + Filament edytor.
- [ ] Public route `/t/{slug}` z SEO meta + schema.org/LocalBusiness.
- [ ] Galeria pojazdów + opis + obszar + CTA do zapytania (z pre-filled `targeted_transporter_ids`).

**Demo:** wpisujesz `/t/firma-xyz`, widzisz profil, klikasz „zapytaj o ofertę", lead idzie tylko do tej firmy.

### Faza 8 — Polish + i18n + Tests (tydzień 9–10)

**Cel:** GA-ready.

- [ ] Tłumaczenia PL/EN/DE/FR/RU (kontynuacja istniejącego i18n).
- [ ] Mobile responsive UI dla publicznego formularza.
- [ ] Feature tests dla `LeadDispatcher`, `QuoteAcceptanceService`, `CalculatorService`.
- [ ] Dokumentacja per-rola (transporter w `resources/help/{locale}/transporter.md`).
- [ ] Smoke test: 5 prawdziwych zapytań → 5 prawdziwych ofert → 5 prawdziwych akceptacji.

**Demo:** publiczna premiera.

### Faza 9 — Płatności (poza zakresem MVP, tydzień 11+)

Stripe / Przelewy24 dla wpłat zaliczki i finalnej. Faktura draft po akceptacji.
KSeF integracja przez billu.pl (już zaplanowana w głównym hovera-spec).

---

## 10. Otwarte pytania / decyzje do podjęcia później

| # | Pytanie | Kiedy decydujemy |
|---|---|---|
| OP1 | Ile dni triala dla transportera? (Stajnia ma 30 dni — czy tak samo?) | Przed fazą 1 |
| OP2 | Czy plan Solo dostaje marketplace, czy tylko płatne plany? | Przed fazą 6 |
| OP3 | Czy ulubionych może być max 5 czy bez limitu? | Przed fazą 5 |
| OP4 | Czy w trybie broadcast widać liczbę powiadomionych transporterów dla zamawiającego? (transparentność vs anti-anchoring) | Przed fazą 6 |
| OP5 | Czy oferty mają konkretny czas ważności (np. 48h od wysłania) niezależny od `expires_at` leadu? | Przed fazą 3 |
| OP6 | Trasy międzynarodowe (DE/CZ/SK/LT) — czy w MVP czy faza późniejsza? | Przed fazą 2 |
| OP7 | Reviews/opinie transporterów — kiedy włączamy? | Po GA |
| OP8 | Verification badge (Hovera-Verified) — kryteria? | Po GA |

---

## 11. Zależności od reszty Hovery

| Co | Czy istnieje | Kiedy potrzebne |
|---|---|---|
| `User` + `TenantMembership` + tenant switcher | ✅ działa | od fazy 1 |
| Stripe billing | ✅ działa | faza 1 (nowe produkty) |
| Filament Panels | ✅ działa | faza 1 (nowy `PanelProvider`) |
| i18n stack | ✅ działa | każda faza |
| FCM push | ✅ działa | faza 6 (notyfikacje leadów) |
| KSeF / billu.pl | 🟡 częściowo | faza 9 (płatności) |
| Audit log | ✅ działa | każda faza |

---

## 12. Pierwsze konkretne kroki (gdy ruszamy)

1. **Stworzyć migrację `2026_XX_XX_add_type_to_tenants.php`** z backfill.
2. **Dodać `TransportPanelProvider`** + zarejestrować w `bootstrap/providers.php`.
3. **Stworzyć skeleton `app/Domain/Transport/`** z pustymi serwisami.
4. **Rozszerzyć signup form** (resources/views/auth lub Filament) o krok typu tenanta.
5. **Pierwszy PR draftowy** z kafelkiem „Czy chcesz prowadzić firmę transportową?"
   w `/app/onboarding` — żeby zwalidować flow zanim zbudujemy resztę.

---

> **Status dokumentu:** v1.0 — gotowy do startu fazy 1.
> Każda zmiana decyzji produktowej (§2) musi być tu odnotowana wraz z datą i powodem.
