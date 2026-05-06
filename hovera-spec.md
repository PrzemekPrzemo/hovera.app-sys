# Hovera — Specyfikacja produktu i wytyczne techniczne

> Dokument dla zespołu programistycznego budującego SaaS do zarządzania stajniami i ośrodkami jeździeckimi na rynek Polski i UE.
>
> **Wersja:** 1.0 (draft)
> **Status:** Pre-MVP, do uzgodnień architektonicznych
> **Język interfejsu:** PL (default), EN, DE, NL, IT, FR, ES — i18n od dnia 1
> **Hosting danych:** wyłącznie UE (RODO)

---

## 1. Wizja produktu

Hovera to wielodyscyplinarny SaaS do prowadzenia stajni i ośrodków jeździeckich w modelu B2B, świadomie projektowany jako **platforma operacyjna**, a nie kolejny "kalendarz dla szkółki". Łączymy w jednym systemie cztery konteksty, które konkurencja zwykle obsługuje osobno:

1. **Szkółka jeździecka / lekcje** — zapisy online, karnety, instruktorzy
2. **Pensjonat (livery) i opieka stała** — boksy, paszporty, opieka zdrowotna, rozliczenia z właścicielami
3. **Sport i trening** — programy treningowe, zawody, integracje sport (FEI/PZJ)
4. **Hodowla (breeding)** — klacze, ogiery, krycia, źrebięta, paszporty (PASB i in.)

Plus warstwa, której nikt w UE jeszcze nie ma porządnie:

- **AI Copilot** dla managera stajni (predykcje obciążenia koni, alerty zdrowotne, podsumowania tygodniowe)
- **E-invoicing-first** (KSeF dla PL, Peppol dla UE, ViDA-ready dla 2028)
- **Marketplace usług** (weterynarz, kowal, transport) z rezerwacją z poziomu systemu
- **Publiczny micro-site stajni** generowany automatycznie (lead generation jako wartość)

### 1.1 Tagline robocze

> "Lift the weight off your stable." — odciążenie administracji jako główny benefit.
>
> Polski wariant: *"Stajnia działa. Ty oddychasz."*

---

## 2. Persony i use case'y

### 2.1 Persony pierwotne (płacące)

| Persona | Skala | Kluczowy ból | Wartość Hovery |
|---------|-------|--------------|----------------|
| **Anna — właścicielka małej szkółki** | 1–8 koni, 30–80 klientów, 1–3 instruktorów | Telefon dzwoni przez cały dzień. Karnety w zeszycie. | Zapisy online + auto-rozliczenia. Mniej telefonów. |
| **Marek — manager średniego ośrodka** | 15–40 koni, livery + szkółka + sport, 5–15 pracowników | 3 systemy + Excel. Zawsze coś nie pasuje. Klienci wpłacają i nie wiadomo za co. | Jeden system, finanse, dziennik koni, raporty dla właścicieli. |
| **Karolina — trener sportowy** | 8–15 koni sport, 1–2 zawody/miesiąc | Programy treningowe w głowie. Brak historii. Pasporty FEI rozsypane. | Programy + historia + integracja FEI/PZJ. |
| **Tomasz — hodowca** | 5–20 klaczy + 1–3 ogiery, źrebięta | Krycia, USG, rejestry hodowlane, paszporty PASB | Moduł hodowli + auto-sync z PASB / Studbook |
| **Sieć stajni (X-Stables)** | 3–8 lokalizacji, white-label | Każdy oddział pracuje inaczej. Brak skonsolidowanego raportu. | Multi-location + white-label + skonsolidowane KPI. |

### 2.2 Persony wtórne (użytkownicy nie płacący bezpośrednio)

- **Klient szkółki / rodzic** — rezerwuje jazdę, kupuje karnet, widzi historię dziecka
- **Właściciel konia w pensjonacie** — widzi dziennik konia, rachunki, wyniki badań
- **Pracownik stajni (groom)** — zaznacza wykonane czynności (karmienie, sprzątanie, wyprowadzanie)
- **Weterynarz / kowal (zewnętrzny)** — dostaje zlecenie, wpisuje raport, fakturuje

### 2.3 Persona wewnętrzna (panel master admin)

- **Master Admin (Ty)** — widzisz wszystkie tenanty, MRR, churn, alerty health, możesz wcielić się w klienta na potrzeby supportu, wystawić fakturę, zablokować konto, włączyć feature flag

---

## 3. Analiza konkurencji

### 3.1 Mapa rynku — pełna tabela

| Produkt | Kraj | Typ | Mocne strony | Słabe strony | Cena (€/mies. lub odpowiednik) |
|---------|------|-----|--------------|--------------|--------------------------------|
| **Horstable** | PL | Szkółki jeździeckie | Mobile app (iOS/Android), wdrożenie 1:1, dobry UX, traction (1400+ jeźdźców) | Wąsko: tylko szkółka. Brak hodowli, sport sport, livery. Brak KSeF. Limity klientów per pakiet. | 0 / 79 / 179 / 299 PLN |
| **Nasza Stajnia** | PL | Szkółka + finanse | Polski język, SMS-y, automatyczne rozliczenia z kadrą, karnety | Lżejsza konkurencyjnie, brak międzynarodowego zasięgu | 60 / 120 PLN |
| **Equicty** | BE | All-in-one EU | AI assistant Hoofy, smart stable board (32" touchscreen), e-invoicing 2026 ready | Cena wysoka (enterprise feel), wymaga sprzętu, słabe na PL | ~50–150 EUR |
| **EquineM** | NL | Stable + EquiBoard | 10+ lat na rynku, multi-platform, EquiBoard hardware | UI archaiczne, brak AI, brak PL, brak KSeF | ~30–80 EUR |
| **Mosson Stable** | DK/SE | Skandynawia | EN/DK/SE/NO/DE, sport + racing | Brak polskiego, brak KSeF, słabe dla szkółki | ~40–100 EUR |
| **EC Pro** | UK | Riding school + booking | Booking-first, dobry przy szkółkach | Tylko UK + EN, brak EU compliance | ~£40–120 |
| **BarnManager** | US | Barn US | Dojrzały produkt, dobre raporty | US-centric, brak EU compliance | ~$30–80 |
| **StableSecretary** | US/CA | Health + billing | Dobry dla zdrowia + faktur | US, brak PL/EU compliance | ~$30 |
| **Stablebuzz** | CA/US | Riding school | Booking, e-signatures | Bardzo lokalny | ~$25–60 |
| **TLore** | UK | Race | Specjalizacja race | Niszowe | enterprise |
| **Paddock Pro** | US | Health + boarding | Mature | Stary stack | ~$30 |
| **Hippovibe** | EU | All-in-one | Modułowy | Niska rozpoznawalność | b/d |
| **EquineGenie** | US | Business | Bardzo szeroki zakres | Bardzo skomplikowany UI | jednorazowo $199+ |

### 3.2 Luki w rynku — gdzie Hovera wygrywa

Po przeglądzie 13+ konkurentów widać **wyraźne luki**:

1. **Nikt nie ma dobrze KSeF + Peppol + ViDA** — to jest gigantyczny moat dla rynku PL i UE 2026+. Mamy expertise z FaktuPilot.
2. **AI w branży jest na poziomie 2023** — Equicty Hoofy to chatbot na danych, nie copilot operacyjny. My możemy zrobić predykcyjne alerty.
3. **Mobile-first z trybem offline** — stajnie mają słabe wifi, większość konkurencji wymaga online. Nasza React Native + offline sync = bezpośrednia przewaga.
4. **Wielodyscyplinarność** — 90% konkurencji jest mocna w 1, max 2 segmentach. Hovera robi 4 (szkółka + livery + sport + breeding).
5. **Otwarte API i webhooki** — żaden polski i większość EU nie ma porządnego public API. To moat dla integratorów i dużych klientów.
6. **Public micro-site + lead gen** — Horstable ma tylko zapisy dla istniejących klientów. My dajemy stajni publiczną stronę z online booking dla nowych = realna wartość marketingowa.
7. **Marketplace usług** — nikt nie buduje sieci weterynarz/kowal/transport. To długoterminowa fosa i drugi przychód.

### 3.3 Analiza Horstable szczegółowo

To główny gracz, którego trzeba pokonać na rynku PL. Co robi dobrze:

- **Wdrożenie 1:1** — kosztowne, ale buduje lojalność. Musimy mieć też dedykowany onboarding.
- **Mobile app jest** w App Store i Google Play — my musimy mieć tak samo.
- **Pricing entry-free** (0 zł starter) — to jest ich akwizycyjny hak. Musimy mieć równie agresywne wejście.
- **Treść SEO** o zapisach online dla szkółek — dominują wyniki wyszukiwań.

Co robią słabo (gdzie atakujemy):

- **Tylko szkółki** — pomijają livery (pensjonat) i hodowlę. Stajnie z 30+ końmi w pensjonacie nie mają oferty.
- **Brak modułu zdrowia konia** (szczepienia, kowal, weterynarz) — odsyła do innych narzędzi.
- **Brak finansów dla właściciela konia** — boarder nie widzi swoich rachunków.
- **Limity klientów per pakiet** — penalizacja sukcesu klienta.
- **Brak KSeF** — od 2026 to absolutny dealbreaker dla polskich firm.
- **Brak API i integracji** — księgowość ręcznie.

---

## 4. Wymagania funkcjonalne — moduły

Notacja: **[MVP]** = w pierwszej wersji, **[v2]** = drugi kwartał, **[v3]** = później.

### 4.1 Core: Konie

Moduł **Horse Records** — fundament wszystkiego.

- **[MVP]** Profil konia (imię, rasa, płeć, data ur., maść, microchip, paszport, właściciel)
- **[MVP]** Galeria zdjęć konia
- **[MVP]** Historia zdrowia: szczepienia, odrobaczania, dentysta, kowal
- **[MVP]** Dziennik konia (timeline) — każde zdarzenie (jazda, zabieg, szczepienie, notatka) jest wpisem w jednym strumieniu
- **[MVP]** Powiadomienia / przypomnienia (np. szczepienie za 30 dni)
- **[v2]** Pomiary biometryczne (waga, BCS, obwód brzucha, tętno spoczynkowe)
- **[v2]** Genealogia (matka, ojciec, rodzeństwo)
- **[v3]** AI: detekcja anomalii w dzienniku (gwałtowny spadek apetytu, częste kulawizny → flag)

### 4.2 Core: Kalendarz i grafik

- **[MVP]** Multi-resource calendar: konie × instruktorzy × ujeżdżalnie × klienci
- **[MVP]** Widok dzienny / tygodniowy / miesięczny / per koń / per instruktor / per klient
- **[MVP]** Drag & drop modyfikacje
- **[MVP]** Wydarzenia cykliczne (szkółka co poniedziałek 17:00)
- **[MVP]** Konflikty — system blokuje podwójne rezerwacje konia/instruktora
- **[MVP]** Eksport ICS / Google Calendar / Outlook
- **[v2]** Heatmap obciążenia konia (ile godzin pracy/tydzień, alert >12h)
- **[v2]** AI auto-scheduling (sugeruje terminy dla klientów na bazie dostępności i preferencji)

### 4.3 Core: Klienci i karnety

- **[MVP]** Baza klientów + dane kontaktowe + RODO consent
- **[MVP]** Rodzice/opiekunowie dziecka jako osobny typ
- **[MVP]** Karnety (X jazd, ważne do daty Y, transferowalne lub nie)
- **[MVP]** Auto-decrement karnetu po zaplanowanej jeździe (z możliwością cofnięcia gdy odwołanie)
- **[MVP]** Polityka odwołań (np. min. 12h przed = bez konsekwencji)
- **[MVP]** Regulaminy podpisywane online (e-signature, prosty checkbox + IP/timestamp + PDF)
- **[v2]** Karnety rodzinne (matka + dziecko współdzielą)
- **[v2]** Subskrypcje zamiast karnetów (8 jazd / miesiąc, auto-recurrence)
- **[v3]** Programy lojalnościowe (X jazd → bonus)

### 4.4 Core: Zapisy online (B2C portal)

- **[MVP]** Publiczny mini-site stajni — `hovera.app/{stajnia-slug}` lub własna domena
- **[MVP]** Klient widzi wolne terminy, wybiera, płaci (Stripe/Przelewy24/Tpay)
- **[MVP]** SMS/email potwierdzający + przypominajki
- **[MVP]** Klient ma swoje konto z historią jazd, fakturami, karnetami
- **[v2]** Recenzje stajni (publiczne) — buduje SEO
- **[v2]** Marketplace stajni: katalog Hovera dla klientów szukających szkółki

### 4.5 Livery / pensjonat

- **[MVP]** Boksy / stanowiska — przypisywanie konia do boksu
- **[MVP]** Umowa boardingu (cena/miesiąc, zakres usług, opcje dodatkowe)
- **[MVP]** Auto-rozliczanie miesięczne (cykliczne faktury)
- **[MVP]** Portal właściciela konia: dziennik konia, wpłaty, faktury, kontakt z managerem
- **[v2]** Zlecanie usług dodatkowych przez właściciela (kowal, weterynarz, masaż)
- **[v2]** Dyżury i checklisty (rano: karmienie, woda, sprzątanie boksu — wszystko z czek-in QR)
- **[v3]** Pasture management (rotacja koni na pastwiskach, czas wypasu)

### 4.6 Sport i trening

- **[MVP]** Programy treningowe (plan tygodniowy/miesięczny per koń)
- **[MVP]** Logi treningu (typ, intensywność, czas, notatki, video link)
- **[MVP]** Cele i postępy (treningowe + zawody)
- **[v2]** Integracja z FEI Database (paszporty FEI, kategorie)
- **[v2]** Integracja z PZJ (Polski Związek Jeździecki) — import starty zawodników
- **[v2]** Plan zawodów (wyjazdy, opłaty startowe, transport, hotele)
- **[v3]** Power BI wyników (analiza per dyscyplina, per koń, trendy)

### 4.7 Hodowla (breeding)

- **[v2]** Klacze: cykle, USG, krycia, ciąże, źrebienia
- **[v2]** Ogiery: dostępność do krycia, kalendarz, kontrakty
- **[v2]** Embryo transfer i AI (sztuczne unasiennianie) workflow
- **[v2]** Źrebięta: rozwój, paszport, identyfikacja
- **[v3]** Integracja z PASB (Polish Arabian Stud Book), KWPN (Holandia), Hannoveraner i inne
- **[v3]** Marketplace źrebiąt na sprzedaż

### 4.8 Zdrowie i opieka weterynaryjna

- **[MVP]** Historia weterynaryjna konia
- **[MVP]** Szczepienia z auto-przypomnieniami
- **[MVP]** Odrobaczania, dentysta, kowal — kalendarze i alerty
- **[v2]** Marketplace weterynarzy / kowali — booking z poziomu systemu
- **[v2]** Portal dla weterynarza: widzi konie do których ma dostęp, wpisuje raport, fakturuje
- **[v3]** AI screening: na bazie wpisów ostrzega o powtarzających się symptomach (np. "ten koń trzeci raz w 3 miesiące ma kolkę — sugerujemy konsultację")

### 4.9 Finanse i fakturowanie

- **[MVP]** Cennik usług (stawki za jazdę indywidualną/grupową, boardin, dodatki)
- **[MVP]** Generowanie faktur z faktycznych zdarzeń (jazdy + boarding + usługi)
- **[MVP]** **KSeF integracja** (Polska, Type 2 cert., zgodność z 2026 mandate)
- **[MVP]** Faktury w PDF i wysyłka mailem
- **[MVP]** Tracking wpłat (manualny + auto przez Stripe/Przelewy24)
- **[MVP]** Raport zaległości
- **[v2]** **Peppol** dla UE
- **[v2]** Belgia 2026 e-invoicing B2B
- **[v2]** Niemcy XRechnung 2025-2027
- **[v2]** Francja PDP 2026
- **[v2]** Eksport do księgowości: iFirma, Wfirma, Comarch ERP, Datev (DE)
- **[v3]** ViDA-ready (2028 EU mandate)

### 4.10 Pracownicy i grafik kadry

- **[MVP]** Lista pracowników, role, stawki
- **[MVP]** Grafik pracy + zaplanowane jazdy / opieka
- **[MVP]** Auto-rozliczenie wynagrodzenia z grafiku (np. instruktor 80 zł/jazda × 32 jazdy)
- **[v2]** Czas pracy z aplikacji mobilnej (clock-in / clock-out z geofencingiem)
- **[v2]** Eksport do programów kadrowych (Symfonia, Optima, Datev Lohn)
- **[v3]** Zarządzanie urlopami i nieobecnościami

### 4.11 Komunikacja

- **[MVP]** Powiadomienia push (mobile)
- **[MVP]** Email (transactional przez Postmark/Resend)
- **[MVP]** SMS (SMSAPI.pl dla PL, Twilio dla EU)
- **[MVP]** Wewnętrzny chat per koń (manager ↔ właściciel)
- **[v2]** Mass mailing do klientów stajni (newsletter, oferty)
- **[v2]** WhatsApp Business API integration (kluczowe dla PL/IT/ES)

### 4.12 AI Copilot — różnicownik

Moduł **Hovera AI**, oparty na danych stajni:

- **[v2]** Tygodniowy raport managera: anomalie + sugestie
- **[v2]** Predykcja obciążenia koni (alert: koń X będzie przeciążony w przyszłym tygodniu)
- **[v2]** Wyłapywanie no-show patterns u klientów (sugeruje mailowanie / blokadę online)
- **[v2]** Smart-templating maili i SMS (zasugeruj odpowiedź klientowi)
- **[v3]** Vision AI — analiza zdjęć konia (BCS estimation, podstawowe symptomy lameness)
- **[v3]** Trening Copilot — sugeruje progresję ćwiczeń per koń

Architektura AI: **on-demand, nie real-time**. LLM (Claude / GPT-4 class) z RAG na danych tenanta. **Zero training na danych klienta** — to musi być zapisane w ToS i brand promise.

---

## 5. Master Admin Panel — specyfikacja

To Twój panel jako operatora SaaS. Osobna aplikacja (`admin.hovera.app`), osobna baza uprawnień, osobny audit log.

### 5.1 Tenant Management

- Lista wszystkich tenantów (stajni)
- Każdy tenant: nazwa, plan, MRR, data utworzenia, ostatnia aktywność, status (active/trialing/suspended/churned)
- Filtry: by plan, by kraj, by stage, by health score
- Drill-down do tenanta: stats, billing history, audit log, support tickets
- **Impersonation** (login as tenant admin) — z pełnym audit logiem każdej akcji
- Manualne akcje: zmiana planu, dodanie kredytów, suspend, delete (z 30-dniowym soft delete)

### 5.2 Subskrypcje i billing

- Stripe Customer Portal podpięty do każdego tenanta
- Manualne fakturowanie (dla klientów wymagających przelewów tradycyjnych)
- Automated dunning (sekwencja przypominajek przy nieopłaconych fakturach)
- MRR / ARR dashboard z breakdown per plan, per kraj, per cohort
- Churn analysis: who churned, why, recovered/not
- Trial conversion funnel
- Coupon / discount management
- Tax handling: VAT EU, OSS rejestracja

### 5.3 Health monitoring per tenant

- **Health score** liczony z heurystyk:
  - Ostatnie logowanie < 7 dni: +30
  - Aktywni użytkownicy w stajni > 50% seats: +20
  - Liczba transakcji w miesiącu rośnie: +20
  - Brak supportów krytycznych w 30 dni: +15
  - Karta płatnicza ważna: +15
  - Score < 50 = at-risk
- Auto-flag dla customer success
- Email alerts dla Ciebie gdy duży klient spadnie poniżej threshold

### 5.4 Feature flags

- Per tenant feature flag overrides (np. AI Copilot dla wczesnych klientów beta)
- Per kraj feature flags (np. Peppol tylko dla EU)
- Kill-switch dla feature przy problemach (turn off AI w razie awarii LLM)
- Postupowy rollout (10% → 50% → 100%)

### 5.5 Support tools

- Lista ticketów z każdego tenanta (jeśli używamy Intercom/Crisp/własne)
- Quick action: utworzyć usera, reset password, refund, comp credits
- Knowledge base management
- Status page management (uptime.com / Atlassian Statuspage)

### 5.6 System config

- Globalne: stawki SMS per kraj, koszt API LLM, koszt storage
- Quota management (max horses per plan, max API calls, max storage)
- Plan management (CRUD planów, ich features, ich cen)
- Email templates per język (z preview)
- Cron jobs status (importy z FEI/PZJ, cleanup, billing runs)

### 5.7 Analytics i raporty

- Cohort retention (D1/D7/D30/D90)
- Time to first value (TTFV) — średni czas od signup do pierwszej "real" akcji
- Feature adoption per plan
- API usage per tenant (kto bije rate limity)
- Cost analysis: profit margin per tenant (revenue – LLM cost – SMS cost – storage cost)

### 5.8 Audit log master admina

- **Każda akcja master admina jest logowana** (kto, kiedy, co, dla którego tenanta)
- Logi niemodyfikowalne (append-only, podwójny store w S3 + Postgres)
- Eksport dla audytów (RODO, security review)

### 5.9 Marketplace administracja (jeśli/gdy uruchamiamy)

- Vendor onboarding (weterynarze, kowale, transport)
- Verification (uprawnienia, ubezpieczenie)
- Commission settings (np. 10% z bookingu)
- Dispute resolution

---

## 6. Wymagania niefunkcjonalne (NFR)

### 6.1 Performance

- p50 API < 150ms, p95 < 500ms, p99 < 1.5s
- Page load (LCP) < 2.5s na 3G simulated
- Mobile app cold start < 3s
- DB queries: każda wymagająca > 100ms loguje warning
- Time to interactive dla web < 3s

### 6.2 Skalowalność

- Architektura ma obsłużyć 10k tenantów / 1M użytkowników bez przepisywania (target 3-letni)
- DB partitioning per tenant na hot tables (events, calendar entries) gotowe od dnia 1
- Stateless app servers, scale horizontal
- CDN dla assets (Cloudflare)

### 6.3 Dostępność (SLA)

- Target uptime 99.9% (43 min downtime / miesiąc)
- Multi-AZ deployment (Hetzner Cloud Frankfurt + fallback Helsinki, lub OVH)
- Database failover automatyczny
- Disaster recovery: RPO < 1h, RTO < 4h

### 6.4 Bezpieczeństwo

- **OWASP Top 10** — checklista przy każdej feature
- TLS 1.3 wszędzie, HSTS, CSP nagłówki
- Hashowanie haseł: argon2id
- MFA obowiązkowe dla master admina, opcjonalne dla tenant admina
- Rate limiting: per IP, per user, per tenant
- DDoS protection (Cloudflare)
- Pen-test przed publicznym launch i co 12 miesięcy
- Bug bounty program po 6 miesiącach od launchu
- Secrets management: HashiCorp Vault albo Doppler
- **Zero-trust** wewnętrznych dostępów (każde API przez auth, nawet wewnętrzne mikroserwisy)

### 6.5 Audit & monitoring

- Sentry (errors)
- Better Stack / Grafana Cloud (logs + metrics + uptime)
- OpenTelemetry tracing
- Audit log per tenant: kto, kiedy, co zmienił (każda zmiana w core entities — koń, klient, faktura, karnet)
- PII access log (kto wyświetlił dane osobowe klienta)

### 6.6 Backupy

- DB: continuous WAL streaming + daily snapshot + 30-day retention
- Storage (S3): versioning + cross-region replication
- Restore drill co kwartał (testujemy że backup działa)

### 6.7 Accessibility

- WCAG 2.1 AA compliance — wymóg prawny w UE (European Accessibility Act 2025)
- Testy z screen reader (NVDA / VoiceOver) per major release
- Kontrasty ≥ 4.5:1
- Keyboard navigation pełna

### 6.8 i18n / l10n

- Każdy string przez i18n (next-intl albo react-i18next)
- Pluralizacja (ICU MessageFormat)
- Date/time/number formats per locale (date-fns + Intl API)
- Waluty per kraj (PLN/EUR/CHF/CZK/HUF)
- Strefy czasowe — wszystko w UTC w bazie, prezentacja w TZ użytkownika
- RTL future-proof (na razie nie wspieramy ale nie blokujemy)

---

## 7. Architektura techniczna

### 7.1 High-level

```
                          ┌─────────────────────┐
                          │    Cloudflare CDN   │
                          │   (DDoS, WAF, edge) │
                          └──────────┬──────────┘
                                     │
              ┌──────────────────────┴──────────────────────┐
              │                                             │
    ┌─────────▼─────────┐                          ┌────────▼──────────┐
    │   Web App (PWA)   │                          │   Mobile App      │
    │   Next.js 15      │                          │   React Native    │
    │   app.hovera.app  │                          │   (Expo)          │
    └─────────┬─────────┘                          └────────┬──────────┘
              │                                             │
              └──────────────────┬──────────────────────────┘
                                 │
                       ┌─────────▼──────────┐
                       │   API Gateway      │
                       │   (rate limit,     │
                       │   auth, routing)   │
                       └─────────┬──────────┘
                                 │
        ┌────────────────────────┼─────────────────────────┐
        │                        │                         │
   ┌────▼──────┐         ┌───────▼────────┐        ┌──────▼─────────┐
   │ Core API  │         │  Worker Queue  │        │   AI Service   │
   │  NestJS   │         │  BullMQ/Redis  │        │   (LLM proxy)  │
   └────┬──────┘         └───────┬────────┘        └──────┬─────────┘
        │                        │                        │
        └────────────┬───────────┴────────────────────────┘
                     │
        ┌────────────┼─────────────┐
        │            │             │
   ┌────▼─────┐ ┌────▼─────┐  ┌───▼──────┐
   │ Postgres │ │  Redis   │  │  S3 / R2 │
   │ (RLS)    │ │ (cache,  │  │ (files,  │
   │          │ │  queues) │  │  backup) │
   └──────────┘ └──────────┘  └──────────┘
```

### 7.2 Stack — rekomendacje

| Warstwa | Wybór | Uzasadnienie |
|---------|-------|--------------|
| **Web frontend** | Next.js 15 (App Router) + React 19 + TypeScript + Tailwind + shadcn/ui | Mature, SSR/ISR dla micro-sites stajni (SEO), świetne DX |
| **Mobile** | React Native (Expo SDK 52+) + TypeScript | Współdzielony kod z webem, OTA updates, dobra wydajność |
| **Backend API** | NestJS (TypeScript) — modular, opinionated, mature | Alternatywa: .NET 8 jeśli preferujesz po doświadczeniach z KSeF C# |
| **Background jobs** | BullMQ (Redis) | Sprawdzony, dobra observability |
| **Database** | PostgreSQL 16 + Row Level Security | Multi-tenancy, JSON, full-text search, partitioning |
| **Cache / queues** | Redis 7 | Standard |
| **Search** | PostgreSQL FTS na start, Meilisearch przy ~1k tenantów | Tańsze niż Elastic, dobre dla MVP |
| **Storage** | Cloudflare R2 (S3-compatible, brak egress fees) lub OVH Object Storage | Tańsze niż AWS S3 dla użycia EU |
| **Hosting** | Hetzner Cloud (Frankfurt + Helsinki) lub OVH (Gravelines) | EU residency, ~3-4× tańsze niż AWS, 100% RODO compliant |
| **DB managed** | Neon (Postgres serverless) lub Supabase lub managed na OVH/Hetzner | Neon = świetny dla wielu tenantów, branching |
| **Email transactional** | Postmark albo Resend | Niski bounce rate, dobre webhooks |
| **SMS** | SMSAPI.pl (PL), Twilio (EU/global) | SMSAPI tańsze 3× dla PL |
| **Payments** | Stripe (EU + global) + Przelewy24 lub Tpay (PL fallback) | Stripe = najlepszy DX, ale 1.4%+0.25 EUR — dla PL Przelewy24 ma BLIK |
| **Auth** | Better-Auth lub Lucia + własne JWT/refresh | NextAuth wystarczy ale Better-Auth ma lepsze RBAC |
| **Monitoring** | Sentry (errors) + Better Stack (logs/uptime) + Grafana Cloud (metrics) | Dobry stack za <€100/mc na start |
| **CI/CD** | GitHub Actions + auto-deploy do Hetzner przez Coolify lub Dokku | Tanie, kontrolujemy infrastrukturę |
| **Infra as Code** | Terraform + Pulumi (jeśli preferowane TypeScript) | Standard |
| **Secrets** | Doppler (SaaS) lub HashiCorp Vault (self-host) | Doppler na MVP, Vault gdy >5 deweloperów |
| **API docs** | OpenAPI 3.1 auto-gen z NestJS + Scalar lub Stoplight | Public dokumentacja API od dnia 1 |

### 7.3 Multi-tenancy

**Strategia: shared database, shared schema, tenant_id na każdym wierszu + Postgres Row Level Security (RLS).**

Powody:

- Tańsze niż schema-per-tenant (mniej migracji do utrzymania)
- RLS daje izolację na poziomie DB (nawet bug w aplikacji nie wycieknie danych innego tenanta)
- Łatwy backup i restore
- Skala do 100k tenantów bez problemu

Implementacja:

- Każda tabela z danymi tenanta ma kolumnę `tenant_id UUID NOT NULL`
- RLS policy: `tenant_id = current_setting('app.current_tenant')::uuid`
- API ustawia `app.current_tenant` w transakcji per request
- Master admin tabele (tenants, billing) są poza RLS, dostępne tylko z dedykowanego service role

Edge case: tabele cross-tenant (np. integracje publiczne, marketplace) projektowane świadomie z innym schematem.

### 7.4 Database schema — kluczowe encje

```sql
-- Tenants (stajnie)
tenants (
  id UUID PK, slug TEXT UNIQUE, name TEXT, country CHAR(2),
  plan TEXT, mrr_cents INT, created_at, status TEXT,
  branding_json JSONB, settings_json JSONB
)

-- Users (każdy człowiek; może należeć do wielu tenantów z różnymi rolami)
users (id UUID PK, email UNIQUE, password_hash, mfa_secret, locale, ...)
tenant_memberships (tenant_id, user_id, role, permissions_json)

-- Konie
horses (
  id UUID PK, tenant_id, name, microchip, passport_number,
  birth_date, breed, sex, color,
  owner_user_id NULL, -- może być właścicielem klient lub stajnia
  metadata_json JSONB
)

-- Wpisy do dziennika konia (timeline)
horse_events (
  id UUID PK, tenant_id, horse_id, type TEXT, -- ride/vet/farrier/feed/note/health
  occurred_at TIMESTAMPTZ, created_by UUID,
  payload_json JSONB, attachments_json JSONB
)

-- Klienci stajni (mogą ale nie muszą być user)
clients (id UUID PK, tenant_id, type, name, phone, email, user_id NULL)

-- Karnety
passes (
  id UUID PK, tenant_id, client_id, plan TEXT,
  total_uses INT, remaining INT, valid_until DATE,
  policy_json JSONB
)

-- Kalendarz
calendar_entries (
  id UUID PK, tenant_id, kind TEXT, -- lesson/training/care/event
  starts_at TIMESTAMPTZ, ends_at,
  horse_id NULL, instructor_id NULL, arena_id NULL, client_id NULL,
  status TEXT, -- requested/confirmed/cancelled/completed
  metadata_json JSONB
)

-- Faktury
invoices (
  id UUID PK, tenant_id, client_id, number TEXT,
  amount_cents INT, currency CHAR(3),
  ksef_status TEXT, ksef_uuid TEXT,
  peppol_status TEXT,
  pdf_url TEXT, issued_at, due_at, paid_at NULL
)

-- I tak dalej dla: arenas, instructors, employees, services_catalog,
-- payments, breeding_records, training_programs, vendors_marketplace, ...
```

Każda tabela: `created_at`, `updated_at`, `deleted_at` (soft delete).

### 7.5 API design

- **REST + JSON:API** convention (lub własny consistent style)
- **OpenAPI 3.1** auto-generowany
- **Versioning** w nagłówku `Hovera-API-Version: 2026-01-15` (date-based, jak Stripe)
- **Pagination**: cursor-based (nie offset)
- **Rate limiting** per API key: 60/min default, 600/min Pro, 6000/min Enterprise
- **Idempotency keys** dla wszystkich POST/PUT
- **Webhooks** z HMAC signature
- **GraphQL** rozważyć dla v3 jeśli klienci enterprise chcą

### 7.6 Mobile + offline-first

To kluczowy differentiator. Architektura:

- **Local-first DB** w aplikacji (SQLite via WatermelonDB lub PowerSync lub TinyBase)
- Każda akcja zapisana lokalnie + flag "needs_sync"
- Sync engine: gdy network OK, push do API, pull zmian innych
- Konflikt resolution: last-write-wins per pole, z manualnym review opcjonalnie
- Praktyka: groom może w stajni bez wifi zaznaczać karmienia, sync gdy wróci na podwórko

---

## 8. Compliance i regulacje

### 8.1 RODO / GDPR

- DPA (Data Processing Agreement) gotowy przy onboardingu
- Privacy policy generator dla każdego tenanta (oni są data controller, my processor)
- Data subject requests: export własnych danych, delete (right to be forgotten)
- Retention policies konfigurowalne per tenant (default: 7 lat dla finansów, 3 lata dla dziennika konia)
- DPO procedure
- Subprocessors list publiczna i aktualizowana
- Data residency: **EU only**, gwarancja w ToS

### 8.2 E-invoicing

- **Polska KSeF**: Type 2 cert (api.ksef.mf.gov.pl), obsługa FA(2), batch sending, status sync — full FaktuPilot expertise reuse
- **Belgia 2026**: Peppol BIS Billing 3.0 (od 1 stycznia 2026 obowiązkowe B2B)
- **Niemcy 2025-2027**: XRechnung / ZUGFeRD (faza 1 receive od 2025, faza 2 send od 2027)
- **Francja PDP** (Plateforme de Dématérialisation Partenaire): rollout 2026-2027
- **Włochy SDI**: Sistema di Interscambio (już obowiązkowe)
- **ViDA 2028**: pan-EU mandate na e-invoicing — nasz stack musi być ready

### 8.3 Sport / hodowla

- **FEI Database**: read-only API, import paszportów koni sport
- **PZJ**: integracja z systemem, widzimy starty zawodników (jeśli udostępnią API; alternatywnie scraping z udostępnieniem)
- **PASB / Studbooks**: import paszportów hodowlanych
- **microchip readers**: BLE, ISO 11784/11785

### 8.4 Inne

- **Accessibility EAA 2025** (WCAG 2.1 AA)
- **ePrivacy / cookies**: cookie banner zgodny, ConsentManager
- **AML/KYC** dla marketplace (vendor verification): Stripe Connect handles to
- **PSD2** dla płatności: Stripe handles SCA

---

## 9. Cennik — propozycja go-to-market

Pricing musi być agresywniejszy niż Horstable na entry, ale mocniej premium na top.

| Plan | Cena | Limity | Target |
|------|------|--------|--------|
| **Free** | 0 zł | 5 koni, 10 klientów, brak online booking | Akwizycja, próbują przed kupnem |
| **Solo** | 49 PLN / mies. | 10 koni, 30 klientów, online booking, mobile app | Mały instruktor, 1-osobowa szkółka |
| **Stable** | 149 PLN / mies. | 30 koni, 100 klientów, karnety, wszystkie podstawy | Średnia szkółka |
| **Pro** | 349 PLN / mies. | 100 koni, bez limitu klientów, finanse, KSeF, livery | Pełnowymiarowy ośrodek |
| **Enterprise** | od 999 PLN / mies. | bez limitów, white-label, multi-location, SSO, custom SLA, AI Copilot, dedicated support | Sieci, kluby sportowe, hodowle |

Add-ony:

- SMS pack: 0,12 zł/SMS po pakiecie
- AI Copilot (na razie addon, nie w base): +99 zł/mies. dla Pro
- Custom domain (publiczny micro-site na własnej domenie): +29 zł/mies.
- Dodatkowe seats pracowników po przekroczeniu limitu

Roczna płatność: -20% (standard SaaS).

---

## 10. Roadmapa — fazy

### 10.1 MVP (3-4 miesiące)

Cel: pokazać wartość dla **szkółki + małego ośrodka** (segment Anny i Marka). Wystarcza żeby konkurować z Horstable Mini/Midi.

- Konie (basic profil + dziennik)
- Kalendarz multi-resource
- Klienci + karnety
- Zapisy online + portal klienta
- Faktury + KSeF
- Mobile app (iOS/Android) — read + basic actions
- Stripe + Przelewy24
- Email + SMS notifications
- Public micro-site stajni (basic)
- Master admin panel: tenant CRUD, billing, audit log, basic analytics

### 10.2 v2 (4-7 miesięcy)

Cel: dorównać Equicty, wyprzedzić Horstable na premium segmencie. Wjazd na rynek BE/NL/DE.

- Livery / pensjonat moduł
- Sport / training programy
- Health & vet records
- AI Copilot v1 (raporty tygodniowe + alerty obciążenia)
- Peppol (BE + DE)
- Eksporty do księgowości
- Marketplace usług (beta — vet/farrier)
- Mobile offline-first
- White-label dla Enterprise

### 10.3 v3 (7-12 miesięcy)

Cel: być liderem regionalnym. Hodowla i sport ekspansja.

- Hodowla pełna (klacze, ogiery, krycia, źrebięta)
- Integracje FEI, PZJ, PASB
- AI Copilot v2 (vision, smart-templating, training copilot)
- Marketplace pełen (transport, hotele, pasze)
- ViDA-ready
- Public API z dev portalem

---

## 11. KPI sukcesu

### 11.1 Akwizycja

- Trial-to-paid conversion: target 25% (industry SaaS B2B = 15-20%)
- CAC payback: <12 miesięcy
- Time to first value (signup → pierwsza zaplanowana jazda lub karnet): <2 dni

### 11.2 Retencja

- Logo retention 12mc: >90% (nisza B2B)
- Net Revenue Retention: >110%
- Monthly churn: <2%

### 11.3 Engagement

- DAU/MAU > 50% dla pakietów Stable+
- Mobile share: >40% wszystkich akcji w 12 miesięcy

### 11.4 Finansowe

- LTV/CAC > 3x w 12 miesięcy
- Gross margin > 75%
- ARR po roku: 500k PLN, po 2 latach 2M PLN, po 3 latach 6M PLN

---

## 12. Decyzje do podjęcia (open questions)

Lista rzeczy do rozstrzygnięcia z Tobą zanim zespół zacznie kodować:

1. **Backend framework: NestJS (TS) czy .NET 8 (C#)?**
   - NestJS = jeden stack z frontem, łatwiej hire web devs
   - .NET = wykorzystanie Twojego doświadczenia z KSeF
2. **Hosting: Hetzner czy OVH czy AWS Frankfurt?**
   - Hetzner: najtańszy, świetny perf, ale brak managed services
   - OVH: polski-friendly, RODO+++, ale słabsze API
   - AWS: enterprise-friendly, drogie
3. **Mobile: React Native (Expo) czy native (Swift + Kotlin)?**
   - RN = 1 kodowiec, szybciej; native = lepszy performance dla offline DB
4. **Marketplace usług: w MVP czy v2?**
   - W MVP daje WOW factor ale 3× komplikacja. Rekomenduję v2.
5. **AI: budować in-house z Claude API czy dedicated z fine-tuned modelem?**
   - In-house = łatwo, niskie koszty na start; fine-tune = za 12 miesięcy gdy będzie data
6. **White-label: w MVP czy v2?**
   - V2. W MVP zbiera czas. Ale jeśli Enterprise klient zażąda, można negocjować.
7. **Współpraca z PZJ / FEI: czy rozmawiamy z nimi już teraz?**
   - Tak — FEI Database ma public API (płatne), PZJ trzeba pisać. To 3-6 miesięcy procesu.
8. **Monetyzacja marketplace: 10% commission czy lead-fee?**
   - Commission lepsze long-term ale komplikuje compliance (Stripe Connect). Lead-fee prostsze na start.
9. **Open Source: część stack open-source czy zamknięty?**
   - Rekomenduję core zamknięty, ale SDK do API + mobile open source dla dev community.
10. **Strategia językowa: polski first + 5 języków UE jednocześnie czy phased?**
    - Phased: PL → EN (lingua franca + UK) → DE → NL → FR → IT → ES. Każdy nowy język = miesiąc pracy QA + tłumaczenia.

---

## 13. Anti-pattern — czego NIE robić

Wyniesione z analizy konkurencji:

- ❌ Nie ograniczamy klientów per pakiet (jak Horstable). Limity są na koniach + seats pracowników, nie klientach końcowych. Klient = revenue dla naszego klienta = nie penalizujemy sukcesu.
- ❌ Nie wymagamy hardware (jak Equicty z ich Smart Stable Board). Wszystko działa na phone/tablet/web, hardware integration opcjonalna.
- ❌ Nie robimy "horse-management-only" jak Stable Secretary. Finanse i KSeF są core, nie addonem.
- ❌ Nie kopiujemy archaicznego UI (EquineM). Mamy nowy stack, nowe wzorce, AI-native UX.
- ❌ Nie zostajemy single-language jak Mosson Stable czy EC Pro.
- ❌ Nie ignorujemy mobile (jak EquineGenie).
- ❌ Nie traktujemy AI jako gimmick chatbot (jak Equicty Hoofy). AI ma realnie obniżać workload.

---

## 14. Załączniki — co dostarczam później

Dla każdego z poniższych tematów planuje powstać osobny dokument:

- `02-database-schema.md` — pełna schema z wszystkimi indexami, constraintami, RLS policies
- `03-api-design.md` — pełna OpenAPI spec z przykładami
- `04-master-admin.md` — wireframes + flow dla każdej strony admin panelu
- `05-mobile-architecture.md` — offline sync, local DB, conflict resolution
- `06-ksef-integration.md` — implementacja KSeF (reuse z FaktuPilot z dostosowaniem)
- `07-ai-copilot.md` — prompts, RAG architecture, model selection, cost forecast
- `08-security-checklist.md` — OWASP, pen-test, compliance checklists
- `09-design-system.md` — Hovera brand applied do UI (kolory, typografia, komponenty)
- `10-go-to-market.md` — content strategy, SEO plan, paid ads, partnerships

---

## Stopka

**Owner produktu:** Przemek
**Last updated:** 2026-05-06
**Następny review:** po decyzjach z sekcji 12

> Ten dokument jest **żywy**. Każda zmiana priorytetu / scope w trakcie budowy = update tego pliku w repo + changelog na końcu.
