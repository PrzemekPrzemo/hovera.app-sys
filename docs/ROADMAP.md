# Hovera — Roadmap rozszerzeń

> Stan: maj 2026 · uzupełniany na bieżąco
>
> Ten dokument trzyma kierunek rozwoju produktu poza tym, co jest już w `FEATURES.md`. Każda pozycja jest świadomą decyzją produktową — można ją wyciąć / przesunąć, ale jeśli wchodzi do realizacji, opis tutaj służy jako spec.

---

## Recently shipped (porządek odwrotnie chronologiczny)

- **Dokumentacja per rola** (#117 + #118 + #119) — manuale PL/EN/DE/FR dla owner / specialist / employee w `/app/help` (per-role dispatch wg `TenantMembership.role`) + manual klienta w `/s/{slug}/portal/help`. Markdown w `resources/help/{locale}/{role}.md`.
- **Pretty URLs + reset hasła** (#116) — `/forgot-password`, `/reset-password` aliasy, "Wyślij link resetu" w `TeamMemberResource`.
- **Dodawanie pracowników** (poprzednie PR) — `TeamMemberResource` + role (owner / admin / manager / instructor / employee / vet / viewer).

---

## In progress / aktywnie planowane

### 1. Widget "Dzisiaj" na dashboardzie `/app`

**Cel:** owner / manager po zalogowaniu od razu widzi co go dziś czeka, bez klikania w 4 zakładki.

**Kafelki (4 stat widgety):**
- 🗓️ **Rezerwacje dziś** — `count` + link do filtra "dziś" w `/app/calendar-entries`,
- 🟢 **Wolne boksy** — `count(boxes where status=free)` + link do `/app/boxes`,
- 🔴 **Przeterminowane zabiegi** — `count(health_records where next_due < today)` + link,
- 💸 **Niezapłacone FV** — `sum(invoices where status=issued && balance > 0)` + link.

**+ Tabela** dzisiejszych rezerwacji (godzina, koń, instruktor, status) — `TodayBookingsWidget` na dole.

**Status:** w trakcie (PR 1/6).

### 2. Szablony zabiegów (TreatmentTemplate)

**Cel:** dodanie szczepienia / kucia jednym kliknięciem z auto-wypełnionym `next_due`.

**Model:**
```
treatment_templates
  - id
  - name (np. "Szczepienie tężec/grypa")
  - type (vaccination/farrier/dental/other)
  - interval_days (np. 365)
  - default_notes
  - is_default (PL standard)
```

**Seed:** szczepienia (tężec 12 mies., grypa 6 mies., EHV 12 mies.), kucie (6 tyg.), dentysta (12 mies.), odrobaczanie (3 mies.).

**Form HealthRecord:** select "Szablon" → klik wypełnia type + suggesteduje `next_due = performed_at + interval_days`.

**Status:** zaplanowane (PR 2/6).

### 3. Karta wagi konia

**Cel:** trener/owner śledzi kondycję — co miesiąc pomiar, wykres trendu.

**Model:**
```
horse_weight_measurements
  - id, horse_id
  - measured_at (date)
  - weight_kg (decimal 5,1)
  - notes
  - measured_by (user_id, nullable)
```

**UI:** tab "Waga" w `HorseResource` z tabelą + Filament `LineChartWidget` (ostatnie 12 mies.). Add przez owner / manager / instructor / employee.

**Status:** zaplanowane (PR 3/6).

### 4. Plan żywienia per koń

**Cel:** pracownik wie kiedy co dać; klient widzi w portalu (transparentność diety boardera).

**Model:**
```
horse_feeding_plan_items
  - id, horse_id
  - meal (breakfast/midday/evening/night)
  - feed_type (string lub feed_item_id w przyszłości)
  - amount_kg (decimal 4,2)
  - notes
  - sort_order
```

**UI:** tab "Plan żywienia" w karcie konia (CRUD) + sekcja w portalu klienta (read-only, widok stylizowany "menu").

**Status:** zaplanowane (PR 4/6).

### 5. Magazyn paszy/siana

**Cel:** stajnia wie kiedy zamówić dostawę; alert gdy poniżej progu.

**Modele:**
```
feed_items
  - id, name, unit (kg/szt./bel)
  - low_stock_threshold (decimal)
  - notes

feed_stock_movements
  - id, feed_item_id
  - delta (decimal, + przyjęcie / - wydanie)
  - kind (purchase / consumption / adjustment / waste)
  - movement_date
  - user_id, notes
```

**Stan = SUM(delta).** Bez auto-deduct z planu żywienia w v1 — wszystkie ruchy ręczne. Pierwsza wersja prosta, połączenie z planem żywienia rozważane jako v2.

**UI:** `FeedItemResource` z kolumną "Stan" + badge "⚠ kończy się" gdy `current < threshold`. Page widgety na dashboardzie pokazują ile pozycji w alercie.

**Status:** zaplanowane (PR 5/6).

### 6. Galeria zdjęć konia

**Cel:** owner/trener wrzucają zdjęcia rosnącego konia, postępy treningowe; klient widzi w portalu.

**Stack:** Spatie Media Library (`spatie/laravel-medialibrary`) — automatyczne thumbnaile, kolekcje, konwersje.

**Model:** Horse `implements HasMedia`, kolekcja `gallery`. Osobno od dokumentów (kolekcja `documents`).

**UI:** tab "Galeria" w karcie konia z grid + drag-and-drop upload. W portalu klienta: lightbox.

**Status:** zaplanowane (PR 6/6).

---

## Future ideas (rozważane, bez decyzji)

### Komunikacja & CRM
- **Kampanie mailowe** — broadcast do segmentu klientów (np. "Dzień dziecka — promo"), template builder
- **Segmenty klientów** — auto-tagowanie (aktywni / uśpieni / wysokowartościowi)
- **SMS** — przypomnienia 24h jako SMS przez Twilio/Plivo (alternatywa dla maila)
- **Newsletter dla portali** — opt-in checkbox przy login

### Analityka & raporty
- **Raport przychodów** — miesięczny PnL per stajnia (FV - koszty wprowadzone ręcznie)
- **Obłożenie boksów** — wykres % zajętych w czasie + prognoza wygasania umów
- **Raport instruktorów** — godziny / liczba lekcji / przychód per trener
- **Cohort retention** — % klientów którzy wracają po pierwszej lekcji
- **Export do CSV / XLSX** wszystkich raportów

### Zaawansowany kalendarz
- **Templaty rezerwacji** — "wieczorny trening grupowy" jednym klikiem
- **Bloki dostępności** — instruktor zaznacza "tylko ten zakres", kalendarz publiczny rezerwuje wewnątrz
- **Multi-day events** — obozy, klinki (np. 3-dniowy clinic z zewnętrznym trenerem)
- **Bookable resources** — nie tylko instruktorzy, też arena/manaż jako zasób (np. „rezerwacja jeźdźca prywatnego na manaż 1 — 60 min")
- **Google Calendar 2-way sync** — instruktor synchronizuje swój kalendarz hovera z prywatnym

### Mobile / PWA
- **PWA wrapper portalu klienta** — instalowalny "app", powiadomienia push
- **Native Capacitor app** — zwłaszcza dla pracowników (offline activity log)

### Konie — zaawansowane
- **Drzewo genealogiczne** (matka/ojciec/dziadkowie z linkami)
- **Dziennik treningowy** — sesja, intensywność, samopoczucie 1–5 (różne od Activities)
- **Tracking zawodów** — start, dyscyplina, wynik, wideo
- **Ksero karty WPK / metryki badań** — auto-rozpoznanie typu dokumentu (OCR lekki)

### Finanse zaawansowane
- **Subscription billing** — "pakiet pensjonariusza" co miesiąc auto-wystawiana FV
- **Split payments** — koszt pensji boksu / treningu osobno
- **Wpłaty zaliczek** klienta + saldo karty klienta
- **Integracja z fakturowaniem polskim** poza KSeF (Fakturownia, iFirma — alternatywa dla małych)
- **Cost tracking** — ręczne wpisanie kosztów (siano, siarka, weterynarz zewnętrzny) → P&L

### Integracje
- **CEK ARMIR** — auto-pull danych konia po EP / passport number (jeśli udostępnią API)
- **Stripe Tax / Avalara** — auto-VAT dla EU
- **Slack notifications** dla stajni z zespołem >5 osób
- **Webhooks** — outgoing dla integratorów (np. Zapier)

### Master admin
- **Per-tenant feature flags** — A/B test nowych modułów na 10% stajni
- **Tenant health score** — auto-detekcja ryzyka rezygnacji (brak loginów 14 dni, spadek liczby rezerwacji)
- **Migration assistant** — import koni / klientów z Excel / poprzedniego systemu (Stallion Manager?)
- **Multi-currency** — EUR / GBP dla stajni poza PL (już zaczęte w settings)

### Compliance & audyt
- **GDPR data export** — klient pobiera ZIP ze wszystkimi swoimi danymi
- **GDPR delete request** — anonimizacja po 7 latach (zgodnie z księgową)
- **Audit log** — kto co kiedy zmienił (Spatie Activity Log)
- **2FA enforcement** per rola (admin musi mieć)

### UX / quality of life
- **Globalne wyszukiwanie** w `/app` (cmd+k) — konie, klienci, faktury
- **Dark mode** w panelu (jest tylko w portalu klienta)
- **Bulk actions** — wystaw 50 FV za pensję jednym klikiem
- **Skróty klawiszowe** w kalendarzu (j/k navigate, n new)
- **Onboarding wizard** dla nowej stajni — 5 kroków zamiast 14 sekcji ustawień

---

## Pomysły odrzucone (z uzasadnieniem, żeby nie wracać)

*(Wstaw tutaj wszystko, co zostało rozważone i świadomie odrzucone — uzasadnienie chroni przed "a może jednak" za pół roku.)*

- *(brak)*

---

## Zasady utrzymania tego dokumentu

- **Każdy zaplanowany feature** trafia tu **przed** rozpoczęciem PR.
- **Po mergu** pozycja przenosi się z "In progress" do "Recently shipped".
- **Gdy odrzucamy pomysł** — ląduje w "Pomysły odrzucone" z 1-zdaniowym uzasadnieniem.
- **Specyfikacja modeli** w punktach jest niewiążąca — finalna wersja zawsze w migracjach + `app/Models`.
