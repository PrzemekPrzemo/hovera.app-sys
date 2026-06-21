# Hovera — Plan wdrożenia Phase 1 (top 10 priorytetów z WOW-PLAN-V2)

> Powiązany z: `docs/WOW-PLAN-V2.md` (analiza per-tenant).
> Data: 2026-06-21.
> Założenie: ~2-3 tygodnie pracy, sekwencja po jednym PR na PR-y backend-heavy, równolegle docs/UI.

---

## Rekomendowana kolejność i rozumowanie

Sekwencja oparta o **3 kryteria**:
1. **Blokery doświadczenia** — bez Tier 1 cały system wygląda na half-baked
2. **Zależności techniczne** — niektóre PR-y otwierają możliwości innych (np. PDF jest wymagany przez bulk-invoicing)
3. **Time-to-value** — szybkie wygrane między ciężkimi pracami (rytm produktywności)

### Sekwencja:

| # | PR | Effort | Persona | Tier | Dlaczego TERAZ |
|---|---|---|---|---|---|
| 1 | **PR D — Batch health complete** | 1 dzień | stable | 1.1 | Dokończenie Phase 1, najtaniej, najszybsze użytkowe |
| 2 | **PR I1 — PDF faktury (DomPDF)** | 4h | stable | 1 | Blokuje #4, #8; klienci czekają |
| 3 | **PR I2 — Calculator extra-horse-fee** | 2h | transporter | 1 | Quick win, zero ryzyka, frustrating gap |
| 4 | **PR I3 — KSeF submit (PR 4b)** | 6h | stable | 1 | Mandatoryjne PL 2026 — deadline |
| 5 | **PR O1 — Owner notifications hub** | 4h | owner | 2 | Najszybszy wow dla owner'a |
| 6 | **PR O2 — Owner HorseResource część 1** (photos + docs tabs) | 1-2 dni | owner | 1 | Krytyczny gap — owner panel content |
| 7 | **PR O3 — Owner HorseResource część 2** (health timeline + waga + boarding history) | 2 dni | owner | 1 | Domknięcie owner experience |
| 8 | **PR S1 — Bulk-monthly boarding invoice** | 1 dzień | stable | 2 | Wymaga PDF (z #2) |
| 9 | **PR O4 — Owner wallet (1-click pay)** | 2 dni | owner | 2 | Generational expectation, retencja |
| 10 | **PR O5 — Owner ↔ stable komunikator** | 2-3 dni | owner | 2 | Przewaga konkurencyjna |

**Phase 1 całość: ~14-17 dni roboczych = 3 tygodnie.**

Po PR #5 (owner notifications) owner widzi *coś* w panelu, więc nawet jeśli reszta opóźni się tydzień, doświadczenie nie jest puste. Dalsze PR-y owner'a (#6, #7) wymagają cross-tenant read pattern który warto raz zaprojektować.

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

## 2. PR I1 — PDF faktury (DomPDF)

### Branch
`claude/pdf-invoices-dompdf`

### Cel
Klient otwiera fakturę przez signed URL → widzi PDF zamiast HTML view. Plus button „Pobierz PDF" na fakturze w panelu stable.

### Pliki kluczowe
- `composer require barryvdh/laravel-dompdf` (już wzorzec dla quote PDF prawdopodobnie)
- `app/Services/Invoicing/InvoicePdfGenerator.php` (NEW)
- `resources/views/pdf/invoice.blade.php` (NEW) — A4, kremowo-ochra branding
- `app/Http/Controllers/Owner/InvoicePdfDownloadController.php` (NEW)
- `app/Filament/App/Resources/InvoiceResource.php` — dorzucamy row action `download_pdf`

### Migration
Brak. Plik PDF generowany on-the-fly, NIE persistujemy (signed URL streamuje).

### Service
```php
class InvoicePdfGenerator
{
    public function generate(Invoice $invoice): string {
        return PDF::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'tenant' => $invoice->tenant,
            'lines' => $invoice->lines,
            'totals' => $invoice->totalsArray(),
        ])->output();
    }
}
```

### Routes
- `GET /i/{slug}/{invoice}/pdf?signature=...` — public download dla klienta końcowego (już istniejący signed URL pattern)
- `GET /app/invoices/{invoice}/pdf` — auth tenant download

### View (skeleton)
`resources/views/pdf/invoice.blade.php`:
- Header: tenant logo (z `branding.logo_url`) + dane sprzedawcy
- Tabela: nr FV, data, kupujący (z `buyer_*` snapshot)
- Linie: opis, ilość, jm, cena netto, vat, brutto
- Footer: bank account, numeracja
- Style inline (DomPDF nie obsługuje wszystkich CSS — żadnego flex/grid)

### Testy
- Generated PDF zawiera tekst „FV/2026/06/01"
- Zawiera numeral cena + summary z `totalsArray`
- Header zawiera nazwę tenant'a

### Manual verification
1. Otwórz fakturę w stable panel → „Pobierz PDF" → otwiera się PDF z brandingiem
2. Wyślij maila do klienta z signed URL → klient otwiera → widzi PDF

### Effort: **4h**

### Zależności
**Otwiera**: #4 (KSeF wymaga PDF jako attachment), #8 (bulk monthly invoice — generator)

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

## 4. PR I3 — KSeF submit (PR 4b)

### Branch
`claude/ksef-submit-4b`

### Cel
Dziś mamy XML build + XAdES-BES signing. Brakuje samego submit'u do gov.pl. Plus retrieval UPO (potwierdzenie odbioru).

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

## 9. PR O4 — Owner wallet (1-click pay)

### Branch
`claude/owner-wallet-quick-pay`

### Cel
Owner klika fakturę w panel → przycisk „Zapłać teraz" → P24/PayU hosted checkout otwiera się w nowym tabie. Po success webhook → status invoice na paid + email do stable.

### Pliki kluczowe
- `app/Filament/Owner/Resources/InvoiceResource.php` — row action `pay_now`
- `app/Services/Owner/InvoicePaymentService.php` (NEW) — generuje P24/PayU session na podstawie payment_provider w settings stable'a
- Webhook handlers już istnieją dla central FV — extend dla owner side

### Migration
Brak (extend Invoice z `owner_payment_url`, `owner_payment_session_id` jeśli potrzeba — choć można robić on-the-fly).

### UI
```php
Tables\Actions\Action::make('pay_now')
    ->label('Zapłać teraz')
    ->icon('heroicon-o-credit-card')
    ->color('success')
    ->visible(fn (Invoice $r) => $r->status === InvoiceStatus::Issued && ! $r->paid_at)
    ->action(function (Invoice $r) {
        $url = app(InvoicePaymentService::class)->createSessionFor($r);
        // Filament redirect modal
        return redirect()->away($url);
    });
```

### Service logic
```php
public function createSessionFor(Invoice $invoice): string {
    $stable = $invoice->tenant;
    $provider = $this->resolvePaymentProvider($stable); // P24 | PayU | Stripe

    return match ($provider) {
        'p24' => app(StableP24InvoiceService::class)->createSession($invoice),
        'payu' => app(StablePayUInvoiceService::class)->createSession($invoice),
        'stripe' => app(StableStripeInvoiceService::class)->createSession($invoice),
    };
}
```

### Testy
- Owner z fakturą Issued → akcja pay_now visible
- Owner z fakturą Paid → akcja hidden
- Service generuje session URL z prawidłowymi providerami
- Post-webhook simulation → invoice.status = Paid + owner notification

### Manual verification
1. Stable wystawia FV 500 PLN dla owner'a
2. Owner: `/owner/invoices/X` → „Zapłać teraz" → P24 hosted checkout
3. Test card 4444... → success → redirect z powrotem → FV widoczna jako paid
4. Stable widzi w panelu invoice paid + email notification

### Effort: **2 dni**

### Zależności
**Wymaga**: #6, #7 (owner widzi invoice list — bez tego nie ma punktu wejścia w UX)

---

## 10. PR O5 — Owner ↔ stable komunikator

### Branch
`claude/owner-stable-messenger`

### Cel
Owner pisze do stable: „Czy mój koń jadł dziś dobrze?". Stable widzi w panel inbox → odpowiada → owner widzi w `/owner/messages`. Thread per koń + ogólny per stable.

### Pliki kluczowe
- Migration central: `messages` table (sender_user_id, receiver_user_id, tenant_id, horse_id null, body, attachment_path null, created_at, read_at)
- `app/Models/Central/Message.php` (NEW)
- `app/Filament/Owner/Resources/MessageThreadResource.php` (NEW)
- `app/Filament/App/Resources/HorseMessageResource.php` — extend (lub nowy stable resource)
- `app/Notifications/NewMessageFromOwnerNotification.php` (NEW)
- `app/Notifications/NewMessageFromStableNotification.php` (NEW)

### Migration (central)
```php
Schema::create('messages', function ($t) {
    $t->ulid('id')->primary();
    $t->ulid('sender_user_id');
    $t->ulid('receiver_user_id')->nullable(); // null = "to stable team"
    $t->ulid('stable_tenant_id');
    $t->ulid('central_horse_id')->nullable(); // null = ogólny thread
    $t->text('body');
    $t->string('attachment_path', 255)->nullable();
    $t->timestamp('read_at')->nullable();
    $t->timestamps();
    $t->index(['stable_tenant_id', 'central_horse_id']);
    $t->index(['receiver_user_id', 'read_at']);
});
```

### UI Owner panel
- Lista thread'ów (per koń + ogólny) z preview ostatniej wiadomości
- Klik → szczegóły z wszystkimi msg → form „Wyślij"
- Notyfikacja przez database + mail gdy nowa odpowiedź

### UI Stable panel
- Resource `HorseMessageResource` (lub `OwnerMessageResource`) per koń lub ogólny
- Inbox z badge unread count
- Reply form

### Testy
- Owner wysyła msg → zapisana w central + email dla owner team stable'a
- Stable user odpisuje → owner notification + email
- Thread per koń separated od ogólnego
- Attachment upload (PDF/JPG) → save do storage

### Manual verification
1. Owner: `/owner/messages` → „Nowa wiadomość" → wybierz koń (lub „ogólny") → wpisz „Jak Trójka się ma?" → wyślij
2. Stable: `/app/horses/X/messages` → unread badge → otwiera msg → odpisuje „Świetnie, dziś jadł dobrze"
3. Owner widzi odpowiedź + email z linkiem do thread

### Effort: **2-3 dni**

### Zależności
Brak (samodzielne ale wymaga że PR O2/O3 już są — owner musi MIEĆ co czytać)

---

## Razem — czas i zależności

```
Tydzień 1:
  Dzień 1:   PR D (batch health)         ← stable
  Dzień 2:   PR I1 (PDF) + PR I2 (calculator)
  Dzień 3-4: PR I3 (KSeF submit)
  Dzień 5:   PR O1 (owner notifications)

Tydzień 2:
  Dzień 1-2: PR O2 (owner horse photos+docs)
  Dzień 3-4: PR O3 (owner horse health+weight+boarding)
  Dzień 5:   PR S1 (bulk monthly invoice)

Tydzień 3:
  Dzień 1-2: PR O4 (owner wallet)
  Dzień 3-5: PR O5 (komunikator)
```

**Razem: 15 dni roboczych = 3 tygodnie.**

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

## Pytania do user'a przed startem

1. **KSeF**: czy mam dostęp do sandbox MF (`ksef-test.mf.gov.pl`)? Jeśli nie — dodajemy do prep listy.
2. **Owner wallet**: który provider preferujesz jako pierwszy? P24 (PL boomer), PayU (PL young — BLIK), Stripe (zagranica + Apple Pay)?
3. **Komunikator**: 1-on-1 per koń, czy multi-user threading (stable team członków też widzi)?
4. **PDF design**: użyć kremowo-ochra brandingu jak `/s/{slug}`, czy bardziej formal/B2B (białe + czarne)?
5. **Bulk invoicing**: czy może być przekierowany do queue (długo trwa dla 50 klientów)?

---

> **Następny krok**: po Twojej akceptacji listy + odpowiedziach na pytania, zaczynam od PR D (najlżejszy, najszybciej widoczny rezultat) + parallel PR I1 (PDF — zależność dla #4 i #8).
