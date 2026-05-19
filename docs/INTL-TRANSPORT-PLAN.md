# Międzynarodowe trasy transportu koni — plan migracji (post-MVP)

> **Status**: plan, decyzje produktowe wymagane przed implementacją.
> **Owner**: PrzemekPrzemo. **Last updated**: 2026-05-19.
> **Patrz**: handover §8 #12, docs/TRANSPORT.md §15 (faza międzynarodowa).

## Cel

Rozszerzyć Hovera Transport marketplace na trasy międzynarodowe (DE / CZ / SK / LT) — obecnie PL-only przez hardcoded voivodeship adjacency map.

Aktualny stan (audit 2026-05-19):
- ✅ Routing providers (ORS / Mapbox / Google) są **generic** — geograficznie nie ograniczone do PL
- ❌ Voivodeship hardcoded: `config/transport.php` `voivodeship_adjacency` — tylko 16 PL województw
- ❌ `transport_leads.pickup_voivodeship` enum constraint PL only
- ❌ `quotes.currency` — PLN hardcoded
- ❌ Zero VAT logiki per kraj (OSS / reverse charge)
- ❌ Zero TRACES hook'ów (UE EHS regulation dla transportu zwierząt cross-border)
- ❌ Brak Filament UI dla konfiguracji per-kraj

---

## Decyzje produktowe wymagane (przed implementacją)

### 1. Kraje first wave — kolejność i harmonogram

**Opcje**:
- **A.** DE-only pilot (3 mc) → CZ → SK → LT (6 mc razem)
  - Pro: max polskich transportów koni jedzie do DE (markets adjacent)
  - Con: dłuższy time-to-market dla CZ/SK
- **B.** PL + DE/CZ/SK równolegle (CEE bundle, 3 mc)
  - Pro: szybciej, complementary voivodeship adjacency
  - Con: więcej decyzji VAT/lawyer naraz, większe ryzyko regresji w PL
- **C.** Full UE pilot z DE/CZ/SK/LT równolegle (decyzja całościowa)
  - Pro: jeden compliance review (lawyer), spójna marketing message
  - Con: highest cost & risk, lawyer review per kraj wzmaga koszt

**Decision needed**: A vs B vs C — uzgodnić z user przed rozpoczęciem.

### 2. Model multi-country tenants

**Opcje**:
- **A.** Tylko **PL→XX export**: polscy transporterzy obsługują trasy zagraniczne, ale firmy z DE/CZ/SK/LT nie mogą się rejestrować na hovera (brak lokalnego marketplace per kraj)
- **B.** **Multi-country tenants**: firmy z każdego kraju mogą się rejestrować + mają lokalny katalog (`/de/transporteure`, `/cz/dopravci`, etc.)
- **C.** **Single global marketplace** z country filter (`/przewoznicy?country=DE`)

**Decision needed**: A jest najszybsze (PL→XX expansion), B i C wymagają poważnego refactor.

### 3. Lokalne języki UI

Już mamy `lang/{pl,en,de,fr,ru}/*`. Brakuje:
- `lang/cs/*` (CZ)
- `lang/sk/*` (SK)
- `lang/lt/*` (LT)

**Decision needed**: dodajemy lokalne wersje przed launch'em per kraj, czy wystarczy DE/EN dla CZ/SK/LT initial?

---

## Decyzje techniczne

### 4. Migracja `voivodeship` → `region_code` (ISO 3166-2)

**Breaking schema change**. Aktualnie:
- `tenants.country` (string, ISO 3166-1, default 'PL')
- `transport_leads.pickup_voivodeship` enum 16 PL voivodeships
- `transport_settings.service_areas` JSON array PL voivodeships
- `config/transport.php voivodeship_adjacency` — 16 PL adjacency map

**Target**:
- Nowa kolumna `region_code` (ISO 3166-2 — np. `PL-MZ`, `DE-BY`, `CZ-PR`, `SK-BA`)
- Shadow infra: kolumny `region_code` obok `pickup_voivodeship` (backward compat)
- Adjacency map: `config/transport.php` → `region_adjacency[country_code][region_code] = [adjacent_region_codes]`
- Filament UI: country selector + region selector cascading

**Migracje** (3-faza shadow):
1. **Phase 1A** — `add_region_code_to_transport_leads.php` (nullable) + backfill PL leads z `pickup_voivodeship` → `PL-{MZ|MA|...}`
2. **Phase 1B** — `add_region_code_to_service_areas.php` + backfill
3. **Phase 2** — Code refactor: voivodeship reads → region_code reads (z fallback na voivodeship gdy region_code null)
4. **Phase 3** — Drop `pickup_voivodeship` (po confirm że nic nie reads)

**TODO checklist**:
- [ ] Migration `add_region_code_to_transport_leads`
- [ ] Migration `add_region_code_to_service_areas`
- [ ] `Country` enum (PL, DE, CZ, SK, LT) + i18n labels
- [ ] `Region` enum z ISO 3166-2 codes per kraj (~70 entries DE, ~10 CZ, ~8 SK, ~10 LT)
- [ ] `RegionAdjacencyService` (replaces hardcoded `config/transport.php`)
- [ ] Filament `RegionSelector` reusable component
- [ ] Update `LeadDispatcher::matchTransporters()` na region_code
- [ ] Tests dla cross-border lead dispatch (PL→DE)

### 5. `quotes.currency` — multi-currency support

Dziś `quotes.currency='PLN'` hardcoded. Cross-border quote w EUR/CZK/GBP wymaga:
- Migration `change_currency_to_per_quote_in_quotes_table` — drop default 'PLN'
- UI: Filament Quote form `currency` Select z `Plan::SUPPORTED_CURRENCIES` (5 walut już w `Plan::prices_per_currency` JSON pattern z PR #229)
- Kalkulator: opcjonalne FX conversion w UI (display "Equivalent: 1230 PLN ≈ 285 EUR" bez side-effect na zapisane wartości)
- KSeF: `<KodWaluty>` już używa `$invoice->currency` (nie zawsze PLN) — `KsefInvoiceXmlBuilder` powinien działać

**TODO**:
- [ ] Migration `change_quotes_currency_to_dynamic`
- [ ] Update `QuoteResource::form` z Select currency (default PLN, optional non-PL)
- [ ] FX display helper (`config/transport.php` `fx_display_rates` — static reference rates, NIE używamy do persistowania)
- [ ] Update `CreateQuote::afterCreate` żeby P24/PayU autopay był skipowany dla non-PLN (wymaga update flow)
- [ ] Tests dla EUR quote acceptance (without P24 since EUR not supported by P24)

### 6. VAT logic per kraj

**Najbardziej skomplikowane**:
- **B2B EU** → reverse charge: polski transporter wystawia FV bez VAT (`vat_rate=0` + nota o reverse charge), niemiecki klient płaci VAT we własnym kraju
- **B2C EU** → OSS (One Stop Shop): polski transporter zbiera VAT DE/CZ/SK/LT po lokalnych stawkach i quartely deklaruje przez polski OSS
- **B2C non-EU** → bez VAT (export)
- **PL→PL** → standard 23% VAT (obecny flow)

**Wymaga**:
- VAT rules engine — `VatCalculator::computeForQuote(quote, seller, buyer)` z input'em country, VAT-ID, buyer_type
- Filament UI: `Quote::buyer_country` + `Quote::buyer_vat_id` + `Quote::buyer_type` (B2B/B2C) fields
- Lawyer review **per kraj** dla wzorów reverse charge notes

**TODO**:
- [ ] `VatCalculator` service z rules per scenariusz
- [ ] Migration `add_buyer_country_and_vat_id_to_quotes`
- [ ] Update KSeF XML — `<P_18>` (znacznik zwrotnego obciążenia) gdy reverse charge
- [ ] Lawyer review dla DE/CZ/SK/LT reverse charge templates
- [ ] OSS registration — decyzja produktowa: hovera platform sama nie deklaruje, tylko transporter (rozłączenie odpowiedzialności)

### 7. TRACES hook dla zwierząt cross-border

UE regulation 1/2005 i EHSR — transport zwierząt UE wymaga:
- Atest przewoźnika typu 2 (Type 2 — międzynarodowy) — już mamy dokument typu w onboarding (`PwlAuthorizationT2`)
- Świadectwo zdrowia (HC) zwierzęcia z TRACES NT (Trade Control & Expert System)
- Notyfikacja TRACES przed wyjazdem (24h)

**Zakres dla MVP intl**:
- Hovera **NIE integruje się z TRACES** w fazie 1 — transporter robi to ręcznie poza systemem
- W oferta PDF dodajemy disclaimer: „transport międzynarodowy wymaga ważnego TRACES — w gestii przewoźnika"
- Per quote → optional checkbox „trasa międzynarodowa wymaga TRACES" → trigger nota w PDF

**Future** (faza 2):
- TRACES NT API client (jeśli MF / KE udostępni dokumentację REST)
- Auto-generate TRACES notification z quote data

---

## Decyzje compliance

### 8. Lawyer review per kraj

Per handover §8 punkt 6: „Native review DE/FR/RU dla 3 plików enum'ów PWL — wymaga lawyer, nie translator (regulatory text)".

Dla intl:
- **DE**: Tierschutztransport-Verordnung (TierSchTrV), §11 GewO — transport zezwolenia
- **CZ**: Zákon č. 246/1992 o ochraně zvířat — dopravci
- **SK**: Zákon č. 39/2007 o veterinárnej starostlivosti — prepravcovia
- **LT**: Pavojingųjų atliekų transportavimas — leidimai

**TODO**:
- [ ] Lawyer engagement per kraj (estimate: 5-8h × 4 kraje × ~€150/h = ~€3-5k)
- [ ] Lokalne regulaminy marketplace per kraj (translation + legal review)
- [ ] Privacy policy per kraj (GDPR uniform, ale local DPO requirements)

### 9. Document verification flow per kraj

Aktualny `TransporterDocumentType` enum ma PL-specific docs:
- `RoadCarrierLicense` (Zezwolenie na wykonywanie zawodu Przewoźnika Drogowego — PL)
- `PwlAuthorizationT1/T2` (PWL — PL)
- `PwlDriverHandlerCertificate` (PL)
- `PwlVehicleApprovalCertificate` (PL)
- `WashDisinfectionLog` (PL)
- `CarrierLiabilityInsurance` (PL OCP)

Equivalents w innych krajach:
- **DE**: Erlaubnis nach §11 TierSchG (zwierzęta) + Transportgenehmigung (drogi) + ADR/SS dla niebezpiecznych
- **CZ**: Schválení dopravce SVS (Státní veterinární správa) + ŘPL (Řidičský průkaz lekce)
- **SK**: Schválenie dopravcu (Štátna veterinárna a potravinová správa) + LP (Licencia prepravcu)
- **LT**: VMVT leidimas (Valstybinė maisto ir veterinarijos tarnyba)

**TODO**:
- [ ] Extend `TransporterDocumentType` enum z country-specific cases (`PwlAuthorizationT1` → `PlPwlAuthorizationT1`, dodać `DeTierSchutzAllowance`, etc.)
- [ ] `DocumentVerificationFlow` per kraj (`PlVerificationFlow`, `DeVerificationFlow`, etc.) — różne required documents per country
- [ ] Filament `TransporterOnboarding` form — country selector + dynamic required docs

---

## Plan migracji (fazowy)

### Faza 1: Shadow infrastructure (1-2 sprint)

Bez breaking changes — dodajemy obok istniejących pól.

- [ ] Migration `add_region_code_to_transport_leads`
- [ ] Migration `add_region_code_to_service_areas`
- [ ] Migration `add_country_to_transport_settings` (kraj operacji transportera)
- [ ] `Country` enum (PL/DE/CZ/SK/LT) + `Region` enum
- [ ] `RegionAdjacencyService` (replaces hardcoded config map)
- [ ] Backfill commands: PL leads → `region_code=PL-{voivodeship}`
- [ ] Tests dla cross-border dispatch

### Faza 2: DE pilot (2-3 sprint)

- [ ] Lawyer review DE (regulaminy marketplace + reverse charge templates)
- [ ] DE region adjacency map (16 Bundesländer)
- [ ] DE document types w `TransporterDocumentType` enum
- [ ] DE onboarding flow (`/de/transport/dolacz`) z lokalnym tekstem
- [ ] DE landing page (`/de/przewoznicy` lub `/de/transporteure`)
- [ ] DE pricing display (EUR jako default per locale `de`)
- [ ] Smoke test: pierwszy DE tenant signup → verification → publish

### Faza 3: CZ + SK + LT roll-out (3-4 sprint)

Analogicznie do DE, jeden kraj naraz. Kolejność: CZ (highest demand) → SK → LT (smallest market).

- [ ] Lawyer review per kraj
- [ ] Region adjacency maps
- [ ] Document types
- [ ] Onboarding + landing per kraj
- [ ] Smoke tests

### Faza 4: VAT engine + TRACES disclaimer (1-2 sprint)

- [ ] `VatCalculator` service
- [ ] `quotes.buyer_country` + `buyer_vat_id` + `buyer_type` fields
- [ ] KSeF reverse charge XML support
- [ ] TRACES disclaimer w PDF templates

---

## Reuse z istniejącej bazy

- **`lang/{de,fr,ru}/*`** — istnieje, łatwo extend na CZ/SK/LT
- **`config/transport.php voivodeship_adjacency`** — wzorzec dla `region_adjacency[country]` per kraj
- **`Plan::prices_per_currency`** JSON pattern (PR #229) — używamy dla `Quote::price_in_currency`
- **`TransporterOnboardingController`** (PR #250) — istnieje, łatwo dodać country selector i conditional document fields
- **`TransporterRankingService`** (PR #241+) — generic enough, ale `attachPrimaryVoivodeships()` musi działać per region_code
- **KSeF `<KodWaluty>`** — już non-PLN aware

---

## Out of scope dla intl phase 1

- **Tax registration per kraj** dla hovery — Hovera nadal jest tylko platformą (`Serndormeco Holding sp. z o.o.`), nie świadczy usług transportowych. Per-kraj VAT registration tylko gdy włączymy własną prowizję (>0% application_fee_percent).
- **OSS deklaracje hovery** — patrz wyżej
- **Wielowalutowość rozliczeń tenant→Hovera** (subskrypcje) — już mamy 5 walut w `plans.prices_per_currency`, tenants płacą w lokalnej walucie
- **Driver-side localizacja** (app dla kierowców) — driver app w ogóle nie istnieje jeszcze (handover §8 #11)

---

## Estimated effort

- **Faza 1 (shadow)**: ~80h (2 sprint × 40h)
- **Faza 2 (DE pilot)**: ~120h + lawyer ~10h
- **Faza 3 (CZ+SK+LT)**: ~80h × 3 + lawyer ~10h × 3
- **Faza 4 (VAT engine)**: ~80h + lawyer review wzorów reverse charge

**Razem**: ~600h dev + ~50h lawyer ≈ **3-4 miesiące** roadmapy.

**Pierwszy commit z faktyczną implementacją** wymaga decyzji produktowych z sekcji „Decyzje produktowe wymagane" wyżej.
