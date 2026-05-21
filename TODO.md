# TODO — Hovera roadmap

## Status: stable ↔ owner ↔ horse linking workflow

✅ **WSZYSTKIE 6 PR-ów ukończone** (sesja 2026-05-21).

| PR | Zakres | Merged |
|---|---|---|
| **PR 1** | Stable: Search & import horse from central registry | #363 |
| **PR 2** | Owner: accept/reject pending boarding requests | #364 |
| **PR 5** | Notifications (Requested / Accepted / Rejected) + dispatch hooks | #366 |
| **PR 4** | Invitation link completion (validation + thanks banner) | #367 |
| **PR 6** | Guided box assignment dla koni bez boksu | #368 |
| **PR 3** | Owner marketplace + stable accept side (bidirectional flow) | #369 |

Workflow działa end-to-end w **obu kierunkach**:
- **Stable → Owner**: `/app/horses` "Importuj z rejestru" → owner notify → `/owner/pending-boarding-requests` accept/reject → Horse materialized w stable tenant + notify stable team
- **Owner → Stable**: `/owner/stables` (marketplace) "Wyślij prośbę" → stable team notify → `/app/pending-boarding-requests` accept/reject → Horse materialized + notify owner
- **Post-accept**: stable klika "Przypisz boks" na koniu bez `box_id` → modal box picker + reason → BoxAssignment row z history

## Pozostałe gaps z OWNER-STABLE-ROADMAP

Otwarte items z `docs/OWNER-STABLE-ROADMAP.md` (poza scope'em linking workflow):

- **C.6** — Pay button na fakturach (P24/PayU dla owner→stable). Wymaga creds per stajnia. Średni scope.
- **C.7** — Historia rozliczeń (suma roczna, eksport CSV). Mały scope.
- **E.5** — Dokument "wymaga podpisu" (owner→stable action item). Mały scope.
- **F.4** — Mobile push (Sanctum + FCM). Własna iteracja, scope na osobny sprint.

## Future enhancements dla linking workflow

Pomysły post-PR (nice-to-have, nie blockery):

- **Secure invitation tokens** — obecnie `?stable=X&token=Y` ignoruje token. Pełny flow z tabelą `boarding_invitations` (hash + expiry) — gdy potrzeba per-link expiry / single-use.
- **Auto-create pending assignment przy `?stable=X`** — po dodaniu konia przez nowego ownera, jeśli ma `invite_origin` w `Tenant.settings`, auto-tworzy `HorseBoardingAssignment.pending`.
- **Stable bulk import** — CSV upload "lista koni boardingu" → bulk requestBoarding dla emails z pliku.

## Otwarte ops / config issues (NIE kod)

### SMTP — działa po PR #354/#357/#358

GalloPTrans config flow:
1. `/admin/smtp-settings` → wypełnij creds
2. Toggle **"Pomiń weryfikację certyfikatu TLS"** dla shared hosting (lh.pl wildcard cert mismatch)
3. Save → Send test

Jeśli 535 auth failed — sprawdź username format (`hi` vs `hi@domain`), App Password (gdy 2FA), SMTP AUTH toggle u providera.
