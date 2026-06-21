# Hovera — Wow Plan v2 (analiza per-tenant)

> Status: roboczy, **2026-06-20**.
> Autor: Claude (sesja, podsumowanie mapy + propozycje).
> Constraints carry-over z poprzedniej sesji:
> - **ZERO AI/ML** w pomysłach — manual UX, dane wprowadzone przez ludzi.
> - **POMIJAMY**: program poleceń, życzenia urodzinowe, achievements/badges (skasowane jako I/J/K w poprzednim planie).
> - **NIE TRACI** spójności z istniejącą kremowo-ochra paletą i lekkim charakterem `/s/{slug}` + `/t/{slug}`.

---

## 1. Stan obecny — TL;DR

Hovera ma **35 zmergowanych PR-ów** w bieżącej sesji, **333 testy zielone**, stabilny MVP dla 3 typów tenantów. Phase 1 wow-plan: PR A (bulk-email faktur), PR B (KPI sparklines), PR C (auto-suggest follow-up booking), PR #424 (504 fix), PR #425/426 (admin docs preview + public certyfikaty), PR #427-433 (mailer config UI: Mailgun, picker drivera, Reply-To, DKIM, diagnostyka). PR D (batch-complete health) wciąż nie zaczęty.

System **DZIAŁA dla MVP** — ale efekt „wow" wymaga teraz dopięcia trzech rzeczy:
1. **Tier 1 — gap'y blokujące doświadczenie** (owner panel pusty, KSeF submit, calculator extra-horse, PDF).
2. **Tier 2 — engagement/retencja** (komunikacja, raportowanie, mobilność).
3. **Tier 3 — różnicujące featuresy** (publiczna marka, marketplace, integracje).

---

## 2. Per-persona: Stable owner (`/app`)

### Co się sprawdza

- **Kalendarz** z conflict detection (koń/instruktor/arena) + recurring + 3 widoki — to fundament i jest mocny.
- **Klient portal** + public booking (`/s/{slug}/book`) — frictionless dla klienta końcowego.
- **Karnety FIFO + auto-restore** — kompletny billing model który konkurencja często upraszcza.
- **KPI sparklines + delta-vs-yesterday** (PR B) — natychmiastowy „pulse check" przy logowaniu.
- **Bulk email faktur** (PR A) — operator nie spamuje guzika 30 razy.

### Gdzie jest gap — 6 priorytetów dla „wow"

| # | Co | Dlaczego wow | Effort |
|---|---|---|---|
| 1 | **PR D — Batch-complete health records** (z poprzedniego planu, niezrealizowany) | Stable robi szczepienia ~8 koni jednego dnia. Klikanie po jednym = 8 minut. Multi-row „mark vaccination done" = 30s. | 1 dzień |
| 2 | **PDF faktury + quoty z wydrukiem** | Klienci PL oczekują „wydruku" mimo KSeF. Plus PDF do email'a klienta zamiast tylko linka. DomPDF/TCPDF. | 2-3h |
| 3 | **KSeF submit** (PR 4b z roadmap) | Mandatoryjne od 2026-02 w PL. To nie wow — to przeżyć. Ale UI „FV wysłana → KSeF UPO odebrane" jest realnie wow dla księgowej tenanta. | 4-6h |
| 4 | **Bulk-monthly boarding invoice** | Stable z 30 boarderami klika 30 razy „nowa FV za pension". Job: select tenants → generate FV za miesiąc na podstawie BoxAssignment + plan cenowy → status draft → manual review → batch-send. | 1 dzień |
| 5 | **Bulk-reminder dla nieopłaconych** (extension PR A) | Po wystawieniu FV w D+7 jeszcze niezapłacone? Bulk mail przypomnienie + ostatecznie suspend portal access. Dunning bez bólu. | 4-6h |
| 6 | **Per-horse profitability report** | Tab w HorseResource: ile godzin lekcji w miesiącu × cena, ile karnetów konsumed, ile boarding revenue, vs koszt utrzymania (z manualnych wpisów). Owner widzi „Ten koń przynosi 4000zł/mc, ten 800". | 1 dzień |

### Bonus „delight" (cheap):

- **Confetti przy 100% completed bookings dziennie** (Filament notification z emoji 🎉)
- **„Twój najlepszy klient w tym miesiącu"** widget na dashboardzie — top-3 z liczbą rezerwacji
- **Quick-add lesson z klawiatury** — global `Ctrl+L` shortcut → modal create

---

## 3. Per-persona: Transporter (`/transport`)

### Co się sprawdza

- **Kalkulator wycen** z multi-currency + fuel surcharge — operator dostaje quote w 30 sekund.
- **Public profile `/t/{slug}`** + sekcja „Certyfikaty i licencje" (PR #426) — social proof dla nowych klientów.
- **Verification flow z dokumentami** + auto-anonimizacja przez admina (PR #425) — wzbudza zaufanie.
- **Stripe Connect Express** — pieniądze idą bezpośrednio do transportera, nie blokujemy cash flow.
- **PWL document expiry alerts** 30d przed → mail z reminderem.

### Gdzie jest gap — 6 priorytetów dla „wow"

| # | Co | Dlaczego wow | Effort |
|---|---|---|---|
| 1 | **Calculator: `horses_count` + `extra_horse_fee`** | Transporter dziś nie potrafi wycenić „2 konie zamiast 1" inaczej niż przez ręczne narzut. To core feature który DZIŚ frustruje. | 1-2h |
| 2 | **`/transport/marketplace` — otwarty board lead'ów** | Owner wrzuca lead → 1-3 transporterów się ścigają. ALE: lead bez wyboru = visible all transporterów. Otwarta giełda. Chęć pracowania.  | 2-3h |
| 3 | **Calculator live UX** (Leaflet preview + debounced recalc) | Operator widzi route na mapie podczas wpisywania adresów. Dzisiaj quote = wpisz, klik, czekaj. To realnie WOW dla operatora. | 1-2 dni |
| 4 | **Driver app — mobile manifest** (web app PWA) | Kierowca dostaje na telefonie listę transportów dnia, klika „Pickup completed" → status w panel transportera live update. Plus magic-link auth (jak portal klienta). | 1-2 dni |
| 5 | **POI library + waypoints reordering** | Transporter ma ulubionych klientów/stadnin. Dzisiaj wpisuje adres za każdym razem. POI = „Wojciech Stadnina" → autocomplete. Plus drag&drop punktów pośrednich. | 2-3 dni |
| 6 | **Public review widget do osadzania** (`<script src=".../t/{slug}/widget.js">`) | Transporter wkleja widget na własną stronę → auto-load opinii z Hovery. Out-of-the-box social proof bez pisania kodu. | 1 dzień |

### Bonus „delight":

- **Animowany odznaka „⭐ Polecany"** na publicznym profilu (gdy `is_featured` true) z subtle pulse.
- **„Najszybciej odpowiada na zapytania"** badge — jeśli median odpowiedzi <2h.
- **Service area map preview** na profilu — `/t/{slug}` pokazuje obsługiwane województwa na statycznej mapie zamiast tagów.

---

## 4. Per-persona: Horse Owner (`/owner`)

### Stan: KRYTYCZNY GAP

Po PR #297-#300 owner ma panel ALE jest praktycznie pusty. Zalogowany owner widzi:
- Lista koni (8 pól)
- Pending boarding requests (akcje accept/reject)
- Stable marketplace (search + request)
- Transport orders (read-only)
- Favorite transporters (max 5)
- Invoices (read-only z stable tenant)

**Brakuje wszystkiego co RABO/Equilab/HorseManager mają**: per-horse timeline (zdjęcia + wpisy weterynaryjne + dokumenty), wallet (BLIK quick-pay faktur), messaging do stable, push notifications, mobile UX dopasowane.

### Gdzie jest gap — 6 priorytetów dla „wow"

| # | Co | Dlaczego wow | Effort |
|---|---|---|---|
| 1 | **HorseResource — pełna karta konia** (PR 6 z roadmap) | Owner klika konia → widzi: zdjęcie, paszport, mikrochip, historia boarding (od kiedy w której stajni), health timeline (vetka, podkucia, szczepienia z dat), waga timeline (manual entries), dokumenty (z cross-tenant API), aktualny plan żywieniowy. To CORE owner experience. | 3-4 dni |
| 2 | **Notifications hub w `/owner` panel** | Dziś owner dostaje powiadomienia mailem ale nie widzi listy w panelu. Filament Notifications resource — read/unread, link do related entity. | 2-3h |
| 3 | **Wallet — BLIK / Apple Pay quick-pay faktur** | Owner klika FV w panel → 1-click pay przez P24 lub Stripe. Dzisiaj musi czekać na mail od stable + płacić ręcznie. To naprawdę WOW dla młodego pokolenia owner'ów. | 1-2 dni |
| 4 | **Komunikator owner ↔ stable** (1-on-1 thread) | Pytania o stan konia, prośby o zdjęcia, ustalenia transportu. Dzisiaj idzie WhatsApp/email — utrata historii. Pełna sekcja messaging z attachments. | 2-3 dni |
| 5 | **Mobile-first owner panel** (PWA installable) | Owner używa telefonu w 90% przypadków (klikać między stajnią-pracą-stajnią). PWA z manifest + service worker + native push. Już mamy `public/sw.js` ale nie dla owner panel'a. | 2-3 dni |
| 6 | **Multi-stable owner view** | Owner z 2 końmi w różnych stajniach widzi WSZYSTKO w jednym dashboardzie zamiast przełączać konta. Magic link auth out-of-the-box. | 1-2 dni |

### Bonus „delight":

- **„Mój koń jest dziś szczęśliwy"** — daily reminder push (jak Calm app) z linkiem do najnowszego zdjęcia konia (jeśli stable wgrał).
- **„Twoje konie spędziły w stajni X łącznie 4 lata 3 miesiące"** — anniversary widget.
- **Quick-share** karty konia → link → na FB/IG „mój koń Trójka — paszport-no XYZ" (publiczna karta z embedded image).

---

## 5. Per-persona: Team members (instructor, vet, employee)

### Co dziś działa

- Każda rola ma scope'owany dostęp (RestrictedByTenantRole).
- Row-level auth na CalendarEntry (instruktor edytuje TYLKO swoje wpisy — PR z audytu G3).
- Wszystkie roles widzą ten sam panel z różnymi resource availability.

### Wow propozycje

| # | Co | Dla kogo | Effort |
|---|---|---|---|
| 1 | **Instructor mobile widok** „mój dzisiejszy plan" | instruktor | Web-app card-stack na telefonie z dzisiejszymi lekcjami, swipe-to-complete | 1 dzień |
| 2 | **Vet visit quick-entry** z autocomplete | vet | „Wczoraj odbyła się wizyta" → dropdown ostatnich koni + template (vaccination/dental/farrier) → 1 klik save | 1 dzień |
| 3 | **Employee batch-feed log** | employee | Lista boxes dziennie → checkbox „nakarmiony" → batch save | 4-6h |
| 4 | **Specialist external login** (`/specialist/login` z magic linkiem) | vet zewnętrzny | Vet który nie jest tenantem ale obsługuje 5 stajni — magic link na email, scoped do swoich wpisów. | 1-2 dni |

---

## 6. Cross-cutting tematy

### A. UX i mobile

- **Wszystkie dashboardy są desktop-first.** Mimo że Filament jest responsive, nie ma offline support'u, nie ma touch-optimized layouts.
- **Propozycja**: PWA z service worker (już mamy `public/sw.js`) + manifest dla 3 panelów oddzielnie. Native push przez Webhook → FCM/APNs.
- **Quick wins:** keyboard shortcuts (Ctrl+L=new lesson, Ctrl+I=new invoice, Ctrl+H=new horse).

### B. Komunikacja

- **Email-only system.** Brak SMS, brak push, brak in-app chat.
- **Propozycja**:
  - SMS przez Twilio/SmsApi (PL local provider) — dla critical events (booking confirmed, payment overdue)
  - Push przez FCM (gdy PWA installed)
  - In-app komunikator dla owner↔stable (już w priorytetach owner'a)
- **Newsletter do klientów stable** (PR H z poprzedniego planu — niezrealizowany). Filament page z TipTap editor + queued send job.

### C. Raportowanie / BI

- **KPI widget jest dobry ale podstawowy.** Brakuje:
  - **Cohort retention** — ile klientów wraca po 30/60/90 dniach
  - **Customer LTV** — średnia wartość klienta przez okres współpracy
  - **Per-horse P&L** — koszty (manual entries) vs revenue per koń
  - **Per-instructor revenue + utilization** — dziś masz `InstructorUtilizationReport`, ale brak revenue.
- **Propozycja**: nowy panel `/app/reports` z 4-5 standardowymi raportami jako Filament Pages. Export do CSV/Excel.

### D. Integracje zewnętrzne

| Integracja | Status | Wow propozycja |
|---|---|---|
| Stripe, Mollie, P24, PayU | ✓ | Apple/Google Pay UI (jeden klik) |
| KSeF | partial | Pełny submit + UPO retrieval (PR 4b) |
| GUS/KRS | ✓ | — |
| Mailgun, SMTP | ✓ (po PR #427-433) | — |
| Mapy (ORS, Google Routes) | partial | Leaflet preview w transport calculator |
| SMS | ❌ | Twilio/SmsApi dla critical events |
| Push | ❌ | FCM dla PWA |
| Slack/Discord | ❌ | Webhook out — gdy nowa rezerwacja → kanał Slack stable team |
| Google Calendar | ❌ | 2-way sync (lekcje stable ↔ Google Calendar instruktora) |
| Zapier / n8n | ❌ | Webhook trigger po events (booking.created, invoice.paid) |
| WhatsApp Business API | ❌ | Notification channel — premium (FB Business Manager) |

### E. Performance i niezawodność

- **Queue worker chodzi co minutę (Plesk cron)** — OK dla low-volume, ale przy 100+ tenantach trzeba supervisor + Horizon.
- **Brak rate limiting per-tenant** — jeden tenant może spamić powiadomienia.
- **Brak distributed tracing** — w razie 504 nie wiemy gdzie czeka.
- **Propozycja**: Sentry / Bugsnag integration + Horizon dashboard dla queue.

### F. Onboarding tenanta

- **Stable**: dobry checklist (`Ustawienia → Public Booking → Konie → ...`) — działa.
- **Transporter**: PWL upload required → status pending → manual review przez master admin. **Czas oczekiwania = friction.** Propozycja: auto-verification dla podstawowych dokumentów (KRS auto-check) + manual review tylko dla edge cases.
- **Horse owner**: 3-step wizard działa, ale **panel po loginie jest pusty** (Tier 1 gap z sekcji 4). Onboarding kończy się rozczarowaniem.

---

## 7. Roadmap propozycji — uporządkowana

### **Phase 1.1 — dokończenie quick wins (1-2 tygodnie)**

Z poprzedniego planu wciąż nie zrealizowane:
- ✅ A — bulk email faktur (DONE)
- ✅ B — KPI sparklines (DONE)
- ✅ C — auto-suggest follow-up booking (DONE)
- 🔲 **D — batch-complete health records** (1 dzień)
- 🔲 **E — photo & event timeline na karcie konia** (2 dni — z manualnymi tagami, BEZ AI)
- 🔲 **F — weather-aware booking suggestions** (1 dzień — OpenWeatherMap free tier)
- 🔲 **G — QR codes per box/horse/instructor** (1 dzień — endroid/qr-code)
- 🔲 **H — newsletter do klientów** (2 dni — Filament + TipTap + queue)

### **Phase 1.2 — krytyczne gap'y (równolegle, 2-3 tygodnie)**

- 🔲 **PR 6 — Owner panel content** (Tier 1, krytyczne, 3-4 dni)
- 🔲 **KSeF submit** (Tier 1, mandatoryjne PL 2026, 4-6h)
- 🔲 **PDF generation faktury + quoty** (Tier 1, 2-3h)
- 🔲 **Calculator extra-horse-fee** (Tier 1, 1-2h)

### **Phase 2 — engagement i retencja (3-4 tygodnie)**

- 🔲 Owner wallet — BLIK / Apple Pay quick-pay (2 dni)
- 🔲 Owner ↔ stable komunikator (3 dni)
- 🔲 Notifications hub w `/owner` (2-3h)
- 🔲 Bulk-monthly boarding invoice (1 dzień)
- 🔲 Bulk-reminder dla nieopłaconych (4-6h)
- 🔲 Per-horse profitability report (1 dzień)
- 🔲 Calculator live UX z Leaflet (1-2 dni)
- 🔲 SMS notifications przez Twilio/SmsApi (1-2 dni)

### **Phase 3 — różnicowanie konkurencyjne (1-2 miesiące)**

- 🔲 Multi-stable owner view (1-2 dni)
- 🔲 PWA installable dla 3 panelów (2-3 dni)
- 🔲 Driver mobile app (PWA, 1-2 dni)
- 🔲 `/transport/marketplace` open board (2-3h)
- 🔲 Public review widget do osadzania (`/t/{slug}/widget.js`) (1 dzień)
- 🔲 Slack/Discord webhook outbound (4-6h)
- 🔲 Google Calendar 2-way sync (3-4 dni)
- 🔲 Zapier/n8n trigger endpoints (1-2 dni)
- 🔲 Reports panel z 4-5 raportami + CSV export (3-4 dni)

### **Phase 4 — mobile native (osobny sprint, 2-3 miesiące)**

- 🔲 React Native / Flutter app
- 🔲 FCM/APNs push notifications
- 🔲 Native scanner (QR + zdjęcia paszportu OCR — bez AI, tylko OCR z `tesseract`)
- 🔲 Offline-first sync

### **Pomijamy świadomie (carry-over z poprzedniej sesji)**

- ❌ Program poleceń (referral) — brak resource'ów na obsługę, complexity ROI niska
- ❌ Życzenia urodzinowe — łatwo postrzegane jako spam
- ❌ Achievements / badges — gimmick bez biznesowej korzyści
- ❌ AI features w jakiejkolwiek formie (zgodnie z constraint'em)

---

## 8. Priorytety — Top 10 do zrobienia teraz

Gdybym musiał wybrać 10 rzeczy które robimy w **kolejności**:

1. **PR 6 — Owner panel content** (3-4 dni) — bez tego owner experience jest zepsuty
2. **PR D — batch-complete health records** (1 dzień) — dokończenie Phase 1
3. **KSeF submit** (4-6h) — mandatoryjne, deadline
4. **PDF generation** (2-3h) — klienci oczekują
5. **Calculator extra-horse-fee** (1-2h) — quick win dla transporterów
6. **Notifications hub w `/owner`** (2-3h) — szybkie ważne
7. **Bulk-monthly boarding invoice** (1 dzień) — stable pain point
8. **Owner wallet — BLIK pay-in-1-click** (2 dni) — generational expectation
9. **Owner ↔ stable komunikator** (2-3 dni) — przewaga vs konkurencja
10. **Calculator live UX z Leaflet** (1-2 dni) — najsilniejszy „wow" dla transporterów

Pozostałe — phase 2-3 zgodnie z roadmap powyżej.

---

## 9. Notes do dyskusji z user'em

Punkty do potwierdzenia przed implementacją:

- **SMS provider** — Twilio (global ale drogi) vs SmsApi.pl (PL, taniej, bez US)?
- **PWA push** — czy jest sens robić native FCM, czy zostać przy email-only do czasu native app?
- **Owner wallet** — który provider preferujesz dla quick-pay? Stripe ma najgładsze Apple Pay, ale PayU/P24 to BLIK który PL chce.
- **Multi-stable owner view** — ile ownerów ma realnie konie w 2+ stajniach? Jeśli <5%, niska priorytetowość.
- **Driver app vs full transporter PWA** — kierowca to OSOBNA rola czy podzbiór existing TenantUser?

---

> **Następny krok**: po Twojej akceptacji listy z Phase 1.1, mogę zacząć od PR D + PR 6 (równolegle — różne pliki).
