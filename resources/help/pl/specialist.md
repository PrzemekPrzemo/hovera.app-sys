# hovera — instrukcja dla specjalisty (kowal / weterynarz)

> Witaj. Ta instrukcja dotyczy roli **Specjalisty** — czyli kowala lub weterynarza, który ma konto w systemie hovera (TenantMembership z rolą `vet`). Dostęp do panelu: `https://app.hovera.app/app`.

---

## 1. Logowanie

1. Wejdź na `https://app.hovera.app/app/login`.
2. Wpisz e-mail i hasło (hasło ustawiasz przy pierwszym logowaniu — zaproszenie przychodzi mailem od stajni).
3. Po zalogowaniu trafisz na stronę startową panelu — domyślnie **Moje zadania**.

> **Reset hasła:** użyj przycisku „Nie pamiętam hasła" lub adresu `https://app.hovera.app/forgot-password`. Wyślemy link na e-mail (TTL 60 min).

---

## 2. Moje zadania

**Ścieżka:** `/app` (strona główna po zalogowaniu, jeśli masz rolę `vet`).

To Twój główny widok. Tabela pokazuje:

- **Dziś** — wizyty zaplanowane na dzisiaj,
- **W tym tygodniu** — kolejne 7 dni,
- **Zaległe** — terminy, które już minęły a nie były zaznaczone jako wykonane.

Każdy wiersz: data, godzina, koń, stajnia (jeśli pracujesz w więcej niż jednej), typ wizyty (kucie / szczepienie / przegląd dentystyczny), status.

### Akcje

- **Otwórz** — szczegóły wizyty, dane konia, historia.
- **Oznacz jako wykonane** — po wizycie kliknij i uzupełnij notatkę. To automatycznie:
  - przesuwa status wizyty na *Zakończona*,
  - aktualizuje plakietkę zdrowia konia (znika 🔴 *X przeterm.*),
  - sugeruje termin następnej wizyty (np. kucie co 6 tygodni → propozycja w kalendarzu).
- **Przesuń** — jeśli musisz przełożyć (awaria sprzętu, choroba), wybierz nowy termin → stajnia dostanie powiadomienie.

---

## 3. Kalendarz

**Ścieżka:** `/app/calendar`

Zobaczysz wszystkie wizyty (Twoje i innych) w stajni — dzień / tydzień. Twoje wpisy są pokolorowane Twoim kolorem (ustawia stajnia w karcie specjalisty).

Filtry:
- **Tylko moje** — pokazuje wyłącznie wpisy przypisane do Ciebie,
- **Typ** — kowal / weterynarz / inne,
- **Status** — oczekuje / potwierdzona / zakończona.

### Dodawanie wpisu

Możesz sam wpisać wizytę (np. „doszłam dziś dodatkowo do tego konia") — klik w pusty slot → formularz:
- koń (wybór z listy stajni),
- typ (szczepienie / kucie / dentysta / inne),
- czas trwania (domyślnie 30 min),
- notatka.

> Stajnia widzi Twój wpis natychmiast — po zatwierdzeniu wystawia za niego pozycję na fakturze (jeśli rozliczacie się przez hovera).

---

## 4. Karta konia

Klik na konia w wizycie → otwiera się jego karta. Sekcje istotne dla Ciebie:

### 4.1 Pielęgnacja i zdrowie (timeline)

Pełna historia:
- szczepień (tężec, grypa, EHV),
- kucia (data, kowal, opis pracy),
- wizyt dentystycznych,
- innych zabiegów weterynaryjnych.

Filtry: typ wpisu, zakres dat, autor (Ty / inny specjalista / stajnia).

### 4.2 Aktywności

Pasaż, ćwiczenia, padokowanie — wpisy stajni z ostatnich 7 dni. Pomocne, bo widzisz co koń robił przed Twoją wizytą (np. czy nie był ciężko trenowany dzień przed kuciem).

### 4.3 Dokumenty

Paszport, polisa OC, badania krwi — możesz pobrać i zobaczyć.

### 4.4 Wiadomości

Czat z właścicielem konia + stajnią. Możesz:
- zobaczyć poprzednie ustalenia,
- napisać wiadomość (np. „Po wczorajszym kuciu zostaw bez pracy 24h"),
- załączyć zdjęcia (PDF/JPG/PNG, max 10 MB każdy).

---

## 5. Oznaczanie wizyty jako wykonane

To Twój najczęstszy flow.

1. Otwórz wizytę (z **Moje zadania** lub kalendarza).
2. Klik **„Oznacz jako wykonane"**.
3. Uzupełnij:
   - **Data faktyczna** (domyślnie dziś),
   - **Notatka** (np. „Lewa przednia bez problemu, prawa wymaga obserwacji"),
   - **Następna wizyta** — sugerowana data (kucie 6 tyg., szczepienie 12 mies.). Możesz zmienić.
   - **Koszt** (opcjonalnie) — jeśli stajnia rozlicza Cię przez hovera.
4. Zatwierdź.

Co się dzieje automatycznie:
- wizyta przechodzi do statusu *Zakończona*,
- timeline konia dostaje wpis,
- plakietka zdrowia na karcie konia się odświeża,
- jeśli ustaliłeś termin następnej wizyty → tworzy się nowy wpis w kalendarzu (status *Oczekuje*),
- właściciel dostaje powiadomienie mailem.

---

## 6. Dwie sytuacje: jesteś pracownikiem stajni vs. zewnętrznym dostawcą

| Aspekt | Pracownik (TenantMembership) | Zewnętrzny |
|---|---|---|
| Logowanie | Tak, masz konto z rolą `vet` | Nie — stajnia wpisuje wizyty za Ciebie |
| Widok „Moje zadania" | Tak | — |
| Otrzymujesz powiadomienia mailem | Tak | Tak (jeśli stajnia ma Twój e-mail) |
| Możesz dodawać wpisy do kalendarza | Tak | Nie |
| Widzisz historię konia | Tak | Nie (chyba że stajnia udostępni dokumenty) |

Jeśli jesteś **zewnętrznym** specjalistą (np. wolny strzelec) — ta instrukcja w większości Cię nie dotyczy. Stajnia kontaktuje się z Tobą mailem / telefonem, a po wizycie sama wpisuje ją do systemu.

---

## 7. Kilka stajni w jednym koncie

Jeśli pracujesz w **kilku stajniach** w hovera, każda zaprasza Cię osobno (oddzielne TenantMembership). W lewym górnym rogu panelu zobaczysz przełącznik stajni (lub URL `/tenant/select`).

Po przełączeniu zobaczysz dane wyłącznie wybranej stajni — koni, kalendarza, zadań.

---

## 8. Język interfejsu

Menu użytkownika (prawy górny róg) → **Polski / English / Deutsch / Français**. Preferencja zapisuje się per użytkownik — zostaje również po wylogowaniu.

---

## 9. Bezpieczeństwo

- **Hasło** — minimum 8 znaków; reset przez `/forgot-password`.
- **2FA** — opcjonalne, włączasz w menu użytkownika → „Dwuskładnikowe uwierzytelnianie" (TOTP, np. Google Authenticator / 1Password).
- **Sesja** — wygasa po 8 godzinach bezczynności.

---

## 9a. Nowości — co dorzuciliśmy ostatnio

- **Szablony zabiegów** — w formularzu nowego wpisu zdrowotnego pojawia się select „Szablon zabiegu". Wybór szablonu (np. „Kucie/korekcja") auto-wypełnia typ, opis, sugerowany termin następnej wizyty (np. +42 dni od dziś). Stajnia ma 6 standardowych PL + może dodać własne. Możesz też zarządzać szablonami w **Stajnia → Szablony zabiegów**.
- **Auto-utworzenie kolejnej wizyty** — przy oznaczaniu wizyty jako wykonanej, jeśli ustawisz „następną wizytę" → automatycznie utworzy się rezerwacja w kalendarzu jako *Oczekuje*.
- **Eksport `.ics` (subskrypcja Twojego kalendarza)** — w **Stajnia → Instruktorzy** (jeśli masz konto pracownika i jesteś na liście) lub w karcie specjalisty → akcja **„Kalendarz .ics"**. Modal pokazuje URL feedu — wklej w **Google Calendar** („Inne kalendarze → + → Z URL"), **Outlook** lub **Apple Calendar**. Lekcje synchronizują się automatycznie co kilka godzin. Window: 6 mies. wstecz + 12 wprzód.
- **Karta wagi konia** — tab **Waga** w karcie konia pokazuje historię pomiarów z kolumną „Zmiana" (🟢 przyrost / 🟡 spadek / ⚪ stabilnie). Pomocne przy ocenie kondycji przed zabiegiem.
- **Galeria zdjęć konia** — tab **Galeria** z miniaturkami. Pomocne przed wizytą — możesz zerknąć jak koń wygląda.
- **Dashboard „Dziś"** (`/app`) — po zalogowaniu widzisz na górze 4 kafelki: Rezerwacje dziś, Wolne boksy, **Przeterminowane zabiegi** (kliknij → filtr w Opiece i zdrowiu), Niezapłacone FV. Pod nimi tabela aktywnych dzisiejszych rezerwacji.

---

## 9b. Co widzisz w panelu — Twoja rola

Sidebar jest filtrowany po roli. Jako **vet** widzisz:

- ✓ Konie, Opieka i zdrowie, Plan dnia (kalendarz), Rezerwacje
- ✓ Specjaliści, Szablony zabiegów
- ✓ Moje zadania (jeśli twoje konto jest powiązane z rekordem Specialist)
- ✗ Klienci, Boksy, Cennik, Faktury, Karnety, Magazyn paszy, Raporty, Ustawienia, Pracownicy

Jeśli musisz coś sprawdzić w sekcji „X której nie widzisz" — poproś admina stajni o krótkotrwałą zmianę roli, albo niech sam wyciągnie informację.

---

## 10. Co nowego — zasady RBAC po audycie

- **Kalendarz** — jako vet jesteś w grupie zaufanego team'u (`owner` / `admin` / `manager` / `instructor` / `vet`). Możesz edytować i usuwać **dowolne** wpisy kalendarza (nie tylko swoje). Sensowne, bo rehabilitacja pokrytkraj często wymaga przesuwania sesji.
- **Szablony zabiegów** (`TreatmentTemplate`) — masz CRUD (grupa `SPECIALIST_STAFF`). Możesz dodawać własne szablony procedur, których potem inni members team'u używają w `HealthRecord`.
- **Finanse** — bez zmian: nie widzisz Faktur, Karnetów, Raportów (różny scope niż vet). W formularzu zdrowotnym możesz wpisać `cost_cents` jako podpowiedź dla managera — on potem wystawia FV.

---

## 11. Wsparcie

- **Stajnia** — kontakt mailowy / telefoniczny widoczny w karcie stajni,
- **hovera (techniczne)** — `support@hovera.app`.

---

*Dokumentacja jest aktualizowana wraz z nowymi funkcjami systemu. Wersja widoczna w stopce panelu.*

Pozostałe role: instrukcja **właściciela / administratora** (`/app/help` z konta z rolą owner/admin) oraz **klienta portalu** (`/s/{slug}/portal/help`).
