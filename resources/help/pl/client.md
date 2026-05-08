# hovera — instrukcja portalu klienta

> Witaj w portalu klienta. To miejsce, w którym znajdziesz wszystkie swoje rezerwacje, karnety, faktury i informacje o swoich koniach. Stajnia, z której korzystasz, hostuje portal pod adresem `https://app.hovera.app/s/{slug-stajni}/portal`.

---

## 1. Logowanie (magic link)

Portal **nie używa hasła**. Logujesz się jednorazowym linkiem wysłanym na e-mail:

1. Wejdź na stronę logowania portalu — adres dostałeś od stajni (np. `https://app.hovera.app/s/stajnia-pegaz/portal/login`).
2. Wpisz adres e-mail (ten sam, który stajnia ma w Twojej karcie klienta).
3. Kliknij **"Wyślij link"**.
4. Sprawdź skrzynkę — w ciągu kilkunastu sekund dostaniesz wiadomość z linkiem.
5. Kliknij link w mailu → zostaniesz zalogowany na **30 dni**.

> **Link jest jednorazowy i ważny 30 minut.** Jeśli nie zdążysz go kliknąć, po prostu poproś o nowy.

### Nie dostaję maila — co robić?

- Sprawdź folder **Spam / Oferty / Promocje**.
- Upewnij się, że adres jest dokładnie taki sam jak w stajni (literówka = brak maila).
- Skontaktuj się ze stajnią — mogą skopiować link bezpośrednio z panelu i wysłać Ci go SMS-em.

---

## 2. Pulpit (dashboard)

Po zalogowaniu zobaczysz jeden ekran z wszystkimi sekcjami. Każda sekcja pojawia się tylko jeśli masz w niej dane.

### 2.1 Nadchodzące rezerwacje

Lista Twoich rezerwacji od dziś w przód, posortowana od najbliższej.

Każda pozycja pokazuje:
- **datę i godzinę** rozpoczęcia + długość lekcji,
- **status** (Oczekuje / Potwierdzona),
- **instruktora**, **konia**, **manaż**,
- przyciski akcji.

#### Akcje

- **Przesuń** — otwiera ekran zmiany terminu (tylko dla statusu *Potwierdzona*). Pokażemy najbliższe wolne sloty u tego samego instruktora; wybór = wysyłka prośby do stajni i mail do Ciebie.
- **Odwołaj** — otwiera bezpieczny formularz odwołania (link podpisany, ważny do startu rezerwacji).

> **Ważne:** akcje "Przesuń" i "Odwołaj" są dostępne tylko dla rezerwacji, które jeszcze się nie rozpoczęły.

### 2.2 Twoje karnety

Jeśli stajnia sprzedaje karnety (np. "10 lekcji"), zobaczysz tu listę aktywnych. Każdy karnet pokazuje:

- pozostałe wejścia (np. **7 / 10 pozostało**),
- pasek postępu,
- datę ważności,
- status (Aktywny / Wykorzystany / Wygasły).

Sekcja **"Ostatnio użyte"** pokazuje 5 ostatnich lekcji, na które zaznaczono wykorzystanie karnetu.

### 2.3 Historia rezerwacji

Lekcje, które już się odbyły, lub zostały odwołane / anulowane. Statusy:

- **Zakończona** — zajęcia się odbyły,
- **Odwołana** — odwołałeś / odwołała stajnia,
- **No-show** — nie pojawiłeś się bez odwołania.

### 2.4 Faktury do opłacenia

Jeśli stajnia wystawiła Ci faktury i są one nieopłacone, pojawią się tu z:

- numerem dokumentu,
- datą wystawienia + datą płatności (czerwonym tekstem, jeśli przeterminowana),
- kwotą do zapłaty.

Kliknięcie wiersza otwiera publiczny widok faktury (signed URL — bez logowania) z przyciskiem **"Zapłać teraz"**, jeśli stajnia ma skonfigurowaną bramkę płatności (Przelewy24 / PayU / Stripe / Mollie).

### 2.5 Wiadomości

5 najnowszych wiadomości z konwersacji o Twoich koniach. Pełna lista: link **"Wszystkie →"** w nagłówku sekcji.

### 2.6 Twoje konie

Konie, których jesteś właścicielem w tej stajni. Każdy wiersz pokazuje:

- imię, rasę, wiek,
- **plakietki zdrowia**:
  - 🔴 **X przeterm.** — X zaległych zaliczeń (szczepienia, kowal, dentysta) **wymaga akcji**,
  - 🟢 **X w 30 dni** — X zaliczeń zaplanowanych w ciągu miesiąca,
  - ⚪ **OK** — wszystko aktualne.

Przy nieprzeczytanych wiadomościach zobaczysz **plakietkę 📬 X nowych wiadomości**.

Kliknięcie wiersza → karta konia (sekcja 3).

---

## 3. Karta konia

Po kliknięciu w konia z dashboardu otwiera się jego pełna karta. Sekcje:

### 3.1 Dane podstawowe

- imię, rasa, maść, data urodzenia, płeć,
- mikrochip, numer paszportu, UELN,
- aktualny boks.

### 3.2 Pielęgnacja i zdrowie (timeline)

Szczepienia, wizyty kowala, dentysty:

- 🔴 **przeterminowane** — termin minął, należy umówić wizytę,
- 🟡 **nadchodzące w 30 dni** — można planować,
- 🟢 **aktualne** — kolejny termin > 30 dni.

Każda pozycja pokazuje datę ostatniej wizyty + sugerowany termin następnej.

### 3.3 Aktywności

Pasaż, ćwiczenia, padokowanie — wpisy z ostatnich 7 dni dodawane przez stajnię.

### 3.4 Wiadomości

Czat z stajnią dotyczący tego konia. Możesz:

- czytać historię wiadomości (od stajni i od Ciebie),
- napisać nową wiadomość (np. "Proszę dziś o pasaż przed lekcją"),
- załączyć **do 5 plików** (PDF/JPG/PNG, **max 10 MB każdy**).

Stajnia dostaje powiadomienie mailem; Ty również o ich odpowiedziach.

### 3.5 Dokumenty

Paszport, umowa, polisa OC, świadectwa — pliki PDF/JPG (max 25 MB).

Akcje:
- **Pobierz** każdy dokument,
- **Wgraj** nowy dokument (paszport, polisa…),
- **Usuń** dokument, który sam wgrałeś (dokumentów stajni nie usuniesz).

---

## 4. Przesuwanie rezerwacji

Klik **"Przesuń"** przy nadchodzącej rezerwacji → otwiera się ekran z:

- aktualnym terminem,
- listą **3-7 najbliższych wolnych slotów** u tego samego instruktora.

Wybierz preferowany slot → klik **"Wyślij prośbę"**:

1. Stajnia dostaje powiadomienie,
2. Ty dostajesz mail z potwierdzeniem,
3. Rezerwacja zostaje przeniesiona (status pozostaje *Potwierdzona*).

> Jeśli żaden slot nie pasuje, napisz do stajni przez sekcję **Wiadomości** w karcie konia lub bezpośrednio mailem.

---

## 5. Odwoływanie rezerwacji

Klik **"Odwołaj"** → otwiera signed URL (link kryptograficznie podpisany, ważny tylko do momentu rozpoczęcia lekcji).

Formularz pokazuje:
- szczegóły rezerwacji,
- pole **"Powód anulowania"** (opcjonalne, ale pomocne dla stajni).

Klik **"Potwierdź anulowanie"** → status zmienia się na *Odwołana*, stajnia dostaje powiadomienie.

> Anulowanie z dużym wyprzedzeniem zwykle zwraca wejście do karnetu. Anulowanie tuż przed lekcją może wiązać się z opłatą — zasady ustala stajnia.

---

## 6. Faktury

Po kliknięciu faktury z dashboardu otwiera się jej publiczny widok:

- pełne dane (sprzedawca, nabywca, pozycje, kwoty, VAT),
- przycisk **"Pobierz PDF"**,
- przycisk **"Zapłać teraz"** (jeśli stajnia ma bramkę).

### Płatność online

Klik **"Zapłać teraz"** → przekierowanie do bramki (BLIK / karta / przelew). Po opłaceniu wracasz na portal — status faktury zmienia się automatycznie po potwierdzeniu webhookiem.

> Jeśli płacisz tradycyjnym przelewem, użyj numeru rachunku i tytułu z faktury — stajnia oznaczy ją ręcznie po księgowaniu.

---

## 7. Wiadomości — pełna lista

Sekcja **"Wszystkie →"** otwiera ekran z wszystkimi wątkami z Twoimi końmi. Filtry: koń, nieprzeczytane.

Kliknięcie wątku przenosi do karty konia, sekcja Wiadomości.

---

## 8. Język portalu

Portal mówi w czterech językach: **polski / angielski / niemiecki / francuski**. Domyślnie używa języka stajni; jeśli przełączasz, preferencja zapisuje się w sesji.

> Nie ma osobnego switcha w portalu (jest w panelu pracownika); jeśli chcesz inny język, daj znać stajni — mogą zmienić ustawienie domyślne.

---

## 9. Bezpieczeństwo i prywatność

- **Logowanie magic-linkiem** = brak hasła do zapamiętania, brak hasła do wycieku.
- **Sesja 30 dni** — po tym czasie znowu wpisujesz e-mail.
- **Wylogowanie** — przycisk u góry ekranu po prawej.
- **Twoje dane** — widzisz tylko *swoje* rezerwacje, *swoje* konie, *swoje* faktury. Nawet jeśli stajnia ma 100 klientów, każdy widzi tylko swoje.

> Portal pokazuje tylko dane z tej jednej stajni. Jeśli korzystasz z kilku — każda ma własny URL portalu.

---

## 10. Najczęstsze problemy

| Problem | Co zrobić |
|---|---|
| Nie dostaję maila z linkiem | Sprawdź spam; potem poproś stajnię o ręczne wysłanie linku |
| Link wygasł / nie działa | Wpisz email jeszcze raz — wyślemy nowy |
| "Przesuń" nie pokazuje slotów | Instruktor może być niedostępny — napisz do stajni |
| Plakietka "X przeterm." nie znika | Stajnia musi zaznaczyć wykonanie wizyty kowala/szczepienia |
| Nie widzę faktury | Skontaktuj się ze stajnią — mogą wystawić ją ponownie |
| Załączniki >10 MB nie wgrywają się | Skompresuj zdjęcie / podziel PDF na części |

---

## 11. Wsparcie

- **Stajnia** — kontakt mailowy / telefoniczny widoczny na publicznej stronie stajni `https://app.hovera.app/s/{slug-stajni}`,
- **hovera (techniczne)** — `support@hovera.app`.

---

*Dokumentacja aktualizowana wraz z nowymi funkcjami portalu. Wersja systemu widoczna w stopce panelu administratora stajni.*
