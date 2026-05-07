#!/usr/bin/env bash
# Hovera update — pobiera nowy kod z gita i odpala migracje + cache rebuild.
#
# Użycie:
#   ./update.sh                # pull origin/main + pełna aktualizacja
#   ./update.sh v1.2.3         # checkout konkretnego tagu / brancha
#   ./update.sh --skip-tenants # pomiń migracje na stajniach
#   ./update.sh --dry-run      # tylko pokaż co by się stało
#   ./update.sh --no-pull      # nie pull-uj (np. po manualnym checkout)
#
# To jest aliasem na deploy.sh — zachowane dla czytelności (update brzmi
# bardziej naturalnie z perspektywy administratora niż "deploy"). Pełna
# logika rolloutu (maintenance mode, queue restart, smoke test) siedzi
# w deploy.sh.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

[[ -f "$SCRIPT_DIR/deploy.sh" ]] \
    || { echo "[fail] Brak deploy.sh w $SCRIPT_DIR"; exit 1; }

exec bash "$SCRIPT_DIR/deploy.sh" "$@"
