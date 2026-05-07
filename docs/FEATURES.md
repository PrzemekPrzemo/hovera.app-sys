# Hovera — funkcjonalności dla właścicieli stajni

> Stan: maj 2026 · 333 testy ✅ · 35 zmergowanych PR-ów
>
> Ten dokument opisuje **co Hovera już dostarcza** (gotowe do wdrożenia) z punktu widzenia właściciela / managera stajni. Służy do (a) szybkiego onboardingu nowego klienta, (b) planowania kolejnych wdrożeń, (c) rozmowy sprzedażowej.

---

## TL;DR — co Hovera robi

System **multi-tenant SaaS** (jeden URL `app.hovera.app`, każda stajnia w osobnej bazie danych — zero leak'u danych między stajniami) przygotowany dla polskich + EU stajni. Pokrywa:

- **Operacje** — kalendarz, rezerwacje, konie, klienci, karnety, opieka weterynaryjna
- **Public booking** — klient z internetu rezerwuje przez `/s/{slug}/book` bez konta
- **Portal klienta per stajnia** — magic-link login, własne rezerwacje, karnety, konie boardera
- **Automatyzacja maili** — potwierdzenia, przypomnienia 24h, odwołania, przesunięcia
- **Płatności online** — Stripe + Mollie + Przelewy24 + PayU, klient wybiera w portalu lub klika z maila
- **Faktury** — FV / Proforma / Korekta z konfigurowalną numeracją per stajnia, auto-FV za karnety, FV na osoby fizyczne (bez NIP)
- **KSeF** — pełne uwierzytelnianie certyfikatem (PFX lub PEM), podpis XAdES-BES, generator FA(3) XML
- **GUS / KRS** — auto-wypełnianie danych firmy po NIP
- **Master admin** — dashboard z MRR/churn/health-score, zarządzanie wszystkimi stajniami

---

## 1. Operacje stajni (panel `/app`)

### 1.1 Konie
- **CRUD** + soft delete + przywracanie z kosza
- Pola: imię, mikrochip, paszport, UELN, rasa, płeć, maść, data urodzenia, zdjęcie okładkowe, notatki, metadata JSON
- **Powiązanie z właścicielem** (`owner_client_id` → Client) — używane przez portal pensjonariusza
- **Relacja do health records** (HasMany) — pełna historia weterynaryjna konia
- **Filtry**: rasa, płeć, status soft-delete

### 1.2 Klienci
- **CRUD** dla 3 typów: indywidualny, rodzina, firma
- Adres (ulica + kod + miasto + kraj), telefon, e-mail
- **NIP / VAT ID** + **przycisk "Pobierz z GUS"** (auto-fill nazwy + adresu po NIP-ie, gdy master-admin skonfigurował klucz GUS)
- **RODO**: data zgody + źródło zgody (web form / kontrakt / inne)
- Soft delete + historia
- **Relacje**: ownruje konie, dostaje karnety, ma rezerwacje, faktury, płatności, wiadomości

### 1.3 Karnety
- **CRUD**: nazwa, łączna liczba użyć, ważny od / do, cena (gr), status (active / exhausted / expired / cancelled)
- **Per-pass override** polityki anulacji w godzinach
- **Auto-konsumpcja przy potwierdzonej rezerwacji** (FIFO po `valid_until`)
- **Auto-restore przy odwołaniu** z polityką wielostopniową:
  1. `pass.cancellation_policy_hours` (per pass)
  2. `tenant.settings.cancellation_policy.hours` (per stajnia)
  3. 12h fallback (system default)
- Audit log każdej operacji `pass.consumed` / `pass.restored` / `pass.cancellation_late`
- **Auto-FV** gdy stajnia tworzy karnet z ceną (PR 3) — system od razu generuje fakturę, nadaje numer, zmienia status na Issued

### 1.4 Opieka i zdrowie konia
- **CRUD**: typ (szczepienie / odrobaczenie / kowal / dentysta / kontrola / weterynarz / lek / inne), data zabiegu, wykonujący, krótki opis, szczegóły, **`next_due_at`** (kiedy ponownie), koszt
- **Domyślne odstępy** per typ: szczepienie 12 mies., odrobaczenie 3 mies., kowal 2 mies., dentysta 12 mies., check-up 6 mies. — auto-suggest przy wpisywaniu
- **Alerty** na dashboard: przeterminowane, w 7 dni, w 30 dni
- **Upcoming Health Alerts widget** na dashboard ownera stajni
- Pokazane też w portalu klienta-pensjonariusza

### 1.5 Instruktorzy
- CRUD, e-mail, telefon, kolor (per-instruktor na kalendarzu), godzinowa stawka, notatki
- `is_active` toggle — nieaktywny instruktor znika z public booking + nie da się przesunąć na niego rezerwacji
- Powiązany z `central_users` (instruktor może być użytkownikiem panelu)

### 1.6 Ujeżdżalnie / Areny
- CRUD, typ (kryta / otwarta), kolor, sort order, notatki
- `is_active` toggle
- Wykorzystywane w **conflict detection** — dwie rezerwacje nie mogą zająć tej samej areny w tym samym czasie

---

## 2. Kalendarz (panel `/app`)

### 2.1 Wizualny widok kalendarza
- Server-rendered Blade (lekki, brak JS framework) z natywnym Filament-em
- Dzień / tydzień / miesiąc
- Widoczne wszystkie blokujące zasoby (Konfirmed, Completed, Requested w trakcie)

### 2.2 Wpisy kalendarza (Rezerwacje)
- Rodzaje: `lesson_individual`, `lesson_group`, `training`, `care`, `event`, `block`
- Status: `requested` → `confirmed` → `completed` / `no_show` / `cancelled`
- Pola: koń, instruktor, arena, klient, czas (od / do), notatki, metadata JSON
- **Conflict detection** półotwartymi przedziałami `[a, b)` — back-to-back 09:00-10:00 i 10:00-11:00 NIE konfliktuje
- Sprawdza wszystkie 3 zasoby: koń + instruktor + arena
- **Walidacja przejścia statusów**: nie można przejść z `requested` do `confirmed` bez przypisanego konia
- Audit log: `calendar.create`, `calendar.update`, `calendar.cancel`, `calendar.complete`

### 2.3 Cykliczne zajęcia (Recurring)
- **Wzorce**: dziennie, tygodniowo (z wybraniem dni tygodnia), miesięcznie
- **Idempotentne rozwijanie** — jeśli już istnieje wpis na daną datę, nie duplikuje
- Przy edycji szablonu — propozycja "zaktualizuj wszystkie przyszłe wystąpienia" (przyszłe iteracje)
- Każde wystąpienie ma `recurrence_id` + `recurrence_occurrence` (numer w serii)

### 2.4 Pass + Calendar integration
- Przy potwierdzaniu rezerwacji `lesson_*` z klientem → system **auto-konsumuje karnet** (FIFO po `valid_until`)
- Przy odwołaniu → **restore karnetu** zgodnie z polityką
- Email do klienta z informacją "Twój karnet został zwrócony" / "Karnet zużyty"

---

## 3. Public booking (`/s/{slug}/book`)

Klient z internetu rezerwuje BEZ konta — w 3 krokach.

### 3.1 Konfiguracja per stajnia (Ustawienia stajni → Online booking)
- **Włącz / wyłącz** (toggle)
- Długość lekcji (15-240 min)
- Godziny pracy: od / do
- Min. wyprzedzenie (h) — anti-last-minute
- Max horyzont (dni) — anti-far-future

### 3.2 Flow klienta
1. **Wybór instruktora** — lista aktywnych z fotką (jeśli `cover_image_path` ustawione)
2. **Wybór dnia** — kalendarz wyświetla tylko dni z wolnymi terminami
3. **Wybór godziny** — dostępne sloty (po conflict detection)
4. **Formularz kontaktowy** — imię, e-mail (auto-lowercase), telefon, notatki
5. **Submit** → zgłoszenie z `status=requested` (bez konia, owner przydzieli przy potwierdzaniu)
6. **Potwierdzenie wizualne** — "Dziękujemy, stajnia potwierdzi w ciągu kilku godzin"
7. **Email do owner-ów stajni** o nowym zgłoszeniu
8. **Email do klienta** z linkiem do odwołania + link do portalu

### 3.3 Bezpieczeństwo
- **Throttle** 30 req/min na endpoint, 6 req/min na submit
- **Race-safe slot booking** — w transakcji DB sprawdza czy slot wciąż wolny zanim utworzy
- **Match-or-create klient** po e-mailu (auto-lowercase, dedup)
- **Status requested** — owner musi explicit potwierdzić zanim koń jest blokowany

### 3.4 Public micro-site (`/s/{slug}`)
- Branded strona stajni: logo, kolor wiodący, opis, godziny otwarcia, adres, telefon, social
- Cache 5 min na controllerze
- CTA "Zarezerwuj online" → `/s/{slug}/book`
- Dark-mode aware

---

## 4. Portal klienta per stajnia (`/s/{slug}/portal`)

Klient stajni loguje się przez **magic link** (bez hasła) — typowy klient stajni rezerwuje 5x rocznie i hasło, którego zapomni jest gorsze niż link.

### 4.1 Login (magic link)
- Wpisuje email → mail z linkiem (signed URL, TTL 30 min, single-use SHA-256 token)
- **Anti-enumeracja**: zawsze pokazuje "Jeśli email jest zarejestrowany, wysłaliśmy link" — nie ujawnia czy email istnieje
- Sesja **namespaced per slug** (`client_portal.{slug}`) — można być zalogowanym do dwóch stajni jednocześnie
- Polish helper text per provider

### 4.2 Dashboard
Sekcje (każda widoczna tylko gdy klient ma dane):
1. **Nadchodzące rezerwacje** — z akcjami "Przesuń" + "Odwołaj"
2. **Historia rezerwacji** — top 20
3. **Karnety** — z progress bar (`X/Y pozostało`), valid_until, status pill, "Ostatnio użyte"
4. **Faktury do opłacenia** — Issued faktury z linkiem do publicznego widoku + "Zapłać teraz", overdue highlighted
5. **Twoje konie** (boarder) — owned konie z liczbą przeterminowanych / nadchodzących health alerts → click do detailu
6. **Wiadomości** — top 5 historycznych maili od stajni z linkiem "Wszystkie →"

### 4.3 Self-service reschedule
- Klient może przesunąć confirmed rezerwację w portal-flow (PR 17b)
- **Limit 2 reschedules** per booking (override przez `tenant.settings.public_booking.max_client_reschedules`)
- Cancellation policy = lead time — gdy za późno żeby odwołać, też za późno żeby przesunąć
- Nowy slot **musi być w `PublicBookingAvailability::slotsFor`** — brak back-doora wokół godzin pracy
- Defence-in-depth conflict check na koniu + arenie
- Mail do klienta po sukcesie: "Rezerwacja przesunięta — Stara data → Nowa data"

### 4.4 Cancel rezerwacji
- Klient klika link "Odwołaj" w mailu → publiczny widok z przyciskiem "Tak, odwołaj"
- Signed URL (TTL = booking start), expired → friendly page (nie 403)
- Decyzja policy: pokazuje czy karnet zostanie zwrócony przed kliknięciem
- Audit log + mail "Twoja rezerwacja została odwołana"

### 4.5 Konie boardera (read-only)
- `/s/{slug}/portal/horses/{horse}` — szczegóły konia owned by klient
- Pełen profil + historia weterynaryjna
- Strict ownership: 404 dla cudzego konia (nie 403 — UX bezpieczniejsze)

### 4.6 Notifications hub (`/s/{slug}/portal/messages`)
- Lista wszystkich maili wysłanych od stajni do klienta
- Każdy: subject, type (np. "Potwierdzenie rezerwacji"), data wysyłki, e-mail odbiorcy
- Paginacja 30/page
- Strict client_id scope

---

## 5. Email automation

Wszystkie maile w polskim, branded (kolor wiodący stajni), z linkiem do portalu i historie w `client_messages` table (widoczne w portal "Wiadomości").

| Typ | Kiedy | Zawartość |
|---|---|---|
| `booking.requested` | Public booking submit | Zgłoszenie otrzymane, "Stajnia potwierdzi w ciągu kilku godzin", cancel link |
| `booking.confirmed` | Owner przesuwa requested → confirmed | Termin, instruktor, koń, ujeżdżalnia, adres, telefon, cancel link, link do portalu |
| `booking.cancelled` | Owner odwołuje LUB klient odwołuje przez signed URL | Termin, instruktor, kto odwołał, info o restore karnetu (jeśli applicable) |
| `booking.reminder` | Cron godzinowy, ~24h przed startem | Te same dane co confirmed + "Przypominamy o jutrzejszej rezerwacji"; idempotentny przez `reminder_sent_at` |
| `booking.rescheduled` | Klient przesunie w portalu | Stara data + Nowa data, instruktor, link do undo |
| `portal.magic_link` | Klient próbuje się zalogować | Link 30-min single-use |
| `invoice.issued` | Owner klika "Wyślij na e-mail" przy fakturze | Numer FV, kwota brutto, daty, "Zobacz fakturę i zapłać" |

**Owner-side**: NewBookingRequestNotification → mail do wszystkich userów z `role: owner` lub `admin` przy nowym zgłoszeniu z public booking.

---

## 6. Płatności online (D2)

System **provider-agnostic** z 4 prawdziwymi integracjami + stub do testów. Każda stajnia wybiera swojego providera w `Ustawienia → Płatności online`, podaje swoje credentials (encrypted-at-rest via Laravel Crypt), wybiera które metody pokazać klientom.

### 6.1 Providery

| Provider | Region | Metody (configurable) | Auth |
|---|---|---|---|
| **Stripe** | global | card, blik, p24, bancontact, ideal, eps, giropay, sepa_debit, sofort | Bearer secret_key + webhook HMAC SHA-256 |
| **Mollie** | EU | creditcard, blik, p24, ideal, bancontact, eps, giropay, sofort, banktransfer, paypal, applepay | Bearer api_key + webhook fetch-by-id |
| **Przelewy24** | PL | force-method (BLIK/Karty/Google Pay/Apple Pay/banki) lub pełna lista | merchant_id + pos_id + crc_key + REST api_key + SHA-384 sign |
| **PayU** | PL/EU | force-method (BLIK/Karty/banki PL) lub pełna lista | OAuth2 client_id+secret + MD5 sign + 50-min token cache |

### 6.2 Flow klienta
1. Klient klika "Zapłać teraz" (z portal lub z maila)
2. `InitiatePayment` action tworzy `Payment` row z `status=pending`, wybiera providera default-y stajni
3. Provider `initiate()` → checkout URL, zmienia status `pending → processing`
4. Klient → checkout → płatność
5. Provider webhook → `handleWebhook()` weryfikuje signature → mark Succeeded/Failed
6. **PaymentObserver** → jeśli `payment.invoice_id` ustawione → marks Invoice.paid + paid_at

### 6.3 Bezpieczeństwo
- **Encrypted-at-rest** wszystkich credentials (api_key, secret, etc.)
- **Method whitelist** — bogus value w settings filtrowany zanim trafi do API
- **Webhook signature verify** PRZED parsowaniem body
- **Idempotency** — drugi delivery webhooka nie cofa statusu z terminal
- **CSRF exemption** dla `/payments/{provider}/webhook` (każdy provider weryfikuje signature)

### 6.4 Refundy
Po stronie aplikacji wszystkie 4 providery mają `refund()` method — częściowo zaimplementowane (Stripe ✓, Mollie ✓, P24 manual via panel, PayU ✓).

---

## 7. Fakturowanie

### 7.1 Typy faktur
- **FV (Faktura VAT)** — standardowa
- **FV Proforma** — oferta, nie księgowa, własna sekwencja
- **FV Korekta** — pełen reversal kopii faktury-źródła z odwróconym znakiem; caller edytuje pozycje

### 7.2 Numeracja per stajnia
Owner stajni konfiguruje w `Ustawienia → Faktury i rozliczenia`:

**Template string** z placeholderami:
- `{seq}` — kolejny numer (1, 2, 3, ...)
- `{seq:NN}` — zero-padded do NN cyfr (np. `{seq:4}` → `0001`)
- `{YYYY}` / `{YY}` — rok
- `{MM}` / `{M}` — miesiąc
- `{DD}` — dzień
- `{prefix}` — wartość z settings.invoicing.prefix (np. "STW")

**Domyślne wzory**:
- FV: `FV/{seq}/{MM}/{YYYY}` → "FV/1/05/2026"
- Proforma: `PRO/{seq}/{MM}/{YYYY}`
- Korekta: `KOR/{seq}/{MM}/{YYYY}`

**Reset interval**: rocznie (default) / miesięcznie / nigdy.

Każdy kind ma osobną sekwencję — FV i Proforma nie kradną sobie numerów. Atomic increment via DB transaction `FOR UPDATE`.

### 7.3 Pola faktury
- Snapshot **sprzedawcy** + **nabywcy** denormalizowany w momencie wystawienia (zmiana danych stajni nie nadpisze historii)
- **NIP nabywcy opcjonalny** — wystawiasz FV dla osób fizycznych bez NIP-u, system nie blokuje
- Pozycje (Repeater w UI):
  - nazwa, opis, ilość, jednostka
  - **VAT rate** jako string ("23", "8", "5", "0", "zw", "np", "oo") — obsługa zwolnień
  - cena netto w groszach, recompute net/vat/total automatycznie
- Currency (PLN default)
- Daty: wystawienia, sprzedaży, termin płatności, opłacenia
- Notatki
- KSeF placeholders: status, reference, sent_at

### 7.4 Lifecycle
```
draft → issued → paid          (happy path)
              → overdue        (po due_at, computed)
              → cancelled      (po wystawieniu korekty)

draft → void                   (anulowana przed wystawieniem)
```

Po wystawieniu (Issued) nie można edytować — jedynie korektę. UI ukrywa Edit/Delete.

### 7.5 Auto-FV za karnety
Gdy owner stajni tworzy karnet z `price_cents > 0` → system automatycznie:
1. Tworzy Draft z 1 pozycją "Karnet: {name}"
2. Snapshot sprzedawcy z `tenant.settings.invoicing` + nabywcy z client
3. Oblicza netto/VAT z brutto karnetu (default 23%)
4. Auto-issue (Draft → Issued, nadaje numer)
5. Toast "Wystawiono fakturę FV/X/MM/YYYY"

`metadata.skip_invoice = true` skip-uje (gdy klient płaci przez online checkout — wtedy faktura wystawi się po `payment.succeeded`).

### 7.6 FV → Email + pay-online
Owner klika **"Wyślij na e-mail"** na fakturze:
1. Generuje signed URL (`InvoicePublicLink`, ważny 90 dni od wystawienia / 14 dni po due_at)
2. Mail do klienta z linkiem "Zobacz fakturę i zapłać" (lub "Zobacz fakturę" gdy bez płatności online)
3. Pisze do `ClientMessageJournal` — pojawi się w portalu w "Wiadomości"

Klient klika → publiczny widok z pozycjami + totalami + **"Zapłać teraz"** (jeśli stajnia ma payment provider). Klik → `InitiatePayment` z D2 → checkout. Po `payment.succeeded` webhook → `PaymentObserver` → marks Invoice.paid.

### 7.7 GUS / KRS auto-fill
Pole NIP w formularzu Klienta dostaje suffix-button **"Pobierz z GUS"** (visible tylko gdy master-admin skonfigurował klucz API GUS):
1. Walidacja NIP checksum lokalnie
2. Hit GUS BIR (3-step Zaloguj → DaneSzukajPodmioty → Wyloguj)
3. Auto-fill: nazwa, ulica + numer + lokal, kod, miasto, kraj=PL, type=organisation

---

## 8. KSeF (Krajowy System e-Faktur)

Pełna infrastruktura uwierzytelniania + budowania FA(3) XML. **Per stajnia** — każda stajnia ma własny certyfikat (KSeF jest osobistym podpisem podatnika).

### 8.1 Konfiguracja stajni (`Ustawienia → KSeF`)
- **Środowisko**: test / demo / prod
- **Context NIP** stajni (kontekst uwierzytelniania)
- **Identifier type**: `certificateSubject` (zwykle PFX) lub `certificateFingerprint` (KSeF-issued)
- **Upload**: PFX/P12 + hasło **ALBO** para `.crt + .key` PEM + opcjonalne hasło klucza
- Po uploadzie pokazane metadane: subject CN, NIP, issuer, fingerprint SHA-256, ważność, typ certyfikatu (personal / seal / ksef)
- Cert + private key + hasło **encrypted-at-rest** via Laravel Crypt

### 8.2 Akcja "Wyślij do KSeF"
Na każdej wystawionej fakturze (visible gdy `KsefClient::isReady()`):
- `GET /AuthorisationChallenge` z context NIP
- `buildAuthTokenRequest` z challenge
- Sign XAdES-BES (PFX lub PEM)
- `POST /InitSigned` z signed XML → session token
- Mark `invoice.ksef_status = 'sent'` + `ksef_sent_at = now()`

Pełen invoice submit (RSA-OAEP wrap + AES-256-CBC + multi-doc batch) zaplanowany w PR 4b.

### 8.3 FA(3) XML builder
Generator XML z naszych Invoice/InvoiceItem zgodny z polskim wzorem **FA(3)**:
- `<Naglowek>` z `KodFormularza="FA (3)"`, kod systemowy, wersja, DataWytworzeniaFa
- `<Podmiot1>` (sprzedawca) + `<Podmiot2>` (nabywca) z DaneIdentyfikacyjne + DaneAdresowe
- **`<BrakID>1` dla nabywcy bez NIP** (osoby fizyczne!)
- `<FaWiersz>` per pozycja: P_7 (nazwa), P_8A (jedn.), P_8B (ilość), P_9A (cena), P_11 (netto), P_12 (VAT)
- Summary: P_1 (data wystawienia), P_2 (numer), P_6 (data sprzedaży), P_13_1 (netto), P_14_1 (VAT), P_15 (brutto)

### 8.4 Bezpieczeństwo
- **Inclusive C14N** (NIE exclusive — to częsty trap, Billu-System komentuje że psuje weryfikację)
- **ECDSA DER → IEEE P1363** konwersja (PHP zwraca DER, XML DSig wymaga raw r||s)
- SHA-256 digest dla document i SignedProperties
- Cert metadata extraction obsługuje 4 konwencje umieszczania NIP (serialNumber, organizationIdentifier z VATPL, O field, raw 10 cyfr)

---

## 9. Multi-tenancy

### 9.1 Architektura
- **Database-per-tenant** — pełna izolacja danych
- 3 połączenia DB: `central`, `tenant`, `provisioner`
- `central` — tabela tenants + users + plans + subscriptions + system_settings (master-admin config)
- `tenant` — wszystko per stajnia (clients, horses, calendar, passes, invoices, payments, audit_log, ...)
- `provisioner` — superuser MySQL (CREATE DB / USER / GRANT) — używany przy tworzeniu nowej stajni

### 9.2 Lifecycle nowej stajni
- Master admin: `/admin → Stajnie → Add tenant` LUB CLI `php artisan tenants:create slug "Nazwa"`
- System tworzy: DB `hovera_t_{slug}`, MySQL user `hovera_t_{slug}` z losowym hasłem (encrypted-at-rest), migruje schemat, wysyła zaproszenie do ownera (magic link)
- Status lifecycle: provisioning → trialing → active → past_due → suspended → churned

### 9.3 Routing
Wszystko pod jednym hostem `app.hovera.app`, ścieżki:
- `/admin` — Filament Master Admin (Indigo)
- `/app` — Filament Tenant Application (Emerald)
- `/s/{slug}` — public micro-site stajni
- `/s/{slug}/book` — public booking
- `/s/{slug}/portal` — portal klienta z magic-link auth
- `/s/{slug}/invoices/{id}` — publiczny widok faktury (signed URL)
- `/s/{slug}/payments/{provider}/webhook` — payment provider webhook

---

## 10. Master admin (`/admin`)

### 10.1 Dashboard
**Stats overview** (polling 60s):
- Aktywne stajnie (active + trialing) z trialing/past_due breakdown
- **MRR** — yearly subs amortyzowane /12
- **ARR** = MRR × 12
- **Churn 30d** — % stajni które przeszły w `churned`
- **Suspended count**

**Tenant Health Table** (full width):
- Lista wszystkich non-deleted tenantów po `last_activity_at` desc
- Status pill, plan, "ostatnia aktywność since", **health score 0..100** badge
- Kolory: ≥80 success, ≥50 primary, ≥30 warning, <30 danger
- Click-through → TenantResource edit

### 10.2 Health snapshot (cron daily 03:30)
`tenants:snapshot-health` recomputes health-score per stajnia z tenant DB:
- +25 active/trialing/past_due
- +25 any booking last 7d
- +15 any booking last 30d
- +15 ≥3 active clients (90d)
- +10 zero overdue vet records
- +10 mature account (>30d)
- −25 past_due
- −50 suspended
- −10 zero bookings ever
- Clamp [0..100], zapis do `tenant.health_score` + `tenant.last_activity_at` + `tenant.settings.health_signals` payload

### 10.3 Konfiguracja
- **GUS / KRS** (`/admin → Konfiguracja → GUS / KRS`):
  - Klucz API GUS (encrypted-at-rest)
  - Środowisko: test / produkcja
  - KRS info-only (publiczne API, brak konfiguracji)

### 10.4 Stajnie
- **TenantResource** — CRUD + soft delete + przywracanie
- **Memberships relation manager** — kto jest userem panelu (role: owner/admin/instructor)
- **Invitations relation manager** — wysłane zaproszenia, ich status
- **Impersonation** (master-admin może "wejść jako" owner stajni — z audyt logu i max time limit)

### 10.5 Audit log
- Master-side audit (`audit_log_master`) — kto co zrobił w master adminie
- Tenant-side audit (per stajnia w `audit_log` w tenant DB) — wszystkie operacje booking, pass, calendar, invoice, payment, ksef

---

## 11. Background jobs (cron)

Wymaga `* * * * * php artisan schedule:run` w systemie (Plesk Scheduled Task).

| Job | Częstotliwość | Co robi |
|---|---|---|
| `bookings:send-reminders` | co godzinę | Mail "twoja jazda jutro" do confirmed booking 22-26h przed startem; idempotentny przez `reminder_sent_at` |
| `tenants:snapshot-health` | codziennie 03:30 | Recomputes health-score per stajnia z tenant DB |

Plesk-friendly setup też dla **queue worker**:
```cron
* * * * * php artisan queue:work --stop-when-empty --max-time=55 --tries=3
```
Bez supervisorska, bez root SSH.

---

## 12. Deploy

`docs/DEPLOY.md` zawiera **Plesk-UI-first** playbook:
- 12 kliknięć żeby przygotować serwer (domena, PHP 8.3, document root, SSL, DB, phpMyAdmin provisioner, Plesk Git auto-deploy, File Manager dla .env, Scheduled Tasks dla cron + queue, Backup Manager)
- `deploy.sh` — idempotentny rollout (maintenance → git pull → composer → cache → migrate central + tenants → storage:link → filament:assets → queue:restart → maintenance off → smoke test)
- SSH potrzebny tylko w 2 brzegowych przypadkach

---

## 13. Liczby (stan: maj 2026)

- **35 zmergowanych PR-ów** z opisami "co i dlaczego"
- **333 testy** (612 linii spec / 47 plików testowych) — wszystkie zielone
- **0 dead code, 0 hardcoded secrets**
- Cały panel po polsku, z polskim DST, polskim formatem dat i kwot
- Encrypted-at-rest: db_passwords, payment credentials, KSeF certs, GUS API key, magic link tokens (jako SHA-256 hash)
- Soft delete + audit log na każdej istotnej tabeli

---

## 14. Co Hovera **NIE** robi (jeszcze)

Świadome scope-cuts dla MVP:

- **Mobile app** — wszystko działa na mobile w przeglądarce (responsive Filament + responsive portal); native API + iOS/Android wciąż w roadmapie
- **AI copilot** — wzmiankowany w spec, jeszcze nie zbudowany
- **Bilety wstępu / passes typu "wejściówka 1x"** — można symulować przez Pass z `total_uses=1`
- **Faktury wielowalutowe + corso wymiany** — currency pole jest, ale konwersja jeszcze nie automatyzowana
- **Zaawansowane raportowanie** (np. "kalkulator ROI per koń per miesiąc") — proste zliczenia są, dashboardy analityczne mogą dojść w kolejnej iteracji
- **KSeF invoice submit** — auth + sign + XML gotowy (PR 4); pełen RSA-OAEP wrap + AES-256-CBC + batch send w PR 4b
- **Master billing** (Hovera → stajnie) — D1 zaplanowany, billu.pl integracja (own invoice numbers `HoveraApp/{seq}/MM/YYYY`) ma swoją osobną gałąź roadmapy

---

## 15. Plan kolejnych iteracji (sugerowany)

Bazując na obecnej funkcjonalności, naturalne next steps:

| Priorytet | Iteracja | Co | Wartość biznesowa |
|---|---|---|---|
| **HIGH** | KSeF submit (PR 4b) | Pełen invoice send: session lifecycle, RSA-OAEP wrap symmetric AES-256-CBC, FA(3) batch upload, status polling, UPO retrieval | KSeF jest obowiązkowy od 2026 dla większych podatników w PL — bez tego stajnie muszą używać innego systemu obok |
| HIGH | PDF generation dla FV | TCPDF / DomPDF integration, PDF download w publicznym widoku faktury i portal-u, attach PDF do email | Klienci oczekują PDF — bez tego "Zobacz fakturę" działa ale nie ma czego "wydrukować" |
| HIGH | D1 — Master billing | Subscription per stajnia (Hovera → stajnia), FV własne z numeracją `HoveraApp/{seq}/MM/YYYY`, billu.pl bridge, master pricing page | Bez tego nie monetyzujemy systemu |
| MED | AI copilot | Predictions ("ten karnet wygaśnie za 2 tygodnie i klient nie dokupił"), automatyzacje notatek, smart booking suggestions | Wartość premium feature, długi runway |
| MED | Dashboard: rapy finansowe per stajnia | "Ile zarobiliśmy w maju", "Top 10 klientów po obrocie", "Konie najczęściej rezerwowane" | Owner-side analytics — pomaga prowadzić biznes, nie tylko zapisywać terminy |
| LOW | Mobile API + native app | OpenAPI spec, sanctum tokens, React Native / Flutter app | Jeśli klienci eksperymentują z mobile-first portfolio |
| LOW | Marketplace integrations | Calendar sync (Google / iCal), Zapier, Slack notifs | Nice-to-have |

---

## 16. Wskazówki onboardingowe dla nowej stajni

Owner po pierwszym logowaniu:

1. **Ustawienia stajni** — uzupełnij Identyfikację (nazwa prawna, NIP), Lokalizację, Branding (kolor + logo)
2. **Konfiguracja public booking** — godziny pracy, długość lekcji, max horyzont
3. **Dodaj instruktorów** + kolory na kalendarzu
4. **Dodaj ujeżdżalnie** (kryta / otwarta)
5. **Dodaj konie** — przynajmniej te które "pracują"
6. **Płatności online** (opcjonalnie) — wybierz providera, wklej credentials, checkbox metody
7. **Faktury i rozliczenia** — sprawdź template numeracji (default OK dla większości), uzupełnij dane sprzedawcy snapshot
8. **KSeF** (gdy masz cert) — upload PFX/PEM, wybierz env=test żeby przetestować
9. **Pierwszych klientów** dodaj ręcznie LUB poczekaj aż przyjdą przez public booking
10. **Karnety** — dodaj produkty które sprzedajesz (np. "Karnet 10x", "Karnet rodzinny 20x")

Dashboard od razu pokazuje upcoming health alerts + rezerwacje na dziś. Portal klienta jest gotowy od momentu pierwszego klienta z e-mailem.

---

## 17. Kontakt techniczny

- **Repo**: github.com/PrzemekPrzemo/hovera.app-sys
- **Master admin**: `przemek@szulecki.pl`
- **Issue / feature request**: GitHub Issues
- **Deploy doc**: `docs/DEPLOY.md`

> Ostatnia aktualizacja: maj 2026, po PR #34 (FV email + pay-online link).
