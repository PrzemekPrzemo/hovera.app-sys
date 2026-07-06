# Hovera — Plan wdrożenia Phase 1 (top 10 priorytetów z WOW-PLAN-V2)

> ⚠️ **NIEAKTUALNE (2026-07-05):** ten dokument szacował KSeF submit i Channel B/C/D messaging jako
> "12-17 dni pozostało" — w rzeczywistości wszystko zostało domknięte w ciągu kolejnych 2 dni (PR
> #449-467). Phase 1 jest w całości ukończona. Zobacz `docs/CURRENT-STATUS.md` i
> `docs/PHASE-1-DECISIONS-CAPTURED.md` (nowszy, ale też sprawdź krzyżowo z CURRENT-STATUS.md).

> Powiązany z: `docs/WOW-PLAN-V2.md` (analiza per-tenant).
> Data: 2026-06-21.
> Założenie: ~2-3 tygodnie pracy, sekwencja po jednym PR na PR-y backend-heavy, równolegle docs/UI.

## 📊 Status implementacji (zaktualizowano 2026-06-21 wieczorem)

Po pełnej implementacji większości plan'u oraz investigation pre-existing infrastruktury:

| PR | Plan effort | Status | Komentarz |
|---|---|---|---|
| **PR D** — Batch health | 1 dzień | ✅ Merged (#437) | `BatchRegisterHealthCompletion` service + tests |
| **PR I1** — PDF templates | 6h | ✅ Merged (#438) | 2 templaty: tenant + Sendormeco/Hovera |
| **PR I2** — Calculator extra-horse-fee | 2h | ✅ Already existed | `horses_count` + `extra_horse_fee_default` w `TransportSettings` |
| **PR O1** — Notifications hub | 4h | ✅ Merged (#439) | `NotificationResource` z scope query, badge, mark-as-read |
| **PR O2** — Owner HorseResource part 1 | 1-2 dni | ✅ Merged (#440) | Standalone Pages already existed; PR dodał link z resource list |
| **PR O3** — Owner HorseResource part 2 | 2 dni | ✅ Already existed | `HorseTimeline` + `HorseCare` + `HorseMessages` standalone Pages |
| **PR S1** — Bulk boarding invoice | 1 dzień | ✅ Merged (#441) | `GenerateBulkBoardingInvoices` + `BulkInvoicing` Page existed; PR dodał testy (11) |
| **PR O4** — Owner wallet 3 providery | 3 dni | ✅ Already existed | `OwnerInvoicePaymentService` z multi-provider routing via `InitiatePayment` action |
| **PR I3** — KSeF prod + wszystkie docs | 3-4 dni | ⚠️ Częściowo (patrz niżej) | Cert upload + auth done; **send/poll + ZAL/UPR/RR brakuje** |
| **PR O5** — Komunikator 4-kanałowy | 5-7 dni | ⚠️ Tylko Channel A | A (stable↔owner per-horse) ~85% done; **B/C/D = 0%** |

**Reality**: ~80% planowanego scope było już dostarczone przed sesją (Fazy 1-5 z OWNER-STABLE-ROADMAP, Faza 3 PR 3.2 auto-billing, OwnerInvoicePaymentService, BulkInvoicing). Plan był pisany bez pełnego inwentarza istniejącej infrastruktury.

### Realne pozostałe gaps

#### PR I3 — KSeF (faktyczny remaining: 5-7 dni)

**Done**:
- `KsefClient::authenticate()` cert-based auth flow (XAdES-BES signed AuthTokenRequest)
- `KsefCertificateService` — PFX + PEM cert parsing
- `KsefSigningService` — XAdES-BES signing
- `KsefInvoiceXmlBuilder` — FA(3) XML dla FV/KOR/PRO
- `KsefSettings` Filament page — per-tenant cert upload, encrypted storage, test/demo/prod selector
- `TransporterKsefService` — pełny submit/poll flow (token-based) dla `TransportInvoice`
- `KsefPollSubmittedInvoicesCommand` — scheduled polling dla transport invoices

**Missing**:
1. **`TenantKsefSubmissionService`** dla regular `Invoice` — ~2-3 dni
   - Migration: extend `invoices` table z `ksef_reference_number`, `ksef_submitted_at`, `ksef_accepted_at`, `ksef_xml`, `ksef_error_payload`, `ksef_environment` columns
   - **Krypto challenge**: cert-based init session wymaga embedded AES-256 key (RSA-OAEP wrapped MF public key) w signed AuthTokenRequest. Aktualny `KsefSigningService::buildAuthTokenRequest()` NIE generuje AES key. Wymaga implementacji KSeF protocol §3.2 ([spec](https://www.podatki.gov.pl/ksef/specyfikacja-techniczna/)). Bez referencji do działającej implementacji ryzyko silent crypto bugs.
   - Submit button + status polling job + scheduled command
2. **ZAL/UPR/RR document types** — ~2 dni + decyzje domain
   - `InvoiceKind` enum: dodać `Zal`, `Upr`, `Rr` cases (zaktualizować 12 plików z match() statements)
   - **ZAL** wymaga: `advance_payment_amount_cents` field na Invoice + `<NumerZaliczki>`, `<KwotyZaliczek>` XML elementy + link do final invoice
   - **UPR** wymaga: validation rule "total ≤450 PLN brutto" + opcjonalność `buyer_nip`
   - **RR** wymaga: osobny FA_RR XML builder (inny schema namespace niż FA(3))
3. **JPK_FA(3) export** — ~1 dzień
   - `JpkFa3Exporter::export(int $year, int $quarter): string`
   - Console command `ksef:export-jpk-fa3`
   - Admin Filament page z download button

**Blocking decisions przed implementacją**:
- ZAL: jakie fields backstop'ują "kwoty zaliczek" — pojedyncza, czy lista? Link 1:1 do final FV czy N:1?
- UPR: validation hard-fail (blokuje issue) czy soft-warn?
- RR: pełna implementacja czy defer'ujemy do Phase 2 (niska częstotliwość użycia)?
- Reference implementation: jest dostęp do Billu-System code? Bez tego implementacja KSeF protocol §3.2 = guesswork.

#### PR O5 — Komunikator (faktyczny remaining: 7-10 dni)

**Done (Channel A — stable ↔ owner per-horse, ~85%)**:
- `HorseMessage` model (tenant DB) z direction enum (from_stable/from_client), read receipts
- `app/Filament/Owner/Pages/HorseMessages.php` — compose form, thread view, file upload (10 files / 25 MB)
- `app/Filament/App/Resources/HorseResource/RelationManagers/MessagesRelationManager.php` — stable-side tab
- `app/Domain/Messages/Owner/OwnerMessagesService.php` — listForHorse + send + markRead + unreadCount
- `OwnerSentMessageToStableNotification` (DB + mail)
- Tests: 5 plików, 500+ linii (HorseMessagesPage, OwnerMessagesService, OwnerMessagesApi, HorseMessageAttachmentStorage)

**Missing**:
1. **Channel B — stable ↔ vet external (magic-link)** — ~2-3 dni
   - Nowa `ExternalSpecialist` table (central DB)
   - `SpecialistMagicLink` token system + email flow
   - `/specialist` Filament panel (`SpecialistPanelProvider`)
   - Specialist-scoped messages resource
2. **Channel C — internal team channels (Slack-like)** — ~2-3 dni
   - `InternalChannel` + `InternalChannelMember` tables (central DB)
   - Auto-create 3 channels per stable on provision: `#general`, `#weterynaria`, `#transport`
   - Multi-member chat UI z @mention
3. **Channel D — cross-tenant owner ↔ vet** — ~3-4 dni (wymaga B done)
   - Unified `MessageThread` model w central DB
   - Owner UI: `/owner/specialists` resource (invite vet by email)
   - Specialist panel: inbox z thread'ami od ownerów
4. **Architektura decyzja**: Refaktor Channel A do central DB (jak B/C/D) czy zostawić w tenant DB osobno?
   - Refaktor: +2-3 dni, ale unified UX
   - Pozostawienie: szybciej, ale fragmentacja message storage

**Blocking decisions**:
- Auto-channels (C): kanały #general/#weterynaria/#transport per provision, czy admin tworzy?
- Specialist whitelist (B/D): open invite (any email), czy curated lista veterynarii?
- Notification cadence (C): per-message email czy daily digest?
- Storage: refactor A do central, czy parallel systems?

### Total remaining real work

| | Plan estimate | Real remaining |
|---|---|---|
| PR I3 | 3-4 dni | **5-7 dni** (z crypto risk) |
| PR O5 | 5-7 dni | **7-10 dni** (z decyzjami) |
| **TOTAL** | 8-11 dni | **12-17 dni** |

Reszta plan'u (PR D, I1, I2, O1, O2, O3, S1, O4) jest **DONE** — ~14 dni estimated effort dostarczone w ciągu ~1 dnia sesji bo większość była pre-existing infrastructure.

### Rekomendacja do dalszych prac

1. **Najpierw**: zebrać decyzje domain dla I3 (ZAL/UPR/RR fields + Billu-System reference access) + O5 (architektura channels). ~1 godzina Q&A.
2. **Potem PR I3a**: `TenantKsefSubmissionService` + migration + UI (najmniej crypto risky — kopia transport pattern). 2-3 dni.
3. **Potem PR I3b**: ZAL/UPR/RR po decyzjach domain. 2 dni.
4. **Potem PR I3c**: JPK_FA(3) exporter. 1 dzień.
5. **Potem PR O5a**: Channel B (vet magic-link). 2-3 dni.
6. **Potem PR O5b**: Channel C (internal channels). 2-3 dni.
7. **Potem PR O5c**: Channel D (cross-tenant). 3-4 dni.

**Sumarycznie**: 12-17 dni = 3-4 tygodnie real work przy poprawnych decyzjach.

---

## ⚠️ ZAKTUALIZOWANE 2026-06-21 po decyzjach user'a

Po sesji decyzyjnej rozmiar Phase 1 **urósł z 3 do ~5 tygodni** ze względu na:

| Element | Stara wersja | Nowa wersja | Powód |
|---|---|---|---|
| **KSeF (PR I3)** | 6h sandbox + tylko FV | **3-4 dni** produkcja + wszystkie dokumenty (FV/proforma/korekta/zaliczkowa/RR/JPK_FA(3)) | User: „od razu produkcyjnie, wszystkie typy, wzorzec z Billu-System" |
| **Owner wallet (O4)** | 2 dni, 1 provider | **3 dni**, **wszystkie 3** providery z admin selectorem per-tenant | User: „admin wybiera per-tenant" — adapter pattern |
| **Komunikator (O5)** | 2-3 dni, 1-on-1 | **5-7 dni**, **4 kanały** wieloosobowe | User: stable↔owner + stable↔vet + stable↔per-user + horse-owner↔vet |
| **PDF (I1)** | 4h, 1 template | **6h**, **2 templaty** | FV tenant'a z brandingiem stable'a/transportera + FV od Hovery z danymi Sendormeco Holding sp. z o.o. |

**Razem nowy effort Phase 1: 22-25 dni roboczych ≈ 5 tygodni kalendarzowych.**

---

## Decyzje user'a (potwierdzone)

### 1. KSeF — produkcja + wszystkie dokumenty

- **Środowisko**: produkcja od dnia 1 (`https://ksef.mf.gov.pl`), nie sandbox.
- **Typy dokumentów (wszystkie)**:
  - FV (faktura sprzedażowa)
  - Faktura korygująca (KOR)
  - Faktura zaliczkowa (ZAL)
  - Faktura proforma (PROF) — choć nie podlega obowiązkowi KSeF, builder XML i tak będzie miał
  - Faktura uproszczona (UPR — do 450 PLN)
  - Faktura RR (rolnicza)
  - JPK_FA(3) export — quartal/year za request
- **Wzorzec**: architektura i flow z `https://github.com/PrzemekPrzemo/Billu-System` (custom PHP, nie Laravel — bierzemy **kontrakty/decyzje biznesowe**, nie copy-paste)
- **Implementacja**: nowa hierarchia `app/Services/Ksef/Documents/{FvBuilder,KorBuilder,ZalBuilder,...}` + wspólny interface `KsefDocumentBuilder`

### 2. Owner wallet — wszystkie 3 providery, per-tenant choice

- Adapter pattern: `OwnerInvoicePaymentService` z driver registry
- W settings stable'a (`/app/settings/payments`): pole `owner_quick_pay_provider` = `p24|payu|stripe`
- Po stronie owner'a `pay_now` używa providera wybranego przez stable
- Każdy provider ma własny webhook (już są — extend dla `payment_for_owner_invoice=true`)
- Jeśli stable nie wybrał — fallback do P24 (najszerszy reach PL)

### 3. Komunikator — 4 kanały komunikacyjne

| Kanał | Sender → Receiver | Topology |
|---|---|---|
| **A** | stable ↔ owner | Per-koń + ogólny thread |
| **B** | stable ↔ vet (external) | Specialist magic-link auth (nie tenant) — nowe konto `external_specialists` |
| **C** | stable ↔ per user (internal team) | Slack-like channels: #general, #weterynaria, #transport |
| **D** | horse-owner ↔ vet | Cross-tenant przez central, vet musi mieć invite od stable lub od owner'a |

- Schema: `messages` table z `channel_type` enum + relacyjne klucze
- Email + database notification per kanał
- Attachment upload (PDF/JPG) — wspólne dla wszystkich kanałów

### 4. PDF — 2 szablony

- **Template 1**: `pdf.tenant-invoice.blade.php`
  - Branding tenant'a: `tenant->branding.logo_url`, `primary_color`
  - Dane sprzedawcy: `tenant.legal_name`, NIP, adres ze `tenant.settings.company.*`
  - Używany przez FV wystawiane przez stable/transporter dla swoich klientów
- **Template 2**: `pdf.hovera-invoice.blade.php`
  - Branding: logo `public/hovera-logo.svg`
  - Dane sprzedawcy: **Sendormeco Holding sp. z o.o.** + NIP/REGON Hovery z config
  - Używany przez central invoicing (Hovera → tenant subscription FV)
- Wybór template'u na bazie `Invoice.issuer_type` (tenant vs central)

---

## Rekomendowana kolejność i rozumowanie (UPDATED)

Sekwencja oparta o **3 kryteria**:
1. **Blokery doświadczenia** — bez Tier 1 cały system wygląda na half-baked
2. **Zależności techniczne** — niektóre PR-y otwierają możliwości innych (np. PDF jest wymagany przez bulk-invoicing)
3. **Time-to-value** — szybkie wygrane między ciężkimi pracami (rytm produktywności)

### Sekwencja:

| # | PR | Effort | Persona | Tier | Dlaczego TERAZ |
|---|---|---|---|---|---|
| 1 | **PR D — Batch health complete** | 1 dzień | stable | 1.1 | Dokończenie Phase 1, najtaniej, najszybsze użytkowe |
| 2 | **PR I1 — PDF faktury (DomPDF, 2 templaty)** | 6h | stable | 1 | Blokuje #4, #8; klienci czekają |
| 3 | **PR I2 — Calculator extra-horse-fee** | 2h | transporter | 1 | Quick win, zero ryzyka, frustrating gap |
| 4 | **PR O1 — Owner notifications hub** | 4h | owner | 2 | Najszybszy wow dla owner'a (przesunięte z #5 — buduje momentum) |
| 5 | **PR O2 — Owner HorseResource część 1** (photos + docs tabs) | 1-2 dni | owner | 1 | Krytyczny gap — owner panel content |
| 6 | **PR O3 — Owner HorseResource część 2** (health timeline + waga + boarding history) | 2 dni | owner | 1 | Domknięcie owner experience |
| 7 | **PR S1 — Bulk-monthly boarding invoice** | 1 dzień | stable | 2 | Wymaga PDF (z #2) |
| 8 | **PR O4 — Owner wallet (3 providery)** | 3 dni | owner | 2 | Adapter pattern + UI selector |
| 9 | **PR I3 — KSeF submit produkcja + wszystkie dokumenty** | 3-4 dni | stable | 1 | Mandatoryjne PL 2026 — pełna implementacja |
| 10 | **PR O5 — Komunikator 4-kanałowy** | 5-7 dni | owner+stable+vet | 2 | Najbardziej kompleksowy — last |

**Phase 1 całość: ~22-25 dni roboczych = 5 tygodni kalendarzowych.**

---

## 1. PR D — Batch-complete health records

### Branch
`claude/batch-complete-health-records`

### Cel
Stable robi szczepienia 8 koni jednego dnia. Multi-row akcja w `HealthRecordResource` z jednym wspólnym formularzem (data, specjalista, typ, notatka), jeden „mark done" dla całej selekcji.

### Pliki kluczowe
- `app/Filament/App/Resources/HealthRecordResource.php` — `bulkActions([BulkAction::make('batch_complete')...])`
- `app/Services/Health/BatchCompleteHealthRecords.php` (NEW) — service który tworzy N pending health records w jednej transakcji
- `tests/Feature/Health/BatchCompleteHealthRecordsTest.php` (NEW)

### Migration
Brak — używamy istniejących pól `HealthRecord` (performed_at, summary, specialist_id, type).

### UI
BulkAction `batch_complete`:
```php
Tables\Actions\BulkActionGroup::make([
    Tables\Actions\BulkAction::make('batch_complete')
        ->label('Oznacz jako wykonane')
        ->form([
            Forms\Components\DatePicker::make('performed_at')->required()->default(now()),
            Forms\Components\Select::make('specialist_id')->relationship('specialist', 'name'),
            Forms\Components\Select::make('type')->options(...)->required(),
            Forms\Components\Textarea::make('summary')->required()->rows(2),
            Forms\Components\DatePicker::make('next_due_at')->helperText('Wspólne next due dla wszystkich'),
        ])
        ->action(fn (Collection $records, array $data) => app(BatchCompleteHealthRecords::class)->execute($records, $data))
])
```

### Service
```php
class BatchCompleteHealthRecords
{
    public function execute(Collection $records, array $data): int {
        $count = 0;
        DB::transaction(function () use ($records, $data, &$count) {
            foreach ($records as $record) {
                $record->forceFill($data + ['completed_at' => now()])->save();
                $count++;
            }
        });
        return $count;
    }
}
```

### Testy
- 5 koni → bulk complete → wszystkie mają `completed_at` set + `performed_at` z formularza
- Próba bulk-complete na completed records → idempotent skip
- Validation: brak `type` w formularzu → ValidationException

### Manual verification
1. `/app/health-records` → zaznacz 3 pending vaccinations → „Oznacz wykonane" → modal z 4 polami → save
2. Lista pokazuje wszystkie 3 jako wykonane z tą samą datą + summary

### Effort: **1 dzień**

---

## 2. PR I1 — PDF faktury (DomPDF, 2 templaty)

### Branch
`claude/pdf-invoices-dompdf`

### Cel
2 odrębne template'y PDF — tenant-issued faktury z brandingiem stable'a/transportera, central-issued FV (od Hovera) z danymi Sendormeco Holding sp. z o.o.

### Pliki kluczowe
- `composer require barryvdh/laravel-dompdf`
- `app/Services/Invoicing/InvoicePdfGenerator.php` (NEW) — z `generateForTenant()` + `generateForCentral()`
- `resources/views/pdf/tenant-invoice.blade.php` (NEW) — branding tenant'a
- `resources/views/pdf/hovera-invoice.blade.php` (NEW) — branding Hovera + dane Sendormeco
- `config/hovera.php` — dorzucamy `legal_entity` block: nazwa, NIP, REGON, adres Sendormeco
- `app/Http/Controllers/Owner/InvoicePdfDownloadController.php` (NEW)
- `app/Filament/App/Resources/InvoiceResource.php` — row action `download_pdf`
- `app/Filament/Admin/Resources/CentralInvoiceResource.php` — analogiczny `download_pdf`

### Service
```php
class InvoicePdfGenerator
{
    public function generateForTenant(Invoice $invoice): string {
        return PDF::loadView('pdf.tenant-invoice', [
            'invoice' => $invoice,
            'tenant' => $invoice->tenant,
            'branding' => (array) ($invoice->tenant->branding ?? []),
            'company' => (array) data_get($invoice->tenant->settings, 'company', []),
            'lines' => $invoice->lines,
            'totals' => $invoice->totalsArray(),
        ])->output();
    }

    public function generateForCentral(CentralInvoice $invoice): string {
        return PDF::loadView('pdf.hovera-invoice', [
            'invoice' => $invoice,
            'seller' => config('hovera.legal_entity'), // Sendormeco data
            'buyer' => $invoice->tenant,
            'lines' => $invoice->lines,
            'totals' => $invoice->totalsArray(),
        ])->output();
    }
}
```

### config/hovera.php (extend)
```php
'legal_entity' => [
    'name' => 'Sendormeco Holding sp. z o.o.',
    'nip' => env('HOVERA_LEGAL_NIP'),
    'regon' => env('HOVERA_LEGAL_REGON'),
    'krs' => env('HOVERA_LEGAL_KRS'),
    'address' => [
        'street' => env('HOVERA_LEGAL_STREET'),
        'city' => env('HOVERA_LEGAL_CITY'),
        'postal_code' => env('HOVERA_LEGAL_POSTAL'),
    ],
    'bank_account' => env('HOVERA_LEGAL_IBAN'),
],
```

### Routes
- `GET /i/{slug}/{invoice}/pdf?signature=...` — public download (klient końcowy stable'a)
- `GET /app/invoices/{invoice}/pdf` — auth tenant download
- `GET /admin/central-invoices/{invoice}/pdf` — auth master admin

### Testy
- Tenant PDF zawiera `tenant.legal_name` jako sprzedawcę
- Tenant PDF zawiera kolory z `tenant.branding.primary_color`
- Hovera PDF zawiera „Sendormeco Holding sp. z o.o." + NIP z config
- Brak logo tenant'a → fallback minimalistic header (no crash)
- KOR invoice → header zawiera „FAKTURA KORYGUJĄCA" zamiast „FAKTURA"

### Manual verification
1. Stable wystawia FV → „Pobierz PDF" → widzi swój logo + dane
2. Master admin wystawia central FV za subscription → otwiera PDF → widzi logo Hovera + Sendormeco
3. Klient stable'a otwiera signed URL → PDF z brandingiem stable'a

### Effort: **6h**

### Zależności
**Otwiera**: #7 (bulk monthly invoice używa generatora), #9 (KSeF wymaga PDF jako attachment do JPK_FA)

---

## 3. PR I2 — Calculator extra-horse-fee

### Branch
`claude/calculator-extra-horse-fee`

### Cel
Transporter wycenia „2 konie zamiast 1" przez selektor `horses_count` w formularzu wyceny. Per-horse fee z TransportSettings.

### Pliki kluczowe
- `app/Domain/Transport/Calculator/CalculatorService.php` — `calculate(... CalculationOptions $opts)` już ma `horsesCount`, sprawdzić czy używa
- `app/Domain/Transport/Calculator/Data/CalculationOptions.php` — DTO check
- `app/Models/Tenant/TransportSettings.php` — dorzucić kolumnę `extra_horse_fee` jeśli brak
- `app/Filament/Transport/Resources/QuoteResource.php` — `horses_count` jako Select/NumericInput w form

### Migration (tenant)
```php
Schema::table('transport_settings', function ($t) {
    $t->integer('extra_horse_fee_cents')->default(0)->after('rate_per_km');
});
```

### Calculator logic
```php
// W CalculatorService::calculate()
$baseCost = $this->routingDistance * $rate;
$extraHorses = max(0, $opts->horsesCount - 1); // pierwszy koń wliczony
$extraHorseFee = $extraHorses * ($settings->extra_horse_fee_cents / 100);
$total = $baseCost + $extraHorseFee + $fuelSurcharge + ...;
```

### Testy
- 1 koń → `extra_horse_fee = 0`
- 3 konie + fee 100 PLN per extra → `extra_horse_fee = 200`
- Zero settings (legacy tenant) → fallback 0

### Manual verification
1. `/transport/settings` → wpisz `extra_horse_fee = 150 PLN`
2. Nowa wycena → wpisz 3 konie → finance pokazuje base + 300 PLN extra horse
3. PDF z wyceny zawiera „dodatkowe konie: 2 × 150 zł"

### Effort: **2h**

---

## 9. PR I3 — KSeF submit (produkcja + wszystkie typy dokumentów)

> **PRZESUNIĘTY** z #4 na #9 ze względu na zwiększony scope. Wymaga dojrzałego flow PDF (#2) + bulk-invoicing rytmu (#7) jako kontekst.

### Branch
`claude/ksef-full-document-suite`

### Cel
Pełna integracja KSeF z produkcyjnym środowiskiem MF — wszystkie typy dokumentów które polski przedsiębiorca może wystawić, plus JPK_FA(3) eksport, plus UPO retrieval z fallback'em na manualną wysyłkę gdy MF API niedostępne.

### Architektura — wzorzec z Billu-System

Billu-System (Twoje istniejące PHP MVC repo) ma sprawdzony flow + decyzje biznesowe dla 7 typów dokumentów. **Nie kopiujemy kodu** (inny framework), ale **przeszczepiamy**:
- Listę pól obowiązkowych per typ dokumentu (z polskiej Ustawy o VAT)
- Mapowanie pól wewnętrznych → KSeF schema FA(3)
- Reguły walidacji przed submit (suma vat, suma netto, numeracja)
- Flow retry przy MF API failure
- Format UPO PDF + parsing

Architecture w Hoverze:

```
app/Services/Ksef/
├── KsefSubmissionService.php       # Orchestrator (główne API)
├── KsefAuthService.php             # OAuth2 + session token (RSA-OAEP wrap)
├── KsefHttpClient.php              # Retry + circuit breaker + HMAC
├── Documents/
│   ├── KsefDocumentBuilder.php     # Interface
│   ├── FvBuilder.php               # Faktura sprzedażowa
│   ├── KorBuilder.php              # Faktura korygująca
│   ├── ZalBuilder.php              # Faktura zaliczkowa
│   ├── ProfBuilder.php             # Proforma (nie KSeF ale builder spójny)
│   ├── UprBuilder.php              # Uproszczona (do 450 PLN)
│   └── RrBuilder.php               # Rolnicza (RR)
├── Export/
│   └── JpkFa3Exporter.php          # Kwartał/rok export do gov
└── Validation/
    └── DocumentValidator.php       # Spójność totals przed submit
```

### Pliki kluczowe (UPDATED)

- `app/Services/Ksef/KsefSubmissionService.php` (NEW, ~200 linii)
- `app/Services/Ksef/Documents/{Fv,Kor,Zal,Prof,Upr,Rr}Builder.php` (NEW, 6 plików × ~80 linii)
- `app/Services/Ksef/Export/JpkFa3Exporter.php` (NEW, ~150 linii)
- `app/Services/Ksef/Crypto/SessionTokenBuilder.php` (NEW) — RSA-OAEP wrap AES-256
- `app/Jobs/Ksef/FetchKsefUpoJob.php` (NEW) — pollowanie statusu
- `app/Models/Tenant/Invoice.php` — extend `kind` enum o nowe typy
- `app/Enums/InvoiceKind.php` — extend: `fv|kor|zal|prof|upr|rr`
- `app/Filament/App/Resources/InvoiceResource.php` — wybór typu w form + akcja submit
- `app/Console/Commands/KsefJpkFa3ExportCommand.php` (NEW) — `php artisan ksef:jpk-fa3 --year=2026 --quarter=2`

### Migrations (tenant + central)

```php
// tenant/...add_ksef_columns_to_invoices.php
Schema::table('invoices', function ($t) {
    $t->string('ksef_reference_number', 60)->nullable()->after('paid_at');
    $t->timestamp('ksef_submitted_at')->nullable();
    $t->timestamp('ksef_upo_at')->nullable();
    $t->string('ksef_status', 16)->default('pending'); // pending|submitted|accepted|rejected|manual_fallback
    $t->string('ksef_upo_path', 255)->nullable();
    $t->text('ksef_error')->nullable();
    $t->string('related_invoice_id', 26)->nullable(); // dla KOR (originalna FV) + ZAL (faktura finalna)
});
```

### Service flow (submit)

1. `KsefSubmissionService::submit(Invoice $invoice)`:
   - `DocumentValidator::validate($invoice)` → throw jeśli totals niespójne
   - `BuilderFactory::for($invoice->kind)->build($invoice)` → XML zgodny z FA(3) schema
   - `XAdESSignerService::sign($xml, $tenant->ksef_cert)` → signed XML (już mamy)
   - `KsefAuthService::initSession($tenant)` → session token (z RSA-OAEP wrap + MF public key)
   - `KsefHttpClient::send($signedXml, $sessionToken)` → element_reference_number
   - Update `Invoice.ksef_reference_number` + `ksef_status='submitted'`
   - Dispatch `FetchKsefUpoJob` z delay 5min

2. `FetchKsefUpoJob`:
   - GET `/api/online/Invoice/Status/{ref}` → `(status, upoPdfUrl?)`
   - `200 + UPO` → download UPO PDF → save `storage/app/ksef/upo/{tenant_id}/{ref}.pdf` → `ksef_status='accepted'`
   - `200 in_progress` → reschedule self za 5min (max 1h retry)
   - `4xx/5xx` → status='rejected' lub `manual_fallback` (gdy MF API down >2h) + alert do master admin
   - Po 1h max retry → status='manual_fallback' + warning na tenant

3. `JpkFa3Exporter::export(int $year, int $quarter): string`:
   - Query: wszystkie tenant'a FV/KOR/ZAL z `issued_at` w danym kwartale
   - Build XML JPK_FA(3) schema
   - Generate plik `jpk_fa3_{nip}_{year}_q{quarter}.xml`
   - Master admin pobiera ręcznie i wysyła do e-Deklaracji (NIE automatycznie)

### UI w Filament

- **InvoiceResource** form: nowy Select `kind` z 6 opcjami + helper text per typ
- **InvoiceResource** infolist: KSeF status badge (pending/submitted/accepted/rejected/manual_fallback) + link do UPO PDF gdy `accepted` + link do oryginalnej FV gdy KOR/ZAL
- Akcje: `submit_to_ksef`, `resubmit_to_ksef`, `force_manual_fallback`, `download_upo`
- Nowa Page `/app/ksef/jpk-fa3` z formularzem (rok + kwartał) + button „Generate JPK_FA(3)"

### Testy

- `DocumentValidator` — Total sum mismatch → ValidationException
- `FvBuilder` → output XML zgodny z FA(3) XSD schema
- `KorBuilder` → wymaga `related_invoice_id` (oryginalna FV)
- `ZalBuilder` → snapshot kwoty zaliczki
- `SessionTokenBuilder` → wrap klucza RSA-OAEP + MF public key (test PEM)
- `KsefHttpClient` → retry 3× przy 500 → fail
- `JpkFa3Exporter` → kwartał Q2 = kwiecień-czerwiec, zwraca tylko issued_at w tym zakresie
- `FetchKsefUpoJob` → reschedule gdy pending, complete gdy accepted

### Manual verification (produkcja!)

1. Master admin wgrywa production cert (PFX) w `/admin/ksef-settings`
2. Stable wystawia FV → wybiera kind=`fv` → submit → status `submitted` → 5-10 min później status `accepted` + UPO PDF widoczny
3. Stable wystawia KOR do tej FV → wybiera kind=`kor` + `related_invoice_id` → submit → analogiczny flow
4. Master admin idzie do `/app/ksef/jpk-fa3` → wybiera Q2 2026 → download XML → submit ręcznie do e-Deklaracji.gov.pl
5. **EDGE CASE**: MF API down → status='manual_fallback' po 1h → admin pobiera signed XML → wysyła ręcznie przez gov UI

### Effort: **3-4 dni**

### Risk

- **HIGH** — produkcyjne KSeF wymaga prawidłowego cert + ochronę przed double-submission (idempotent key z `reference_number`)
- Backup plan: `manual_fallback` status gdy API niedostępne → admin sam wysyła XML do gov

### Zależności

**Wymaga**: #2 (PDF — UPO PDF preview obok FV PDF w UI)
**Otwiera**: Phase 2 — auto-generate KOR przy zwrocie + automatyczne ZAL przy zaliczkach od klienta

### Pliki kluczowe
- `app/Services/Ksef/CentralKsefService.php` — dorzucamy `submitInvoice(Invoice $invoice): string` (zwraca element ref number)
- `app/Services/Ksef/Crypto/KsefSessionTokenBuilder.php` (NEW) — RSA-OAEP wrap AES-256 klucza sesyjnego MF public key'iem
- `app/Jobs/Ksef/FetchKsefUpoJob.php` (NEW) — polluje status po 5min, pobiera UPO PDF
- `app/Models/Central/Invoice.php` — kolumny `ksef_reference_number`, `ksef_upo_at`, `ksef_status`, `ksef_upo_path`
- `database/migrations/central/...add_ksef_columns_to_invoices.php` (NEW)

### Migration (central)
```php
Schema::table('invoices', function ($t) {
    $t->string('ksef_reference_number', 60)->nullable()->after('paid_at');
    $t->timestamp('ksef_submitted_at')->nullable()->after('ksef_reference_number');
    $t->timestamp('ksef_upo_at')->nullable()->after('ksef_submitted_at');
    $t->string('ksef_status', 16)->default('pending')->after('ksef_upo_at'); // pending|submitted|accepted|rejected
    $t->string('ksef_upo_path', 255)->nullable()->after('ksef_status');
    $t->text('ksef_error')->nullable()->after('ksef_upo_path');
});
```

### Service flow
1. `submitInvoice($invoice)`:
   - Build XML (już mamy)
   - Sign XAdES-BES (już mamy)
   - Build session token: AES-256-CBC random key + IV → wrap key RSA-OAEP MF public key → base64
   - POST `https://ksef-test.mf.gov.pl/api/online/Session/InitToken` z `init_token.xml` (zawiera session token, NIP, timestamp)
   - Odpowiedź: `session_token` (JWT-like) ważny 1h
   - POST `/api/online/Invoice/Send` z signed XML + Authorization session_token → `element_reference_number`
   - Zapisz w `Invoice.ksef_reference_number` + status='submitted'
   - Dispatch `FetchKsefUpoJob($invoice)` z delay 5min

2. `FetchKsefUpoJob`:
   - GET `/api/online/Invoice/Status/{ref}` → status (200=in progress, 200+UPO=success, błąd=rejected)
   - Jeśli ready → pobierz UPO PDF → save do `storage/app/ksef/upo/{tenant_id}/{element_ref}.pdf`
   - Update `Invoice.ksef_upo_at`, `ksef_status='accepted'`, `ksef_upo_path`
   - Jeśli pending → reschedule job za 5min (max 1h retry)
   - Jeśli rejected → status='rejected', zapisz error, dispatch alert do master admin

### UI w Filament (admin + tenant)
- `InvoiceResource::getInfolist` lub form: pokazujemy ksef_status badge + link do UPO PDF gdy accepted
- Akcja `resubmit_to_ksef` (manual retry przy rejected)

### Testy
- `submitInvoice` → mockujemy MF endpoint → assert Invoice ma `ksef_reference_number` set
- `FetchKsefUpoJob` → mockujemy status endpoint → assert `ksef_upo_at` + path set
- Rejected response → status='rejected', error zapisany
- 500 ze strony MF → retry 3× then fail

### Manual verification
1. Wystaw FV w stable → status draft → click "Send to KSeF" → notification "Wysłano, czekam na UPO"
2. Po 5-10 min: ksef_status zmienia się na 'accepted', pojawia się link UPO PDF
3. Test rejection: mock invoice z błędnym NIP → status='rejected', error widoczny

### Effort: **6h**

### UWAGA prawne
- KSeF wymaga rejestracji testowego konta MF (`https://ksef-test.mf.gov.pl/web`)
- Production endpoint po accepted certyfikacji
- Mamy już cert + public key infrastructure

### Zależności
**Wymaga**: #2 (PDF) jako side-by-side preview UPO vs FV

---

## 5. PR O1 — Owner notifications hub

### Branch
`claude/owner-notifications-hub`

### Cel
Owner po loginie do `/owner` widzi listę swoich notyfikacji (faktury wystawione, wizyty weterynaryjne, akceptacje boardingu, recenzje quoted). Read/unread badge.

### Pliki kluczowe
- `app/Filament/Owner/Resources/NotificationResource.php` (NEW)
- `app/Filament/Owner/Resources/NotificationResource/Pages/ListNotifications.php` (NEW)
- `app/Models/Central/User.php` — używamy istniejącej tabeli `notifications` (Laravel native)
- `app/Providers/Filament/OwnerPanelProvider.php` — register resource + navigation badge (count unread)

### Schema
Brak. `notifications` table już jest (z Laravel notification scaffold).

### UI
```php
class NotificationResource extends Resource
{
    public static function table(Table $table): Table {
        return $table->query(fn () => Auth::user()->notifications())
            ->columns([
                IconColumn::make('read_at')->boolean()->trueIcon('check')->falseIcon('exclamation-circle'),
                TextColumn::make('data.title')->searchable(),
                TextColumn::make('data.body')->wrap()->limit(120),
                TextColumn::make('created_at')->since(),
            ])
            ->actions([
                Action::make('open')->url(fn ($r) => $r->data['url'] ?? null)
                    ->after(fn ($r) => $r->markAsRead()),
                Action::make('mark_read')->visible(fn ($r) => ! $r->read_at)
                    ->action(fn ($r) => $r->markAsRead()),
            ])
            ->bulkActions([
                BulkAction::make('mark_all_read')->action(fn (Collection $r) => $r->each->markAsRead()),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getNavigationBadge(): ?string {
        $count = Auth::user()->unreadNotifications()->count();
        return $count > 0 ? (string) $count : null;
    }
}
```

### Testy
- Listing pokazuje tylko notifications dla zalogowanego user'a
- `markAsRead` updates `read_at`
- Badge pokazuje count unread, znika gdy 0

### Manual verification
1. `/owner` → boczne menu → „Powiadomienia (3)"
2. Klik → lista
3. Klik „Otwórz" przy InvoiceIssued → przekierowanie do `/owner/invoices/X` + read_at set

### Effort: **4h**

---

## 6. PR O2 — Owner HorseResource część 1 (photos + docs)

### Branch
`claude/owner-horse-photos-docs`

### Cel
Owner klika konia → 2 nowe tabs w karcie: zdjęcia, dokumenty. Cross-tenant read przez TenantManager → stable tenant DB → `HorsePhoto` i `HorseDocument`.

### Pliki kluczowe
- `app/Filament/Owner/Resources/HorseResource.php` — extend form/infolist z 2 tabami
- `app/Services/Owner/HorseDataAggregator.php` (NEW) — wrapper który przepina TenantManager do stable i czyta dane
- `app/Filament/Owner/Resources/HorseResource/RelationManagers/PhotosRelationManager.php` (NEW)
- `app/Filament/Owner/Resources/HorseResource/RelationManagers/DocumentsRelationManager.php` (NEW)

### Cross-tenant pattern (kluczowe!)
```php
class HorseDataAggregator
{
    public function getPhotosFor(Horse $ownerHorse): Collection {
        $stableTenantId = $ownerHorse->primary_boarding_stable_tenant_id;
        if (! $stableTenantId) return collect();

        $stable = Tenant::find($stableTenantId);
        return app(TenantManager::class)->execute($stable, function () use ($ownerHorse) {
            return HorsePhoto::query()
                ->where('central_horse_id', $ownerHorse->central_horse_id)
                ->orderByDesc('taken_at')
                ->get();
        });
    }
}
```

### Migration
Brak — używamy istniejących tabel `horse_photos`, `horse_documents` w per-tenant DB.

### UI
W `HorseResource::form` lub infolist:
```php
Forms\Components\Tabs::make()->tabs([
    Forms\Components\Tabs\Tab::make('Zdjęcia')
        ->schema([/* repeater readonly */]),
    Forms\Components\Tabs\Tab::make('Dokumenty')
        ->schema([/* repeater readonly z download links */]),
])
```

### Testy
- Cross-tenant lookup zwraca photos dla central_horse_id
- Brak stable → empty collection (no crash)
- Owner widzi TYLKO photos swojego konia (nie wszystkich w stajni)

### Manual verification
1. Stable wgrywa 3 zdjęcia konia w `/app/horses/X/photos`
2. Owner loguje się na `/owner`, klika konia → tab "Zdjęcia" → widzi te 3 zdjęcia
3. Dokumenty: stable dodaje paszport scan → owner widzi w tab "Dokumenty" z download link

### Effort: **1-2 dni**

### Zależności
**Wymaga**: cross-tenant read pattern (one-time design)
**Otwiera**: #7 (część 2 — health timeline + waga + boarding history używa tego samego patternu)

---

## 7. PR O3 — Owner HorseResource część 2 (health + waga + boarding history)

### Branch
`claude/owner-horse-health-weight-boarding`

### Cel
3 kolejne taby: health timeline (vetka/podkucia/szczepienia z dat), weight timeline (wagi w czasie), boarding history (od kiedy w której stajni).

### Pliki kluczowe
- `app/Filament/Owner/Resources/HorseResource.php` — extend tabs
- `app/Services/Owner/HorseDataAggregator.php` — dorzucamy `getHealthRecordsFor()`, `getWeightHistoryFor()`, `getBoardingHistoryFor()`
- Komponenty UI: charty (weight) — Chart.js przez Filament `Stat::chart()` lub HTML/SVG własny

### Migration
Brak.

### Health timeline UI
Lista vertical z ikoną per typ (vaccination/dental/farrier/visit) + data + summary + specialist.
```php
Forms\Components\Tabs\Tab::make('Historia zdrowotna')
    ->schema([
        Forms\Components\Placeholder::make('health_timeline')
            ->content(fn ($record) => view('owner.horse.health-timeline', [
                'records' => app(HorseDataAggregator::class)->getHealthRecordsFor($record),
            ])),
    ])
```

### Boarding history UI
Tabela z kolumnami: stable nazwa, od, do (nullable = w trakcie), powód zakończenia.

### Weight chart
Filament `Stat::make`-like chart albo `<canvas>` z Chart.js. Dane jako JSON.

### Testy
- HealthRecords pobrane z stable DB poprzez central_horse_id
- Boarding history pokazuje wszystkie historic + current assignments
- Weight chart renderuje empty state gdy brak wpisów

### Manual verification
1. Stable wpisuje 3 wagi konia w `/app/horses/X/weights`: 545kg → 548kg → 552kg
2. Owner: tab "Waga" → chart z 3 punktami trendującymi w górę
3. Stable rejestruje wizytę weterynaryjną → owner widzi w timeline
4. Stable kończy boarding → owner widzi "Stajnia Wisła: 2024-01 do 2026-06"

### Effort: **2 dni**

---

## 8. PR S1 — Bulk-monthly boarding invoice

### Branch
`claude/bulk-monthly-boarding-invoice`

### Cel
Stable z 30 boarderami klika 1 button → generuje FV za miesiąc dla wszystkich na podstawie BoxAssignment + plan cenowy. Status draft → manual review → batch send.

### Pliki kluczowe
- `app/Filament/App/Pages/BulkInvoiceMonthly.php` (NEW) — Filament page z formularzem + akcja preview/generate
- `app/Services/Invoicing/BulkMonthlyInvoiceGenerator.php` (NEW)
- `app/Jobs/Stable/SendBulkInvoicesJob.php` (NEW) — analogiczny do `SendInvoiceToClientJob`

### Migration
Brak (używamy istniejących Invoice, Client, BoxAssignment).

### Service
```php
class BulkMonthlyInvoiceGenerator
{
    public function preview(int $year, int $month, array $clientIds): Collection {
        $assignments = BoxAssignment::query()
            ->whereIn('client_id', $clientIds)
            ->where(function ($q) use ($year, $month) {
                // assignment aktywny w danym miesiącu
                $q->where('started_at', '<=', Carbon::create($year, $month)->endOfMonth())
                  ->where(fn ($q) => $q->whereNull('ended_at')
                      ->orWhere('ended_at', '>=', Carbon::create($year, $month)->startOfMonth()));
            })
            ->with(['client', 'box', 'horse'])
            ->get();

        return $assignments->groupBy('client_id')->map(function ($items, $clientId) use ($year, $month) {
            $client = $items->first()->client;
            return [
                'client' => $client,
                'lines' => $items->map(fn ($a) => [
                    'description' => "Pension {$a->horse->name} (box {$a->box->name})",
                    'quantity' => $this->daysInMonth($a, $year, $month),
                    'unit' => 'dni',
                    'price_cents' => $a->box->daily_rate_cents ?? $a->plan->monthly_rate_cents / 30,
                ]),
                'total_cents' => /* sum */,
            ];
        });
    }

    public function generate($previewData): int {
        $count = 0;
        DB::transaction(function () use ($previewData, &$count) {
            foreach ($previewData as $clientData) {
                Invoice::create([
                    'client_id' => $clientData['client']->id,
                    'status' => InvoiceStatus::Draft,
                    'lines' => $clientData['lines'],
                    // ...
                ]);
                $count++;
            }
        });
        return $count;
    }
}
```

### UI Page
- Wybór miesiąca + roku (Select z 12 ostatnich miesięcy)
- Multi-select klientów (lub „all active boarders")
- Button „Preview" → tabela z N wierszami: klient, koni, dni, łączna kwota
- Button „Generate drafts" → tworzy N draft FV
- Powiadomienie z linkiem do `/app/invoices?status=draft&created_today=1`

### Testy
- 5 klientów × 1 koń × 30 dni → 5 draft invoices z prawidłową kwotą
- Boarder z assignment od połowy miesiąca → tylko prorated days
- Klient z 2 końmi → 1 FV z 2 liniami
- Already invoiced za ten miesiąc → skip + warning

### Manual verification
1. `/app/bulk-invoices` → wybierz „Czerwiec 2026" + „All active boarders"
2. Preview pokazuje 12 klientów × średnio 1.3 koń = 18 lines, łącznie 32400 PLN
3. „Generate" → 12 draft FV w `/app/invoices?status=draft`
4. Manual review → bulk send (z PR A — bulk email)

### Effort: **1 dzień**

### Zależności
**Wymaga**: #2 (PDF) — bulk email wysyła PDFy

---

## 8. PR O4 — Owner wallet (3 providery, per-tenant choice)

### Branch
`claude/owner-wallet-adapter`

### Cel
Stable w settings wybiera quick-pay provider (P24 | PayU | Stripe). Owner klika fakturę → button „Zapłać teraz" → providery-agnostyczny adapter wybiera prawidłowego providera ze stable'a → hosted checkout.

### Pliki kluczowe
- `app/Filament/Owner/Resources/InvoiceResource.php` — row action `pay_now`
- `app/Filament/App/Pages/PaymentSettings.php` — extend o Select `owner_quick_pay_provider`
- `app/Services/Owner/OwnerInvoicePaymentService.php` (NEW) — adapter / driver registry
- `app/Services/Owner/Drivers/P24OwnerPaymentDriver.php` (NEW)
- `app/Services/Owner/Drivers/PayUOwnerPaymentDriver.php` (NEW)
- `app/Services/Owner/Drivers/StripeOwnerPaymentDriver.php` (NEW)
- `app/Services/Owner/Drivers/OwnerPaymentDriverInterface.php` (NEW)
- Webhook handlers extend: P24, PayU, Stripe — recognize `owner_invoice_payment` flow

### Migration (per-tenant)

```php
Schema::table('payment_settings', function ($t) {
    $t->string('owner_quick_pay_provider', 16)->default('p24')->after('default_provider');
    // Per-provider creds już istnieją (mamy P24/PayU/Stripe configurable)
});
```

### Adapter pattern

```php
interface OwnerPaymentDriverInterface
{
    public function createCheckoutSession(Invoice $invoice): string;
    public function handleWebhook(array $payload): ?Payment;
}

class OwnerInvoicePaymentService
{
    public function __construct(
        private readonly array $drivers = [
            'p24' => P24OwnerPaymentDriver::class,
            'payu' => PayUOwnerPaymentDriver::class,
            'stripe' => StripeOwnerPaymentDriver::class,
        ],
    ) {}

    public function createSessionFor(Invoice $invoice): string
    {
        $stable = $invoice->tenant;
        $provider = data_get($stable->settings, 'payments.owner_quick_pay_provider', 'p24');

        $driverClass = $this->drivers[$provider] ?? throw new \InvalidArgumentException("Unknown provider: {$provider}");
        return app($driverClass)->createCheckoutSession($invoice);
    }
}
```

### UI — Stable settings extension

W `/app/payment-settings` nowa sekcja „Owner quick-pay":
- Select „Provider dla owner-side wallet"
- Helper text: „Wybrany provider będzie używany gdy owner kliknie 'Zapłać teraz' w `/owner/invoices`. Zmienia tylko ich UX — Twoje pieniądze idą tak samo."

### UI — Owner side
```php
Tables\Actions\Action::make('pay_now')
    ->label('Zapłać teraz')
    ->icon('heroicon-o-credit-card')
    ->color('success')
    ->visible(fn (Invoice $r) => $r->status === InvoiceStatus::Issued && ! $r->paid_at)
    ->action(function (Invoice $r) {
        $url = app(OwnerInvoicePaymentService::class)->createSessionFor($r);
        return redirect()->away($url);
    });
```

### Testy

- Per-driver: P24/PayU/Stripe driver generuje session URL z prawidłowymi creds tenant'a
- Service wybiera prawidłowego drivera na podstawie settings stable'a
- Fallback do P24 gdy stable nie wybrał provider'a
- Per-webhook: 3 webhook handlery recognize `owner_invoice_payment` z metadata → update Invoice + dispatch notification
- Test każdego providera w izolacji (Mock HTTP)

### Manual verification

1. Stable A: `/app/payment-settings` → wybiera „Stripe" → save
2. Owner of stable A: `/owner/invoices/X` → „Zapłać teraz" → Stripe Checkout (Apple Pay button)
3. Stable B: `/app/payment-settings` → wybiera „P24" → save
4. Owner of stable B: → P24 z BLIK selector
5. Stable C: nie wybrał → owner widzi P24 (fallback)
6. Test card success → wszystkie 3 webhooki → invoice.paid_at set + email do stable

### Effort: **3 dni**

### Zależności

**Wymaga**: #5, #6 (owner widzi invoice list — bez tego nie ma punktu wejścia)

---

## 10. PR O5 — Komunikator 4-kanałowy (stable↔owner / stable↔vet / stable↔per-user / horse-owner↔vet)

### Branch
`claude/multichannel-messenger`

### Cel
4 niezależne kanały komunikacyjne pod jedną abstrakcją threadingu:

| Kanał | Use case | Topology | Auth |
|---|---|---|---|
| **A** | stable ↔ owner | Per-koń + ogólny | Tenant user ↔ Owner user (oba central) |
| **B** | stable ↔ vet (external) | Per-wizyta + ogólny | Tenant user ↔ External specialist (magic link) |
| **C** | stable ↔ per user (internal team) | Slack-like channels (#general, #weterynaria, #transport) | Tenant user ↔ Tenant user |
| **D** | horse-owner ↔ vet | Cross-tenant (owner → vet z innej stajni) | Owner user ↔ External specialist |

### Pliki kluczowe

**Schema**:
- `database/migrations/central/...create_messages_table.php` (NEW)
- `database/migrations/central/...create_message_threads_table.php` (NEW)
- `database/migrations/central/...create_external_specialists_table.php` (NEW) — dla kanału B i D
- `database/migrations/central/...create_internal_channels_table.php` (NEW) — dla kanału C (Slack-like)

**Models**:
- `app/Models/Central/MessageThread.php` (NEW) — abstrakcja threada per channel
- `app/Models/Central/Message.php` (NEW)
- `app/Models/Central/ExternalSpecialist.php` (NEW) — vet z magic-link auth
- `app/Models/Central/InternalChannel.php` (NEW) — Slack-like channel
- `app/Models/Central/InternalChannelMember.php` (NEW)

**Filament resources** (różne dla każdej persony):
- `app/Filament/App/Resources/MessageThreadResource.php` (NEW) — stable widzi 4 kanały: A (z owner), B (z vet), C (internal channels)
- `app/Filament/Owner/Resources/MessageThreadResource.php` (NEW) — owner widzi: A (ze stable), D (z vet)
- `app/Filament/Specialist/Resources/MessageThreadResource.php` (NEW) — vet (zewn) widzi: B (ze stable), D (z owner'a)

**Specialist panel** (NEW):
- `app/Providers/Filament/SpecialistPanelProvider.php` (NEW) — `/specialist` panel z magic-link auth
- `app/Http/Controllers/Specialist/MagicLinkController.php` (NEW)
- `app/Models/Central/SpecialistMagicLink.php` (NEW)

**Notifications**:
- `app/Notifications/NewMessageNotification.php` (NEW) — generic dla wszystkich kanałów (per channel adapter)
- Notification routing: email + database; SMS gdy kanał A i message contains keyword „pilne"

### Migration — message_threads (central, abstrakcja)

```php
Schema::create('message_threads', function ($t) {
    $t->ulid('id')->primary();
    $t->string('channel', 16); // 'stable_owner' | 'stable_vet' | 'internal' | 'owner_vet'
    $t->ulid('stable_tenant_id')->nullable(); // dla A, B, C; null dla D
    $t->ulid('owner_user_id')->nullable(); // dla A, D
    $t->ulid('specialist_id')->nullable(); // dla B, D — reference do external_specialists
    $t->ulid('internal_channel_id')->nullable(); // dla C — reference do internal_channels
    $t->ulid('central_horse_id')->nullable(); // optional context — per-koń
    $t->string('subject', 200)->nullable();
    $t->timestamp('last_message_at')->nullable();
    $t->timestamps();
    $t->index(['channel', 'stable_tenant_id']);
    $t->index(['owner_user_id']);
    $t->index(['specialist_id']);
});
```

### Migration — messages

```php
Schema::create('messages', function ($t) {
    $t->ulid('id')->primary();
    $t->foreignUlid('thread_id')->constrained('message_threads')->cascadeOnDelete();
    $t->ulid('sender_user_id')->nullable(); // null = sender był specialist (sender_specialist_id)
    $t->ulid('sender_specialist_id')->nullable();
    $t->text('body');
    $t->json('attachments')->nullable(); // array of {path, original_name, mime, size}
    $t->timestamp('read_at_by_sender')->nullable(); // dla read receipts
    $t->json('read_at_by_recipients')->nullable(); // {user_id_or_specialist_id: timestamp, ...}
    $t->timestamps();
    $t->index(['thread_id', 'created_at']);
});
```

### Migration — external_specialists (kanały B, D)

```php
Schema::create('external_specialists', function ($t) {
    $t->ulid('id')->primary();
    $t->string('name', 120);
    $t->string('email', 200)->unique();
    $t->string('phone', 40)->nullable();
    $t->string('specialty', 32); // 'vet' | 'farrier' | 'dentist' | 'other'
    $t->ulid('invited_by_user_id')->nullable(); // kto zaprosił (stable owner lub horse owner)
    $t->timestamp('invited_at');
    $t->timestamp('first_login_at')->nullable();
    $t->timestamps();
});
```

### Migration — internal_channels (kanał C)

```php
Schema::create('internal_channels', function ($t) {
    $t->ulid('id')->primary();
    $t->ulid('stable_tenant_id');
    $t->string('name', 40); // 'general', 'weterynaria', 'transport'
    $t->string('topic', 200)->nullable();
    $t->boolean('is_default')->default(false); // #general jest default
    $t->timestamps();
    $t->unique(['stable_tenant_id', 'name']);
});

Schema::create('internal_channel_members', function ($t) {
    $t->ulid('channel_id');
    $t->ulid('user_id');
    $t->timestamp('joined_at');
    $t->timestamp('last_read_at')->nullable();
    $t->primary(['channel_id', 'user_id']);
});
```

### Routing thread'a → adresat

Per channel logic:

| Channel | Message arrived → notify |
|---|---|
| A (stable_owner) | stable team members z rolą `owner|admin|manager` + horse owner |
| B (stable_vet) | stable team z rolą `vet|admin|manager` + external specialist (email + magic link) |
| C (internal) | wszyscy członkowie channel (poza sender) |
| D (owner_vet) | horse owner + external specialist |

### Specialist panel `/specialist`

Magic-link auth (jak portal klienta):
1. Stable lub owner zaprasza vet'a przez email
2. Vet dostaje email z linkiem `/specialist/auth?token=...`
3. Klika → session set → ląduje na `/specialist/threads`
4. Widzi listę thread'ów ze stable'a (B) lub horse owner'ów (D)

### Testy

- Stable user wysyła msg do owner'a → thread A → owner widzi w panel + email
- Stable user wysyła do internal channel #weterynaria → wszyscy members notified
- External vet receives invite → klika → ląduje w specialist panel → widzi thread B
- Horse owner pisze do vet'a → thread D → vet widzi w specialist panel
- Read receipts: każda strona widzi „read 2 min temu"
- Attachment PDF upload → save do `storage/messages/{thread_id}/{file}` → download z signed URL
- Per kanał routing: msg do A NIE leci na maila vet'a (chyba że jest też w team stable'a)

### Manual verification (4 osobne flow):

**Flow A — stable ↔ owner**:
1. Stable user `/app/messages` → nowa wiadomość → wybiera owner'a + koń → wpisuje „Trójka źle wczoraj jadła" → wyślij
2. Owner: `/owner/messages` → unread badge → otwiera thread → odpisuje
3. Stable widzi odpowiedź + email notification

**Flow B — stable ↔ vet (external)**:
1. Stable user `/app/specialists` → zaproś nowego vet'a (imię + email)
2. Vet dostaje email z magic-linkiem
3. Klika → ląduje `/specialist/threads/X` → odpowiada na wiadomość

**Flow C — stable internal channel**:
1. Stable owner `/app/channels` → tworzy channel #transport
2. Dodaje do channel: 2 instruktorów + 1 employee
3. Wpisuje msg w #transport → wszyscy 3 dostają database notification + email digest (1× dziennie zamiast per-msg)

**Flow D — horse owner ↔ vet**:
1. Horse owner `/owner/specialists` → zaproś vet'a (jego znajomy weterynarz spoza stajni)
2. Vet dostaje email
3. Klika → `/specialist/threads/Y` (cross-stable, na poziomie central)
4. Owner pisze „Czy zalecasz dodatkowe badanie?"
5. Vet odpowiada

### Effort: **5-7 dni**

### Risk

- **HIGH** — 4 kanały w jednej abstrakcji są skomplikowane. Każdy ma osobne routing notifications.
- Specialist panel jest nowym panelem Filament — wymaga `SpecialistPanelProvider`, midddleware, panel-specific resources.
- Magic-link flow musi być solidnie zabezpieczony (anti-replay, expiry).

### Mitigation

- **Etap 1** (dzień 1-3): kanały A + C (stable↔owner i internal) — najprostsze, wszyscy są tenant users.
- **Etap 2** (dzień 4-5): specialist panel + magic-link auth.
- **Etap 3** (dzień 6-7): kanały B + D (z external specialist).
- Każdy etap merged osobno jako follow-up PR (możliwe że to nie 1 PR a 2-3).

### Zależności

**Wymaga**: O2/O3 (owner musi mieć karty koni żeby attachowac do msg „o tym koniu")
**Otwiera**: Phase 2 — SMS notifications dla critical messages, in-app notification sounds

---

## Razem — czas i zależności (UPDATED po decyzjach)

```
Tydzień 1 (foundations):
  Dzień 1:   PR D (batch health complete)      ← stable
  Dzień 2:   PR I1 (PDF 2 templaty)             ← otwiera S1 + I3
  Dzień 3:   PR I2 (calc extra-horse) + start PR O1 (owner notifications)
  Dzień 4-5: PR O2 część 1 (owner horse photos + docs)

Tydzień 2 (owner content):
  Dzień 1:   PR O2 część 2 — finalize + tests
  Dzień 2-3: PR O3 (owner horse health + weight + boarding history)
  Dzień 4:   PR S1 (bulk monthly invoice)

Tydzień 3 (payment + KSeF):
  Dzień 1-3: PR O4 (owner wallet 3 providery)
  Dzień 4-5: PR I3 (KSeF start — auth + FvBuilder + submit flow)

Tydzień 4 (KSeF finalize + messenger start):
  Dzień 1-2: PR I3 (KSeF — KOR/ZAL/PROF/UPR/RR builders + JPK_FA(3) exporter + UPO retrieval + tests)
  Dzień 3-5: PR O5 etap 1 (kanały A + C — stable↔owner + internal channels)

Tydzień 5 (messenger finalize):
  Dzień 1-2: PR O5 etap 2 (specialist panel + magic-link auth)
  Dzień 3-5: PR O5 etap 3 (kanały B + D — z external vet)
```

**Razem: 22-25 dni roboczych ≈ 5 tygodni kalendarzowych.**

### Checkpoint po każdym tygodniu
- **Tydzień 1**: PDF + batch health + calc + owner photos = wszystko TIER 1 quick wins gotowe
- **Tydzień 2**: Owner panel content fully usable
- **Tydzień 3**: Owner wallet działa + KSeF auth flow gotowy
- **Tydzień 4**: KSeF produkcyjny + start komunikatora
- **Tydzień 5**: Pełny komunikator z external specialists

---

## Risk analysis

| Risk | Mitigation |
|---|---|
| KSeF submit (PR #4) requires sandbox MF account | Mam dostępy? Jeśli nie — dodajemy do checklisty przed startem |
| Cross-tenant pattern w PR O2 nowy concept | Done well w pierwszym → reuse w O3, O4, O5 |
| Owner wallet — 3 providers (P24/PayU/Stripe) | Adapter pattern — zaczynamy od najpopularniejszego (P24), rest jako follow-up |
| Komunikator — attachment storage | Use `local` disk najpierw, S3 jako follow-up |
| Tests for Filament Livewire pages mogą być flaky | Trzymamy się service-level testów + Livewire smoke tests |

---

## Rytm

- **1 PR na 1-2 dni roboczych** — łatwo do review
- **Rozróżniam Tier 1 vs Tier 2** — Tier 1 (#1-7) ZANIM Tier 2 (#8-10)
- **Każdy PR ma własny branch** — łatwa selektywna merge'a
- **Po każdym tygodniu — checkpoint** z user'em: priorytety nadal aktualne?

---

## Pytania carry-over (potwierdzone 2026-06-21)

| # | Pytanie | Decyzja user'a |
|---|---|---|
| 1 | KSeF: sandbox czy produkcja? | **Produkcja od dnia 1.** Wszystkie typy dokumentów (FV/KOR/ZAL/PROF/UPR/RR + JPK_FA(3)). Wzorzec z Billu-System. |
| 2 | Owner wallet — który provider? | **Wszystkie 3** (P24/PayU/Stripe) z adapter pattern + admin per-tenant choice. |
| 3 | Komunikator topology? | **4 kanały** — stable↔owner / stable↔vet / stable↔per-user / horse-owner↔vet. Specialist panel z magic-link auth dla vet. |
| 4 | PDF design? | **2 templaty**: FV od tenant'a → branding tenant'a. FV od Hovery → logo Hovery + dane Sendormeco Holding sp. z o.o. |
| 5 | Bulk invoicing — queue? | **Tak** dla 50+ klientów (implicit — default behavior z queue worker). |

## Otwarte pytania techniczne (do potwierdzenia przed startem)

- **KSeF production cert**: gdzie obecnie żyje cert tenanta? Czy potrzebujemy upload UI dla każdego stable osobno, czy mamy jedną centralną instancję?
- **Sendormeco Holding sp. z o.o. dane**: jakie NIP / REGON / KRS / IBAN? (potrzebne do `config/hovera.php` env vars)
- **External specialist invite source**: czy stable może zapraszać dowolnego vet'a (open invite), czy mamy whitelist (np. tylko zweryfikowani vetowie z bazy)?
- **Internal channels (Slack-like)**: czy chcemy 3 default channels (#general, #weterynaria, #transport) auto-utworzone dla każdej stajni przy provision'ie, czy explicit creation?

---

> **Następny krok**: zaczynam od **PR D + PR I1 równolegle** (najlżejsze, otwierają pipeline dla S1+I3). Specjalna uwaga: muszę dostać dane Sendormeco Holding (NIP, REGON, adres, IBAN) zanim domknę PR I1 z poprawnym Hovera template'em.
