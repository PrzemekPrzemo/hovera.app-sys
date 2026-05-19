# Hovera — Moduł Transportu Koni

> Stan: maj 2026 · faza 1–7 + post-MVP batch 1 wdrożone; pozostają faza 8 (testy/polish)
> i faza 9 (płatności). Dokument jest aktualizowany na bieżąco po każdym merge'u.
>
> Ten dokument jest źródłem prawdy dla modułu transportu. Wszystkie decyzje produktowe
> i architektoniczne uzgodnione z właścicielem produktu (PrzemekPrzemo) są tu spisane.
> Faza implementacyjna powinna każdorazowo zaczynać od konsultacji odpowiedniego rozdziału.
> Statusy ✅/🟡/⬜ w §9 są źródłem prawdy o tym co już istnieje w `main`.

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
| Plany Stripe | Starter / Pro / Multi | Start / Pro / Business / Enterprise (PLN/EUR/GBP/AUD/NZD) |
| Główny resource | Horse, CalendarEntry | Vehicle, Quote, Lead |

Jedna osoba może mieć **i stajnię, i firmę transportową** — multi-tenancy bez zmian,
istniejący tenant-switcher obsługuje to natywnie.

### 1.4 Onboarding transportera — dokumenty PWL (obowiązkowe)

Każdy nowy transporter musi przed pierwszą ofertą wgrać 6 dokumentów PWL
(Przewóz Wewnątrzwspólnotowy Zwierząt Żywych) — bez kompletu konto pozostaje
w statusie `pending` / `under_review`, nie może wysyłać ofert ani faktur.
Sprawdzany jest komplet PWL plus rejestr firmy (KRS / CEIDG).

| # | Dokument | Wydaje | Podstawa prawna |
|---|----------|--------|-----------------|
| 1 | Zezwolenie na wykonywanie zawodu Przewoźnika Drogowego | GITD / starosta | Rozp. WE 1071/2009 + ustawa o transporcie drogowym z 2001 r. |
| 2 | Zezwolenie dla Przewoźnika Typ 1 (< 8h) **LUB** Typ 2 (> 8h) PWL | PIW (Powiatowy Inspektorat Weterynarii) | Rozp. WE 1/2005 |
| 3 | Licencje dla kierowców i osób obsługujących (PWL) | PIW | Rozp. WE 1/2005 art. 6 |
| 4 | Świadectwo Zatwierdzenia Środka Transportu (PWL) | PIW | Rozp. WE 1/2005 art. 18 (< 8h) lub art. 19 (> 8h) |
| 5 | Książka mycia i dezynfekcji Środka Transportu | własne prowadzenie + kontrola PIW | Ustawa o ochronie zdrowia zwierząt z 11.03.2004 |
| 6 | OC Przewoźnika | komercyjny ubezpieczyciel | dobrowolna w PL, ale wymagana przez Hovera marketplace |

**Reguła T1/T2:** transporter wybiera jeden z dwóch (zależnie od profilu transportów).
Posiadanie Typu 2 pokrywa również użycia Typu 1. UI w `/transport/transporter-documents`
pokazuje oba sloty obok siebie z helper textem.

**Reguła expiry:** wszystkie 6 dokumentów PWL mają termin ważności. Cron
`transporter:docs-expiry-notify` (codziennie 04:00, patrz `routes/console.php`)
wysyła mail do owner'a 30 dni przed `expires_at`. Idempotencja przez
`expiry_notified_at` na rekordzie dokumentu; re-upload nowej wersji
re-armuje notify (porównanie z `updated_at`).

**Legacy mapping:** dawniejsze typy `insurance_ocp`, `animal_transport_cert`,
`vehicle_registration` zostały zachowane jako deprecated dla wstecznej
kompatybilności rekordów w istniejących tenant DB. `insurance_ocp` jest
migracyjnie mapowany 1:1 na `carrier_liability_insurance` (data fix —
`2026_05_18_140000_remap_legacy_transporter_document_types.php`).

---

## 2. Decyzje produktowe (zafixowane)

| # | Decyzja | Co to znaczy |
|---|---|---|
| **D1** | **Self-service trial** (jak stajnia) | Transporter rejestruje się sam, ma X dni triala, potem Stripe checkout. Bez kontaktu sprzedaży. |
| **D2** | **4 plany wg skali firmy** | `Start` (250 PLN, 4 pojazdów / 4 kierowców) → `Pro` (549 PLN, 12 / 8) → `Business` (999 PLN, 25 / 15) → `Enterprise` (cena indywidualna, no limits). Plany **cumulative** (każdy wyższy zawiera wszystko z niższego). 5 walut: PLN (base) + EUR/GBP/AUD/NZD overlay. Source of truth: `hovera.app/produkt/transport/` (Astro `CarrierOnboarding.astro`). Lock-in 12 mc z gwarancją niezmienności, promocja do 2026-07-31. Patrz §15.4. |
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

**Wejście z publicznego profilu (post-MVP, PR #219).** Z formularza zapytania
otwartego linkiem `/t/{slug}?transporter={slug}` (CTA „Zapytaj o ofertę" na profilu)
`TransportInquiryController` automatycznie pre-fill'uje `targeted_transporter_ids =
[{tenant_id tej firmy}]` i wymusza `mode = direct`. Pole wyboru transporterów jest
ukryte, użytkownik widzi tylko baner „Wysyłasz zapytanie do: {nazwa firmy}".
Lead nigdy nie trafia do nikogo innego — to jest właściwy tryb dla
SEO-trafficu z publicznego profilu i partnerstw zewnętrznych.

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

### 6.5 Lista zaimplementowanych notyfikacji (`app/Domain/Transport/Notifications/`)

| Notification | Adresat | Powód | PR |
|---|---|---|---|
| `LeadReceivedNotification` | transporter | dostałeś nowy lead (broadcast lub direct) | #214 |
| `LeadClosedNotification` | transporter (przegrany) | lead zamknięty, wybrano kogoś innego | #216 |
| `QuoteSentNotification` | zamawiający | dostałeś PDF oferty z linkiem akceptacyjnym | #201 |
| `QuoteAcceptedNotification` | transporter (zwycięzca) | klient zaakceptował Twoją ofertę | #202 |
| `QuoteRejectedNotification` | transporter (przegrany ofertą) | klient wybrał innego dostawcę | #216 |
| `TransporterVerifiedNotification` | transporter | master admin zweryfikował konto | #206 |
| `TransporterRejectedNotification` | transporter | master admin odrzucił dokumenty | #206 |
| `TransportInvoiceSentNotification` | klient końcowy | faktura po realizacji (PDF w załączniku) | #208 |

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

Status na 2026-05-18: faza 1–7 ✅ + post-MVP batch 1 ✅. Pozostają faza 8
(polish + testy E2E) i faza 9 (płatności).

### Faza 1 — Foundations ✅ (PR #187–#194)

**Cel:** transporter może się zarejestrować, dodać pojazd, ustawić stawki.

- [x] Migracja `tenants.type` + backfill (`type='stable'` dla istniejących) — PR #187.
- [x] Signup flow: krok wyboru typu tenanta — PR #194.
- [x] `TransportPanelProvider` (Filament) → `/transport` — PR #188.
- [x] `VehicleResource` (CRUD) — PR #189.
- [x] `TransportSettings` (singleton page) — PR #190.
- [x] `DriverResource` (CRUD) — PR #191.
- [x] Migracja `transport_*` tabel central + per-tenant — PR #192.
- [x] Stripe products: Solo / Pro / Fleet + gating na `vehicles`/`drivers` count — PR #193.
- [x] Plans seed updated to match marketing spec (Start/Pro/Business/Enterprise, 5 walut, 6 global add-ons) — see §15.5.

### Faza 2 — Calculator + Routing ✅ (PR #195–#198)

**Cel:** transporter potrafi wycenić trasę z mapą.

- [x] `RoutingProvider` interfejs + 3 implementacje (ORS, Google, Mapbox) — PR #195.
- [x] `RoutingService` z plan-aware selection + `route_cache` — PR #195.
- [x] `FuelPriceService` (scraper e-petrol + cache + manual override) — PR #196.
- [x] `CalculatorService` (km × stawka + paliwo + opłaty + VAT) — PR #197.
- [x] UI kalkulatora w panelu (Filament action) z geocodingiem Mapbox — PR #198.

### Faza 3 — Quotes + PDF + Email ✅ (PR #199–#202)

**Cel:** transporter wystawia ofertę, klient dostaje PDF mailem.

- [x] `QuoteResource` (Filament) + Save-as-quote z kalkulatora — PR #200.
- [x] `QuoteNumberGenerator` (OF/YYYY/MM/NNNN, per tenant) — PR #199.
- [x] Generator PDF (`QuotePdfGenerator`) + osobny SMTP transport — PR #201.
- [x] Publiczna akceptacja oferty (signed URL z maila) + notyfikacje zwrotne — PR #202.

### Faza 3.5 — Verification + Admin + Invoices + API config + Dashboard v1 ✅ (PR #203–#210)

Wewnętrznie nazwana „kroki A–E z feedbacku produkcyjnego" — wpadła między
fazą 3 a 4 jako warunek koniecznym do startu marketplace.

- [x] Verification data layer + UI + gating wysyłki przed weryfikacją — PR #203–#205.
- [x] Master admin `TransporterResource` (verify/reject + impersonation) — PR #206.
- [x] `TransportInvoice` data layer + IssueFromQuote + Filament UI + PDF + email — PR #207–#208.
- [x] „Test API key" button w `TransportSettings` (probe ORS/Mapbox/Google) — PR #209.
- [x] Dashboard v1 (KPI + top FV + korytarze + transporty) — PR #210.

### Faza 4 — Migracja istniejącego klienta ⬜ (odsunięta na po GA)

Po feedbacku produkcyjnym przesunięta: najpierw GA marketplace'u, dopiero potem
przenosimy płacącego klienta starego kalkulatora. Plan §8 pozostaje aktualny.

- [ ] `transport:import-legacy` artisan command.
- [ ] Faktyczna migracja (T-0 z planu §8.3).
- [ ] Stary URL → 301 redirect.

### Faza 5+6 — Service areas + Favorites + Lead marketplace ✅ (PR #211–#216)

**Cel:** zapytania trafiają do transporterów, transporter odpowiada ofertą.

- [x] Service areas UI dla transportera (multi-select województw) — PR #211.
- [x] `transport_favorites` UI (gwiazdki w stable panelu) — PR #212.
- [x] Publiczny formularz `/transport/zapytanie` (PL/EN/DE/FR/RU) — PR #213.
- [x] `LeadDispatcher` (broadcast/direct routing + email do transporterów) — PR #214.
- [x] `LeadResource` (inbox transportera) + akcja „Odpowiedz ofertą" — PR #215.
- [x] `QuoteAcceptanceService` (1 accept → reject reszty + `LeadClosedNotification`) — PR #216.

### Faza 7 — Public profile `/t/{slug}` ✅ (PR #217)

**Cel:** transporter ma stronę-wizytówkę indeksowalną przez Google.

- [x] `TransporterProfileController` + view `public/transport/profile.blade.php`.
- [x] i18n PL/EN/DE/FR/RU (`lang/*/public/transporter_profile.php`).
- [x] Renderuje tylko transporterów `verified_at != null` (404 inaczej).
- [x] SEO meta + schema.org/LocalBusiness + canonical.

### Faza 7.5 — post-MVP batch 1 ✅ (PR #218–#221)

Drobne, ale ważne dla early-traction features. Wszystkie wdrożone bez bumpa
zakresu fazy 8.

| PR | Co | Pliki kluczowe |
|---|---|---|
| #218 | **Sitemap + robots.** `/sitemap.xml` (lista zweryfikowanych profili `/t/` i publicznych stajni `/s/`) oraz `/robots.txt` z `Sitemap:` directive. | `SitemapController`, `public.sitemap.blade.php` |
| #219 | **Direct lead z profilu.** `/t/{slug}` → CTA „Zapytaj o ofertę" otwiera `/transport/zapytanie?transporter={slug}`, formularz pre-fill'uje cel i wymusza `mode=direct`. | `TransportInquiryController` (extended), `inquiry.blade.php` |
| #220 | **OG image 1200×630.** `/t/{slug}/og-image.png` — pre-rendered grafika do social share'ów. Plain GD + bundled DejaVu Sans (bez node, bez headless chrome). Cache 24h. | `TransporterOgImageController`, `resources/fonts/DejaVu*.ttf` |
| #221 | **Mini-dashboard v2** — 4 nowe widgety dorzucone do `TransportDashboardService`: `LeadsKpiWidget` (leady tygodniowo + win rate 30d), `UpcomingTransportsWeekWidget` (kalendarz 7 dni), `TopPaidInvoicesWidget` (best FV 90d), `RoutesHeatmapWidget` (najczęstsze trasy). | `app/Filament/Transport/Widgets/*Widget.php`, `dashboard.php` i18n |

### Faza 8 — Polish + Tests + Help center 🟡 (in progress)

**Cel:** GA-ready, w tym kompletny help center dla transporterów.

- [x] Tłumaczenia PL/EN/DE/FR/RU dla wszystkich publicznych view'ów i notyfikacji.
- [x] Dokumentacja per-rola transporter w `resources/help/{locale}/transporter.md` (PL/EN — full, DE/FR/RU — machine-translated, flagged for native review).
- [ ] Mobile responsive review (smoke pass na 360px / 768px / 1280px).
- [ ] Feature tests E2E: lead → response → acceptance → invoice → email delivery.
- [ ] Smoke test prod: 5 prawdziwych zapytań → 5 ofert → 5 akceptacji.
- [ ] Audit log review (wszystkie state transitions na `transport_leads` / `transport_lead_responses`).

### Faza 9 — Płatności ⬜ (post-MVP, zakres do uzgodnienia)

Kandydaci (patrz §14 dla rekomendacji):

**Status:** ✅ MVP wdrożony (URL-based direct charge — patrz §13 niżej).
Pełna integracja Stripe Connect API + webhooks w późniejszej iteracji.

Pierwotny scope (Stripe / Przelewy24 dla zaliczki + finalnej z webhookami,
KSeF integracja per-transporter ✅ — patrz §14.1) jest zachowany jako follow-up.
KSeF już zaimplementowany w PR #225 (transporter-as-issuer, Hovera passthrough).

---

## 10. Otwarte pytania / decyzje do podjęcia później

| # | Pytanie | Status | Decyzja |
|---|---|---|---|
| OP1 | Ile dni triala dla transportera? | ✅ | 14 dni (krócej niż stajnia — transporter szybciej waliduje wartość przez pierwszy lead). |
| OP2 | Czy plan Solo dostaje marketplace? | ✅ | TAK — wszystkie plany mają marketplace, różnica w limitach pojazdów/kierowców i routing API. |
| OP3 | Czy ulubionych może być max 5 czy bez limitu? | ✅ | Max 5 (egzekwowane w `TransportFavoriteManager`). |
| OP4 | Czy w trybie broadcast widać liczbę powiadomionych transporterów? | ✅ | NIE pokazujemy w UI zamawiającego — anti-anchoring. Liczbę zna tylko master admin (`transport_lead_dispatch`). |
| OP5 | Czas ważności oferty niezależny od leadu? | 🟡 | Aktualnie oferta żyje tyle co lead (`expires_at`). Reopen w razie sygnału od UX. |
| OP6 | Trasy międzynarodowe (DE/CZ/SK/LT) | ⬜ | Post-MVP — patrz §14. PL-only w MVP. |
| OP7 | Reviews/opinie transporterów | ✅ | Wdrożone — patrz §13.6. 14 dni od preferred_date, magic link, 1–5 ★ + komentarz. Hovera nie moderuje preventywnie; transporter może zgłosić review do moderacji master adminowi. |
| OP8 | Verification badge (Hovera-Verified) — kryteria | ✅ | Zaimplementowane przez `verified_at` na `Tenant`, kryteria w `app/Domain/Transport/Verification/` — master admin verify/reject na podstawie uploadowanych dokumentów (OC przewoźnika, licencja, NIP). |

---

## 11. Zależności od reszty Hovery

| Co | Status | Notatka |
|---|---|---|
| `User` + `TenantMembership` + tenant switcher | ✅ działa | używane od fazy 1, multi-tenancy dla osoby która ma i stajnię i transport. |
| Stripe billing | ✅ działa | produkty Solo/Pro/Fleet wdrożone w PR #193 z gatingiem `vehicles`/`drivers`. |
| Filament Panels | ✅ działa | `TransportPanelProvider` (PR #188) wyłączony per `tenants.type`. |
| i18n stack (PL/EN/DE/FR/RU) | ✅ działa | wszystkie 5 lokali pokryte we wszystkich publicznych view'ach i notyfikacjach. |
| FCM push | ✅ działa | używane przez `LeadReceivedNotification` (faza 6). |
| Audit log | ✅ działa | wpisy z `TransporterResource::verify/reject`, `QuoteAcceptanceService`, `LeadDispatcher`. |
| Routing API (ORS / Mapbox / Google) | ✅ działa | 3 providery zaimplementowane (`app/Domain/Transport/Routing/Providers/`), plan-gated, „Test API key" probe w settings. |
| Mapbox geocoding | ✅ działa | używane w kalkulatorze do autocomplete'u adresów. |
| Help center publiczny | ✅ działa | `/help/{persona}` (PR #182). Po fazie 8 doklejamy persona `transporter` (osobny markdown — patrz §13). |
| Sitemap / robots | ✅ działa | `/sitemap.xml` + `/robots.txt` z directive `Sitemap:`. Indeksuje `/t/{slug}` i `/s/{slug}`. |
| KSeF / billu.pl | 🟡 częściowo | faktury stajni już idą przez KSeF. Per-transporter — odsunięte do fazy 9 (patrz §14). |
| Stripe Connect | ⬜ | nie ma — kandydat do fazy 9 (patrz §14, rekomendacja: Express). |

---

## 12. Marketplace positioning — Hovera ≠ firma transportowa

To jest fundament prawny i produktowy całego modułu. **Każda kolejna decyzja
(KSeF, płatności, reviews, międzynarodowe trasy) wypada z tego rozdziału.**

### 12.1 Stwierdzenie pozycji

> **Hovera jest dostawcą SaaS i pośrednikiem marketplace'owym, NIE firmą transportową.**
> Hovera nie przewozi koni, nie posiada pojazdów, nie zatrudnia kierowców, nie
> wystawia faktur za usługę transportową. Hovera **łączy** zamawiających
> z przewoźnikami i dostarcza im narzędzia (panel, kalkulator, oferty, faktury,
> profil publiczny). Punkt.

### 12.2 Trójkąt prawny

```
  ┌──────────────┐                                  ┌─────────────────┐
  │  Zamawiający │ ◀── umowa transportu (B2B/B2C) ─▶│   Transporter   │
  │  (klient)    │                                  │   (firma X)     │
  └──────┬───────┘                                  └────────┬────────┘
         │                                                   │
         │    obsługa pośrednictwa (free dla zamawiającego)  │  subskrypcja SaaS
         │                                                   │  (+ przyszła prowizja)
         ▼                                                   ▼
        ┌──────────────────────────────────────────────────────────┐
        │                    Hovera (Hovera sp. z o.o.)            │
        │   ↳ platforma, marketplace, narzędzia, brand, security   │
        └──────────────────────────────────────────────────────────┘
```

**Umowa transportowa = strict bilateralna: zamawiający ↔ transporter.**
Hovera nie jest stroną tej umowy. Hovera dostarcza tylko software'ową
infrastrukturę, w której ta umowa jest zawierana (formularz, oferta, akceptacja
przez signed URL, kalendarz, PDF, faktura).

### 12.3 Model przychodów

| Strumień | Status | Komu fakturujemy |
|---|---|---|
| Subskrypcja SaaS od transporterów (Solo/Pro/Fleet) | ✅ MVP | Transporter (B2B, NIP, Stripe) |
| Subskrypcja SaaS od stajni (Starter/Pro/Multi) | ✅ od początku Hovery | Stajnia |
| Prowizja od skutecznych deali | ⬜ post-MVP | Transporter (% od `transport_lead_response.price_net` zaakceptowanej) |
| Sponsorowane pozycje na liście profili / reklamy „Wyróżnij profil" | ⬜ post-MVP | Transporter |
| Payment processing fee (jeśli włączymy Stripe Connect) | ⬜ post-MVP | Transporter (% od transakcji + flat fee) |

**Co fakturuje TRANSPORTER (nie Hovera):**

- Usługa transportu konia zamawiającemu (faktura VAT z NIPem transportera, jego
  numeracja, wystawiana w `TransportInvoiceResource`).
- Faktury idą przez KSeF transportera (post-MVP, faza 9 — patrz §14).

### 12.4 Podział odpowiedzialności

| Obszar | Odpowiada Transporter | Odpowiada Hovera |
|---|---|---|
| Stan techniczny pojazdu, OC przewoźnika, licencja, badania | ✅ pełna odpowiedzialność | sprawdzamy dokumenty przy onboardingu (verify), nie aktualizujemy ich |
| Bezpieczeństwo transportu (warunki w pojeździe, kompetencje kierowcy, plan trasy) | ✅ pełna odpowiedzialność | — |
| Realizacja umowy z zamawiającym (terminy, jakość, reklamacje) | ✅ pełna odpowiedzialność | mediacja dobrowolna, brak SLA |
| Wystawienie faktury VAT / paragonu | ✅ pełna odpowiedzialność (swoim NIPem, swoją numeracją, swoim KSeF-em) | dajemy tylko narzędzie (`TransportInvoiceResource`) |
| Roszczenia za szkodę w transporcie | ✅ Konwencja CMR + OC przewoźnika | — |
| Compliance RODO względem danych zamawiającego (dane konia, kontakt) | współ-administrator (po przyjęciu leadu) | współ-administrator (do momentu przekazania leadu) |
| Dostępność platformy (uptime), security, backupy danych | — | ✅ |
| Aktualność danych w profilu publicznym, treść opisu | ✅ | moderacja przy verify, brak ciągłej weryfikacji |
| Brand i reputacja marketplace'u | współ | ✅ (zasady, ban, weryfikacja) |

### 12.5 Konsekwencje dla decyzji produktowych

To pozycjonowanie **wymusza** następujące decyzje (na które będziemy się
powoływać w kolejnych pracach):

1. **KSeF = per-transporter.** Hovera nie wystawia faktur za transport — to robi
   transporter. Integracja KSeF w fazie 9 dotyczy *jego* numeracji, *jego*
   NIPu, *jego* tokenów. Hovera robi tylko passthrough (signal API + UI w
   `TransportInvoiceResource`).
2. **Płatności = direct charge lub Stripe Connect z transporterem jako MoR.**
   Hovera nie inkasuje pieniędzy w swoje imię za transport. Jeśli włączymy
   Stripe (post-MVP), to przez Connect Express — transporter jest Merchant of
   Record, Hovera tylko orchestruje + ściąga prowizję.
3. **Reviews = real reviews.** Nie wstydzimy się ich, bo nie wykonujemy
   transportu — opinie dotyczą *transportera*, nie Hovery. Włączamy bez ryzyka
   reputacyjnego dla nas.
4. **Reklamacje = bezpośrednio do transportera.** Mamy tylko mediation tool
   (kanał kontaktu), nie centralę reklamacyjną. Klauzula w regulaminie
   marketplace'u: „Hovera może wspomóc mediację, ale nie jest stroną umowy
   transportu".
5. **Ban / rejection = nasze prawo, nie obowiązek.** Hovera **nie ma SLA na
   weryfikację** ani na odrzucenie — to discretionary, master admin tool.

### 12.6 Linki referencyjne

- `/regulamin` — regulamin świadczenia usługi SaaS (Hovera ↔ tenant).
- `/regulamin-marketplace` — regulamin marketplace'u (zamawiający ↔ transporter,
  Hovera jako pośrednik). **TODO post-MVP** — równoległa praca w branchu
  `claude/transport-legal-intermediary`. Do czasu wdrożenia: relewantne klauzule
  trafiają tymczasowo do `/regulamin` § „Marketplace transportu".
- `/polityka-prywatnosci` — RODO art. 13 (oba kierunki).
- `/dpa` — RODO art. 28 (współ-administrowanie danymi zamawiającego między
  Hoverą a transporterem).

---

## 13. Master admin — co jest w `/admin` dla transporterów

Master admin (`/admin`, gated po `is_master_admin = true`) ma pełen wgląd
i sterowanie nad transporterami w central DB. Trzy główne resource'y:

### 13.1 `TransporterResource` (PR #206 + PWL extension)

`app/Filament/Admin/Resources/TransporterResource.php` — Eloquent query
scope'owany do `tenants.type = transporter`. Funkcje:

- **List view:** wszyscy transporterzy z kolumną statusu (`pending` /
  `verified` / `rejected`), sort by `verified_at`, filter by status.
- **`TransporterDocumentsRelationManager`:** tabela dokumentów PWL per
  transporter — czytamy z tenant DB (TenantManager::setCurrent przed
  query). Akcje per-row: „Zatwierdź dokument" / „Odrzuć" (modal z `reason`).
  Każda akcja wpis w audit log: `transporter_document.verify` /
  `transporter_document.reject`.
- **Verify action (auto-block):** master admin nie może zatwierdzić
  tenant'a (status `verified`) dopóki wszystkie wymagane PWL dokumenty
  nie mają indywidualnego statusu `verified`. Sprawdza
  `VerificationChecklistService::isComplete()`; jeśli false — toast
  „Najpierw zweryfikuj wszystkie wymagane dokumenty PWL (X/Y). Brakuje: …".
- **Verify success:** wysyła `TransporterVerifiedNotification` z LISTĄ
  zweryfikowanych dokumentów (mail do owner'a wymienia konkretnie co
  przeszło — transparentność prawna). Wpis audit log
  `transporter.verify` z `payload.verified_docs`.
- **Reject action:** modal z polem `reason`, ustawia `verified_at = null`
  + zapisuje powód, wysyła `TransporterRejectedNotification`. Mail
  zawiera listę dokumentów które nie przeszły weryfikacji.
- **Impersonation:** master admin może wejść w panel `/transport` jako dany
  transporter (przez istniejący `ImpersonationController`) — dla supportu
  i debug.

**Checklist widget (transporter side + master admin side):**
`app/Domain/Transport/Verification/VerificationChecklistService.php` buduje
deterministyczną listę slotów PWL (KRS, zezwolenie zawód, PWL T1/T2
[alternatywa — wybiera „lepszy" status z dwóch], świadectwo kierowców,
świadectwo pojazdu, książka mycia, OC). Każdy slot zwraca status
`verified` / `pending` / `rejected` / `missing`. Widget renderowany w
`/transport/transporter-documents` (X/Y wgranych) i w master adminie
(tooltip blokady verify-tenant).

### 13.2 `TenantResource` (audience filter)
### 15.2 `TenantResource` (audience filter)

Bazowa lista wszystkich tenantów (stajnie + transporterzy) z filtrem
`type` w toolbarze — kliknięcie filtra przełącza widok między „stajnie"
i „transporterzy". To samo źródło danych, inny perspektywa.

### 15.3 `PlanResource`

Plany Stripe (stajnia: Starter/Pro/Multi; transporter: Solo/Pro/Fleet)
z badge'em `audience` ∈ `stable` / `transporter`. Master admin może
dodać nowy plan dla wybranej grupy bez wycieku do drugiej.

### 15.4 `InvitationResource` (filtered)

Filtr po `tenant.type` — admin widzi osobno zaproszenia czekające
w stajniach vs w transporterach.

### 15.5 Co NIE jest w master adminie

- Master admin **nie widzi treści ofert ani leadów per-tenant** (privacy
  by default). Dostęp tylko do agregatów (liczba leadów / liczba ofert
  per transporter) przez `MasterStatsWidget`.
- Master admin **nie może wystawić faktury** za transportera (to by
  złamało §12.4 — KSeF per-transporter).
- Master admin **nie inkasuje płatności** — Stripe jest między klientem
  a transporterem (subscription billing) bezpośrednio.

### 15.6 Reviews (po zaakceptowanym quote)

Marketplace reviews — `app/Filament/Admin/Resources/TransportReviewResource.php`
+ `/transport` panel (TransportReviewResource po stronie tenant'a). Patrz §12.5
pkt 3 dla pozycjonowania (real reviews, Hovera ≠ przewoźnik).

**Bramka „real deal":** invite generowany **tylko** dla
`transport_lead_responses.status = accepted` przez `TransportReviewInviteService`
(cron `transport:dispatch-review-invites`, daily 09:00 Warsaw). Brak akceptacji
oferty = brak invite = brak opinii. Recenzja anonimowych „użytkowników" jest
strukturalnie niemożliwa.

**14-dniowy delay:** invite leci dopiero gdy `preferred_date + 14d ≤ now()`.
Powód: klient ma czas ochłonąć, opinia jest bardziej trafna niż „świeży"
emocjonalny pierwszy wrażeniowy strzał.

**Magic link UX:** klient nie ma konta w Hoverze i nie musi go zakładać.
Token (48 znaków `Str::random`, sha256 w DB) w URLu `/transport/review/{token}`
= jedyna poświadczeniowa. TTL = 30 dni od `invite_sent_at`. Token użyty raz —
kolejny GET pokazuje friendly „Już zostawiłeś opinię" (200, nie 404).

**1 opinia per (lead × response):** unique key w DB. Idempotent dispatch
(re-run cron nie wyśle drugiego invite). Anti-double na poziomie DB > app
race conditions.

**Default publish, opt-out moderacja:** po submit'cie `status = published`
natychmiast (Hovera = pośrednik). Transporter widzi opinię w `/transport`
panel, może:
- **„Odpowiedz publicznie"** → `transporter_response` + timestamp,
  widoczne pod opinią na `/t/{slug}`. Edycja dozwolona.
- **„Zgłoś do moderacji"** → `status = flagged` + `flagged_reason`,
  review znika publicznie. Trafia na master admin's queue.

**Master admin moderacja:** `/admin/transport-reviews` z badge'em flagged
count. Akcje: `publish` (przywróć), `hide` (zatwierdź ukrycie), `reject`
(soft delete — używać oszczędnie). Każda akcja w `MasterAuditLogger`.

**GDPR data minimisation:** email zamawiającego po wysyłce invite
trzymamy tylko jako sha256 hash + redacted string („j***@example.com").
Imię i nazwisko publicznie redaktowane do formy „Jan K." — pełne nazwisko
nie wycieka. Aggregate (count/avg/distribution) cached 10 min, bustowany
po nowym submit / response / flag / moderacja.

---

## 14. Co dalej (post-MVP, ordered)

Lista priorytetów po fazie 8. Każda pozycja ma uzasadnienie biznesowe; kolejność
odzwierciedla relację „koszt budowy ↔ wpływ na konwersję/retencję".

### 14.1 KSeF integracja per-transporter

**Co:** transporter podpina swój KSeF (token autoryzacyjny w
`transport_settings.ksef_*`), Hovera w `TransportInvoiceResource` wysyła
faktury w jego imieniu (passthrough). Numer faktury = numeracja transportera,
NIP wystawcy = NIP transportera.

**Dlaczego priorytet 1:** w PL od 1 lutego 2026 KSeF jest **obowiązkowy dla
B2B**. Bez tego transporterzy nie mogą legalnie fakturować po fazie 8.

**Estymata:** 2–3 tygodnie. Bazujemy na istniejącym `app/Domain/Ksef/`
(używanym dla faktur stajni / hovery).

#### KSeF (transporter sam wystawia FV) — status MVP

Pierwsza wersja per-transporter KSeF jest w `app/Domain/Transport/Ksef/`
(`TransporterKsefService`). Decyzje:

- **Hovera = passthrough, NIE wystawca.** Wynika to wprost z §12.5 pkt 1.
  Każdy transporter ma WŁASNY token KSeF (zdobyty w panelu MF), WŁASNY NIP
  i wybiera środowisko (test/demo/prod). Hovera nigdzie nie figuruje jako
  podatnik wystawiający tę FV. Tag `<SystemInfo>` w XML = "Hovera Transport
  Passthrough" — żeby przy audycie MF było jasne, że jesteśmy tylko
  software'em.
- **Storage tokenu.** Kolumna `transport_settings.ksef_token_encrypted`
  (zaszyfrowana przez `Crypt::encryptString`). Token nigdy nie wraca do UI
  w czystej formie — po zapisie pole tokenu wyświetla się jako puste
  z notką "Token jest zapisany; wpisz nowy aby zmienić". Helper
  `TransportSettings::redactedTokenPreview()` zwraca podgląd typu
  `abc********xyz` do bezpiecznego logowania ops.
- **Audit logging = TENANT scope.** Wszystkie wywołania (submit, refresh,
  reject, error) idą do `audit_log` tenanta przez `TenantAuditLogger`, NIE
  do `MasterAuditLogger`. To akcja transportera, nie Hovery.
- **Status FV.** Nowy enum `App\Enums\TransportKsefStatus` z wartościami
  `not_submitted` (default) → `submitted` → `accepted | rejected | error`.
  Submit dozwolony tylko dla FV w stanie `issued/paid/overdue`; draft/void
  blokuje się w UI (nie ma sensu wysyłać szkicu).
- **Akcje w `TransportInvoiceResource`.** Single-row: „Wyślij do KSeF",
  „Odśwież status z KSeF". Bulk: „Wyślij zaznaczone do KSeF" z limitem 50
  (KSeF MF API jest rate-limited). Każda akcja confirm-modal.
- **Schema:** dwie migracje (`tenant`): `add_ksef_columns_to_transport_settings`
  (token + nip + environment + enabled) oraz `extend_transport_invoices_ksef_columns`
  (`ksef_reference_number`, `ksef_submitted_at`, `ksef_accepted_at`,
  `ksef_xml`, `ksef_error_payload` + index `(ksef_status, ksef_submitted_at)`
  pod przyszły cron poll).
- **Pełny handshake KSeF (production-grade flow).** Token autoryzacyjny
  trzymany przez transportera to NIE jest SessionToken używany w nagłówkach
  KSeF — to long-lived "klucz" wydany w panelu MF, który musimy wymienić
  na krótko-żyjący SessionToken (TTL ~2h) przez 3-etapowy handshake:
  1. `POST /online/Session/AuthorisationChallenge` z body
     `{contextIdentifier:{type:"onip", identifier:<NIP>}}` → MF zwraca
     `{challenge, timestamp}`.
  2. Lokalnie: generujemy świeży AES-256 klucz + IV, wrapujemy klucz przez
     RSA-OAEP z **klucz publicznym MF** (per-środowisko, dystrybuowany
     przez dokumentację KAS), szyfrujemy `<token>|<challenge_unix_ms>`
     przez AES-256-CBC. Buduje to payload `InitSessionTokenRequest`
     (XML z fragmentami base64).
  3. `POST /online/Session/InitToken` z tym XML-em → MF zwraca
     `{sessionToken: {token, expirationDate}}`. Trzymamy oba: SessionToken
     do nagłówka, AES klucz do dalszego szyfrowania payloadu faktur
     w obrębie tej samej sesji.

  Implementacja: `app/Domain/Transport/Ksef/Api/KsefHttpClient.php`
  (low-level), `app/Domain/Transport/Ksef/Session/KsefSessionManager.php`
  (cache + force-refresh), `TransporterKsefService` (orchestration).

- **Cache SessionToken'ów.** Tabela `ksef_session_tokens` (central DB,
  migration `2026_05_18_230000`). Klucz unikalny: `(tenant_id, environment)`.
  Trzymamy zaszyfrowany SessionToken + zaszyfrowany AES klucz + `expires_at`.
  Re-handshake automatyczny gdy `expires_at` < now + 60s (margines na
  clock skew). Re-handshake forsowany przy HTTP 401 z MF (token revoked).
  Bez cachu batch 50 faktur = 150 round-tripów do MF. Z cachem = 1 handshake
  + 50 sendów. Patrz `KsefSessionManager`.

- **Klucz publiczny MF.** Per-środowisko (test/demo/prod), dystrybuowany
  przez dokumentację KAS (gov.pl). Strategia ładowania w `KsefHttpClient::getPublicKey()`:
  (1) plik PEM w `storage/app/ksef/public-key-<env>.pem`,
  (2) fallback z `KSEF_PUBLIC_KEY_<ENV>_PEM` env var (inline PEM).
  Świadomie NIE pobieramy klucza z HTTP — MF nie publikuje stabilnego
  REST endpointa, a fetch over-the-wire wprowadza wektor MITM podmiany
  klucza. Ops MUSI wgrać klucz ręcznie przy provision'ingu środowiska.

- **Cron `transport:ksef:poll-submitted`.** Polluje wszystkich aktywnych
  transporter tenantów i dla każdego invoice'a w stanie `submitted`,
  starszego niż 5 minut (minimum age, żeby dać MF chwilę na processing)
  a młodszego niż 7 dni (max age, po tygodniu rezygnujemy i zostawiamy
  do manualnej obsługi) — wywołuje `refreshStatus()`. Mapowanie kodów MF:
  `200` → `accepted` (+ `ksef_accepted_at`); `100/110/300/305/315` →
  pozostaje `submitted`; `4xx` → `rejected` + `error_payload`; `5xx`
  → `error`. Batch limit 200 per tenant per run (`--limit=200`).
  Harmonogram: co 30 minut między 06:00 a 22:00 Warsaw (zob.
  `routes/console.php`). `withoutOverlapping(30)` chroni przed
  podwójnym uruchomieniem. Per-tenant try/catch — jeden zepsuty DB nie
  blokuje pozostałych.

- **Test handshake'u w UI.** Akcja „Test połączenia z KSeF" na stronie
  ustawień wykonuje pełen handshake (force-refresh sesji), żeby user
  od razu wiedział czy token został zaakceptowany przez MF. Komunikat
  błędu wyciąga sensowną informację, ale nigdy nie pokazuje samego
  tokenu (`cleanErrorMessage()` scrubuje długie alfanumeryczne ciągi).

- **Co JESZCZE nie zrobione (follow-up PR):**
  (1) Faktury korygujące (KOR) — XML builder ma case, ale flow korekt
      wymaga referencji do oryginalnej FV w KSeF.
  (2) XSD walidacja FA(3) przed wysyłką — MF i tak waliduje serwerowo,
      ale lokalna walidacja dałaby szybszy feedback w UI.
  (3) Batch endpoint `/Invoice/Batch` — obecnie wysyłamy pojedynczo.
      Dla regularnych transporterów (50+ FV/m-c) batch byłby tańszy.
  (4) Notyfikacje email/in-app dla `rejected` — obecnie status sam się
      aktualizuje przez cron, ale user musi wejść do UI by go zobaczyć.

### 14.2 Reviews / opinie transporterów

**Co:** po `accepted` leadzie, zamawiający dostaje link do oceny (1–5 + opis),
opinia idzie na publiczny profil `/t/{slug}`.

**Dlaczego priorytet 2:** dramatycznie zwiększa konwersję anonimowego ruchu
z SEO (osoby trafiające na profil widzą social proof). Bezpieczne dla nas,
bo §12 — Hovera nie wykonuje transportu, opinie są o transporterze.

**Estymata:** 1–2 tygodnie. Schema: `transport_reviews` (central, 1:1 z
`transport_leads` po `accepted`).

### 14.3 Płatności — Stripe Connect Express

**Rekomendacja:** Stripe Connect **Express** (nie Standard, nie Custom).

**Dlaczego Express:**

- **Hovera = platform**, **transporter = connected MoR.** Pieniądze nigdy
  nie przechodzą przez Hovera balance — direct flow od klienta do
  transportera. Z §12 — Hovera nie inkasuje za transport.
- **Onboarding minimalny.** Stripe-hosted KYC, ~5 minut z UI transportera.
  Express bierze KYC na siebie (compliance Stripe, nie nasze).
- **Prowizja jako `application_fee_amount`.** Auto-deduct od każdej
  transakcji — to jest nasz przychód z prowizji (§12.3).
- **Wypłaty transportera = direct na jego konto bankowe.** Bez middleware
  bookkeeping po naszej stronie.

**Alternatywa rozważona i odrzucona:** direct charge bez Connect. Łatwiej
zbudować, ale prowizja musiałaby być fakturowana osobno post-fact, plus
nie skaluje się gdy dojdzie subscription pricing per-transakcja.

**Estymata:** 3–4 tygodnie (Connect onboarding + payment flow w
`transport_lead_responses` + reconciliation).

### 14.4 Trasy międzynarodowe (DE / CZ / SK / LT)

**Co:** rozszerzenie `voivodeship_adjacency` na regiony zagraniczne,
i18n routing (de.hovera.app/transport/anfrage), Vat-OSS / reverse charge
w fakturach, walidacja licencji przewozowej UE.

**Dlaczego priorytet 4:** największy unlock TAM, ale wymaga prawnego
rozeznania per-jurysdykcja (RODO różnie interpretowane w DE, FR ma
swój COVOA).

**Estymata:** 4–6 tygodni; może być rozłożone po krajach (DE first, bo
~40% niemieckich stajni już mówi po polsku z naszymi klientami z LZ).

### 14.5 Sponsored placements / reklama wewnątrz marketplace'u

**Co:** transporter płaci miesięczny boost żeby być wyżej w wynikach
wyszukiwania `/transport` lub żeby mieć badge „Polecany" na profilu.

**Dlaczego priorytet 5:** dodatkowy strumień przychodów, ale wymaga
że marketplace ma już krytyczną masę (>100 aktywnych transporterów),
inaczej nie ma o co konkurować.

### 14.6 Driver app (mobile)

**Co:** osobna mobile-first webview / PWA dla kierowców
(`/driver/{token}`) — zlecenie, dane konia, telefon do stajni,
podpis odbioru i dostawy, photo upload.

**Dlaczego priorytet 6:** Fleet plan unlock — bez tego transporter z 5+
kierowcami musi mailem rozsyłać zlecenia. Ważne dla retention klientów
z planu Fleet.

**Estymata:** 3 tygodnie.

### 14.7 Audit log review + monitoring

Niżej priorytetowe, ale do zaplanowania: dashboard master admina z
queries po `audit_logs.action IN ('transporter.verify', 'lead.dispatch',
'quote.accept')` — alerting na anomalie (np. 1 transporter akceptuje
80% leadów = możliwy sock-puppeting).

---

> **Status dokumentu:** v2.0 — odzwierciedla faktyczny stan modułu po PR #211–#221
> (2026-05-18). Aktualizacja: każda zmiana decyzji w §2 lub §12 → wpis w `git log`
> z prefixem `docs(transport):`.
## 15. Płatności — direct charge (MVP)

**Status:** ✅ wdrożone w gałęzi `claude/transport-payments-mvp`.

### 15.1 Model biznesowy

Hovera jest pośrednikiem marketplace (patrz §1.1 — pozycjonowanie). Nie jesteśmy
merchantem of record, nie obsługujemy bramki, nie trzymamy środków klienta.

Klient płaci **bezpośrednio do transportera** — Stripe Payment Link, Przelewy24,
BLIK, przelew tradycyjny, gotówka — *whatever the carrier accepts*. Konsekwencje:

- transporter ma własną relację z PSP (Payment Service Provider),
- transporter rozlicza VAT i podatek dochodowy bezpośrednio,
- Hovera nie ma webhooków → potwierdzenie wpływu jest **ręczne** (akcja
  „Oznacz jako opłacone" w `QuoteResource`),
- reklamacje płatności idą do transportera, nie do Hovery (disclaimer na
  landing'u + PDF + mailu).

### 15.2 Zakres MVP

| Komponent | Co robi |
|---|---|
| `quotes.payment_url` | URL do bramki transportera (paste-and-go) |
| `quotes.payment_method_label` | krótka etykieta (np. „Stripe", „BLIK") |
| `quotes.payment_completed_at` | ręczne potwierdzenie wpływu |
| `quotes.payment_notes` | wewnętrzne notatki ws. płatności |
| `transport_settings.default_payment_url_template` | szablon z placeholderami |
| `transport_settings.default_payment_method_label` | domyślna etykieta |
| `transport_settings.payment_instructions` | tekst-fallback gdy URL pusty |

Auto-fill: gdy transporter wstawi `default_payment_url_template` w settings
(z placeholderami `{quote_number}`, `{gross_total_pln}`, `{customer_name}`),
system rozwija go i przypisuje do nowej oferty przy create (przez
`PaymentUrlTemplate::expand()` w `CreateQuote::afterCreate()`).

### 15.3 UI — cztery stany sekcji płatności

Na publicznym landing'u (`/transport/quote/{slug}/{token}`) sekcja płatności
pokazuje się **tylko gdy quote.status = accepted**, z priorytetem:

1. **payment_completed_at set** → zielony banner „Płatność potwierdzona przez
   przewoźnika (:date)".
2. **payment_url set** → primary CTA „Zapłać teraz (:amount :currency)"
   (target="_blank", rel="noopener noreferrer nofollow"), opcjonalna etykieta.
3. **payment_instructions w settings** (URL pusty) → info-box z instrukcjami
   przelewu tradycyjnego.
4. **nic** → CTA „Skontaktuj się z {transporter}" + dane kontaktowe z
   `tenant.branding`.

Każdy stan zawiera disclaimer:
> Płatność realizowana BEZPOŚREDNIO do {transporter_name}. Hovera jest
> pośrednikiem marketplace i NIE przyjmuje płatności. Reklamacje płatności
> kieruj bezpośrednio do przewoźnika.

Disclaimer ten widoczny też w PDF (sekcja „Płatność") i w mailu („Jak zapłacić").

### 15.4 Plany & add-ony — source of truth

Plany transportowe są w pełni opisane w **marketingowej specyfikacji**
(`hovera.app/produkt/transport/` — komponent Astro `CarrierOnboarding.astro`).
W tym repo trzymamy seedery i kontrolery zgodne 1:1. Każda zmiana w marketingu
**musi** mieć PR w `hovera.app-sys` aktualizujący `TransportPlansSeeder`,
`TransportAddonsSeeder` i (jeśli dotyczy struktury) odpowiednie migracje.

**Kanoniczne kody w DB:**

- `transport_start` — 250 PLN / 4 drivers / 4 vehicles / 100 quotes/mc.
- `transport_pro` — 549 PLN / 8 / 12 / 500. **Most popular.**
- `transport_business` — 999 PLN / 15 / 25 / unlimited quotes.
- `transport_enterprise` — cena indywidualna (price_monthly_cents = 0,
  `features.marketing_cta = 'contact_sales'`). Renderowany w `/pricing/transport`
  jako CTA "Skontaktuj się" zamiast ceny.

Legacy plany (`transport_solo`, `transport_pro` 349 PLN, `transport_fleet`)
zostały zsoftbanowane via migrację `2026_05_18_220200_rename_legacy_transport_plans`
(`*_legacy` suffix, `is_active=false`, `is_public=false`) żeby istniejące Stripe
subskrypcje nie pękły. Master admin może przepiąć aktywnych klientów na nowe
plany przez `PlanResource` w admin panelu.

**Multi-currency:** `plans.prices_per_currency` JSON overlay dla EUR/GBP/AUD/NZD.
Bazowa cena (PLN) w `price_monthly_cents`. Helper `Plan::priceFor($currency, $cycle)`
zwraca cents lub `null` (Enterprise / nieznana waluta). 5 walut zafixowanych
w `Plan::supportedCurrencies()`.

**Trial flow:** marketing spec ("1 miesiąc gratis OD WERYFIKACJI, nie od signupu")
zrealizowany jako:
- `CreateTenant` ustawia `trial_ends_at = null` dla `TenantType::Transporter`
  + status `provisioning`.
- `TransporterResource::verify()` wywołuje `Tenant::startTrialOnVerification()`
  który ustawia trial 30 dni + status `trialing`. Idempotentny.
- Notyfikacja `TransporterVerifiedNotification` zawiera linię o aktywacji trialu.

**Stables get transport free:** `Tenant::canUseTransport()` — stable na każdym
planie ≠ `free` ma dostęp do modułu transport bez dodatkowych opłat (w ramach
swojego planu Hovery). Free-plan stable musi upgradeować Stable plan żeby wejść
w transport.

**Add-ony (6 globalnych, `is_global=true`, `plan_id=NULL`):**

| code | type | PLN |
|---|---|---|
| `migrate_excel` | one_time | 499 |
| `migrate_system` | one_time | 1499 |
| `onboarding_live` | one_time | 9,99 (sym.) |
| `invoice_setup` | one_time | 299 |
| `extra_driver` | recurring_monthly | 25/mc |
| `extra_vehicle` | recurring_monthly | 35/mc |

Każdy z `prices_per_currency` overlay (EUR/GBP/AUD/NZD). Add-ony stosują się
do każdego planu — odpytujemy `PlanAddon::where('is_global', true)`.

**Lock-in & promocja:** stawki obowiązują przy umowie min. 12 mc z gwarancją
niezmienności ceny; promocja do 2026-07-31. Klauzule w `lang/pl/public/legal.php`
sekcja 5 (Opłaty i płatności).

**Stripe Price IDs:** seedery NIE ustawiają `stripe_price_*_id` — trzeba je
uzupełnić ręcznie po stworzeniu produktów w Stripe Dashboard (komentarz w
`TransportPlansSeeder` docblocku). Bez tego checkout Stripe nie odpali na
nowych planach. **TODO przed produkcją.**

### 15.5 Co świadomie pominięte (post-MVP)

- **Stripe Connect API** (Express / Standard accounts) — pełna integracja
  z OAuth flow, encrypted credentials, server-side payment intents.
- **Webhooki PSP** — auto-mark-as-paid po `payment.succeeded` (zamiast ręcznie).
- **Multi-step payments** — zaliczka + pozostała kwota jako osobne URL'e.
- **Refundy / dispute handling** — UI dla zwrotów i sporów.
- **KSeF auto-fakturowanie** po `payment_completed_at` — w połączeniu z
  `IssueTransportInvoiceFromQuote`.

Decyzja: MVP wystarczy do walidacji UX. Pełna integracja Stripe Connect ma sens
dopiero gdy mamy 10+ aktywnych transporterów wystawiających 100+ ofert/mc —
inaczej koszt utrzymania webhooków > value.

---

> **Status dokumentu:** v2.1 — odzwierciedla faktyczny stan modułu po PR #211–#228
> (2026-05-18). Dodano §15 (Płatności direct charge MVP). Aktualizacja: każda zmiana
> decyzji w §2 lub §12 → wpis w `git log` z prefixem `docs(transport):`.
