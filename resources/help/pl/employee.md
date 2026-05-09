# hovera — instrukcja dla pracownika stajni

> Witaj. Ta instrukcja dotyczy ról **Trener / Pracownik / Manager / Tylko podgląd** w panelu stajni `/app`. Pełna instrukcja właściciela jest pod adresem `/app/help` z konta właściciela / admina.

---

## 1. Logowanie

1. Wejdź na `https://app.hovera.app/app/login`.
2. E-mail i hasło — zaproszenie dostałeś od stajni mailem; przy pierwszym kliknięciu ustawiasz własne hasło.
3. Po zalogowaniu trafisz na stronę startową odpowiednią dla Twojej roli.

### Reset hasła

`https://app.hovera.app/forgot-password` lub przycisk „Nie pamiętam hasła" na ekranie logowania. Link ważny **60 minut**.

### 2FA (opcjonalnie)

Menu użytkownika (prawy górny róg) → **„Dwuskładnikowe uwierzytelnianie"** → zeskanuj QR kod aplikacją TOTP (Google Authenticator / 1Password / Authy). Zapisz kody zapasowe.

---

## 2. Role w stajni

W zależności od roli zobaczysz różny zakres menu i akcji:

| Rola | Co widzisz | Co możesz |
|---|---|---|
| **Manager** | Wszystko poza ustawieniami stajni i mitarbeiterami | Zarządzasz kalendarzem, fakturami, klientami, końmi |
| **Trener** | Kalendarz, swoje rezerwacje, swoje konie, klienci | Edytujesz swoje rezerwacje, dodajesz aktywności do koni które trenujesz |
| **Pracownik** | Karta konia (dziennik aktywności), kalendarz tylko-podgląd | Wpisujesz pasaż, karmienie, padokowanie |
| **Tylko podgląd** | Wszystko czytelnie | Nic nie zmieniasz |

> Jeśli jesteś **kowalem / weterynarzem**, masz osobną instrukcję (rola `vet` → patrz instrukcja specjalisty).

---

## 3. Codzienne czynności — Trener

### 3.1 Kalendarz dzienny

**Ścieżka:** `/app/calendar` (dzień / tydzień, grupowane po manaż / instruktor).

- Kliknij wpis → otwierasz szczegóły (uczestnicy, koń, status).
- Pusty slot → klikasz, dodajesz nową rezerwację (jeśli rola pozwala).

### 3.2 Lista rezerwacji

**Ścieżka:** `/app/calendar-entries` — pełna tabela. Filtry: typ, status, „tylko moje", „nadchodzące".

Statusy: *Oczekuje* → *Potwierdzona* → *Zakończona* / *Odwołana* / *No-show*.

Po lekcji oznaczasz status:
- **Zakończona** — uczestniczył,
- **No-show** — klient się nie pojawił bez odwołania,
- **Odwołana** — klient odwołał (z powodem).

### 3.3 Twoje konie

**Ścieżka:** `/app/horses`. Domyślnie filtr „Moje" (te, które trenujesz).

Karta konia → tab **Aktywności**:
- ➕ **„Dodaj aktywność"** → typ (pasaż / karmienie / padok / inne), notatka.

> Ostatnie 7 dni aktywności jest widoczne dla właściciela w portalu klienta.

---

## 4. Codzienne czynności — Pracownik

Twoja główna sekcja: **Karta konia → Aktywności**.

Wpisujesz codzienne czynności wokół koni:

- **Karmienie** — np. „6:00 – siano + owies",
- **Pasaż** — czas + uwagi,
- **Padokowanie** — który padok, ile godzin,
- **Inne** — np. „Wypadła podkowa, zadzwonić do kowala".

Wpis trafia natychmiast do timeline'u konia. Stajnia i właściciel widzą.

> Jeśli zauważysz coś niepokojącego (kulawizna, kaszel) — dodaj wpis **i** napisz wiadomość przez kartę konia → tab **Wiadomości**. Stajnia dostanie powiadomienie mailem.

---

## 5. Codzienne czynności — Manager

Manager ma najszerszy dostęp poza ustawieniami systemowymi:

- **Kalendarz + rezerwacje** — pełne zarządzanie, przesuwanie cudzych wpisów,
- **Klienci** — dodawanie, edycja, generowanie magic linków do portalu,
- **Konie** — dodawanie, edycja, dokumenty, zdrowie,
- **Faktury** — wystawianie, wysyłka mailem, oznaczanie jako opłacone,
- **Mehrfachkarten** — sprzedaż, anulowanie, edycja terminu ważności.

Pełny opis każdego modułu jest w instrukcji właściciela (`/app/help` z konta owner/admin).

---

## 6. Wiadomości i powiadomienia

### 6.1 Wiadomości w karcie konia

Każdy koń ma czat między stajnią a właścicielem. Możesz:
- czytać historię,
- napisać wiadomość (np. „Dziś padokowanie 3h" — ale to też możesz przez Aktywności),
- załączyć pliki (PDF/JPG/PNG, max 10 MB każdy, do 5 plików).

### 6.2 Powiadomienia mailowe

Domyślnie dostajesz mail przy:
- nowej rezerwacji u Ciebie (Trener),
- odpowiedzi klienta na Twoją wiadomość,
- powiadomieniu od stajni („Jutro przyjeżdża kowal o 14:00").

W menu użytkownika → **„Powiadomienia"** możesz wyłączyć poszczególne kategorie.

---

## 7. Język interfejsu

Menu użytkownika (prawy górny róg) → **Polski / English / Deutsch / Français**. Preferencja zapisana per użytkownik — działa też przy następnym logowaniu.

> Klienci widzą portal w języku ustawionym przez stajnię (lub własnym, jeśli jest taka opcja). Twoje przełączenie nie wpływa na to, co widzą oni.

---

## 8. Bezpieczeństwo

- **Hasło** — min. 8 znaków; nie używaj tego samego co w innych serwisach.
- **Reset** — `/forgot-password` (link mailem, TTL 60 min).
- **2FA** — silnie zalecane dla ról Manager / Trener.
- **Wylogowanie** — menu użytkownika → „Wyloguj"; sesja sama wygasa po 8h bezczynności.

> **Nigdy nie udostępniaj swojego hasła kolegom z pracy.** Każdy ma własne konto — to ważne dla audytu (kto wprowadził błędną fakturę, kto wpisał aktywność).

---

## 9. Najczęstsze problemy

| Problem | Co zrobić |
|---|---|
| Nie pamiętam hasła | `/forgot-password` → mail z linkiem |
| Nie dostaję maila resetującego | Sprawdź spam; daj znać właścicielowi stajni |
| Nie widzę rezerwacji | Sprawdź filtr „tylko moje" — może odznacz |
| Nie mogę edytować — „brak uprawnień" | Twoja rola tego nie pozwala — poproś admina o zmianę |
| Klient skarży się że nie dostał maila | Sprawdź adres w karcie klienta; spróbuj „Wyślij ponownie" |

---

## 10. Wsparcie

- **Twoja stajnia** — właściciel / admin (kontakt w karcie stajni),
- **hovera (techniczne)** — `support@hovera.app`.

---

*Dokumentacja jest aktualizowana wraz z nowymi funkcjami systemu. Wersja w stopce panelu.*

Pozostałe role: instrukcja **właściciela / administratora** (`/app/help` z konta owner/admin), instrukcja **specjalisty** (`/app/help` z konta vet) oraz **klienta portalu** (`/s/{slug}/portal/help`).
