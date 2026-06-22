# Phase 1 — Captured Decisions for Resume

> Data: 2026-06-22
> Sesja: 14 PRs delivered (#437-#448). Pozostałe blocked items mają teraz answered decyzje — dokument ten kompletuje input do fresh resume session.

## Status sesji 2026-06-22

**Merged**:
- PR D (#437) batch health
- PR I1 (#438) PDF templates (tenant + Hovera/Sendormeco)
- PR O1 (#439) notifications hub
- PR O2 (#440) HorseDetail nav link
- PR S1 (#441) bulk billing tests
- #442 Phase 1 status update
- PR I3a (#443) invoice KSeF columns scaffold
- PR I3 JPK_FA(3) exporter (#444)
- PR I3 UPR (#445)
- PR I3 ZAL (#446) multi 1:N
- PR I3 RR (#447) scaffold
- PR I3 JPK CLI + admin page (#448)

**Discovered already done** (pre-existing, no PR needed):
- PR I2 calculator extra-horse-fee
- PR O3 owner horse detail (timeline/photos/docs)
- PR O4 owner wallet (multi-provider)
- Channel A messaging (stable ↔ owner per-horse, ~85% complete)

## Decyzje captured — gotowe do implementacji w fresh sesji

### 1. TenantKsefSubmissionService (regular invoice send/poll)

**Status**: speculative implementation against KSeF test environment.

**User decyzja**:
- Pisz speculative implementation
- **Mam wszystko: test creds + cert + test NIP** → end-to-end testowanie możliwe na `ksef-test.mf.gov.pl`

**Plan implementacji**:
1. **Modyfikuj** `KsefSigningService::buildAuthTokenRequest`:
   - Wygeneruj ephemeral AES-256 key
   - Wrap kluczem RSA-OAEP via MF public key (`KsefHttpClient::getPublicKey`)
   - Embed wrapped key w `<EncryptionKey>` block signed XML
   - Zwróć zarówno signed XML jak i raw AES key (caller potrzebuje obu)
2. **Modyfikuj** `KsefClient::authenticate`:
   - Zwraca tuple `{sessionToken, aesKey}` zamiast samego token
   - Cache w `KsefSessionManager` (parallel z transport flow)
3. **Stwórz** `App\Domain\Invoicing\TenantKsefSubmissionService`:
   - Mirror `TransporterKsefService` ale dla regular `Invoice` (boarding/lekcje/pasze)
   - `submit(Invoice $invoice)` → `KsefSubmissionResult`
   - `refreshStatus(Invoice $invoice)` → `KsefStatusResult`
4. **Console command** `ksef:poll-tenant-invoices` (mirror `KsefPollSubmittedInvoicesCommand`)
5. **InvoiceResource action** — rozszerz istniejący `ksef` action z full send (zamiast tylko auth)
6. **Tests**:
   - Mock `KsefHttpClient` dla unit-level
   - End-to-end test przeciwko ksef-test (osobny test marker `@group ksef-live`)

**Effort**: 2-3 dni focused work. Crypto risk obniżony bo masz test env do iteracji.

**Spec referencja**: https://www.podatki.gov.pl/ksef/specyfikacja-techniczna/ §3.2 "Session token z certyfikatem".

### 2. KsefRrInvoiceXmlBuilder (FA_RR schema)

**Status**: scaffold w PR #447. Full impl unblocked via WebFetch.

**User decyzja**:
- Implementuj na bazie WebFetch z podatki.gov.pl

**Plan implementacji**:
1. **WebFetch** spec FA_RR z https://www.podatki.gov.pl/ksef/struktury-fa/
2. **Stwórz** `App\Services\Ksef\KsefRrInvoiceXmlBuilder`:
   - Schema namespace inny niż FA(3) (sprawdzić w spec)
   - Root element `<Faktura>` ale z `<KodFormularza kodSystemowy="FA_RR">`
   - `<RolnikRyczaltowy>` podmiot1 (zamiast `<Podmiot1>` z NIP — rolnik ryczałtowy nie ma NIP)
   - Buyer = kupujący VAT-owiec → `<Podmiot2>` z NIP
3. **Modyfikuj** `InvoiceResource::ksef` action — route na podstawie `InvoiceKind`:
   - `FvRr` → `KsefRrInvoiceXmlBuilder` + dedykowany send flow
   - Reszta → istniejący `KsefInvoiceXmlBuilder`
4. **Walidacja przy issue**: "RR może być wystawiona tylko gdy buyer.country_code = PL i seller jest VAT-owcem" (art. 116 ustawy o VAT)
5. **Form changes**: dla RR pokaż dodatkowe pola — rolnik nie ma NIP, ma PESEL + numer identyfikacyjny rolnika (sprawdzić w spec które są wymagane)
6. **Tests**: pełne XML structure + integration

**Effort**: 1-1.5 dnia.

### 3. PR O5 Channel B — vet magic-link

**Status**: 0% done.

**User decyzje**:
- **Hybrid**: open invite (any email), ale flag `unverified` na badge
- **7d magic link** + password setup + account ma kod weryfikacyjny

**Plan implementacji**:
1. **Migrations** (central DB):
   - `external_specialists`: id, email (unique), display_name, specialty, verified_at (nullable), verified_by_user_id (nullable), password_hash (nullable), created_by_user_id, created_at
   - `specialist_magic_links`: id, specialist_id, token_hash (sha256), expires_at, used_at, kind enum (initial_setup, password_reset, login)
   - `specialist_messages` table dla Channel B threads
2. **Models**: `ExternalSpecialist`, `SpecialistMagicLink` (TTL 7d)
3. **Flow**:
   - Stable Filament action "Zaproś weterynarza" → form (email + display_name + specialty) → tworzy `ExternalSpecialist` row + magic link + wysyła email
   - Vet klika link → `/specialist/setup/{token}` → ustawia hasło + dostaje email verification code (osobny mail) → musi wpisać code do verify
   - Po setup'cie, login przez `/specialist/login` (email + password)
4. **Filament Specialist panel** (`SpecialistPanelProvider`):
   - `/specialist` route, osobny auth guard
   - Resources: tylko `SpecialistMessageThread` + `SpecialistProfile`
5. **Verification flow**:
   - `unverified` badge na thread UI gdy `specialist.verified_at IS NULL`
   - Master-admin action "Verify specialist" w admin panel (sprawdza PWZ / NIP manualnie)
6. **i18n** (pl + en)
7. **Tests**:
   - Magic link generation + redemption
   - 7d expiry
   - Password setup + verification code flow
   - Stable invite flow end-to-end

**Effort**: 2-3 dni.

### 4. PR O5 Channel C — internal channels (Slack-like)

**Status**: 0% done.

**User decyzje**:
- **Hybrid**: 3 auto-created channels (`#general`, `#weterynaria`, `#transport`) per provision + admin może dodać dodatkowe
- **Daily digest 08:00 Europe/Warsaw**, skip gdy 0 unread

**Plan implementacji**:
1. **Migrations** (tenant DB — internal team is per-stable):
   - `internal_channels`: id, slug (`#general` etc.), name, description, is_system (true dla 3 auto-created), created_by, created_at
   - `internal_channel_members`: channel_id, user_id (FK do tenant_user), joined_at, role enum (admin, member), notif_preference enum (immediate, digest, off)
   - `internal_messages`: id, channel_id, author_user_id, body, attachments JSON, mentions JSON (user_id list), created_at
2. **Models**: `InternalChannel`, `InternalChannelMember`, `InternalMessage`
3. **Auto-create flow**: tenant onboarding hook (`TenantOnboardingService::completeProvisioning`) creates 3 default channels + adds tenant.owner as member of all
4. **Filament**:
   - `InternalChannelResource` w `/app` panel — list + detail (Slack-like, reverse-chrono messages)
   - `MessageThread` Livewire component dla per-channel chat UI
   - File attachments (mirror `HorseMessageAttachmentStorage` pattern)
   - `@mention` autocompletion via Livewire dropdown
5. **Digest job**: `Schedule::job(new SendDailyDigestJob)->dailyAt('08:00')->timezone('Europe/Warsaw')`
   - Per user with unread messages, build digest email z N nowych wiadomości grouped per channel
   - Skip jeśli user 0 unread
6. **i18n** (pl + en)
7. **Tests**:
   - Auto-create 3 channels on tenant provision
   - Admin can create custom channel
   - Member management (add/remove)
   - Message send + mention extraction
   - Digest job email content
   - Skip empty digest

**Effort**: 3 dni.

### 5. PR O5 Channel D — owner ↔ vet cross-tenant

**Status**: 0%. **Depends on Channel B**.

**Plan implementacji** (po Channel B):
1. Reuse `ExternalSpecialist` + `SpecialistMagicLink` z Channel B
2. **Migration**: `owner_specialist_threads` (central) — owner_user_id + specialist_id + horse_id (optional)
3. **Owner UI**: `/owner/specialists` resource — invite vet by email (workflow same as Channel B)
4. **Specialist UI**: thread inbox z owner messages, scoped permissions
5. **Cross-tenant access**: vet sees specific horses owner shares (NOT all owner's horses)
6. **i18n** + tests

**Effort**: 3-4 dni (po B done).

## Rekomendowana kolejność dla resume sesji

| # | Epic | Effort | Dependencies | Notes |
|---|---|---|---|---|
| 1 | **KSeF TenantKsefSubmissionService** | 2-3 dni | None | Masz test env — wynik testowalny |
| 2 | **KsefRrInvoiceXmlBuilder** | 1-1.5 dni | None | WebFetch spec, samodzielne |
| 3 | **O5 Channel B (vet magic-link)** | 2-3 dni | None | Blocker dla D |
| 4 | **O5 Channel C (internal channels)** | 3 dni | None | Independent of B |
| 5 | **O5 Channel D (owner ↔ vet)** | 3-4 dni | Channel B done | Cross-tenant |

**Total estimate**: 11-15 dni. Realistycznie 3-4 tygodnie sesji per-tydzień jeden epic.

## Co RECCMENDUJE w fresh sesji

1. **Start fresh** — duża sesja 14 PRów daje context fatigue. Fresh sesja per epic = lepsza jakość.
2. **Per epic = dedicated branch + długo-żywa sesja** zamiast wielu małych PR jak teraz.
3. **TenantKsefSubmissionService najpierw** — masz test env, ROI najwyższy (regular faktury wreszcie idą do KSeF).
4. **Channels B/C/D ostatnie** — najbardziej elastyczne, najmniej regulatory pressure.

---

## Test credentials reminder (KSeF)

User potwierdził:
- Cert załadowany do `KsefSettings` dla test tenant'a
- Test NIP skonfigurowany
- `ksef-test.mf.gov.pl` endpoint dostępny

**Sprawdź przy resume**: czy cert nie wygasł, czy test NIP nie jest zablokowany przez MF rate limits.
