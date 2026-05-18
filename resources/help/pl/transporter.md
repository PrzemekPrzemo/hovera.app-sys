# Witaj w Hovera Transport

Cieszymy się, że jesteś z nami. Ten dokument prowadzi Cię przez pierwsze
dni jako przewoźnik — od aktywacji konta do pierwszej zaakceptowanej
oferty.

## Jak to działa

- **Rejestracja → wgrywanie dokumentów PWL → weryfikacja → aktywacja → wystawianie ofert.**
  Założenie konta jest natychmiastowe, ale wysyłka ofert do klientów jest
  zablokowana do czasu zweryfikowania **wszystkich 6 dokumentów PWL** przez
  zespół Hovera. Zwykle 1–2 dni robocze. Pełna lista dokumentów + skąd je
  zdobyć — niżej w sekcji „Dokumenty PWL".
- **Hovera to marketplace pośredniczący, NIE firma transportowa.**
  Nie posiadamy pojazdów, nie zatrudniamy kierowców, nie ponosimy
  odpowiedzialności za realizację transportu. Łączymy Cię z klientami
  i dajemy Ci narzędzia: panel zarządzania, kalkulator wyceny, generator
  ofert, fakturowanie, publiczny profil.
- **Umowy transportowe są bezpośrednio z klientami końcowymi.** To Ty
  wystawiasz fakturę swoim NIPem, swoją numeracją, swoim KSeF-em. Hovera
  nie pojawia się jako strona umowy.

## Dokumenty PWL (wymagane do weryfikacji)

PWL = Przewóz Wewnątrzwspólnotowy Zwierząt Żywych. Każdy transporter koni
w Polsce musi mieć 6 dokumentów, które Hovera sprawdza przed aktywacją konta.
Wgraj je w panelu `/transport/transporter-documents` (lewy sidebar →
„Weryfikacja konta").

1. **Zezwolenie na wykonywanie zawodu Przewoźnika Drogowego**
   Wydaje GITD (Główny Inspektorat Transportu Drogowego) lub starosta.
   Podstawa: Rozporządzenie WE 1071/2009 + ustawa o transporcie drogowym
   z 2001 r. Jeśli nie masz — wniosek przez `gitd.gov.pl` lub starostwo
   powiatowe; trwa zwykle 30 dni.

2. **Zezwolenie dla Przewoźnika Typ 1 LUB Typ 2 (PWL)**
   Wydaje PIW (Powiatowy Inspektorat Weterynarii). Podstawa: Rozp. WE 1/2005.
   - **Typ 1** — transporty do 8 godzin. Wybierz jeśli wykonujesz wyłącznie
     krótkie trasy regionalne.
   - **Typ 2** — transporty powyżej 8 godzin. Pokrywa również Typ 1
     (czyli z Typem 2 możesz wozić również krótko).
   Wniosek przez właściwy terenowo PIW (lista: `wetgiw.gov.pl`).

3. **Licencje dla kierowców i osób obsługujących (PWL)**
   Świadectwo kompetencji dla każdego kierowcy i osoby obsługującej
   zwierzęta. Podstawa: Rozp. WE 1/2005 art. 6. Wydaje PIW po szkoleniu
   + egzaminie. Wgraj komplet dla całego zespołu (jeden PDF scalony OK).

4. **Świadectwo Zatwierdzenia Środka Transportu (PWL)**
   Wystawiane per pojazd. Podstawa: Rozp. WE 1/2005 — art. 18 dla
   transportów < 8h, art. 19 dla > 8h. Wydaje PIW po inspekcji pojazdu.
   Jeśli masz flotę — wgraj scalony PDF.

5. **Książka mycia i dezynfekcji Środka Transportu**
   Podstawa: Ustawa o ochronie zdrowia zwierząt z 11 marca 2004 r. Twoja
   ewidencja bieżąca — wpisy z ostatnich 12 miesięcy. Skan lub fotokopie
   ostatnich stron.

6. **OC Przewoźnika**
   Polisa odpowiedzialności cywilnej przewoźnika drogowego. Komercyjny
   ubezpieczyciel (PZU, Warta, Allianz, Generali — wszystkie mają linię
   transportową). Hovera sprawdza datę ważności i sumę gwarancyjną.

**Reguła weryfikacji:** wszystkie 6 typów + dane rejestrowe firmy (KRS / CEIDG)
muszą mieć status „zweryfikowany" przez Hovera, zanim Twoje konto przechodzi
w `verified`. Brakujące lub odrzucone dokumenty blokują aktywację.

**Cron przypomnień:** 30 dni przed wygaśnięciem dowolnego dokumentu PWL
dostajesz mail z linkiem do panelu. Wgraj nową wersję — Hovera ponownie
zweryfikuje, w międzyczasie konto pozostaje aktywne.

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

## Jak dostać dobre opinie

Po każdym zaakceptowanym transporcie klient dostaje od Hovery email
14 dni po `preferred_date` z prośbą o opinię. To real-deal gate —
anonimowi „użytkownicy" nie mogą wystawić Ci opinii, tylko klienci
po realnym dealu. Jak zwiększyć szansę na 5 ★:

1. **Komunikuj się.** Mail/SMS dzień przed transportem („Cześć, jutro
   o 8:00 jesteśmy pod stajnią. Mateusz, kierowca, +48 ...") +
   krótkie powiadomienie po załadunku i po dotarciu. Klient wie co
   się dzieje = spokojniejszy.
2. **Pojazd czysty, kierowca w firmowej koszulce.** Drobiazgi
   wizerunkowe robią różnicę między 4 ★ a 5 ★.
3. **Punktualność > wszystko.** Spóźnienie 30 min = -1 ★ średnio.
   Jeśli wiesz że się spóźnisz, zadzwoń **przed** terminem,
   nie po.
4. **Po transporcie:** krótki SMS „Dotarliśmy bezpiecznie, dzięki
   za zaufanie. Za ~14 dni dostaniesz od Hovery prośbę o opinię —
   będziemy wdzięczni za parę słów" zwiększa response rate
   o ~40%.

**Co zrobić z negatywną opinią?**

- Najpierw **odpowiedz publicznie** w panelu „Opinie klientów"
  (akcja „Odpowiedz publicznie"). Profesjonalna, krótka odpowiedź
  („Dziękujemy za feedback, wzięliśmy to do siebie — od teraz...")
  działa lepiej niż brak reakcji. Nawet 1 ★ z dobrą odpowiedzią
  buduje zaufanie.
- Tylko gdy opinia jest **fake / zniesławiająca / pomyłka**
  (np. opinia o innej firmie), użyj „Zgłoś do moderacji". Zespół
  Hovery zweryfikuje i zdecyduje. **Nie używaj** tej akcji jako
  „bo mi się nie podoba" — opinia wróci do publikacji, a Ty
  stracisz wiarygodność.

**Link do opinii** działa 30 dni, jednorazowo. Profil `/t/{slug}`
pokazuje średnią + ostatnich 10 opinii. Możesz też udostępniać
link do profilu (Facebook, Google My Business, wizytówki) — opinie
realne, niewykasowalne przez transportera, dodają wiarygodności.

## Wsparcie

- **Email supportu:** `support@hovera.app` (czas reakcji: 1 dzień
  roboczy w planie Solo, 4h w Pro, 1h w Fleet).
- **Dokumentacja:** `docs.hovera.app/transport` (najnowsza wersja
  tego dokumentu + opisy techniczne).
- **Status systemu:** `status.hovera.app` (uptime, incydenty,
  planowane przerwy).
- **Bug reporter w panelu:** w prawym dolnym rogu — modal otwiera się
  bez przekierowania, raport idzie prosto do nas z metadanymi.
