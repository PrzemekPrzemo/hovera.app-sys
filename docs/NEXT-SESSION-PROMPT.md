# Starter prompt do nowej sesji

Skopiuj poniższy blok jako pierwszy message:

---

Pracujesz dalej nad sprintem marketplace + calculator parity opisanym w `docs/MARKETPLACE-ROADMAP.md`. Pełen handoff z poprzedniej sesji jest w `docs/SESSION-HANDOFF.md` — przeczytaj go najpierw.

**Pilne — zrób od razu:**

Branch `claude/calculator-leaflet-map` ma 1 commit lokalnie (`c1265c3`, "feat(transport): Leaflet map z trasą wyceny"), który **nie trafił na GitHub** (poprzednia sesja straciła sync MCP). Najpierw:

1. `git checkout claude/calculator-leaflet-map`
2. `git push --force-with-lease origin claude/calculator-leaflet-map`
3. Utwórz draft PR (treść w `git log -1 --format=%B`)
4. Po merge wróć na main

**Następnie:** ruszaj sekwencyjnie z trzech podzakresów Calculator live UX opisanych w `SESSION-HANDOFF.md` §"🟢 Co zostało":

1. Debounced live recalc API endpoint + JS (~3h) — najważniejsze
2. Sticky summary card z live breakdown (~1h)
3. One-shot save-as-quote (~1.5h)

Każdy jako osobny PR ~1-3h, draft, branch `claude/<slug>`.

**Konwencje (przestrzegaj):**
- Polskie komentarze w klasach
- `vendor/bin/pint --dirty` + tests przed commit
- Snapshot wszystkiego co historyczne (rate, fuel, surcharge, line items)
- Defensive parse JSON input'u
- Test schema sync we wszystkich ~14 plikach gdy dodajesz nową kolumnę do `quotes`/`transport_settings`
- Migracje idempotent (`dropIfExists` przed `create`) gdy MySQL może mieć partial state
- Explicit constraint names dla wielokolumnowych unique (limit MySQL 64 znaki)
- PL + EN i18n dla wszystkich nowych keys
- Po push'u: zawsze sprawdź czy PR rzeczywiście trafił na GitHub (`gh pr view` lub MCP get_status)
- Baseline testów: **1248 tests, 7 errors + 1 failure pre-existing** — nie cofaj się, ale flaguj jeśli rośnie

**Praca w trybie autonomicznym** — sam tworzysz PR-y, doprowadzasz CI do zieleni, pushed branches z prawidłowo opisanymi commit message + draft PR body. Po `<github-webhook-activity>` o merge ruszaj z kolejnym.

Zaczynamy.

---
