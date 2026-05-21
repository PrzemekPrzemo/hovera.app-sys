# Session Handoff — Hovera Marketplace + Calculator Parity Sprint

> Stan: maj 2026, koniec sesji. Wszystkie 🔴/🟡 z `docs/MARKETPLACE-ROADMAP.md` zmergowane. Trwa 🟢 Calculator live UX.

---

## Co zostało zrobione w tej sesji (PR-y 303–319 + jedna pending #320 zaplanowana)

### ✅ 🔴 Krytyczne (5/5)
| PR | Tytuł |
|---|---|
| #303 | Owner panel content (HorseResource, TransportOrderResource, OrderTransport mini-Calculator, UpcomingTransportWidget) |
| #304 | Calculator: `horses_count` + `extra_horse_fee` |
| #305 | Uprość `/transport/quotes/create` — auto-routing + ukryj redundantne pola |
| #306 | PR 8 Open Board `/transport/marketplace` |
| #309 | PR 7: `transport_lead.client_type` + boarder picker (FV do owner'a, nie stajni) |

### ✅ 🟡 Średnie (8/8)
| PR | Tytuł |
|---|---|
| #307 | Owner panel — notifications hub (3 stat'y na dashboardzie) |
| #308 | Cross-tenant horse registry — foundation (PR 4/5) |
| #311 | ORS routing z weight/height pojazdu |
| #313 | Calculator: `fixed_fees` + `surcharge_percent` |
| #314 | Multi-currency z NBP exchange rate |
| #315 | Copy na `/register/horse-owner` — boarding management hint |
| #316 | Calculator: fuel mode toggle (surcharge vs full cost) |
| #317 | Calculator: `quote_items` line items + PDF breakdown |

### ✅ Hotfixy
| PR | Tytuł |
|---|---|
| #310 | MySQL identifier limit + light mode dla `/t/*` i `/transport/*` |
| #312 | Idempotent migration `horse_boarding_assignments` (`dropIfExists`) |

### ⚠️ 🟢 W trakcie — Calculator live UX (~6-8h całość, ~2h zrobione)
| PR | Tytuł | Status |
|---|---|---|
| #318 | Waypoints + POI library — foundation | ✅ merged |
| #319 | POIResource panel + waypoints UI w QuoteResource | ✅ merged |
| **#320 (TODO)** | **Leaflet map z trasą wyceny** | ⚠️ **branch `claude/calculator-leaflet-map` pushed lokalnie, NIE jest na GitHub'ie** |

---

## ⚠️ ZRÓB JAKO PIERWSZE w nowej sesji

**Branch `claude/calculator-leaflet-map`** ma 1 commit (`c1265c3`) który **nie trafił na GitHub** (MCP się rozłączył w trakcie sesji, sync ucięty). Konieczne:

```bash
git fetch origin main
git checkout claude/calculator-leaflet-map      # lokalnie istnieje
git rebase origin/main                            # gdyby było potrzebne
git push --force-with-lease origin claude/calculator-leaflet-map
```

Potem utwórz draft PR z body w `git log -1 --format=%B` (commit ma pełny opis).

Co dodaje: komponent Blade `<x-route-map>` (Leaflet 1.9.4 z CDN + OSM tiles + polyline decoder JS), zintegrowany w:
- `resources/views/filament/transport/pages/calculator.blade.php` (result section)
- `app/Filament/Transport/Resources/QuoteResource.php` (form route section, `Forms\View::make('components.route-map')` + viewData)
- `app/Filament/Transport/Pages/Calculator.php` — `calculate()` back-fillsuje `pendingPickupLat/Lng` z geocodingu

Tests: `tests/Feature/Transport/RouteMapComponentTest.php` (6 nowych, all green).

---

## 🟢 Co zostało do zrobienia z roadmapy (Calculator live UX — kolejne 3 podzakresy)

Plan: każdy jako osobny PR ~1-3h. **Wszystkie zależą od merge'a #320 (Leaflet map).**

### 1. Debounced live recalc API endpoint + JS (~3h) — najważniejszy
**Cel:** podczas edytowania pól w `/transport/calculator` cena przelicza się automatycznie po 500ms idle bez submit'u formy.

**Plan implementacji:**
- Nowy endpoint: `POST /api/transport/calculator/preview` (auth: filament session, sanctum middleware)
  - Body: `{from_address, to_address, calculation_mode, loaded, horses_count, fixed_fees, surcharge_percent, waypoints}`
  - Response: `Quotation::toArray()` (już istnieje DTO)
- Controller: `app/Http/Controllers/Transport/CalculatorPreviewController.php`
- Route w `routes/api.php` (sanctum.transport prefix już istnieje dla innych api endpoint'ów — sprawdź)
- Throttle: 60 req/min per user (preview może być spam'owany)
- Calculator blade: dorzucić AlpineJS + fetch z debounce(500) z `transport.calc.data`
- Sticky summary card po prawej (osobny PR? zobacz #2 niżej)

**Tests:** mock CalculatorService, sprawdź że endpoint zwraca poprawny JSON, throttle działa.

### 2. Sticky summary card z live breakdown (~1h)
**Cel:** zamiast tabeli pod formem, fixed sticky karta po prawej pokazuje live updated breakdown podczas edycji.

**Plan implementacji:**
- Calculator blade rewrite: grid 2-col (form po lewej, sticky card po prawej)
- Card używa AlpineJS x-data wired do response preview API
- Mobile: card collapse'uje się do bottom drawer

### 3. One-shot save-as-quote (~1.5h)
**Cel:** zamiast 2-step (Calculator → CreateQuote z session pending), jeden submit zapisuje quote.

**Plan implementacji:**
- Calculator action: nowy `saveAsQuoteInline` zamiast `saveAsQuote` (który redirectuje na CreateQuote)
- Tworzy Quote bezpośrednio + redirect na EditQuote (nie Create — quote już istnieje)
- Existing 2-step flow zostaje jako fallback dla complex form (np. customer_id picker, line_items)

---

## Konwencje (przestrzegane w tej sesji)

### Code style
- `vendor/bin/pint --dirty` przed commit (zwykle auto-fix, czasem manual)
- Komentarze po polsku w klasach (zgodnie z istniejącym stylem)
- Defensive parse na user input (zwłaszcza JSON — `Quote::normaliseLineItems` pattern)
- Snapshot wszystkiego co historycznie ważne (rate, fuel, surcharge, exchange_rate)

### Migrations
- Tenant migracje w `database/migrations/tenant/`
- Central w `database/migrations/`
- **Idempotent dla unique constraint'ów: `dropIfExists` PRZED `create`** gdy MySQL może mieć partial state (lessons learned z #310/#312)
- **Explicit constraint names dla wielokolumnowych unique** (MySQL limit 64 znaków): np. `hba_horse_stable_status_unique`

### Testing
- SQLite in-memory dla tenant DB (`tempnam + sqlite`)
- Każdy test patcher schema musi MIRRORować prod migrację — gdy dodajesz nową kolumnę, **uaktualnij wszystkie ~14 test schema setup'ów** (skrypt patcher w PR-ach pokazuje wzorzec — grep `create('quotes'` lub `create('transport_settings'`)
- Filament Resource'y z `getUrl()` wymagają `Filament::setCurrentPanel(Filament::getPanel('owner'))` w setUp testów (patrz `OrderTransportTest`)
- Pre-existing test debt: **7 errors + 1 failure** — nie ścigaj ich, ale flaguj jeśli się powiększy

### i18n
- PL/EN dla wszystkich keys (z konwencji projektu)
- Pliki w `lang/{pl,en}/transport/`, `lang/{pl,en}/owner/`, `lang/{pl,en}/public/`
- Enum labels w `lang/{pl,en}/enums.php`

### Filament patterns
- Resources używają `RestrictedByTenantRole` trait (Concern w `app/Filament/Concerns/`)
- Form auto-routing flow: `mutateFormDataBeforeCreate` w Pages\\Create klasie
- Cross-DB relations: services w `app/Domain/{Domain}/`, modele Central/Tenant w `app/Models/`

### Git
- Branch naming: `claude/<feature-slug>`
- Commit message po polsku, full opis (cel + zmiany + tests + roadmap status)
- Sufiks `https://claude.ai/code/session_*` na końcu commit message + PR body
- Draft PR z opisem skondensowanym z commit message + test plan checklist

### MCP github
- Po push'u zawsze sprawdzaj status MCP (czasem rozłącza się w trakcie sesji)
- Branch może być pushed lokalnie do `127.0.0.1` ale nie zsynchronizowany z GitHub — wtedy create_pull_request rzuci `422 Validation Failed: No commits between main and <branch>`
- W razie disconnect: `mcp__github__authenticate` daje URL do browser OAuth + callback do paste

---

## Pliki/foldery dotknięte w sesji (mapa zmian)

```
app/
  Domain/
    Horses/HorseRegistrySyncService.php (NEW, #308)
    Transport/
      Calculator/
        CalculatorService.php — rozbudowany o waypoints, multi-currency, fixed_fees, surcharge, fuel mode
        Data/CalculationOptions.php, Quotation.php — wszystkie nowe pola DTO
      Currency/NbpExchangeRateService.php (NEW, #314)
      Fuel/FuelPriceService.php — `calculateFullCost` (#316)
      Routing/
        Providers/OpenRouteServiceProvider.php — `profile_params.restrictions` (#311)
        Data/RouteOptions.php — `weightTons`, `heightMeters`
  Enums/
    FuelCalculationMode.php (NEW)
  Filament/
    Owner/ — całość (#303, #307)
    Transport/
      Resources/
        PoiResource* (NEW, #319)
        QuoteResource.php — line_items, waypoints, exchange rate snapshot
      Pages/
        Calculator.php — fixed_fees, surcharge, waypoints, multi-currency
        TransportSettings.php — wszystkie nowe defaulty + fuel mode select
  Http/Controllers/Public/
    TransportInquiryController.php — boarder picker (#309)
    TransportMarketplaceController.php (NEW, #306)
  Models/
    Central/
      CentralHorseRegistry.php (NEW)
      HorseBoardingAssignment.php (NEW)
      NbpExchangeRate.php (NEW)
      TransportLead.php — client_type
    Tenant/
      OwnerHorse.php, TransportOrder.php (NEW, #303)
      Poi.php, QuoteWaypoint.php (NEW)
      Quote.php — wszystkie nowe snapshoty + line_items + waypoints relation
      TransportSettings.php — wszystkie nowe defaulty

database/migrations/
  tenant/2026_05_20_172200_create_transport_orders_table.php
  tenant/2026_05_20_174000_add_extra_horse_fee_to_transport_settings.php
  tenant/2026_05_20_174100_add_horses_count_to_quotes.php
  tenant/2026_05_20_184200_add_central_horse_id_to_horses.php
  tenant/2026_05_20_190100_add_height_cm_to_vehicles.php
  tenant/2026_05_20_200000_add_fixed_fees_and_surcharge_to_transport_settings.php
  tenant/2026_05_20_200100_add_fixed_fees_and_surcharge_to_quotes.php
  tenant/2026_05_20_203100_add_exchange_rate_to_quotes.php
  tenant/2026_05_20_204300_add_fuel_calculation_mode_to_transport_settings.php
  tenant/2026_05_20_205200_add_line_items_to_quotes.php
  tenant/2026_05_20_211200_create_quote_waypoints_table.php
  tenant/2026_05_20_211300_create_pois_table.php
  2026_05_20_184000_create_central_horse_registry_table.php
  2026_05_20_184100_create_horse_boarding_assignments_table.php — IDEMPOTENT (dropIfExists)
  2026_05_20_192000_add_client_type_to_transport_leads.php
  2026_05_20_203000_create_nbp_exchange_rates_table.php

resources/views/
  components/route-map.blade.php (NEW, w #320 pending)
  filament/owner/* (NEW)
  filament/transport/pages/calculator.blade.php — extra fees, marża, fuel mode, Leaflet
  public/transport/marketplace.blade.php (NEW, #306)
  public/transport/_inquiry-form.blade.php — boarder picker
  transport/quote-pdf.blade.php — wszystkie nowe sekcje (line items, fixed fees, surcharge, waypoints, exchange rate footnote)

tests/Feature/
  Owner/ (NEW)
  Horses/HorseRegistrySyncServiceTest.php (NEW)
  Transport/
    Currency/NbpExchangeRateServiceTest.php (NEW)
    PoiResourceTest.php (NEW)
    QuoteLineItemsTest.php (NEW)
    QuoteResourceSimplifiedFormTest.php (NEW, #305)
    QuoteWaypointTest.php (NEW)
    RouteMapComponentTest.php (NEW, #320 pending)
    CalculatorServiceTest.php — rozbudowany ~10× (waypoints, multi-currency, fuel mode, surcharge, fixed_fees, vehicle params)
    FuelPriceServiceTest.php — calculateFullCost
    RoutingProvidersTest.php — ORS profile_params restrictions
  Public/TransportMarketplaceTest.php (NEW)
  TransportLeadClientTypeTest.php (NEW)
```

---

## Środowisko / setup

- PHP 8.4.19, Laravel 11.31, Filament 3.2
- Composer deps zwykle nie są installed na start sesji — `composer install` ~30s
- `vendor/bin/phpunit tests/Feature/` ~3-5 min full run; **baseline po sesji = 1248 tests, 7 errors + 1 failure** (wszystkie pre-existing)
- Pint: `vendor/bin/pint --dirty` ~10s

---

*Powodzenia w kolejnej sesji.*
