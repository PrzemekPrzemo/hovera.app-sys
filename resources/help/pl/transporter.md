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

### KSeF — Twój token, Twoja faktura

- Konfigurujesz w `Ustawienia → KSeF`: wklejasz **token autoryzacyjny
  KSeF** zdobyty w panelu MF (mf.gov.pl, sekcja "Token autoryzacyjny"),
  wybierasz środowisko (test / demo / produkcja) i włączasz integrację.
  Token przechowujemy zaszyfrowany — nigdy nie wyświetlamy go w UI,
  nigdy nie logujemy w czystej formie.
- **Test połączenia** (przycisk w `Ustawienia → KSeF`) wykonuje pełen
  handshake z MF — jeśli zwróci "OK", możesz spokojnie wysyłać faktury.
  Jeśli zwróci błąd: sprawdź czy token nie został revoke'owany w MF,
  oraz czy NIP w ustawieniach zgadza się z NIP'em z którym token został
  wydany.
- **Wysyłka faktury:** w `Faktury` przy każdej wystawionej FV pojawia
  się akcja **„Wyślij do KSeF"**. Pierwsza wysyłka po dłuższej przerwie
  potrwa 2–4 sekundy (handshake z MF), kolejne w ciągu 2h są błyskawiczne
  (cache'owana sesja). Status FV przechodzi `not_submitted` → `submitted`
  → `accepted` / `rejected` (`rejected` = MF odrzucił merytorycznie).
- **Polling statusu:** co 30 minut (w godzinach 06:00–22:00 Warsaw)
  Hovera automatycznie sprawdza, czy MF zaakceptował Twoje wcześniejsze
  wysyłki. Możesz też ręcznie wymusić przez akcję „Odśwież status
  z KSeF" na fakturze.
- **Limity i ograniczenia:** korekty (KOR) NIE są jeszcze wspierane —
  follow-up PR. Wysyłka pojedynczo (nie batch) — dla dużych ilości
  (50+ FV jednorazowo) użyj akcji bulk z confirm modal'em (max 50).
- **Co się dzieje pod spodem:** Hovera jest tylko software'em
  pośredniczącym — nie figurujemy nigdzie jako wystawca Twoich
  faktur. Tag `<SystemInfo>Hovera Transport Passthrough</SystemInfo>`
  w XML KSeF jest jedynym śladem naszej obecności (dla audytu MF).
  Numer faktury, NIP wystawcy, treść — wszystko Twoje.

## Stripe Connect — szybka aktywacja online płatności

Jeden klik aktywuje przyjmowanie płatności online (karta, BLIK,
Przelewy24) na każdej Twojej ofercie. Pieniądze trafiają **bezpośrednio
do Ciebie** (Hovera nie pośredniczy w cash flow).

**Jak aktywować:**

1. Wejdź w `Cennik i stawki` → sekcja **„Stripe Connect Express"**.
2. Kliknij **„Połącz konto Stripe"** — przekieruje Cię do Stripe.
3. U Stripe (KYC): podaj NIP, dane firmy, konto bankowe — to Twoja
   umowa z Stripe, nie z Hovera.
4. Po zatwierdzeniu (typowo 1–2 dni roboczych Stripe) wracasz do
   panelu Hovera, status zmienia się na **„Aktywne ✓"**.

**Co zyskujesz:**

- Każda nowa oferta automatycznie dostaje przycisk „Zapłać online"
  z linkiem do Stripe Checkout (karta / BLIK / Przelewy24).
- Webhook Stripe automatycznie oznacza zlecenie jako **opłacone** w
  momencie wpływu płatności — nie musisz klikać ręcznie „Oznacz jako
  opłacone".
- Stripe daje Ci dashboard z wypłatami, raportami, refundami.

**Ważne:**

- To **Twoje konto Stripe** — Hovera tylko technicznie umożliwia
  checkout. Reklamacje, refundy, dyspuy chargeback — załatwiasz
  bezpośrednio przez Stripe.
- Stripe pobiera prowizję transakcyjną (~1.4% + 0.25 PLN dla kart EU,
  ~0.5% dla BLIK/P24) — to standardowa opłata Stripe, identyczna
  jakbyś otworzył konto Stripe sam. Hovera **nie dolicza** swojej
  prowizji (chyba że w przyszłości regulamin §15 to zmieni — wtedy
  poinformujemy mailem z 30-dniowym wyprzedzeniem).
- Możesz w każdej chwili wyłączyć Stripe w panelu — wrócisz do paste'owania
  URL'a ręcznie albo dyktowania danych do przelewu.

**Status nie aktualizuje się?** Kliknij „Sprawdź status" — Hovera
pobierze najnowszy stan z Stripe (czasem webhook się opóźnia).

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

## Publiczny katalog `/przewoznicy`

Twój profil pojawi się w katalogu zweryfikowanych przewoźników
(`/przewoznicy`) **automatycznie po weryfikacji** dokumentów przez
zespół Hovery. Nie musisz nic dodatkowo robić — katalog czyta listę
verified tenantów bezpośrednio z bazy.

**Ranking w katalogu:**

1. **Ocena malejąco** (Reviews — średnia z opublikowanych opinii).
2. Liczba opinii (więcej = bardziej zaufany przy tej samej średniej).
3. **Recency** (`created_at` DESC) — nowi przewoźnicy widoczni
   nawet bez opinii.

**Co poprawia widoczność:**

- Komplet branding (logo + primary color) — karta wygląda lepiej.
- Wypełniony `tagline` (1-2 zdania) — pojawia się pod nazwą firmy.
- Lista województw w „Obszar działania" — filtry katalogu pokażą
  Cię klientom z danego regionu.
- Aktywne pozyskiwanie opinii (linki do `/transport/review/...`
  po zakończeniu zlecenia) — średnia + count przeszywają sortowanie.

**Anty-spam:** tenant z statusem `suspended` znika z katalogu
natychmiast (cache 60 s s-maxage). Suspended ≠ verified — choć
zachowujesz status verification, marketplace ukrywa firmę dopóki
Hovera nie zdejmie blokady.

## Klient firmowy — pobieranie danych z GUS / VIES

W formularzach z polem **NIP** (Klienci, Faktura, Public quote landing
po stronie klienta) jest przycisk „Pobierz z GUS / VIES". Jeden przycisk
obsługuje:

- **NIP polski** (10 cyfr, np. `5260250274`) → GUS BIR (REGON) + CEIDG
  + KRS. Wypełnia: nazwę firmy, ulicę, kod, miasto. Source attribution
  („Źródło: GUS, CEIDG") pojawia się w toaście.
- **NIP UE** (np. `DE123456789`, `IT12345678901`, `FR12345678901`) →
  VIES (publiczne API Komisji Europejskiej). Walidacja + opcjonalnie
  nazwa i adres (zależnie od państwa — DE/ES zwracają „---" gdy
  firma wyłączyła pokazywanie danych).

Spacjowanie i myślniki są tolerowane: `DE 123 456 789` lub `PL 526-025-02-74`
też zadziała. Master admin konfiguruje globalne klucze API w
`/admin/company-lookup-settings` (GUS API key, CEIDG token, VIES base URL).

**Walidacja NIP-u UE w trakcie akceptacji oferty** — jeśli klient na
public quote landing zaakceptuje ofertę jako firma z NIP-em UE, Hovera
zweryfikuje go w VIES *przed* przyjęciem akceptacji. Niepoprawny /
nieaktywny NIP UE → klient widzi czerwony toast i nie może wysłać
formularza.

## FV w walucie obcej (NBP rate, Art. 31a ustawy o VAT)

Gdy quote / faktura jest w walucie innej niż PLN (EUR, USD, GBP, CZK,
SEK itd.), Hovera **automatycznie pobiera** średni kurs NBP z tabeli A.

Zgodnie z **Art. 31a ust. 1 ustawy o VAT** używany jest kurs z **dnia
ROBOCZEGO poprzedzającego dzień wystawienia FV**:

- Wystawiasz FV w czwartek 21 maja → kurs z 20 maja (środa)
- Wystawiasz FV w poniedziałek → kurs z piątku (cofamy się przez
  weekend)
- Wystawiasz w dniu po święcie (np. wtorek 27 maja po święcie konst.)
  → cofamy się aż do ostatniej publikacji NBP

Snapshot kursu zapisany jest immutable na FV (kolumny: `exchange_rate`,
`exchange_rate_date`, `exchange_rate_source = nbp_a`). Korekta tworzy
NOWĄ FV z nowym snapshot'em — oryginalna FV nie jest modyfikowana.

**Soft-fail:** jeśli NBP API jest niedostępne podczas wystawienia, FV
przechodzi do statusu Issued **bez** kursu (kolumny zostają null) +
log warning. Możesz potem ręcznie uzupełnić kurs przed wysyłką do KSeF
(KSeF wymaga PLN-equivalentów dla FV walutowej — Art. 106e ust. 11).

## FV firmowa po akceptacji oferty (public quote landing)

Klient otrzymuje link do oferty mailem — działa **bez logowania**. Na
stronie akceptacji wybiera typ odbiorcy FV:

- **Osoba prywatna** (domyślnie) → akceptacja zapisuje status, Ty
  wystawiasz FV imienną na klienta (`customer_name` z quote)
- **Firma** → klient wpisuje NIP + nazwę firmy + adres. Wbudowany
  przycisk „Pobierz dane z GUS" odpalany po NIP-ie wypełnia nazwę i
  adres automatycznie. Po akceptacji `quote.customer_company` /
  `customer_tax_id` / `customer_address` są snapshot'owane.

**Po akceptacji** używaj akcji „Wystaw FV" w `Oferty → [oferta] →
akcje`. `IssueTransportInvoiceFromQuote` snapshot'uje pełne dane (w
tym kurs NBP gdy waluta != PLN). FV idzie do KSeF zgodnie z Twoją
konfiguracją.

## KSeF — krok po kroku

1. **Dostęp do KSeF** dla firmy transportowej konfigurujesz w
   `Ustawienia transportu → KSeF`:

   - **Środowisko** — test (deweloperski) / demo / production. Master
     admin systemu Hovera pomoże Ci przejść z demo na prod gdy będziesz
     gotowy (post-2026-02-01).
   - **NIP kontekstu** — Twój NIP jako wystawcy. Domyślnie zaciągamy
     z danych firmy.
   - **Certyfikat** — wgrywasz `.pfx` (PKCS#12) lub parę `.crt + .key`
     (PEM). Hovera szyfruje go przez Laravel Crypt (AES-256-CBC + HMAC).

2. **Identifier type** — `certificateSubject` lub `certificateFingerprint`.
   Wybierz zgodnie z konfiguracją certyfikatu w Twoim podpisie
   elektronicznym (typowo `certificateSubject`).

3. **Test send** — w sekcji KSeF jest „Testuj wysyłkę próbnej FV". Wyśle
   dummy FV do środowiska test i pokaże response z KSeF. Jeśli sukces
   → konfiguracja OK; jeśli błąd → log payload widoczny dla supportu.

4. **Auto-send włączony** — od momentu zapisania konfiguracji, każda
   `IssueInvoice` / `IssueTransportInvoiceFromQuote` wysyła FV do KSeF
   asynchronicznie. Status w panelu: `ksef_status` = `submitted` →
   `accepted` (przyjęte przez MF) lub `rejected` (z error_payload).

5. **Tryb offline** — jeśli klient (np. konkurencyjna stajnia) prosi
   o FV ad-hoc i Twój internet padł — FV wystawiona lokalnie z
   `ksef_status = pending`; cron `KsefPollSubmittedInvoicesCommand`
   spróbuje wysłać ponownie co 5 min, do 24h. Po 24h fail = manual
   intervention przez support.

**Master admin override** — od 2026-02-01 KSeF jest obowiązkowy w
Polsce. Jeśli Hovera dostanie informację o awarii MF (publiczny
incident), master admin może czasowo wyłączyć auto-send dla
wszystkich tenantów — wracasz do trybu offline-only. Powrót do
normalnego trybu jest automatyczny gdy MF wraca online.

## Wsparcie

- **Email supportu:** `support@hovera.app` (czas reakcji: 1 dzień
  roboczy w planie Solo, 4h w Pro, 1h w Fleet).
- **Dokumentacja:** `docs.hovera.app/transport` (najnowsza wersja
  tego dokumentu + opisy techniczne).
- **Status systemu:** `status.hovera.app` (uptime, incydenty,
  planowane przerwy).
- **Bug reporter w panelu:** w prawym dolnym rogu — modal otwiera się
  bez przekierowania, raport idzie prosto do nas z metadanymi.
