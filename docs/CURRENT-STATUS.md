# Hovera — stan bieżący (źródło prawdy)

> Data: 2026-07-05 · Zastępuje/koryguje: `ROADMAP.md`, `FEATURES.md`, `MARKETPLACE-ROADMAP.md`,
> `OWNER-STABLE-ROADMAP.md`, `IMPLEMENTATION-PLAN-PHASE-1.md`, `PHASE-1-DECISIONS-CAPTURED.md`,
> `KRYTYCZNE-WOOW.md` w zakresie, w jakim któryś z nich twierdzi coś sprzecznego z tym dokumentem.
>
> Ten plik powstał po audycie, w którym większość powyższych dokumentów okazała się nieaktualna
> lub sprzeczna z faktycznym stanem kodu (przykład: `KRYTYCZNE-WOOW.md` z 23.06 twierdził że owner
> nie może zapłacić faktury jednym kliknięciem, mimo że ta funkcja została zmergowana 15.06 w PR #421).
> **Zasada na przyszłość: przed zaplanowaniem nowej pracy nad punktem z jakiegokolwiek roadmapu,
> zweryfikuj w kodzie (grep/find/read) czy faktycznie nie istnieje — nie ufaj samej dokumentacji.**
>
> Produkcja: `app.hovera.app` działa live z realnymi klientami (potwierdzone przez właściciela produktu
> 2026-07-05). Ostatni merged PR: #470 (2026-06-23).

## 1. Owner (`/owner`) — stan faktyczny po weryfikacji w kodzie

| Funkcja | Status | Dowód w kodzie |
|---|---|---|
| Przycisk "Zapłać" na fakturze (C.6) | ✅ Gotowe (PR #421, 2026-06-15) | `app/Filament/Owner/Pages/InvoiceShow.php` + `OwnerInvoicePaymentService::initiate()` — redirect na hosted checkout stajni (P24/PayU/Stripe, co skonfigurowane) |
| Historia rozliczeń / eksport CSV (C.7) | ✅ Gotowe | `InvoiceList.php::csvExportUrl()` → `route('owner.invoices.export-csv')` |
| Auto-billing (miesięczne faktury draft za pensjonat) | ✅ Gotowe | `app/Jobs/Owner/GenerateMonthlyBoardingInvoicesJob.php` — idempotentny, chunk 100, per-horse items (box + monthly services), scheduler 1. dnia miesiąca |
| `invoice_items.horse_id` (C.4) | ✅ Gotowe | widoczne w `GenerateMonthlyBoardingInvoicesJob::createInvoice()` |
| Powiadomienia DB + widget aktywności | ✅ Gotowe | `Owner/Resources/NotificationResource.php`, `Owner/Widgets/{NotificationsStatsWidget,LastOwnerActivityWidget,RecentInvoicesWidget,InviteOriginCardWidget,UpcomingTransportWidget}.php` |
| Upload zdjęć przez ownera | ✅ Gotowe | `Owner/Pages/HorseGallery.php` + `OwnerPhotosService` |
| Upload dokumentów przez ownera | ✅ Gotowe | `Owner/Pages/HorseDocuments.php` + `OwnerDocumentsService` |
| Pełny widok konia (zdrowie/boks/waga/żywienie) | ✅ Gotowe | `Owner/Pages/{HorseDetail,HorseCare}.php` — read-only, domyka punkty A.5/A.6 |
| Zagregowany feed historii/timeline | ✅ Gotowe | `Owner/Pages/HorseTimeline.php` |
| Wiadomości owner↔stajnia: załączniki, unread badge, auto-mark-read | ✅ Gotowe | `Owner/Pages/HorseMessages.php` |
| Panel marketplace ownera (zamawianie transportu) | ✅ Gotowe | `Owner/Pages/OrderTransport.php`, `Owner/Resources/TransportOrderResource.php`, `Owner/Resources/FavoriteTransporterResource.php` |
| Kanał D (owner↔wet) | ✅ Gotowe | `Owner/Resources/SpecialistThreadResource.php` |
| **E.5 — flaga "wymaga podpisu" na dokumencie** | ❌ Otwarte | brak `requires_signature`/`signed_at` w `HorseDocument` model i brak UI — potwierdzone grepem, model NIE istnieje (roadmap mylnie mówił że istnieje) |
| **Flow zatwierdzania zmian wrażliwych pól konia** (imię/paszport/microchip proponowane przez stajnię) | ❌ Otwarte | zero wystąpień `pending_change`/`PendingHorseChange`/`requires_approval` w repo — wymaga decyzji produktowej o zakresie przed startem |
| **Digest mailowy do wiadomości** | ❌ Otwarte | brak joba/mailable dla okresowego digestu nieprzeczytanych wiadomości |
| BLIK jako w pełni wbudowany "1-tap" (bez przekierowania) | 🟡 Częściowo | Pay button już działa i przekierowuje na hosted checkout stajni — dla P24/PayU w Polsce BLIK zwykle jest tam dostępny od razu. Prawdziwy gap to tylko *inline* płatność bez opuszczania panelu, nie brak BLIK-a jako takiego |

## 2. Transporter (`/transport`) — zweryfikowane fragmenty

- Kalkulator `extra_horse_fee` / `horses_count` — **zweryfikowane w kodzie jako gotowe**: `app/Domain/Transport/Calculator/CalculatorService.php:138-143`.
- Kreator Stripe Product/Price per plan — **gotowy**, `PlanResource` header action "Utwórz Product + Cenę w Stripe" (`StripeProductCreator`) — wymaga tylko kliknięcia przez master-admina + potwierdzenia że w produkcji są prawdziwe klucze Stripe.
- Migracja klienta legacy (250 PLN/mc) — narzędzie gotowe: `/admin/legacy-plan-migration`, `LegacyPlanMigrator`.
- Hotfixy #468-470 (constructor injection → 500 gdy brak env creds): **zweryfikowany pełny audyt** (2026-07-05) — nie znaleziono innych wystąpień tej klasy buga. Wszystkie wieloakcyjne klasy Filament już rozwiązują `StripeBillingService`/`PayUService`/`Przelewy24Service`/`TransporterStripeConnectService` leniwie przez `app(Service::class)` w konkretnej akcji. Jedyne pozostałe wstrzyknięcia przez konstruktor to kontrolery webhooków (`Public/*WebhookController.php`) — te są jednoakcyjne (`__invoke`), więc to inna, mniej dotkliwa sytuacja niż oryginalny bug (żadna niepowiązana akcja nie pada).
- Refund/dispute UI, KSeF korekty (KOR), płatności wieloetapowe — status **niepotwierdzony w kodzie** w tej sesji (dokumentacja `TRANSPORT.md` twierdzi że otwarte, ale biorąc pod uwagę jak bardzo myliła się dokumentacja ownera, **zweryfikuj w kodzie przed planowaniem pracy**, nie ufaj samemu dokumentowi).

## 3. Infrastruktura / CI — nowe ustalenie i zmiany wprowadzone 2026-07-05

**`hovera.app-sys` nie ma żadnego CI** — brak katalogu `.github/workflows` w ogóle (w przeciwieństwie do `hovera.app-android`/`hovera.app-ios`, które mają CI budujące+testujące na każdy PR). Testy (`php artisan test`, `vendor/bin/pint`) są uruchamiane ręcznie per PR, zgodnie z checklistami w opisach PR-ów.

**Zmiana 2026-07-05**: `HOVERA_PREVENT_LAZY_LOADING=true` włączone na stałe w `phpunit.xml` (poprzednio istniało tylko jako opcjonalna flaga, nic jej nie wymuszało). Uruchomienie pełnego suite'a z tą flagą ujawniło jeden realny N+1 — `OwnerMessagesService::listForHorse()` lazy-loadował relację `client` na `HorseMessage` (naprawione: dodano `->with('client')`). Po fixie: **1999 passed / 1999 testów bez N+1, Pint czysty**.

**Nowe, niepowiązane odkrycie**: `Tests\Feature\Stable\TodayDashboardTrendTest::unpaid_invoices_count_reflects_as_of_state` pada deterministycznie (powtarzalnie, niezależnie od flagi N+1) — asercja "2 days ago: still unpaid" oczekuje `1`, dostaje `0`. To pre-existing bug/test niezwiązany z N+1 (prawdopodobnie zależność testu od realnego `now()` vs zaszytych dat granicznych) — **nie naprawiony w tej sesji, poza zakresem** (task dotyczył tylko N+1). Wymaga osobnego zbadania.

## 4. Mobile (Android/iOS)

Bez zmian względem wcześniejszej analizy — obie appki są feature-complete (4 role, offline sync, push, biometria, i18n 5 języków) od maja 2026, ale zero przygotowania do publikacji w sklepach (brak Firebase/`google-services.json`, brak keystore/podpisywania, brak kont Apple Developer/Play Console, CI buduje tylko debug). Patrz plan wdrożeń (`/root/.claude/plans/potrzebuje-pe-nej-listy-i-glowing-bentley.md`) — Strumień C.
