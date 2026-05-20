# Marketplace + Calculator parity — roadmap kontynuacji

> Stan: maj 2026 — po ukończeniu PR #297-#300 (HorseOwner foundation + landing redesign)
>
> Ten plik trzyma listę otwartych zadań po deep-dive sesji marketplace'u + analizy TransportKoni-Kalkulator. Każda pozycja powinna być **osobnym PR-em w osobnej sesji** (jeden chunk per session żeby context był czysty). Kolejność niżej = rekomendowana kolejność wykonania.
>
> Reszta produktu — patrz `docs/ROADMAP.md`.

---

## Status szybko

### ✅ Już zrobione (live na main)

**Marketplace foundation:**
- #297 — `TenantType::HorseOwner` + plan `owner_free` + migracje ENUM→VARCHAR
- #298 — `OwnerPanelProvider` (Filament panel `/owner`)
- #299 — `/register/horse-owner` self-serve signup + invite support (`?stable=...&token=...`)
- #300 — `/transport` landing redesign — 3 ścieżki (broadcast / owner account / direct booking)

**Wcześniejsze (bugfixes + features):**
- #287 blade error · #288 ORS fallback · #289 vehicle type · #290 Quotation Wireable · #291 places autocomplete · #292 PendingInvoicesWidget · #293 sendQuote gate · #294 calc modes · #295 Customer entity + MF/KRS · #296 saveAsQuote return type

### 🔴 Krytyczne, nie zrobione

| Priority | PR | Effort | Blocker for |
|---|---|---|---|
| 1 | PR 6 — Owner panel content | ~3-4h | bez niego owner panel = pusty Dashboard |
| 2 | Calculator: `horses_count` + `extra_horse_fee` | ~3h | transporter dostaje tę samą cenę za 1 i 4 konie |
| 3 | Uprość `/transport/quotes/create` | ~2h | feedback usera: za dużo pól |
| 4 | PR 9 — Direct booking CTA | ~1h | szybki win, mały scope |
| 5 | PR 8 — Open Board `/transport/marketplace` | ~2h | publiczna lista otwartych leadów |

### 🟡 Średnie

| PR | Effort |
|---|---|
| PR 4/5 — Cross-tenant horse + boarding | ~4-5h |
| PR 7 — Stable zamawia "for boarder" (per-zlecenie wybór klienta) | ~2h |
| PR 11 — Notifications hub w owner panelu | ~2h |
| Calculator: `fixed_fees` + `surcharge_percent` | ~3h |
| Calculator: fuel mode toggle (surcharge vs full cost) | ~2.5h |
| Calculator: `quote_items` line items + PDF breakdown | ~3h |
| Multi-currency z NBP exchange rate | ~2.5h |
| ORS routing z weight/height pojazdu | ~1.5h |

### 🟢 Duże, na później

| PR | Effort |
|---|---|
| Waypoints + reorder + POI library | ~8-10h |
| Calculator live UX (debounced recalc + Leaflet map) | ~6-8h |

---

## Szczegółowe specyfikacje PR-ów

### 🔴 PR 6 — Owner panel content (PRIORITY 1)

**Cel:** Po loginie do `/owner` user widzi treść zamiast pustego Dashboard placeholder'a.

**Pliki do utworzenia:**

```
app/Filament/Owner/Resources/HorseResource.php
app/Filament/Owner/Resources/HorseResource/Pages/{List,Create,Edit}Horse.php
app/Filament/Owner/Resources/TransportOrderResource.php
app/Filament/Owner/Resources/TransportOrderResource/Pages/{List,View}TransportOrder.php
app/Filament/Owner/Pages/OrderTransport.php  ← mini-Calculator
app/Filament/Owner/Widgets/UpcomingTransportWidget.php
app/Models/Tenant/OwnerHorse.php  ← legkie: name, breed, dob, passport_no, photos
app/Models/Tenant/TransportOrder.php
database/migrations/tenant/{date}_create_owner_horses_table.php
database/migrations/tenant/{date}_create_transport_orders_table.php
lang/{pl,en}/owner/{horses,transport,common}.php
tests/Feature/Owner/{HorseResource,OrderTransport}Test.php
```

**Schema:**
- `owner_horses` (per-tenant) — minimal: id, name, breed?, dob?, passport_no?, photos jsonb, notes, timestamps
- `transport_orders` (per-tenant) — id, central_lead_id (FK do TransportLead), horse_id, pickup/dropoff, status enum, timestamps. Tracks state of "moje zamówienie" — łącznik do centralnego TransportLead.

**`OrderTransport` page** = mini-Calculator dla owner'a:
- Pick horse z listy (`owner_horses`)
- Pickup address (z autocomplete)
- Dropoff address (z autocomplete)  
- Preferred date
- Mode (one-way / round-trip / return-home — z PR #294)
- Notes
- Submit → tworzy `TransportLead` w central DB (audience='public', `client_type='owner'`, `client_user_id=owner_user`) + `TransportOrder` row w owner DB
- Lead idzie broadcast'em do verified transporterów (jak istniejący flow)
- Owner widzi w "Moje zamówienia" status + responses

**Tenant DB schema considerations:** Owner ma OSOBNĄ tenant DB (provisioning z PR #297 zakłada full schema). Z perspektywy MVP można reuse'ować schemat stable'a (horses table już istnieje), albo zrobić light owner-only schema. **Decyzja:** użyć istniejącego `horses` table (już seedowanego przy provision'ie), ale ograniczyć resource do minimum kolumn (horse w stable ma 30+ pól, owner potrzebuje 8).

**Tests:** podstawowe CRUD + smoke że OrderTransport flow tworzy TransportLead w central.

---

### 🔴 Calculator: `horses_count` + `extra_horse_fee` (PRIORITY 2)

**Cel:** Transporter ładujący 4 konie liczy więcej niż 1 konia. Aktualnie kalkulator ignoruje liczbę koni.

**Migracje (tenant):**
```sql
ALTER TABLE quotes ADD COLUMN horses_count TINYINT UNSIGNED NOT NULL DEFAULT 1;
ALTER TABLE transport_settings 
  ADD COLUMN extra_horse_fee_default DECIMAL(8,2) NOT NULL DEFAULT 0;
ALTER TABLE quotes 
  ADD COLUMN extra_horse_fee_snapshot DECIMAL(8,2) NOT NULL DEFAULT 0;
```

**Zmiany w CalculatorService:**
```php
// W calculate() po obliczeniu distance/base/fuel:
$extraHorses = max(0, $options->horsesCount - 1) * $settings->extra_horse_fee_default;
$subtotalRaw = $baseCost + $fuelSurcharge + $extraHorses;
// reszta jak teraz
```

**CalculationOptions DTO:** dorzucić `public int $horsesCount = 1`.

**Calculator UI:** pole "Liczba koni" (number 1-30, default 1).

**QuoteResource:** snapshot `horses_count` + `extra_horse_fee_snapshot` przy zapisie.

**PDF breakdown:** osobna linia "Dodatkowe konie: 3 × 150 PLN = 450 PLN" gdy `horses_count > 1`.

**Tests:** 1 horse = no extra fee. 4 horses = 3 × extra_horse_fee dodane. Snapshot na quote.

**i18n PL/EN.**

---

### 🔴 Uprość `/transport/quotes/create` (PRIORITY 3)

**Feedback usera:** "za dużo pól tam jest zbędnych".

**Audyt obecnego formu (`QuoteResource::form()`):**

Sekcje + pola które się ujawniają user'owi w CreateQuote:

```
Header (3 pola):
  - number       ← OK (read-only, auto-gen)
  - status       ← OK (Draft default)
  - valid_until  ← OK (date)

Customer (8 pól):
  - customer_id picker
  - customer_name (required)
  - customer_email
  - customer_phone
  - customer_company
  - customer_tax_id
  - customer_address  ← rzadziej używane
  → REDUKCJA: ukryć pól customer_* gdy customer_id wybrany (już snapshot przez afterStateUpdated)

Route (10 pól!):
  - pickup_address
  - dropoff_address
  - pickup_lat       ← UKRYJ — auto z geocodingu
  - pickup_lng       ← UKRYJ — auto z geocodingu
  - dropoff_lat      ← UKRYJ — auto z geocodingu
  - dropoff_lng      ← UKRYJ — auto z geocodingu
  - preferred_date
  - preferred_time
  - round_trip (toggle)  ← UKRYJ jeśli mamy calculation_mode (PR #294)
  - loaded (toggle)

Resources (3 pola): vehicle, trailer, driver  ← OK

Pricing (11 pól!):
  - distance_km             ← auto z routingu, ukryć w trybie auto
  - rate_per_km
  - duration_seconds        ← UKRYJ — auto z routingu
  - base_cost               ← auto kalkulacja, ukryć
  - fuel_surcharge          ← auto kalkulacja, ukryć
  - minimum_adjustment      ← auto kalkulacja, ukryć
  - net_total               ← auto = base + fuel + min_adj
  - vat_rate
  - vat_amount              ← auto = net × vat_rate/100
  - gross_total             ← auto = net + vat
  - currency                ← UKRYJ (default z tenant settings)

Terms (3+ pola)
Payment (4+ pól)
```

**Plan:**

1. **Lat/lng:** zmienić na `Hidden` field — populowane przez geocoder w `mutateFormDataBeforeCreate()`.
2. **Auto-calculated finanse:** `base_cost`, `fuel_surcharge`, `minimum_adjustment`, `vat_amount`, `gross_total` → `Hidden` + populowane w lifecycle hook (po wybraniu vehicle + distance). Czy `disabled().dehydrated(true)` dla widoczności bez edytowalności.
3. **`duration_seconds`:** `Hidden` (auto z routingu).
4. **`currency`:** ukryć pole, default z `TransportSettings.currency` w `mutateFormDataBeforeCreate`.
5. **`round_trip`:** usunąć (zastąpione przez `calculation_mode` z PR #294).
6. **Customer fields:** zawijać w `->visible(fn (Get $get) => ! $get('customer_id'))` — gdy customer wybrany z bazy, nie wyświetlać redundantnych input'ów (pokazać badge z imieniem).
7. **Tryb "Auto-routing":** dodać toggle "Auto-routing z adresów" (default ON). Gdy ON → distance/duration/polyline auto. Gdy OFF → user wpisuje ręcznie (edge case).
8. **Sekcja "Pricing" cała na auto** — pokazać tylko `rate_per_km`, `vat_rate`, `net_total` + `gross_total` (read-only).

**Effort:** ~2h.

**Tests:** stary suite + nowy `test_lat_lng_auto_populated_from_geocoding` + `test_customer_id_hides_redundant_customer_fields`.

---

### 🔴 PR 9 — Direct booking CTA (PRIORITY 4)

**Cel:** Z `/przewoznicy` katalogu user klika kartę przewoźnika → ląduje na `/transport/zamow?przewoznik={slug}` → form z hidden `target_transporter_id` → lead idzie do TYLKO tego jednego przewoźnika.

**Pliki:**
- `routes/web.php` — `GET /transport/zamow` route
- `app/Http/Controllers/Public/DirectBookingController.php` (nowy)
- `resources/views/public/transport/direct-booking.blade.php` (form similar do `_inquiry-form.blade.php` ale z hidden + visible label "Wysyłasz zapytanie do: {Nazwa Firmy}")
- Update `resources/views/public/transport/directory.blade.php` — dorzucić "Zamów transport" CTA na każdej karcie

**Logika:**
- Lead utworzony przez `TransportInquiryController::store()` (istniejący) ale z `target_transporter_id` w payload
- Lead status = 'open' ale `is_direct=true` + `target_transporter_tenant_id` snapshot
- Lead NIE jest broadcast'em do innych — tylko ten jeden transporter widzi
- Transporter ma 24h żeby odpowiedzieć (timeout → wraca do open board)

**Effort:** ~1h. Najszybszy do zrobienia.

**Tests:** `test_direct_booking_creates_targeted_lead` + `test_direct_booking_form_pre_selects_transporter`.

---

### 🔴 PR 8 — Open Board `/transport/marketplace` (PRIORITY 5)

**Cel:** Lista publicznie widocznych otwartych leadów. Verified transporter klika "Złóż ofertę" → tworzy `TransportLeadResponse`. Owner/Stable widzą swoje leady w "Moje zamówienia" (innym widoku).

**Pliki:**
- `routes/web.php` — `GET /transport/marketplace`
- `app/Http/Controllers/Public/TransportMarketplaceController.php`
- `resources/views/public/transport/marketplace.blade.php`
- Update `lang/{pl,en}/public/transport_landing.php` — dorzucić sekcję "Marketplace" do path_directory albo nową

**Filtry (query params + select'y):**
- Service area (woj. PL z dropdownu)
- Vehicle type (truck / van / trailer)
- Calculation mode (one_way / round_trip / return_home)
- Distance range (`0-100km`, `100-500km`, `500-1000km`, `1000+km`)
- Date range (next 7/14/30 days)

**Lead card:** 
- Pickup → Dropoff (kraje/województwa, NOT full address — privacy)
- Date + horses_count
- "Złóż ofertę" CTA → modal/page dla verified transportera

**Effort:** ~2h. Trochę więcej UX work niż direct booking.

**Tests:** `test_marketplace_shows_open_leads_only` + `test_filters_apply` + `test_verified_transporter_can_submit_quote`.

---

### 🟡 PR 4/5 — Cross-tenant horse + boarding (TRUDNE)

**Cel:** Owner = source of truth dla swojego konia. Stajnia widzi konie pensjonariuszy, ale nie owns ich. Owner widzi gdzie jego konie boardują.

**Schema:**
```sql
-- Central DB:
CREATE TABLE central_horse_registry (
  id ULID PRIMARY KEY,
  primary_owner_user_id ULID NULL,  -- FK do central.users (owner)
  name VARCHAR(120) NOT NULL,
  breed VARCHAR(120) NULL,
  dob DATE NULL,
  passport_no VARCHAR(64) NULL UNIQUE,  -- unikalny jeśli wpisany
  created_at, updated_at
);

CREATE TABLE horse_boarding_assignments (
  id ULID PRIMARY KEY,
  central_horse_id ULID NOT NULL FK,
  stable_tenant_id ULID NOT NULL FK,
  owner_user_id ULID NULL FK,  -- może być NULL gdy stable ma horse'a bez przypisanego owner account
  status ENUM('pending','active','ended','disputed') NOT NULL DEFAULT 'pending',
  started_at DATETIME NULL,
  ended_at DATETIME NULL,
  created_at, updated_at,
  UNIQUE (central_horse_id, stable_tenant_id, status)  -- jeden aktywny boarding per stable
);

-- Tenant DBs (stable + owner):
ALTER TABLE horses ADD COLUMN central_horse_id ULID NULL FK;
```

**Flow:**

1. **Owner adds horse** (via `/owner/horses/create`):
   - Tworzy central_horse_registry row z primary_owner_user_id
   - Tworzy local horse row z central_horse_id
2. **Owner requests boarding at stable:**
   - W `/owner/horses/{id}/board-at` → wybiera stajnię z dropdown'a (search po slug/name)
   - Tworzy horse_boarding_assignments z status='pending'
   - Stable widzi pending request w panelu, klika "Akceptuj"
3. **Stable invites owner:**
   - W `/app/horses/{id}/invite-owner` → wpisuje email → owner dostaje invite link z stable_tenant_id + token
   - Owner się rejestruje (PR #299 obsługuje ten case przez `invite_stable_id` query param)
   - System auto-creates boarding_assignment
4. **Existing stable horses bez owner_user_id:**
   - Migration nic nie zmienia — stable horse zostaje bez central_horse_id (NULL)
   - Stable owner może claim'ować owner'a przez "Powiąż z właścicielem" → invite

**Effort:** ~4-5h. Najtrudniejsze bo cross-tenant, race conditions, edge cases.

**Tests:** 
- `test_owner_creates_horse_propagates_to_central_registry`
- `test_owner_requests_boarding_stable_must_accept`
- `test_stable_invite_owner_links_existing_horse`
- `test_unique_active_boarding_per_stable`

---

### 🟡 PR 7 — Stable zamawia "for boarder" (~2h)

**Cel:** Gdy stable zamawia transport, może wybrać czy klientem jest stajnia (default) czy konkretny boarder.

**Wymaga:** PR 4/5 (musimy wiedzieć kto boarduje co).

**Zmiana:** W stable's `CreateLead` / `Calculator` / `CreateQuote`:
- Nowy Select "Klient zlecenia":
  - "Stajnia (ja)" — domyślne, FV do stajni
  - "Boarder: [horse name] — [owner_name]" — z listy aktywnych boarding_assignments
- Gdy boarder wybrany:
  - `TransportLead.client_type = 'owner'`, `client_user_id = boarder.owner_user_id`
  - Owner dostaje notification email + widzi lead'a w swoim panelu
  - FV po acceptacji → na owner'a (nie stajnię)

**Migration:**
```sql
ALTER TABLE transport_leads 
  ADD COLUMN client_type ENUM('stable','transporter','owner','anonymous') NOT NULL DEFAULT 'anonymous',
  ADD COLUMN client_user_id ULID NULL,  -- gdy client_type='owner'
  ADD COLUMN created_by_tenant_id ULID NULL;  -- gdy lead z panelu stable
```

**Effort:** ~2h.

---

### 🟡 PR 11 — Notifications hub w owner panelu (~2h)

**Wymaga:** PR 6 (panel musi mieć content).

**Cel:** Owner widzi notyfikacje w panelu — new responses, quote accepted, transport upcoming, document expiry.

**Pliki:**
- `app/Filament/Owner/Widgets/NotificationsWidget.php`
- `app/Notifications/Owner/*` — event handlers (LeadResponseReceived, QuoteAccepted, TransportUpcoming, DocExpiring)
- `app/Console/Commands/OwnerNotifications/DailyDigest.php` — opt-in email digest

**Effort:** ~2h.

---

### 🟡 Calculator parity z TransportKoni-Kalkulator

Wszystkie analizy w prior session log. Brakuje:

| Element | Effort |
|---|---|
| `fixed_fees` (autostrady/prom) | ~1.5h |
| `surcharge_percent` (marża %) edytowalne per-quote | ~1.5h |
| Fuel mode toggle (surcharge vs full cost) w admin | ~2.5h |
| `quote_items` line items + PDF z breakdown | ~3h |
| Multi-currency z NBP exchange rate | ~2.5h |
| ORS routing z weight/height pojazdu (Vehicle.gross_weight_kg → routing params) | ~1.5h |

---

### 🟢 Duże inicjatywy

#### Waypoints + reorder + POI library (~8-10h)
- Tabela `quote_waypoints` per-tenant (id, quote_id, order, kind, address, lat, lng)
- Tabela `pois` (własna biblioteka POI transportera — baza/stajnia/parking)
- CalculatorService multi-leg routing (sum segmentów)
- Repeater UI z drag-drop reorder w Calculator/QuoteResource
- POIResource panel

#### Live recalc UX (~6-8h)
**Wymaga:** wszystkie calculator parity PR-y wcześniej (horses_count, fixed_fees, surcharge, fuel mode, quote_items).

- Calculator UI rewrite — JS debounced fetch `/api/transport/calculator/preview` na każdej zmianie pola
- Sticky summary card po prawej z live breakdown
- Leaflet map z geometrią (polyline decode)
- One-shot `save-as-quote` (zamiast 2-step Calculator → CreateQuote)

---

## Hard dependencies graph

```
PR 6 (owner panel content)
  └─→ PR 11 (notifications widget)
  └─→ PR 4/5 (cross-tenant horse)
        └─→ PR 7 (stable for boarder)

PR 8 (Open Board) — independent, requires nothing
PR 9 (Direct booking) — independent, requires nothing

horses_count — independent
fixed_fees — independent
surcharge_percent — independent
fuel_mode_toggle — independent
quote_items — independent (but better after horses_count + fixed_fees + surcharge)
multi_currency — independent
ORS vehicle params — independent

Live UX — requires ALL calculator parity PRs

Quote/create simplification — independent, can be done any time
Waypoints — independent (greenfield)
```

---

## Pomocnicze info dla agenta podejmującego się któregoś PR-a

### Setup
1. Pull main: `git checkout main && git pull origin main`
2. Branch: `git checkout -b claude/<short-name>`
3. Po commit: `git push -u origin <branch>`
4. PR przez `mcp__github__create_pull_request` (draft=true)

### Konwencje
- **Komentarze po polsku** w klasach (zgodnie z istniejącym stylem)
- **i18n we wszystkich locale'ach** (PL/EN minimum; DE/FR/RU dla enum'ów)
- **Testy** PHPUnit w `tests/Feature/` — porządek `vendor/bin/phpunit tests/Feature/` przed commit
- **Pint lint** `vendor/bin/pint --dirty` przed commit
- **NIE wpisywać** model identifier (`claude-opus-X`) do commitów / PR-ów

### Multi-tenant warnings
- Tenant DB migracje w `database/migrations/tenant/` (NIE w głównej `migrations/`)
- Central DB migracje w `database/migrations/`
- Każda tenant DB ma SWÓJ kopię tabel — migracja musi działać driver-agnostycznie (MySQL prod + SQLite test)
- Owner = `TenantType::HorseOwner` ma własną tenant DB, ale schema na razie reused ze stable'a (light usage)

### Pre-existing test debt
Pełen `tests/Feature/` ma **7 errors + 1 failure** które nie są regresjami mojej pracy. Nie ścigać ich w trakcie innego PR-a, ale zgłosić jeśli się powiększy.

---

*Po realizacji któregoś z PR-ów — odznacz w sekcji "Status szybko" + dorzuć do "Recently shipped" w głównym `docs/ROADMAP.md`.*
