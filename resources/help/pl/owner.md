# Instrukcja obsługi systemu hovera

> Witaj w hovera. Ten przewodnik prowadzi Cię przez wszystkie funkcje panelu właściciela stajni. Większość kroków zaczyna się od głównej nawigacji w panelu klienta `/app`.

---

## 1. Pierwsze kroki

Po zalogowaniu trafiasz na panel `/app`. Po lewej stronie masz nawigację podzieloną na grupy:

- **Stajnia** — konie, klienci, boksy, budynki, specjaliści, karnety, opieka, cennik pensji
- **Kalendarz** — plan dnia, rezerwacje, cykliczne zajęcia, instruktorzy, ujeżdżalnie
- **Finanse** — faktury
- **Ustawienia** — ustawienia stajni, faktury, płatności, KSeF, pracownicy

Na początek zalecamy:

1. **Ustawienia stajni** — uzupełnij dane firmy, branding, godziny pracy
2. **Budynki** — utwórz przynajmniej jeden ("Stajnia główna")
3. **Boksy** — przypisz każdy do budynku
4. **Cennik pensji** — zdefiniuj usługi (siano, sprzątanie boksu, transport)
5. **Instruktorzy + Ujeżdżalnie** — żeby kalendarz mógł przyjmować rezerwacje

---

## 2. Ustawienia stajni

**Ścieżka:** `/app/tenant-settings`

Sekcje formularza:

- **Identyfikacja** — nazwa, nazwa prawna, NIP
- **Lokalizacja** — kraj, język domyślny, strefa czasowa, waluta
- **Branding** — kolor wiodący, logo, hero image (na stronie publicznej)
- **Strona publiczna `/s/{slug}`** — tagline, godziny otwarcia, opis, kontakt, social media
- **Online booking** — czy klienci mogą rezerwować przez stronę publiczną; długość lekcji, godziny pracy, wyprzedzenie

**Przykład:**

| Pole | Wartość |
|---|---|
| Nazwa | Stajnia "Pod Lipami" |
| Tagline | Pensjonat, lekcje, rekreacja od 1995 |
| Godziny otwarcia | Pn–Pt 9:00–20:00 · Sob–Nd 8:00–18:00 |
| Min. wyprzedzenie | 4 godziny |
| Max horyzont | 30 dni |

---

## 3. Konie

**Ścieżka:** `/app/horses`

### Dodawanie konia

Klik **"Utwórz koń"** → wypełnij:

- **Imię** (np. "Bucefał")
- **Właściciel** (Select z listy klientów; pusty = stajnia jest właścicielem)
- **Box** (Select z aktywnych boksów)
- **Mikrochip / Nr paszportu / UELN** (Universal Equine Life Number)
- **Płeć:** Klacz / Wałach / Ogier / Ogier kryjący
- **Rasa, Maść, Data urodzenia**
- **Pensja — usługi naliczane** (multi-select z cennika)

Po zapisie koń trafia na listę. Kolumny: Imię, Rasa, Płeć, Maść, Wiek, Właściciel.

### Karta konia (zakładki)

- **Konie → edytuj** otwiera edycję + 4 zakładki:
  - **Opieka i zdrowie** — szczepienia, kowal, dentysta (z auto-sugestią next_due)
  - **Aktywności** — karmienie, pielęgnacja, wypuszczenie na padok
  - **Wiadomości** — czat z właścicielem (mail-out)
  - **Dokumenty** — paszport, umowa, ubezpieczenie (PDF/JPG, do 25 MB)

---

## 4. Boksy i budynki

### Budynki — `/app/buildings`

1 stajnia (jako miejsce) może mieć kilka budynków: "Stajnia czerwona", "Stajnia nowa", "Pawilon padokowy". Każdy budynek to grupa boksów.

**Pola:** nazwa, kolejność, aktywny.

### Boksy — `/app/boxes`

Pola formularza: budynek, nazwa/numer, krótki kod (np. "12"), typ (wewnętrzny / padok / zewnętrzny / kwarantanna), rozmiar (m²), miesięczna cena pensji, aktywny.

Kolumny tabeli:
- **Budynek** (z grupowaniem — można zwinąć "Stajnię czerwoną")
- **Nazwa, Typ, m²**
- **Status** — Wolny (zielony) / Zajęty (szary)
- **Płeć konia** (jeśli przypisany)
- **Pensjonat (zł/m-c)**

**Przykład struktury:**

```
Budynek "Stajnia czerwona"
  ├ Box 1 — wewnętrzny — Wolny
  ├ Box 2 — wewnętrzny — Zajęty (Klacz "Łucja")
  └ Box 3 — wewnętrzny — Zajęty (Wałach "Bucefał")

Budynek "Padok wschodni"
  ├ Pad 1 — padok — Wolny
  └ Pad 2 — padok — Zajęty (Ogier kryjący "Tytan")
```

---

## 5. Klienci

**Ścieżka:** `/app/clients`

### Dodawanie klienta

- **Typ:** osoba prywatna / rodzina / firma
- **Imię i nazwisko / nazwa**
- **Email, telefon**
- **NIP** (z opcją "Pobierz z GUS" jeśli skonfigurowane GUS API)
- **Identyfikacja właściciela konia (ARMiR):**
  - **Nr EP** — numer producenta nadany przez ARMiR przy rejestracji konia w Centralnej Bazie Koniowatych
  - **PESEL** — fallback, jeśli właściciel nie ma EP

### Karta klienta

Po wejściu w `Edytuj` widzisz:

- Wszystkie pola formularza
- **Tab "Konie"** — lista koni należących do klienta
- Action **"Skopiuj link portalu"** — generuje magic link (TTL 30 min) do skopiowania
- Action **"Wyślij link na e-mail"** — wysyła email z linkiem logowania

---

## 6. Kalendarz

### Plan dnia — `/app/calendar`

Widok dnia z grupowaniem per instruktor/ujeżdżalnia. Kliknij pusty slot żeby dodać rezerwację. Kliknij wpis żeby edytować/usunąć.

### Rezerwacje — `/app/calendar-entries`

Pełna tabela rezerwacji. Filtry: typ, status, koń, instruktor, "tylko nadchodzące".

**Statusy:** Zgłoszone (oczekuje na potwierdzenie) → Potwierdzone → Zakończone / Odwołane / Nieobecność.

**Typy rezerwacji:** Jazda indywidualna, Jazda grupowa, Trening, Opieka (wet/kowal), Wydarzenie, Blokada.

### Cykliczne zajęcia — `/app/recurring-calendar-entries`

Tworzy serię (np. "Szkółka pon. 17:00, co tydzień, do końca roku"). Action **"Wygeneruj wystąpienia"** rozkłada serię na pojedyncze rezerwacje (max 365 jednorazowo).

---

## 7. Specjaliści (kowale + weterynarze)

**Ścieżka:** `/app/specialists`

Lista zewnętrznych kontrahentów oraz pracowników stajni którzy podkuwają / leczą konie.

### Dodawanie

- **Specjalność:** Weterynarz / Kowal
- Imię i nazwisko, email, telefon, kolor w kalendarzu
- **Konto w systemie (opcjonalne)** — jeśli specjalista jest pracownikiem (TenantMembership), powiąż go z User. Dzięki temu zalogowany pracownik widzi widok **"Moje zadania"** z listą swoich zabiegów do wykonania.

### Powiązanie z wpisami zdrowotnymi

Przy tworzeniu HR (`/app/health-records`) wybierz typ:
- "Kowal" → lista specjalistów filtruje się do `farrier`
- inne typy → lista filtruje się do `vet`

W tabeli HR kolumna "Wykonał" pokazuje imię specjalisty zamiast pustego pola.

---

## 8. Pracownicy stajni

**Ścieżka:** `/app/team-members` (widoczne tylko dla owner / admin)

### Dodawanie pracownika

Klik **"Dodaj pracownika"** → wpisz:
- email, imię i nazwisko, rola

System automatycznie:
- jeśli user istnieje → tworzy `TenantMembership`
- jeśli nie istnieje → wysyła `UserInvitation` (link aktywacyjny mailem, TTL 7 dni)

### Role

| Rola | Opis |
|---|---|
| **Właściciel** | Pełen dostęp |
| **Admin** | j.w. minus usuwanie stajni |
| **Manager** | Zarządzanie kalendarzem, fakturami |
| **Instruktor** | Kalendarz, rezerwacje, swoje konie |
| **Pracownik** | Activity log (karmienie, sprzątanie) |
| **Weterynarz** | Powiązanie ze specjalistą + Moje zadania |
| **Tylko podgląd** | Read-only |

### Reset hasła

Action **"Wyślij link resetu hasła"** (klucz przy aktywnym pracowniku) — wysyła email z linkiem do `/app/password-reset/request`. Link wygasa po 60 minutach.

---

## 9. Faktury

### Konfiguracja — `/app/invoicing-settings`

- **Numeracja FV / Proforma / Korekta** — wzór z placeholderami `{seq}`, `{YYYY}`, `{MM}`, `{prefix}`
- **Reset numeracji** — Rocznie / Miesięcznie / Nigdy
- **Domyślny termin płatności** — np. 7 dni
- **Dane sprzedawcy** (snapshot na fakturze)

### Wystawianie — `/app/invoices`

1. Klik **"Utwórz"** → wybierz klienta (auto-fill danych nabywcy)
2. Dodaj pozycje (Repeater): nazwa, ilość, jedn., cena netto, VAT
3. Save → status `Draft` → klik **"Wystaw"** → numer i data
4. Klik **"Wyślij na e-mail"** → klient dostaje link do publicznego widoku (z opcją "Zapłać teraz" jeśli masz aktywną bramkę online)
5. Klik **"Wyślij do KSeF"** (jeśli KSeF skonfigurowany) → podpis + wysyłka

### Korekty

Klik **"Korekta"** na zaksięgowanej fakturze → tworzy draft korekty z zerowymi pozycjami; uzupełnij ilości "po zmianie" → wystaw.

---

## 10. Płatności online — `/app/payment-settings`

Wybierz domyślną bramkę:

- **Brak** — wszystko offline (przelew, gotówka)
- **Przelewy24** — wpisz Merchant ID + POS ID + CRC + API key
- **PayU** — POS ID + OAuth credentials
- **Stripe** — secret_key + webhook_secret
- **Mollie** — API key

Po skonfigurowaniu, każda wystawiona FV ma w mailu przycisk **"Zapłać teraz"**.

---

## 11. KSeF (e-faktury) — `/app/ksef-settings`

Wymagane:
- **NIP stajni** (kontekst KSeF)
- **Środowisko:** test / demo / prod
- **Certyfikat** — PFX (.pfx) lub PEM (.crt + .key); klucz i hasło szyfrowane Laravel Crypt + AES-256

Po wgraniu wystawiona FV ma akcję **"Wyślij do KSeF"** — uwierzytelnienie certyfikatem + wysyłka XML.

---

## 12. Strona publiczna i widgety embed

### Strona publiczna — `https://app.hovera.app/s/{slug}`

Renderuje się automatycznie na podstawie sekcji "Strona publiczna" z `/app/tenant-settings`. Kolor wiodący, logo, hero image, opis, godziny otwarcia, kontakt, social media. Opcjonalnie: lista instruktorów, cennik pensji, dostępność boksów.

### Embed widgety

W `/app/tenant-settings` → sekcja "Widgety" — gotowe iframe-y do wklejenia w Wordpress / Squarespace / własna strona WWW:

- **Wolne boksy** ("Mamy X wolnych boksów")
- **Zarezerwuj online** (CTA do booking flow)
- **Cennik pensjonatu** (tabela)
- **Lista instruktorów**

---

## 13. Portal klienta

Klient loguje się przez `https://app.hovera.app/s/{slug}/portal/login`:
- wpisuje email
- dostaje magic link mailem (TTL 30 min)
- klik → ląduje na dashboardzie

Z poziomu **`/app/clients/{id}`** możesz wygenerować link manualnie (do SMS/Messenger) lub wysłać mailem (akcja "Wyślij link na e-mail").

W portalu klient widzi:
- nadchodzące rezerwacje (z opcją "Przesuń" / "Odwołaj")
- swoje karnety (X / Y pozostało)
- historię rezerwacji
- niezapłacone faktury
- swoje konie (z alertami zdrowotnymi)
- wiadomości

---

## 14. Wskazówki

- **Skróty z klawiatury** — `?` w panelu pokazuje listę skrótów
- **Język** — przełącz w user menu (PL / EN / DE / FR); preferencja zapisuje się per user
- **Wsparcie** — kontakt: support@hovera.app

---

*Wersja systemu: zobacz stopkę panelu. Dokumentacja aktualizowana wraz z nowymi modułami.*
