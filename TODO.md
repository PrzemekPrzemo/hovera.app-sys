# TODO — Hovera roadmap (do dorobienia)

Lista otwartych PR-ów po sesji 2026-05-21. Sekwencja zaproponowana od najwyższego priorytetu (blocker → nice-to-have).

## Stable ↔ Owner ↔ Horse linking workflow

**Background:** model + serwisy (`HorseBoardingAssignment`, `HorseRegistrySyncService::{requestBoarding,activateBoarding,endBoarding}`, `HorseOwnerStableAccessGate`) są zaimplementowane i pokryte testami w `tests/Feature/Horses/HorseRegistrySyncServiceTest.php`. Brakuje **UI w obu panelach + notyfikacji** — bez tego boarding assignment to martwy kod.

Patrz: `docs/OWNER-STABLE-ROADMAP.md`, `docs/MARKETPLACE-ROADMAP.md`.

### PR 1 — Stable: Search & import horse from central registry  🔴 BLOCKER
- Lokalizacja: `app/Filament/App/Resources/HorseResource/Pages/ListHorses.php`
- Nowy `Action::make('import_from_registry')` w header
- Form: chip / passport_number / UELN + email właściciela
- Backend: query `CentralHorseRegistry` po chip/passport/UELN, sprawdź czy email matchuje `primary_owner_user_id->email`
- Sukces → `HorseRegistrySyncService::requestBoarding($centralHorseId, $stableTenant)` (status=pending)
- Dispatch notif do owner'a (zależność: PR 5 lub stub)
- Tests: pokrywaj happy path + mismatch chip vs email + nieistniejący koń + duplikat pending

### PR 2 — Stable: PendingBoardingAssignmentsResource + accept  🔴 BLOCKER
- Lokalizacja: `app/Filament/App/Resources/PendingBoardingAssignmentResource.php` (nowy)
- ListPage: tabela pending boarding'ów dla aktualnego tenant'a (cross-tenant query do central z `stable_tenant_id`)
- Action "Akceptuj" → `activateBoarding()` (status=active, started_at=now()) → `Horse::create` w tenant DB z `central_horse_id` linkiem → dispatch notif do owner'a
- Action "Odrzuć" → status=rejected + powód (textarea)
- Tests: accept tworzy Horse w tenant DB + linkuje, reject ustawia status, idempotency

### PR 5 — Notifications: boarding events  🟡 HIGH
- Lokalizacja: `app/Notifications/Boarding/`
- `HorseBoardingRequestedNotification` (do ownera, database + mail) — gdy stable wysłał request
- `HorseBoardingAcceptedNotification` (do stable, database + mail) — gdy owner zaakceptował (jeśli kiedyś flow odwrotny)
- `HorseBoardingRejectedNotification` (do drugiej strony, database + mail)
- Dispatch z `HorseRegistrySyncService` methods
- Templates: PL + EN
- Tests: każda notif lokalizuje correctly, payload zawiera linki

### PR 3 — Owner: marketplace stable list + request boarding form  🟡 HIGH
- Lokalizacja: `app/Filament/Owner/Pages/StableMarketplace.php` (nowa) + `StableDetail.php`
- Lista stajni (Tenant where type=stable + active + opcjonalnie filtr geo)
- Detail: form "Wyślij konia do boardingu" — picker z `CentralHorseRegistry::where(primary_owner_user_id=Auth::id())`
- Sukces → `requestBoarding()` w odwrotnym kierunku (owner → stable) → notif do stable team
- Tests: lista filtruje aktywne, request tworzy pending, owner nie może wysłać konia którego nie jest properly owner'em

### PR 4 — Invitation link completion  🟢 MEDIUM
- Lokalizacja: `app/Http/Controllers/Public/HorseOwnerRegistrationController.php` `submit()`
- Aktualnie czyta `invite_stable_id` + `invite_token` w `show()` ale `submit()` je IGNORUJE
- Walidacja tokena (hash w `UserInvitation` lub osobna tabela `BoardingInvitation`?)
- Po `CreateTenant::execute()` — jeśli invitation valid, auto-tworzy `HorseBoardingAssignment.pending`
- Tests: invalid token nie crashuje, expired token nie tworzy assignment, valid token + register → pending assignment widoczny w stable panel

### PR 6 — Guided box assignment modal po accept  🔵 LOW (post-blockers)
- Po `activateBoarding()` (PR 2), pokaż modal w stable panel: "Gdzie umieścić konia?"
- Form: box picker (only `is_active=true`) + start date (default today) + notes
- Sukces → `BoxAssignment::create(horse_id, box_id, assigned_at)` + redirect na `HorseResource::EditPage`
- Tests: modal pokazuje tylko active boxes, prevent double-assignment

---

## Pozostałe gaps z OWNER-STABLE-ROADMAP

Otwarte items z `docs/OWNER-STABLE-ROADMAP.md`:

- **C.6** — Pay button na fakturach (P24/PayU dla owner→stable). Wymaga creds per stajnia. Średni scope.
- **C.7** — Historia rozliczeń (suma roczna, eksport CSV). Mały scope.
- **E.5** — Dokument "wymaga podpisu" (owner→stable action item). Mały scope.
- **F.4** — Mobile push (Sanctum + FCM). Własna iteracja, scope na osobny sprint.

---

## Otwarte ops / config issues (NIE kod)

### SMTP 535 auth failed dla GalloPTrans (`hi@galloptrans.pl`)

**Status:** kod ze strony Hovery jest OK po PR #354/#357/#358. Server jest reachable, TLS handshake działa (po włączeniu `skip_tls_verify`), ale serwer SMTP odrzuca creds (kod 535).

**Czeklist diagnostyczny (po stronie user'a / hostingu lh.pl):**

1. **Domena typo?** — w jednym message było `galoptrans.pl`, w drugim `galloptrans.pl` (jedno L vs dwa L). Sprawdź `/admin/smtp-settings` że username dokładnie matchuje skrzynkę faktycznie założoną w panelu hostingowym.
2. **Format username** — niektóre serwery wymagają samej części lokalnej (`hi`) zamiast pełnego email (`hi@galloptrans.pl`). Spróbuj obu.
3. **Hasło** — wpisz na nowo w `/admin/smtp-settings` (pole pokazuje puste celowo, "" = zachowaj poprzednie). Jeśli przeklejasz z menedżera haseł, sprawdź czy nie ma whitespace na końcu.
4. **App password** — jeśli to Google Workspace / Microsoft 365 z 2FA, zwykłe hasło NIE działa. Musisz wygenerować osobne App Password (`myaccount.google.com/apppasswords`).
5. **Active mailbox?** — w panelu hostingowym lh.pl sprawdź czy skrzynka `hi@...` jest aktywna (nie zablokowana, nie wygasła subskrypcja).
6. **SMTP AUTH włączony?** — niektórzy hosting providerzy mają osobny toggle "Pozwól na zewnętrzne SMTP" / "Allow SMTP AUTH" — domyślnie wyłączony dla nowych skrzynek.
7. **Limity wysyłki** — czasem 535 to soft-block po zbyt wielu próbach. Odczekaj 15 min, spróbuj ponownie.
8. **Test creds direct** — sprawdź czy te same creds działają w innym mail kliencie (Thunderbird / iPhone Mail / curl).

**Próba diagnostyki przez curl** (zastąp `***` swoim hasłem base64'd):

```bash
{
  echo "EHLO test"
  echo "AUTH LOGIN"
  echo "$(echo -n 'hi@galloptrans.pl' | base64)"
  echo "$(echo -n 'PASSWORD' | base64)"
  echo "QUIT"
} | openssl s_client -connect smtp.galloptrans.pl:465 -crlf -quiet 2>&1 | head -30
```

Jeśli curl też zwraca 535 → creds błędne lub mailbox zablokowana. Jeśli curl działa → problem jest po stronie Symfony Mailer i wracamy do kodu.

**Workaround tymczasowy** — przełącz `MAIL_DEFAULT_FROM` na innego providera (Gmail SMTP, SendGrid free tier, Mailtrap) żeby maile w ogóle wychodziły, a problem z lh.pl rozwiąż osobno z ich supportem.
