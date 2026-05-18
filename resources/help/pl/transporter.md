# Witaj w Hovera Transport

Cieszymy się, że jesteś z nami. Ten dokument prowadzi Cię przez pierwsze
dni jako przewoźnik — od aktywacji konta do pierwszej zaakceptowanej
oferty.

## Jak to działa

- **Rejestracja → weryfikacja dokumentów → aktywacja → wystawianie ofert.**
  Założenie konta jest natychmiastowe, ale wysyłka ofert do klientów jest
  zablokowana do czasu zweryfikowania dokumentów przez zespół Hovera
  (OC przewoźnika, licencja, NIP, dowód rejestracyjny pojazdów).
  Zwykle 1 dzień roboczy.
- **Hovera to marketplace pośredniczący, NIE firma transportowa.**
  Nie posiadamy pojazdów, nie zatrudniamy kierowców, nie ponosimy
  odpowiedzialności za realizację transportu. Łączymy Cię z klientami
  i dajemy Ci narzędzia: panel zarządzania, kalkulator wyceny, generator
  ofert, fakturowanie, publiczny profil.
- **Umowy transportowe są bezpośrednio z klientami końcowymi.** To Ty
  wystawiasz fakturę swoim NIPem, swoją numeracją, swoim KSeF-em. Hovera
  nie pojawia się jako strona umowy.

## Pierwsze kroki po aktywacji

1. **Dodaj pojazdy** w sekcji `Pojazdy` (lewy sidebar). Dla każdego
   wpisz: nazwę, numer rejestracyjny, ładowność (ile koni), masę
   całkowitą, zdjęcia (rekomendowane 3–6, w tym wnętrze), wyposażenie
   (zawieszenie pneumatyczne, kamera). Te dane trafiają na faktury
   i na Twój publiczny profil.
2. **Dodaj kierowców** w sekcji `Kierowcy`. Każdy kierowca dostaje
   notyfikacje o nadchodzących zleceniach na swój email/telefon
   (z osobnego SMTP `transport@hovera.app`, więc nie mieszają się
   z innymi mailami z Hovery).
3. **Skonfiguruj obszary obsługi** w `Ustawienia → Obszary obsługi`
   (multi-select województw). To kluczowe — bez tego nie dostaniesz
   leadów z marketplace'u w trybie broadcast. Lead z mazowieckiego
   trafia tylko do transporterów którzy mają mazowieckie lub
   sąsiednie województwo (zaszywka w `config/transport.php`).
4. **Skonfiguruj profil publiczny** (`/t/{Twój-slug}`). To Twój
   marketing landing — pojawia się w Google, można go linkować
   na Facebooku/Instagramie, ma własną grafikę OG do social share'ów.
   Wypełnij opis, dodaj logo, zaznacz obszary obsługi, zweryfikuj
   numer telefonu i email kontaktowy. CTA „Zapytaj o ofertę" prowadzi
   bezpośrednio do Ciebie (tryb direct, lead nie idzie do nikogo innego).
5. **Podłącz routing API** w `Ustawienia → Routing`. W planie Solo
   dostajesz darmowy OpenRouteService. W planie Pro/Fleet możesz
   podłączyć własny klucz Mapbox lub Google Maps Routes API dla
   lepszej jakości tras (zwłaszcza dla pojazdów ciężarowych — uwzględnia
   mosty, zakazy, restrykcje wagowe). Po dodaniu klucza kliknij
   „Testuj klucz" — Hovera zrobi probe i pokaże czy klucz działa.
6. **Wystaw pierwszą ofertę.** Otwórz `Kalkulator` (główny sidebar),
   wpisz adres odbioru i dostawy, datę, liczbę koni. Kalkulator
   policzy trasę, doliczy paliwo, opłaty, VAT i pokaże cenę netto/brutto.
   Kliknij „Zapisz jako ofertę" → przejdziesz do `QuoteResource`,
   gdzie możesz dopisać warunki i wysłać ofertę mailem do klienta.
   Klient dostaje PDF + signed URL „Akceptuję ofertę" w mailu.

## Marketplace leadów

Lead = zapytanie od klienta. Wpada do Ciebie w jednym z dwóch trybów:

- **Tryb broadcast.** Anonimowy klient z `/transport/zapytanie` albo
  zalogowana stajnia bez wyboru ulubionego — lead trafia do wszystkich
  transporterów obejmujących obszar (województwo startu + celu +
  sąsiednie). Każdy może odpowiedzieć ofertą. Klient widzi wszystkie
  oferty i wybiera jedną. **Nie ma „kto pierwszy ten lepszy"** — masz
  do 14 dni żeby odpowiedzieć (do `expires_at` leadu).
- **Tryb direct.** Klient świadomie wybiera Cię (przez gwiazdkę
  w panelu stajni, przez CTA na Twoim publicznym profilu, lub przez
  link partnerski) — lead trafia **tylko do Ciebie**. Brak konkurencji.

Inbox leadów: `Leady` w lewym sidebarze. Klikasz lead → widzisz szczegóły
(trasa, daty, ile koni, notatki) → akcja `Odpowiedz ofertą`. Akcja
otwiera kalkulator z pre-fillowanymi adresami, doklejasz cenę i wysyłasz.

**Co się dzieje gdy klient zaakceptuje czyjąś ofertę:**

- Jeżeli zaakceptował **Twoją** ofertą: dostajesz `QuoteAcceptedNotification`,
  status leadu → `accepted`, możesz wystawić fakturę.
- Jeżeli zaakceptował **czyjąś inną**: dostajesz `LeadClosedNotification`
  („Twoja oferta nie została wybrana — wybrano innego dostawcę") albo
  `QuoteRejectedNotification` jeśli wystawiłeś ofertę. Status Twojej
  oferty → `rejected`.

## Wystawianie ofert i faktur

- **Oferta** (`Oferty` w sidebarze): numeracja `OF/YYYY/MM/NNNN`, status
  `draft` → `sent` → `accepted`/`rejected`/`expired`. PDF generowany
  automatycznie, wysyłany osobnym mailerem `transport@hovera.app`
  (osobny od głównego Hovera mailera dla lepszej deliverability).
  Klient akceptuje przez signed URL z maila — nie musi mieć konta.
- **Faktura transportowa** (`Faktury` w sidebarze): wystawiasz po
  realizacji. Akcja `Wystaw FV z oferty` przepisuje pozycje z oferty,
  Ty doklejasz tylko datę realizacji. Numeracja Twoja (`FV/YYYY/MM/NNNN`
  domyślnie, konfigurowalne w `Ustawienia → Numeracja`), NIP Twój.
  KSeF — wkrótce (Faza 9 roadmapy, ETA 2026 Q3).

## Mini-dashboard

`Pulpit` (główny widok po zalogowaniu) — 4 widgety:

- **KPI leady:** liczba leadów tygodniowo + win rate 30 dni (procent
  zaakceptowanych ofert względem wystawionych).
- **Nadchodzące transporty:** kalendarz na 7 dni do przodu — zaakceptowane
  oferty z `proposed_date` w oknie. Klikalne, prowadzi do oferty.
- **Top faktury 90 dni:** ranking najlepiej opłaconych zleceń. Pomaga
  zidentyfikować rentowne pary trasy/klient.
- **Heatmapa tras:** które województwa→województwa robisz najczęściej.
  Dane z `transport_lead_responses.distance_km` agregowane.

## FAQ

**Czy Hovera odpowiada za uszkodzenia konia / pojazdu w transporcie?**
Nie. Jesteś przewoźnikiem, Ty ponosisz pełną odpowiedzialność (Konwencja
CMR + Twoje OC przewoźnika). Posiadanie aktualnej polisy OC przewoźnika
jest wymogiem aktywacji konta.

**Czy Hovera wystawia faktury za transport?**
Nie. Ty wystawiasz fakturę swoim NIPem, swoją numeracją. Hovera daje
narzędzie (`Faktury` w panelu) — nic poza tym.

**Co jeśli klient nie zapłaci?**
Egzekwujesz bezpośrednio. Hovera nie pośredniczy w płatnościach
za transport (chyba że w przyszłości włączymy Stripe Connect — wtedy
będziesz mógł włączyć płatność kartą przy akceptacji oferty).

**Czy mogę mieć więcej niż jeden pojazd?**
Tak — limit zależy od planu:
- **Solo** — 1 pojazd, 1 kierowca, marketplace bez ograniczeń.
- **Pro** — do 5 pojazdów, 5 kierowców, własny klucz routing API,
  branding mapy.
- **Fleet** — pojazdy/kierowcy unlimited, Google Maps Routes API
  z konta Hovery, priorytet w supporcie.

**Mogę zmienić plan?**
Tak, w `Ustawienia → Subskrypcja → Zmień plan`. Upgrade działa
natychmiast, downgrade na koniec okresu rozliczeniowego.

**Co się dzieje gdy nie zaakceptuję leadu w 14 dni?**
Lead wygasa (`expires_at`). Pozostałe oferty (jeśli były) idą do
`withdrawn`, klient dostaje propozycję „przedłuż / wyślij ponownie".
Nie ma kar — po prostu nic z tym nie robisz.

**Czy mogę zablokować konkretnego klienta?**
Nie ma takiej funkcji w MVP — jeśli masz problemowego klienta,
zgłoś przez `support@hovera.app`, master admin może go zbanować
lub zawiesić tymczasowo.

**Czy mogę używać Hovery jako CRM (lista klientów, historia)?**
Tak — sekcja `Klienci` (per tenant) trzyma wszystkich klientów
do których wystawiłeś chociaż jedną ofertę. Historia ofert, faktur,
notatki. Wkrótce: tagi i segmentacja.

**Czy mogę robić trasy międzynarodowe (DE/CZ/SK)?**
W MVP — tylko PL. Trasy międzynarodowe są w roadmapie post-MVP
(Faza 14.4). Tymczasowo możesz wystawić ofertę z adresem zagranicznym
ręcznie (kalkulator dotrasuje, ale brak walidacji prawnej i VAT-OSS).

**Czy mogę mieć i stajnię, i firmę transportową w Hoverze?**
Tak — multi-tenancy. Tworzysz drugi tenant w `Ustawienia → Konto →
Dodaj firmę`, wybierasz typ `Transporter`. Tenant switcher (góra
ekranu) przełącza między nimi.

## Wsparcie

- **Email supportu:** `support@hovera.app` (czas reakcji: 1 dzień
  roboczy w planie Solo, 4h w Pro, 1h w Fleet).
- **Dokumentacja:** `docs.hovera.app/transport` (najnowsza wersja
  tego dokumentu + opisy techniczne).
- **Status systemu:** `status.hovera.app` (uptime, incydenty,
  planowane przerwy).
- **Bug reporter w panelu:** w prawym dolnym rogu — modal otwiera się
  bez przekierowania, raport idzie prosto do nas z metadanymi.
