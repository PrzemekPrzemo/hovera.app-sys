# Hovera — Handoff dla następnej sesji

> Data: 2026-06-23 (post-session continuation, 31 PRów merged)
> Status: Phase 1 ukończony 100%, Channel B foundation 6/6, pozostała Channel B core + C + D

## 1. Stan kończący tej sesji

### Merged w sesji 2 (#449-#461)

**KSeF send/poll epic** — kompletny end-to-end:
- #449 docs decisions captured
- #450 KSeF cert-flow z embedded AES key (RSA-OAEP wrapped)
- #451 `TenantKsefSubmissionService::submit` (full FA(3) send)
- #452 `TenantKsefSubmissionService::refreshStatus` (status polling)
- #453 `KsefPollTenantInvoicesCommand` (scheduled cron 06:15-22:15 Warsaw)
- #454 `InvoiceResource` UI: full send + manual refresh
- #455 `KsefRrInvoiceXmlBuilder` (FA_VAT_RR — faktura rolnicza, spec verified vs Billu-System)

**O5 Channel B foundation**:
- #456 `ExternalSpecialist` + `SpecialistMagicLink` models + 14 testów
- #457 `SpecialistInviteService` + `SpecialistInvitationNotification` + 6 testów
- #458 `SetupController` (/specialist/setup/{token}) + 3 Blade views + 11 testów
- #459 Stable Filament action "Zaproś weterynarza"
- #460 Master-admin `ExternalSpecialistResource` + verify/unverify actions + 5 testów
- #461 `specialists:prune-magic-links` cron + 2 testy

### Pushed bez PR (czeka na MCP reconnect)

**`claude/phase-1-decisions-update-after-session`** — docs update do `docs/PHASE-1-DECISIONS-CAPTURED.md`. Branch jest na origin, gotowy do PR.

**Action**: utwórz PR ręcznie albo polecaj nowemu agentowi.

## 2. Pozostała praca — priority order

Cała poniższa praca to **15-20h focused work** rozłożona realistycznie na 3-4 sesje per epic.

### Epic 1: O5 Channel B finish (~7-8h)

**Cel**: domknąć Channel B do funkcjonalnego stanu — vet ma swój panel + threading z tenant'em.

#### 1.1 `SpecialistPanelProvider` — Filament panel + auth guard (~2h)

**Pliki do utworzenia**:
- `app/Providers/Filament/SpecialistPanelProvider.php` — wzoruj się na `App\Providers\Filament\OwnerPanelProvider.php`
- `config/auth.php` — dodaj guard `specialist` (pattern: `'driver' => 'session', 'provider' => 'external_specialists'`) + provider `external_specialists` (model `App\Models\Central\ExternalSpecialist`)
- `app/Filament/Specialist/Pages/Dashboard.php` — landing po login (placeholder)

**Routing**: panel pod `/specialist` (login: `/specialist/login`, dashboard: `/specialist`).

**Kluczowe**:
- `canAccessPanel()` na `ExternalSpecialist` model — musi zwrócić `true` gdy `has_completed_setup === true`
- Login form używa `Filament\Pages\Auth\Login` z `email` + `password` — guard `specialist`
- Setup completed page (#458) musi linkować do `/specialist/login` zamiast dummy text

**Tests** (~30min):
- Guest dostaje 302 na `/specialist` → `/specialist/login`
- Login z poprawnym hasłem → sesja na guard `specialist`
- Specialist bez `has_completed_setup` → 403 (nawet z poprawnym hasłem)

#### 1.2 Add-to-local Specialist autolink (~30min)

**Plik**: nowa migracja `add_external_specialist_id_to_specialists_table.php` na tenant DB.

**Model**: `App\Models\Tenant\Specialist` — dodaj `external_specialist_id` do fillable + relacja `externalSpecialist()` (BelongsTo to `Central\ExternalSpecialist`).

**Flow**: gdy stable wypełnia formularz Create Specialist z emailem który istnieje jako `ExternalSpecialist`, automatycznie linkuj. `SpecialistResource::form()` — afterStateUpdated na `email` field szuka ExternalSpecialist + show "Zweryfikowany" badge w UI.

#### 1.3 `SpecialistThread` + `SpecialistMessage` models (~1h)

**Migracje (central DB — cross-tenant threading)**:
- `specialist_threads` (id ULID, specialist_id FK, tenant_id FK, horse_id nullable, subject, last_message_at, soft_deletes, timestamps)
- `specialist_messages` (id ULID, thread_id FK, sender_type enum ('tenant_user', 'specialist'), sender_id, body text, attachments JSON, read_at, timestamps)

**Modele**: BelongsTo + HasMany relations. `Thread::scopeForTenant($tenantId)`, `scopeForSpecialist($specialistId)`.

#### 1.4 UI w stable panel — Channel B compose + thread view (~2h)

**Plik**: nowa Filament Resource `App\Filament\App\Resources\SpecialistThreadResource`. Wzór: existing `App\Filament\Owner\Pages\HorseMessages.php` (Channel A — Channel B ma tę samą strukturę ale subject = specialist zamiast horse).

**Cross-tenant query**: thread'y żyją w central DB, więc `getEloquentQuery()` używa `SpecialistThread::query()->forTenant($tenant->id)` (NIE `TenantManager::execute`).

**Compose action**: header action "Nowy wątek" — modal z select ExternalSpecialist (tylko ci z stable's `Specialist` z `external_specialist_id IS NOT NULL`).

**Thread view**: per-thread page z list messages + reply form.

**Verified badge**: w thread list, gdy `specialist.verified_at IS NULL` — pokazuj `<x-filament::badge color="warning">Niezweryfikowany</x-filament::badge>`.

#### 1.5 UI w specialist panel — inbox + reply (~2h)

**Plik**: `App\Filament\Specialist\Resources\InboxResource` — Filament panel `specialist`. Lista wszystkich thread'ów gdzie `specialist_id === Auth::user()->id` (guard `specialist`).

**Per-thread**: ten sam template co w stable panel, ale `sender_type = specialist` przy reply.

**Notyfikacje**: `NewSpecialistMessageNotification` (database + mail) dla tenant user'ów gdy specialist odpisał, i odwrotnie. Mail dispatched przez `Notification::send()` na collection user'ów stable'a.

### Epic 2: O5 Channel C internal channels (~5-6h, **independent of B**)

**Cel**: per-stable Slack-like channels (#general, #weterynaria, #transport auto-created + admin może dodać).

**Migracje (tenant DB)**:
- `internal_channels` (id ULID, slug unique, name, description, is_default boolean, created_by_user_id FK, timestamps, soft_deletes)
- `internal_channel_members` (channel_id FK, user_id FK, joined_at, notifications_enabled boolean default true, primary [channel_id, user_id])
- `internal_messages` (id ULID, channel_id FK, author_user_id FK, body text, attachments JSON, mentions JSON, timestamps)

**Auto-create hook**: na tenant onboarding completion (sprawdź `TenantOnboardingService::completeStep()` albo Observer) — tworzy 3 default channels.

**Filament**:
- `App\Filament\App\Resources\InternalChannelResource` (lista + edit nazwa/description gdy admin)
- Per-channel page z reverse-chrono message list + compose form (Livewire dla real-time-ish refresh)
- `@mention` autocompletion — Livewire `wire:model.live` na body + autocomplete dropdown z tenant users

**Daily digest job**:
- `App\Jobs\Internal\SendDailyDigestJob` — per user, agreguje unread `internal_messages` z ostatnich 24h
- `Schedule::job(new SendDailyDigestJob)->dailyAt('08:00')->timezone('Europe/Warsaw')` w `routes/console.php`
- Skip user'ów z 0 unread (per captured decisions §4)
- `InternalDailyDigestNotification` mail template z grouped sections per channel

**Tests** (~1h):
- Auto-create na onboarding
- Add admin channel
- Send message → unread count
- Daily digest content + skip empty
- @mention extraction

### Epic 3: O5 Channel D cross-tenant owner↔vet (~3-4h)

**Blocker**: requires Channel B SpecialistPanelProvider done (epic 1.1).

**Cel**: horse owner może bezpośrednio chatować z external specialist (cross-tenant — vet niekoniecznie należy do tej samej stajni).

**Migracje (central)**:
- `owner_specialist_threads` (id, owner_user_id FK, specialist_id FK, horse_id nullable, subject, last_message_at, timestamps)
- `owner_specialist_messages` (mirror Channel B)

**Owner UI**: nowy `App\Filament\Owner\Resources\SpecialistThreadResource` w owner panel + invite action "Zaproś specjalistę" (reuse `SpecialistInviteService` z `invitingUser = owner`).

**Specialist panel**: rozszerz inbox o Channel D thread'y (osobna tab "Od właścicieli koni").

**Cross-tenant access controls**: vet widzi tylko horse'a którego owner explicit shared w thread (NOT all owner horses). Implementacja: `horse_id` w thread = explicit grant.

## 3. Konteksty / gotchas dla nowej sesji

### Repo state
- Branch `main` — wszystkie 31 PRów merged
- Branch `claude/phase-1-decisions-update-after-session` — pushed, czeka na PR
- Wszystkie testy zielone (200+ added in this run)
- Lint pass, i18n verified pl + en

### Klucze architektoniczne
- **Tenant Specialist** (`App\Models\Tenant\Specialist`) — per-stable contact, tenant DB
- **External Specialist** (`App\Models\Central\ExternalSpecialist`) — cross-tenant auth identity, central DB
- Łącznik między nimi = `external_specialist_id` na tenant `specialists` (do dodania w epic 1.2)

### Tożsame patterny
- Filament panel + auth guard: wzór `App\Providers\Filament\OwnerPanelProvider.php`
- Magic link redemption: zrobione w `SetupController` (#458) — wzór do dalszych flows (password reset, login)
- Cross-tenant query: brak `TenantManager::execute` — `SpecialistThread` jest central, używa standard Eloquent query z filter na `tenant_id`
- Channel A messages (`HorseMessage`) — wzór dla Channel B/C/D message UI (Filament Page + Livewire)

### Captured user decisions (z `docs/PHASE-1-DECISIONS-CAPTURED.md`)
- Hybrid invite: open invite + unverified badge dopóki master-admin nie potwierdzi PWZ
- 7d magic link + password setup + email verification implementowane jako "klik = verified"
- Channel C: hybrid 3 auto-created (#general/#weterynaria/#transport) + admin add
- Channel C digest: daily 08:00 Europe/Warsaw, skip empty
- Channel D: cross-tenant access tylko do explicit shared horses (NOT wszystkie)

### Test patterns
- `RefreshDatabase` trait
- Mock `TenantManager` gdy testujesz cross-tenant flow (wzór z `JpkFa3ExporterTest`)
- `Notification::fake()` przed `Notification::send()` tests
- `Auth::login($adminUser)` dla admin actions
- Filament action testing via reflection (wzór z `AdminVerifyActionTest`)

### Rate limits / known gotchas
- KSeF test env: cert załadowany, test NIP `5260250274` — gotowy do live smoke
- MF endpoint `/Invoice/Status` może być rate limit'owany — poller ma `min-age-minutes=5` defensive
- Email verification w setup'ie = klik link (per decisions) — NIE 6-digit code (decyzja: simpler UX)
- Magic link plain token NIGDY w DB — tylko sha256 hash. Plain w mail tylko.

## 4. Rekomendowana kolejność per epic

1. **Najpierw**: utwórz PR z pending branch (`claude/phase-1-decisions-update-after-session`) — to porządki
2. **Epic 1.1 (SpecialistPanelProvider)** — najwartościowsze user-visible, ~2h
3. **Epic 1.2 (autolink)** — szybki win, ~30min
4. **Epic 1.3 (Thread models)** — przygotowanie pod UI, ~1h
5. **Epic 1.4 (stable UI)** — vet widzi się w stable panelu, ~2h
6. **Epic 1.5 (specialist UI)** — vet widzi swój inbox, ~2h
7. **Epic 2 (Channel C)** — niezależny, można w innej sesji
8. **Epic 3 (Channel D)** — po Channel B done

## 5. Prompt dla nowej sesji (do skopiowania)

```
Kontynuuję pracę na Hovera SaaS po sesji z 31 PRami (#437-#461).
Przeczytaj docs/HANDOFF-NEXT-SESSION.md — masz tam priorytetyzowaną
listę pozostałej pracy, captured user decisions i konteksty
architektoniczne.

Zaczynamy od epic 1.1 — SpecialistPanelProvider (Filament panel +
auth guard 'specialist' + login flow). Wzoruj się na
App\Providers\Filament\OwnerPanelProvider.php. Czas estymowany ~2h
focused work, deliver jako 1-2 PRy (panel + tests).

Branch: claude/o5-channel-b-specialist-panel
```

---

**Total wartość sesji (sumarycznie obie sesje)**: 31 PRów merged, Phase 1 zamknięty na poziomie infrastruktury, KSeF kompletny end-to-end, Channel B foundation 100%, ~200+ testów dodanych.

**Pozostała praca**: 3 epicy × 5-8h każdy = ~15-20h dedicated work podzielone na 3-4 sesje.
