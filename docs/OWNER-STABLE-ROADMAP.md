# Owner ↔ Stable shared view — roadmap

> ⚠️ **NIEAKTUALNE (2026-07-05):** większość checklist poniżej (Faza 1-6, w tym C.4-C.7, notyfikacje,
> upload zdjęć/dokumentów, timeline, pełny widok konia) jest już zaimplementowana — zweryfikowane
> bezpośrednio w kodzie. Zobacz `docs/CURRENT-STATUS.md` po aktualny, zweryfikowany stan. Realnie
> otwarte zostały tylko: E.5 (flaga "wymaga podpisu"), flow zatwierdzania zmian wrażliwych pól, digest
> mailowy wiadomości. Nie planuj pracy na podstawie samych checklist poniżej bez weryfikacji w kodzie.

> Pełna implementacja: właściciel konia (`tenant.type=horse_owner`) widzi w panelu `/owner` wszystkie dane swojego konia, historię działań stajni, rozliczenia z pensjonatem oraz prowadzi komunikację z stajnią (wiadomości + wymiana plików/zdjęć).
>
> Kontynuacja po zamknięciu 🟢 Calculator live UX (sprint maj 2026). Ten plik trzyma plan wdrożenia — każda faza = osobny PR (lub kilka), każda fazą zamykana w osobnej sesji żeby context był czysty.
>
> Reszta produktu: `docs/ROADMAP.md`, `docs/MARKETPLACE-ROADMAP.md`.

---

## Stan obecny (research-grounded)

### Co już jest po stronie stajni (`tenant.type=stable`)

**Modele konia + powiązane (wszystkie istnieją):**
- `Horse` — `central_horse_id`, `name`, `microchip`, `passport_number`, `ueln`, `breed`, `sex`, `color`, `birth_date`, `owner_client_id`, `box_id`, `cover_image_path`, `notes`, `metadata`
- `HealthRecord` — wizyty wet/dentysta/farrier (`specialist_id` → Specialist)
- `BoxAssignment` — historia boksów (`assigned_at`/`vacated_at`, `assigned_by_user_id`)
- `BoardingService` + pivot `horse_boarding_services` (price_override, quantity, starts_at/ends_at)
- `StableActivity` — aktywności dzienne (feeding/cleaning/paddock)
- `HorseWeightMeasurement` — pomiary masy
- `HorseFeedingPlanItem` — plan żywienia (meal-based)
- `HorsePhoto` — galeria (`sort_order`, `uploaded_by_role`)
- `HorseDocument` — dokumenty (passport/contract; `kind` enum, `valid_until`)
- `HorseMessage` — directional `from_stable`/`from_client`, `attachments` JSON, `read_by_*_at` timestamps
- `Invoice` + `InvoiceItem` — faktury (KSeF integration ready, brak `horse_id` linka)

**Klucz cross-tenant:**
- `Client.central_user_id` (stable DB) → `User.id` (central DB) — **stable wie kim jest owner w central**
- `HorseBoardingAssignment` (central) — `central_horse_id`, `stable_tenant_id`, `owner_user_id`, `status` (pending/active/ended/disputed)
- `CentralHorseRegistry` (central) — source of truth dla `name`, `breed`, `dob`, `passport_no`, `primary_owner_user_id`
- `TenantManager::execute(Tenant, callable)` — runtime switch tenant connection + restore

### Czego brakuje (luki które ten roadmap zamyka)

❌ Owner panel `HorseResource` ma tylko 8 podstawowych pól — **zero dostępu** do health, box, weight, photos, dokumentów, wiadomości, faktur.
❌ Brak cross-tenant read API dla owner panel'u (jedyne istniejące endpoint'y to mobile sync V1 — scoped per stable).
❌ Brak link'u `horse_id` na fakturach (faktura per Client, nie per koń) — trudno filtrować "ile za mojego konia w czerwcu".
❌ Brak auto-billing — boarding miesięczny generowany ręcznie przez operator stajni.
❌ Brak owner-side upload'u plików (HorsePhoto/HorseDocument zakładają `uploaded_by_role` ale obecne UI wspiera tylko stable side).
❌ Brak threading/UI dla `HorseMessage` po stronie ownera (model istnieje, view nie).
❌ Brak approval flow dla zmian kluczowych pól (jeśli stajnia zmieni `name` lub `passport_number`, owner się o tym nie dowie).
❌ Brak realtime/push notifications dla zdarzeń typu "nowa wiadomość od stajni", "nowa faktura", "weterynarz dziś rano".

---

## Cel docelowy — checklist funkcji

### A. Dane konia (read)
- [ ] Owner widzi **wszystkie** pola konia: passport, microchip, UELN, breed, sex, color, dob, notes, cover_image
- [ ] Owner widzi **aktualny boks** (`BoxAssignment` active) + nazwę budynku
- [ ] Owner widzi **historię boksów** (timeline)
- [ ] Owner widzi **boarding services** aktywne dla konia (np. "Pensjonat full board — 1800 zł/mies, dodatek owies — 200 zł/mies")
- [ ] Owner widzi **pomiary masy** (timeline + ostatni)
- [ ] Owner widzi **plan żywienia** (jeśli stajnia prowadzi)

### B. Historia "działań na koniu" (timeline feed)
- [ ] Wpisy z `HealthRecord` (wizyty wet, leczenia, szczepienia, kowal, dentysta) — z notatkami, kosztem (jeśli rozliczone)
- [ ] Wpisy z `StableActivity` filtrowane do tych dotyczących konia (paddock, longe, kąpiel itd.)
- [ ] `BoxAssignment` zmiany (przeniesienie do innego boksu)
- [ ] `HorseWeightMeasurement` (nowy pomiar)
- [ ] `Photo` upload (nowe zdjęcie dodane przez stajnię)
- [ ] `HorseDocument` upload (nowy dokument)
- [ ] Filtrowanie per kind, per zakres dat
- [ ] Export do PDF / CSV (dla wet history)

### C. Rozliczenia (Owner ↔ Stable)
- [ ] Owner widzi listę faktur wystawionych mu przez stajnię (Invoice scoped przez Client.central_user_id = owner_user_id)
- [ ] PDF download faktury (signed URL, valid 24h)
- [ ] Status płatności (Draft/Issued/Paid)
- [ ] Per-koń breakdown — wymaga dodania `horse_id` na `InvoiceItem` (`nullable`) żeby filtrować
- [ ] Auto-billing — scheduled job generuje draft invoice 1. dnia miesiąca dla wszystkich `horse_boarding_services` z `frequency=monthly` per active boarding
- [ ] Pay button (P24/PayU jeśli stajnia ma skonfigurowane creds → reuse `TransporterP24QuoteService` pattern dla owner-facing payments)
- [ ] History rozliczeń (suma roczna, eksport CSV)

### D. Komunikacja
- [ ] Thread per koń (`HorseMessage` już istnieje z `direction`+`attachments`) — UI po obu stronach
- [ ] Owner może pisać do stajni (POST z owner panel'u przez cross-tenant API)
- [ ] Załączniki (zdjęcia, PDF, MP4) — upload po obu stronach, max 25MB/plik, max 10 plików/wiadomość
- [ ] Read receipts (`read_by_client_at` / `read_by_stable_at`)
- [ ] Unread counter na dashboardzie
- [ ] Database notification + email digest (daily) dla unread

### E. Pliki i zdjęcia
- [ ] `HorseDocument` — owner może uploadować (kontrakt boardingu, paszport, świadectwa szczepień)
- [ ] `HorsePhoto` — galeria zdjęć z `uploaded_by_role` (stable/owner) — owner widzi wszystko, sortowane po dacie
- [ ] Sygnowane URL-e dla downloads (24h TTL)
- [ ] Owner może dodawać zdjęcia z mobile (Sanctum API)
- [ ] Owner może oznaczać dokumenty jako "wymagają podpisu" → stajnia widzi action item

### F. Notifications & UX polish
- [ ] Database notifications dla owner'a (`Notifications` dispatch po stronie stajni, target = central user_id)
- [ ] Owner Dashboard widget: ostatnia aktywność (5 najnowszych eventów z timeline)
- [ ] Approval flow dla zmian kluczowych pól (passport/name/microchip) — stajnia proponuje → owner zatwierdza
- [ ] Mobile push (sanctum + Expo/FCM) — przyszłość, scope dla osobnej iteracji

---

## Architektura — kluczowe decyzje

### 1. Cross-tenant read: live query, nie snapshot

**Decyzja:** Owner panel czyta dane stajni **live** poprzez `TenantManager::execute($stableTenant, callable)`. Brak duplikacji do central / brak background sync.

**Powód:**
- Snapshot wymaga eventu/observer'ów na każdym z ~10 modeli stajni + invalidation logic = duża maintenance burden
- Live query: jeden gate (`HorseOwnerStableAccessGate`) + `execute()` switch — wszystko prostsze
- Performance: owner sesja jest "rzadka" (kilka req/min), nie hot path

**Konsekwencje:**
- Wszystkie odczyty z owner'a muszą iść przez serwis-snapshot który robi `TenantManager::execute` i zwraca DTO (nie Eloquent — eloquent connections się gubią po switch'u)
- `TenantManager::execute` ma overhead (config rewrite + PDO purge) — jeden switch per request, cachowanie wyników dozwolone
- Brak realtime (jeśli kiedyś będzie potrzebny — webhook stable→owner przy ważnych eventach)

### 2. Cross-tenant write: tylko via central API gateway

**Decyzja:** Operacje pisania (np. owner wysyła wiadomość, uploaduje plik) idą przez **central HTTP endpoint** `/api/owner/...` który:
1. Auth: Sanctum SPA session + role gate (owner musi być primary_owner_user_id)
2. Wczytuje `HorseBoardingAssignment.status=active`
3. Switch'uje na stable tenant (`TenantManager::execute`)
4. Wykonuje write w stable DB
5. Restore (try/finally)

**Powód:** Cleanest mental model — owner panel nie ma `tenant.id` stajni w session, używa `central_horse_id` jako adresy. Centralne API skleja.

### 3. Gate: `HorseOwnerStableAccessGate`

Nowy serwis `app/Domain/Horses/HorseOwnerStableAccessGate.php`:
```php
public function authorize(User $owner, string $centralHorseId): HorseBoardingAssignment;
// rzuca AuthorizationException jeśli:
//   - brak active assignment dla (owner_user_id, central_horse_id)
//   - assignment.status != 'active' (pending/ended/disputed = denied)
//   - owner nie jest primary_owner_user_id w CentralHorseRegistry
```

Wszystkie nowe API endpoint'y i Filament page'e wywołują ten gate **zanim** wejdą w cross-tenant context.

### 4. Snapshot DTOs zamiast Eloquent

`app/Domain/Horses/Snapshots/`:
- `HorseSnapshot` — wszystkie pola konia + active box + active services
- `HorseTimelineEntry` — unified DTO dla feed (kind, date, payload, source_model)
- `HorseInvoiceSummary` — invoice header bez full items (lazy load on demand)

Cross-tenant context czyta Eloquent → mappuje do DTO → zwraca. Po wyjściu z `execute()` Eloquent jest niedostępny ale DTO jest serializable.

### 5. Schema: dodać `horse_id` na `invoice_items`

Migration dodaje `nullable horse_id` (ULID, soft FK). Pole stażywne dla owner'a (filtrowanie "ile za Iskrę w 2026"). Backward-compat — istniejące invoices mają `horse_id=null`.

### 6. File storage cross-tenant

Pliki uploadowane przez ownera trafiają na **stable disk** (per-tenant storage prefix `tenants/{tenant_id}/horse-messages/...`). Owner panel pobiera przez signed URL z central — `Storage::disk('s3')->temporaryUrl()` z stable tenant context.

**Alternatywa (gdyby cross-tenant storage był skomplikowany):** central disk z prefiksem `boarding/{assignment_id}/...`. Mniej "natural" ale eliminuje switch przy każdym pobraniu pliku.

**Decyzja default:** stable disk — pliki "należą" do stajni (są jej audit trail). Owner ma read access via signed URL.

---

## Fazy implementacji

> Każda faza = osobny PR(-y) w osobnej sesji. Estymaty zakładają solo dev. Wszystkie konwencje projektu: PL komentarze w klasach, `vendor/bin/pint --dirty`, tests, snapshot historyczny, defensive parse JSON, PL+EN i18n, idempotent migrations.

---

### Faza 1 — Foundation: cross-tenant access gate + snapshot service (~4h)

**Cel:** Stworzyć bezpieczny mechanizm dostępu owner'a do stable DB. Bez tego nic dalej nie ruszy.

**PR-y:**

#### PR 1.1 — `HorseOwnerStableAccessGate` + `StableHorseSnapshotService`
- `app/Domain/Horses/HorseOwnerStableAccessGate.php` (gate)
- `app/Domain/Horses/Snapshots/HorseSnapshot.php` (DTO)
- `app/Domain/Horses/StableHorseSnapshotService.php` (z `TenantManager::execute` switch'em)
- Tests: gate authorize (active/pending/ended/missing), snapshot completeness, no leak between horses

#### PR 1.2 — Owner panel `HorseDetailPage` (Filament Page, nie Resource)
- `app/Filament/Owner/Pages/HorseDetail.php` — `/owner/horses/{centralHorseId}/details`
- Sekcje: identyfikacja, aktualny boks (jeśli active boarding), boarding services, ostatnie 5 eventów (placeholder na timeline z fazy 2)
- Tests: dostęp tylko dla primary owner, 403 dla obcych, dane się ładują

**Schema changes:** brak (Faza 1 czyta tylko istniejące tabele).

**Acceptance:** owner widzi `/owner/horses/{id}` z pełnymi danymi konia gdy ma active boarding; 403 inaczej.

---

### Faza 2 — Timeline "historia działań na koniu" (~5h)

**Cel:** Owner widzi chronologiczny feed wszystkich akcji wykonanych przez stajnię na jego koniu.

**PR-y:**

#### PR 2.1 — `HorseTimelineService` (cross-tenant aggregator)
- `app/Domain/Horses/Timeline/HorseTimelineService.php` — w stable context łączy:
  - `HealthRecord` (kind: 'health.*' z subkind = vet/dentist/farrier/vaccination)
  - `BoxAssignment` zmiany (kind: 'box.assigned'/'box.vacated')
  - `HorseWeightMeasurement` (kind: 'weight.measured')
  - `StableActivity` dotyczące konia (kind: 'activity.*' subkind = paddock/longe/grooming)
  - `HorsePhoto` upload (kind: 'photo.added')
  - `HorseDocument` upload (kind: 'document.added')
  - `BoardingService` start/end (kind: 'service.started'/'service.ended')
- DTO `HorseTimelineEntry` z polami `kind`, `subkind`, `occurred_at`, `actor_role` (stable/owner/system), `payload` (array specyficzny per kind)
- Sortowanie DESC po `occurred_at`, paginacja kursor-based (50/page)
- Filtrowanie: `?kind=health` lub `?kind=health,weight` + `?from=2026-01-01&to=2026-12-31`
- Tests: każdy kind composite'owany, sort stabilny, filtry działają

#### PR 2.2 — Owner panel `HorseTimeline` page + UI
- `app/Filament/Owner/Pages/HorseTimeline.php` — tab w HorseDetail
- Timeline UI: ikona per kind, expandable szczegóły, "load more" cursor pagination
- i18n: `lang/{pl,en}/owner/horse_timeline.php` z kluczami per kind/subkind
- Tests: rendering, filter form, pagination

#### PR 2.3 — Export do PDF (vet history)
- `app/Domain/Horses/Timeline/HorseVetHistoryPdf.php` — generator (reuse `quote-pdf.blade.php` pattern)
- Filtr: kind='health', subkind='vet'/'vaccination'
- Use case: owner musi pokazać historię szczepień nowemu wetowi
- Tests: PDF się generuje, zawiera wszystkie wpisy

**Schema changes:** brak.

**Acceptance:** owner widzi timeline z możliwością filtrowania per kind + export PDF historii wet.

---

### Faza 3 — Rozliczenia (faktury per koń + auto-billing) (~6h)

**Cel:** Owner widzi faktury wystawione mu przez stajnię z breakdownem per koń, może płacić online jeśli stajnia ma skonfigurowane creds.

**PR-y:**

#### PR 3.1 — Schema: `horse_id` na `invoice_items`
- Migration tenant: `add_horse_id_to_invoice_items.php` (idempotent — `dropIfExists` jeśli partial state)
- `InvoiceItem::$fillable` += `horse_id`, casts += `'horse_id' => 'string'`
- Backward compat: stare items mają `horse_id=null`, nowe (z auto-billing) zawsze wypełnione
- Update wszystkich ~14 test schema setup'ów (`create('invoice_items'` w grep)
- Tests: snapshot, query filtering per horse, nullability

#### PR 3.2 — Auto-billing: `GenerateMonthlyBoardingInvoicesJob`
- `app/Jobs/Horses/GenerateMonthlyBoardingInvoicesJob.php` — iteruje wszystkie active `HorseBoardingAssignment`, dla każdej generuje draft `Invoice` z items:
  - `box.monthly_rate_cents` (jeśli horse ma `box_id`)
  - Każdy aktywny `horse_boarding_services` z `frequency=monthly` × `quantity` × `price_override_cents ?? price_cents`
  - `horse_id` = central_horse_id snapshot
- Scheduler w `routes/console.php`: pierwszego każdego miesiąca o 02:00
- Idempotent — nie duplikuje invoice'a jeśli już istnieje draft dla (client, period_start, period_end)
- Tests: full flow z 1 koniem, 2 końmi, brak boxa, soft-delete'd horse, ended boarding (skip)

#### PR 3.3 — Cross-tenant API endpoints: `/api/owner/invoices/*`
- `app/Http/Controllers/Api/Owner/InvoicesController.php`:
  - `GET /api/owner/horses/{centralHorseId}/invoices` — list (paginated, scope per horse via `invoice_items.horse_id`)
  - `GET /api/owner/invoices/{invoiceId}` — show (auth: HorseBoardingAssignment active dla któregoś z items)
  - `GET /api/owner/invoices/{invoiceId}/pdf` — signed URL (24h, S3 temporary)
  - `POST /api/owner/invoices/{invoiceId}/pay` — inicjuje P24/PayU session (reuse `TransporterP24QuoteService` z pattern)
- Sanctum SPA mode + role gate
- Tests: list filtering, no leak between owners, PDF signed URL, pay flow

#### PR 3.4 — Owner panel `InvoiceList` + `InvoiceShow` pages
- `app/Filament/Owner/Pages/InvoiceList.php` — globalna lista wszystkich faktur ownera (wszystkie konie)
- `app/Filament/Owner/Pages/HorseInvoices.php` — tab w HorseDetail (per-horse)
- `app/Filament/Owner/Pages/InvoiceShow.php` — szczegóły + PDF download + "Zapłać" button
- i18n: `lang/{pl,en}/owner/invoice.php`
- Tests: rendering, filter, payment flow integration

**Schema changes:** `invoice_items.horse_id` (nullable ULID, soft FK do central registry).

**Acceptance:** owner widzi listę swoich faktur per koń + globalnie, może pobrać PDF i zapłacić online; auto-billing generuje draft invoice 1. dnia miesiąca.

---

### Faza 4 — Komunikacja (threading + read receipts) (~4h)

**Cel:** Owner i stajnia wymieniają wiadomości per koń z attachments i read receipts.

**PR-y:**

#### PR 4.1 — Cross-tenant API: `/api/owner/horses/{id}/messages/*`
- `app/Http/Controllers/Api/Owner/HorseMessagesController.php`:
  - `GET /api/owner/horses/{centralHorseId}/messages` — list threaded (paginate, 30/page)
  - `POST /api/owner/horses/{centralHorseId}/messages` — wysyła wiadomość: `subject`, `body`, `attachments[]` (multipart upload)
  - `POST /api/owner/horses/{centralHorseId}/messages/{messageId}/read` — markuje `read_by_client_at = now()`
- Cross-tenant write: gate → `TenantManager::execute` → `HorseMessage::create` z `direction='from_client'`
- Tests: send/list/read flow, attachments upload, cross-tenant write integrity

#### PR 4.2 — File storage: signed URLs dla attachments
- `app/Domain/Files/HorseMessageAttachmentStorage.php` — `store`/`signedUrl`/`delete`
- Storage prefix: `tenants/{stable_tenant_id}/horse-messages/{message_id}/{original_name}`
- Constraints: max 25MB/plik, max 10 plików/wiadomość, allowed types: image/jpeg, image/png, image/webp, application/pdf, video/mp4 (kąpiel/longe), video/quicktime
- `attachments` JSON na `HorseMessage` zawiera `[{ filename, mime, size, path, signed_url_expires_at }]`
- Tests: upload size/type validation, signed URL expiry, delete cascade

#### PR 4.3 — Owner panel `HorseMessages` page + UI
- `app/Filament/Owner/Pages/HorseMessages.php` — tab w HorseDetail
- Thread-style UI (chat bubbles), file picker, drag-drop upload
- Unread badge na nav linku
- i18n: `lang/{pl,en}/owner/messages.php`
- Tests: send via Livewire action, file upload via API endpoint, read receipt

#### PR 4.4 — Stable side: Filament UI dla wiadomości od ownerów
- Update `HorseResource` (stable panel) — dodać tab Messages z UI threadingu
- Notification dla operatora stajni gdy owner wyśle wiadomość
- Tests: stable widzi wiadomość, może odpowiedzieć

**Schema changes:** brak (HorseMessage już ma wszystkie potrzebne pola, attachments to JSON).

**Acceptance:** owner i stajnia prowadzą full threaded conversation per koń z załącznikami; obie strony widzą read receipts.

---

### Faza 5 — Pliki i zdjęcia (dokumenty + galeria) (~4h)

**Cel:** Owner ma dostęp do galerii zdjęć konia + dokumentów + może dodawać własne.

**PR-y:**

#### PR 5.1 — Cross-tenant API: `/api/owner/horses/{id}/photos/*`
- `app/Http/Controllers/Api/Owner/HorsePhotosController.php`:
  - `GET /api/owner/horses/{centralHorseId}/photos` — list (paginated, sortowane po `created_at` DESC)
  - `POST /api/owner/horses/{centralHorseId}/photos` — upload (multipart, `uploaded_by_role='owner'`)
  - `DELETE /api/owner/horses/{centralHorseId}/photos/{id}` — soft delete (tylko jeśli uploaded_by owner)
- Image processing: resize do 2 wariantów (thumb 300px, full 1920px max)
- Storage: `tenants/{stable_tenant_id}/horse-photos/{horse_id}/...`
- Tests: upload, list, delete, role enforcement

#### PR 5.2 — Cross-tenant API: `/api/owner/horses/{id}/documents/*`
- `app/Http/Controllers/Api/Owner/HorseDocumentsController.php`:
  - `GET /api/owner/horses/{centralHorseId}/documents` — list
  - `POST /api/owner/horses/{centralHorseId}/documents` — upload (`kind`, `valid_until`, `requires_signature` flag)
  - `GET /api/owner/horses/{centralHorseId}/documents/{id}/download` — signed URL
  - `DELETE /api/owner/horses/{centralHorseId}/documents/{id}` — soft delete (owner uploaded only)
- Tests: kinds enforcement, signed URL TTL, role enforcement

#### PR 5.3 — Owner panel `HorseGallery` + `HorseDocuments` pages
- `app/Filament/Owner/Pages/HorseGallery.php` — masonry grid, lightbox, upload action
- `app/Filament/Owner/Pages/HorseDocuments.php` — table view + upload modal
- i18n: `lang/{pl,en}/owner/gallery.php`, `documents.php`
- Tests: rendering, upload, role-based visibility

#### PR 5.4 — Stable side: pokazuj kto upload'ował (role badge)
- Update `HorseResource` (stable) photos/documents tabs — dodać kolumnę `uploaded_by_role` z badge "Stajnia"/"Właściciel"
- Filter: pokaż tylko owner uploads / tylko stable uploads
- Tests: stable widzi owner uploads z badge

**Schema changes:** może `requires_signature` na `horse_documents` (boolean default false) — sprawdzić czy już jest, dodać jeśli nie.

**Acceptance:** owner widzi pełną galerię zdjęć (stable + own) i dokumenty (kontrakty/paszport/szczepienia), może uploadować i kasować własne.

---

### Faza 6 — Notifications + UX polish (~3h)

**Cel:** Owner dostaje powiadomienia o ważnych zdarzeniach (nowa wiadomość, faktura, ostatnia wizyta wet).

**PR-y:**

#### PR 6.1 — Database notifications hub
- Subclasses: `NewMessageForOwner`, `NewInvoiceForOwner`, `VetVisitRecordedForOwner`, `BoxChangedForOwner`
- Dispatch z stable tenant context (gdy stable wykona akcję) target = central `User.id` z `Client.central_user_id`
- Owner Dashboard widget: ostatnie 5 unread + link do akcji
- Mailable + daily digest dla unread (cron 09:00)
- Tests: dispatch flow, digest grouping

#### PR 6.2 — Owner Dashboard widget "Last activity"
- `app/Filament/Owner/Widgets/LastHorseActivityWidget.php` — ostatnie 5 eventów z timeline (across all owner's horses)
- Klikalne → przekierowuje do HorseDetail z fokusem na konkretny event
- Tests: rendering, multi-horse aggregation

#### PR 6.3 — Approval flow dla zmian kluczowych pól
- `app/Models/Central/HorseFieldChangeRequest.php` — central model (`central_horse_id`, `field`, `old_value`, `new_value`, `proposed_by_tenant_id`, `status`)
- Trigger: stable obserwator (`StableHorseObserver`) wykrywa zmianę `name`/`passport_number`/`microchip` → tworzy pending request zamiast direct apply
- Owner panel: `HorseChangeRequestsPage` — list, accept/reject UI
- Tests: change detection, owner accept → propagate update, owner reject → revert

**Schema changes:** nowa central tabela `horse_field_change_requests`.

**Acceptance:** owner dostaje database notif + daily email digest; ma kontrolę nad zmianami kluczowych pól.

---

### Faza 7 — Mobile API + push (przyszłość, scope dla osobnego sprintu)

> **Out of scope** dla tego roadmap'u — wzmianka żeby przy projektowaniu kontroler'ów (faza 1-5) zostawić room na mobile. Sanctum bearer tokens już działają (`/api/v1/auth/*`), wystarczy dodać owner-scoped endpoint'y i FCM/APNs integration.

- `POST /api/v1/auth/login` — owner też może logować (już działa)
- Endpointy z Faz 1-5 reuse'ować pod prefix `/api/v1/owner/*` (auth: bearer + tenant header lub central_horse_id)
- Push: Firebase Cloud Messaging / Apple Push (server-side abstraction, reuse `App\Models\Central\Device` model)

---

## Migracje — summary

| Faza | Migration | Side | Idempotent? |
|---|---|---|---|
| 3.1 | `add_horse_id_to_invoice_items.php` | tenant | tak — `dropIfExists` + `hasColumn` check |
| 5 | `add_requires_signature_to_horse_documents.php` (jeśli nie ma) | tenant | tak |
| 6.3 | `create_horse_field_change_requests_table.php` | central | tak |

Wszystkie migracje:
- Z `dropIfExists` PRZED `create` (MySQL może mieć partial state)
- Explicit constraint names dla wielokolumnowych unique (limit 64 znaków)
- Update wszystkich ~14 test schema setup'ów (grep `create('quotes'` / `create('invoice_items'`)

---

## Konwencje (przestrzegaj)

### Code style
- `vendor/bin/pint --dirty` przed commit
- Komentarze po polsku w klasach (zgodnie z istniejącym stylem)
- Defensive parse na user input (zwłaszcza JSON — `Quote::normaliseLineItems` pattern)
- Snapshot wszystkich danych historycznie ważnych (np. `unit_price_cents` na `invoice_items` zamrażany w momencie wystawienia)

### Cross-tenant patterns
- Wszystkie write'y idą przez central API (`/api/owner/*`) + gate → `TenantManager::execute`
- Read'y mogą być inline w Filament Page przez `StableHorseSnapshotService` (też używa `execute`)
- Po `execute()` używamy DTO, nigdy Eloquent (connection się rozłącza)
- Auth wszędzie: `auth:sanctum` (SPA mode) + `HorseOwnerStableAccessGate::authorize()`

### Tests
- SQLite in-memory dla tenant DB (tempnam + sqlite)
- `actingAs($owner)` + ustaw tenant przez reflection (wzór z `CalculatorSaveAsQuoteInlineTest`)
- Mock `MapboxGeocoder`, `HttpFactory` dla external services
- Pre-existing baseline: **1280+ tests, 7 errors + 1 failure** (niezmienione przez ten roadmap)

### i18n
- PL/EN dla wszystkich nowych keys
- Pliki w `lang/{pl,en}/owner/` (nowy folder dla ownera) + `lang/{pl,en}/api/` (komunikaty błędów API)
- Enum labels w `lang/{pl,en}/enums.php` (np. `HorseTimelineKind`)

### Filament patterns
- Owner panel resources używają `RestrictedByTenantRole` trait (już istnieje)
- Form auto-routing flow analogiczny do `mutateFormDataBeforeCreate` w Transport Pages\\Create
- Cross-tenant — page'e używają snapshot service'ów, nie bezpośrednio modeli stajni

### Git
- Branch naming: `claude/owner-stable-<feature-slug>`
- Commit message po polsku, full opis (cel + zmiany + tests + faza statusu)
- Draft PR z opisem skondensowanym z commit message + test plan checklist

---

## Open questions (do decyzji PRZED rozpoczęciem)

### Q1 — Auto-billing: kto akceptuje?
Stable generuje draft invoice 1. dnia miesiąca. **Czy:**
- (a) Auto-issue (status='issued') — owner od razu dostaje fakturę, P24/PayU pay button aktywny
- (b) Draft → operator stajni manual issue button (current pattern dla `InvoiceResource`)

**Rekomendacja:** (b) — operator stajni ma kontrolę nad timing'em, może dostosować przed wysyłką (np. dodać one-time charge za leczenie). Owner widzi tylko issued+ invoices.

### Q2 — Storage cross-tenant: per-tenant disk czy central?
- (a) Per-tenant disk (`tenants/{tenant_id}/...`) — naturalne (pliki należą do stajni), wymaga `TenantManager::execute` przy każdym signed URL
- (b) Central disk (`boarding/{assignment_id}/...`) — eliminuje switch, ale pliki "znikają" jeśli boarding ended

**Rekomendacja:** (a) — pliki są częścią audit trail stajni, jeśli ended boarding to owner traci dostęp ale stajnia zachowuje historię.

### Q3 — Czy ended boarding = całkowita utrata dostępu?
Gdy `HorseBoardingAssignment.status='ended'`, czy owner dalej widzi historyczne dane (timeline, faktury z okresu boardingu)?

**Rekomendacja:** Tak — owner zachowuje **read-only** dostęp do timeline + faktur **z okresu boardingu** (date range = `started_at..ended_at`). Tylko writes (nowe wiadomości, upload plików) są zablokowane. Argument: owner musi mieć dostęp do historii rozliczeń na potrzeby księgowości.

### Q4 — Approval flow scope (Faza 6.3)
Które pola wymagają approval ownera przed zmianą po stronie stajni?
- (a) Tylko `name`, `passport_number`, `microchip` (identyfikacyjne)
- (b) Powyższe + `breed`, `sex`, `color`, `birth_date` (charakterystyka)
- (c) Wszystko poza `notes`, `metadata`

**Rekomendacja:** (a) — minimalne tarcie, tylko pola które realnie definiują "tożsamość" konia (paszport może być nadpisany przy nowej rejestracji, name często zmieniany dla sport horses).

### Q5 — Mobile parity (Faza 7)
Czy mobile app dla ownera ma być zbudowany razem z webem (wspólny REST API) czy w osobnym sprincie po MVP webowym?

**Rekomendacja:** Osobny sprint. MVP web (Fazy 1-6) → user testy → mobile w iteracji 2. Endpointy z Faz 1-5 trzymane w `/api/owner/*` od początku tak, żeby mobile mógł reuse bez zmian.

---

## Estymata całkowita

| Faza | Effort | Cumulative |
|---|---|---|
| 1 — Foundation | ~4h | 4h |
| 2 — Timeline | ~5h | 9h |
| 3 — Rozliczenia | ~6h | 15h |
| 4 — Komunikacja | ~4h | 19h |
| 5 — Pliki/zdjęcia | ~4h | 23h |
| 6 — Notifications + polish | ~3h | 26h |
| **MVP web razem** | | **~26h** |
| 7 — Mobile (osobny sprint) | ~8-12h | 34-38h |

**Realistycznie** — 5-6 sesji po ~4-5h każda zamknie MVP webowy.

---

## Kolejność rekomendowana

1. **Faza 1** musi być pierwsza — bez gate'a/snapshot service'u reszta nie zadziała
2. **Faza 2 lub 3** — albo timeline (user-value: "widzę co robi się z moim koniem") albo rozliczenia (business-value: "kontrola nad płatnościami"). Rekomendacja: **3 (rozliczenia) wcześniej** — bardziej krytyczne business-wise, owner będzie używał codziennie
3. **Faza 4** — po fazie 3, bo wiadomości naturalnie odnoszą się do faktur ("dlaczego było +200zł?")
4. **Faza 5** — po fazie 4 (Reuse storage layer z attachments)
5. **Faza 6** — last (dopiero gdy są zdarzenia o których powiadamiać)
6. **Faza 7** — osobny sprint po MVP

---

## Powiązane dokumenty

- `docs/MARKETPLACE-ROADMAP.md` — equine marketplace + transport (status: 🟢 Calculator live UX merged, sprint zamknięty)
- `docs/ROADMAP.md` — globalna roadmapa produktu
- `docs/SESSION-HANDOFF.md` — konwencje code style + git workflow
- `docs/API.md` — istniejące mobile API endpoint'y (Sanctum bearer tokens)

---

*Stan: maj 2026. Plan opracowany po deep-dive research'u istniejącej architektury stajni/owner'a. Każda faza gotowa do otwarcia jako osobna sesja Claude Code.*
