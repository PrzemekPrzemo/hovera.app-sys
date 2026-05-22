# Backlog — zaplanowane, niezrealizowane

Lista zadań w kolejności priorytetu. Format: krótki opis + uzasadnienie + szkic
implementacji. Aktualizować po każdym PR z tej listy.

## 1. Audit log viewer w `/admin`

**Po co.** `MasterAuditLogger` zapisuje wpisy do `central.master_audit_logs`
przy każdej krytycznej operacji (tenant.destroy, tenant.bulk_purge,
tenant.update, ksef.send, impersonation.start itd.). Brak UI do przeglądu —
master admin musi `SELECT * FROM master_audit_logs` ręcznie. Wartość:

- **Compliance**: gdy klient pyta "co robiliście z moimi danymi" (RODO art. 15)
  — wyciąg z audit log w 30 sekund zamiast godziny grzebania
- **Debug**: gdy coś się popsuło — kiedy i kto co zmieniał?
- **Security**: pokaż impersonation events, niespodziewane purge'y itp.

**Szkic implementacji** (1 PR, ~3-4h):

- `app/Models/Central/MasterAuditLogEntry.php` jeśli nie istnieje (sprawdzić)
- `app/Filament/Admin/Resources/MasterAuditLogEntryResource.php` (read-only,
  `canCreate=false`, `canEdit=false`, `canDelete=false`)
  - Table: timestamp, actor (user_id → email), tenant (jeśli set), action,
    target_type, target_id, payload (JSON column)
  - Filters: actor / tenant / action / date range
  - Search: action keyword
  - Default sort: created_at DESC
- Navigation: `/admin/audit-log` pod sekcją "Bezpieczeństwo" lub
  "System"
- i18n: PL/EN
- Test: read-only enforcement (canCreate/canEdit/canDelete → false dla
  master admin)

**Acceptance criteria.**
- [ ] `/admin/audit-log` listuje wszystkie wpisy z paginacją
- [ ] Filter po action (e.g. tenant.destroy)
- [ ] Filter po actor (master admin email)
- [ ] Filter po tenant slug
- [ ] Date range filter
- [ ] Brak edycji / kasowania (też dla master admin — audit log immutable)
- [ ] Test: insert do audit log → pojawia się w UI

---

## 2. Audyt funkcjonalny horse_owner panel

**Po co.** Po audycie RBAC vet/employee siostra dla horse_ownera. Sprawdzić
realny flow z perspektywy właściciela konia:

- Czy widzi FV od stajni z poziomu panelu? (`OwnerInvoiceFeedService` istnieje
  — sprawdzić UI)
- Czy działają notyfikacje "nowa oferta od przewoźnika"?
- Czy może komunikować się z przewoźnikiem po akceptacji oferty?
- Czy dashboard pokazuje co trzeba (najbliższe wizyty, ostatnie FV,
  nadchodzące transporty)?
- Czy `OnboardingWizard` (PR #381) ma sensowne CTA?
- Czy `QuickStartWidget` (#384) faktycznie pomaga?
- Czy gdzieś jest ślepa trasa (link do strony do której nie ma uprawnień)?
- Czy mobile UX dla wszystkich kluczowych przepływów jest OK?
  (horse_owner pewnie głównie z telefonu)

**Szkic implementacji** (1 audyt + 1 PR):

1. Audit phase: 3 Explore agents
   - Agent A: per-page UX (każda strona w `/owner` — co user widzi, jakie CTA)
   - Agent B: notyfikacje (które wydarzenia trigger'ują maile/in-app?)
   - Agent C: end-to-end flow walk-through (od rejestracji do
     akceptacji pierwszej oferty)
2. Fix PR: naprawia konkretne luki znalezione w audycie

**Sugerowane sprawdzenia.**

- `/owner/dashboard` — co widać po pierwszym logowaniu (przed wizardem)
- `/owner/horses/{id}` — czy może edytować swoje konie + galerię
- `/owner/order-transport` — full flow z FV firmową (już test'owałem
  w PR #376, ale tu real-life walk-through)
- `/owner/transport-orders` — historia, status, czy widać ofertę po
  akceptacji
- `/owner/invoices` — FV od stajni (Payments → P24/PayU/Stripe)
- `/owner/messages` — czy istnieje? komunikacja ze stajnią / przewoźnikiem
- Notyfikacje email: nowa oferta, FV wystawiona przez stajnię, transport
  zaakceptowany, transport zrealizowany

**Acceptance criteria.**
- [ ] Dokument `docs/audits/HORSE-OWNER-UX.md` z findings + status
- [ ] Wszystkie konkretne luki w osobnym PR-ze
- [ ] Manual smoke test pełnego flow na świeżym koncie horse_owner

---

## Inne sygnały do rozważenia (lower priority)

- **Notyfikacje per kluczowe zdarzenia** — globalny audit, ujednolicenie
  channels (mail / database / SMS), template review
- **PWA dla owner** — manifest + offline shell dla mobile-first horse_owner
- **Global search** w panelu — bar typu Cmd+K
- **CSV export** dla wszystkich raportów (nie tylko FV)
- **2FA dla master admin** — sprawdzić czy jest, dodać jeśli nie
- **Performance audit** — N+1 queries (`Telescope` / `Debugbar`), missing
  indexes
- **Health checks deeper** — po pierwszej iteracji rozszerzyć o real
  pings (KSeF ping endpoint, NBP test fetch)
