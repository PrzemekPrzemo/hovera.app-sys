#!/usr/bin/env bash
# Hovera deploy — bezpieczny, idempotentny rollout dla app.hovera.app.
#
# Użycie:
#   ./deploy.sh                     # pull origin/main + full deploy
#   ./deploy.sh v1.2.3              # checkout konkretnego tagu
#   ./deploy.sh --skip-tenants      # pomiń migrate na tenantach
#   ./deploy.sh --no-pull           # nie pull-uj (np. po manualnym checkout)
#   ./deploy.sh --dry-run           # tylko wypisz co by się stało
#
# Wymagania na serwerze (jednorazowo):
#   - PHP 8.3+ z rozszerzeniami: pdo_mysql, mbstring, bcmath, intl, openssl, tokenizer, xml, ctype, json, fileinfo, curl, redis (opcjonalne)
#   - composer 2.x w PATH
#   - git
#   - node + npm (tylko jeśli budujemy frontend assets — dziś Filament nie wymaga)
#   - mysql client (do health check)
#   - .env z prawidłowymi credentials (patrz docs/DEPLOY.md)
#
# Skrypt można uruchamiać jako użytkownik domeny (np. hovera_app) — NIE jako root.

set -euo pipefail

# ── Konfiguracja ────────────────────────────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

REF="${1:-}"
SKIP_TENANTS=false
NO_PULL=false
DRY_RUN=false

for arg in "$@"; do
    case "$arg" in
        --skip-tenants) SKIP_TENANTS=true ;;
        --no-pull) NO_PULL=true ;;
        --dry-run) DRY_RUN=true ;;
    esac
done

# Pierwszy pozycyjny arg (jeśli nie jest flagą) traktujemy jako git ref
if [[ -n "$REF" && "$REF" == --* ]]; then
    REF=""
fi

# ── Helpery ─────────────────────────────────────────────────────────
log()  { printf '\033[36m[deploy]\033[0m %s\n' "$*"; }
warn() { printf '\033[33m[warn]\033[0m %s\n' "$*"; }
fail() { printf '\033[31m[fail]\033[0m %s\n' "$*" >&2; exit 1; }
run()  {
    if $DRY_RUN; then
        printf '\033[35m[dry]\033[0m %s\n' "$*"
    else
        eval "$@"
    fi
}

# ── Pre-flight ──────────────────────────────────────────────────────
log "Hovera deploy startuje w $SCRIPT_DIR"

[[ "$(id -u)" == "0" ]] && fail "Nie uruchamiaj skryptu jako root (użyj usera domeny w Plesku)."
[[ -f .env ]] || fail "Brakuje .env — skopiuj .env.example, uzupełnij i ponów."
command -v php >/dev/null || fail "Brak PHP w PATH."
command -v composer >/dev/null || fail "Brak composer w PATH."
command -v git >/dev/null || fail "Brak git w PATH."

php_version=$(php -r 'echo PHP_VERSION;')
log "PHP: $php_version"
php -r 'exit(version_compare(PHP_VERSION, "8.2", ">=") ? 0 : 1);' \
    || fail "Wymagany PHP 8.2+. Skonfiguruj odpowiednią wersję w Plesku (Domain → PHP Settings)."

# ── Maintenance mode ────────────────────────────────────────────────
log "→ Maintenance mode ON"
run "php artisan down --render='errors::503' --retry=15 || true"

trap 'log "→ Maintenance mode OFF (cleanup)"; php artisan up || true' EXIT

# ── Git ─────────────────────────────────────────────────────────────
if ! $NO_PULL; then
    log "→ git fetch + checkout"
    run "git fetch --tags --prune"
    if [[ -n "$REF" ]]; then
        run "git checkout '$REF'"
    else
        # Domyślnie origin/main, fast-forward only
        run "git checkout main"
        run "git pull --ff-only origin main"
    fi
fi

CURRENT_REV=$(git rev-parse --short HEAD)
log "Wdrożenie rewizji: $CURRENT_REV"

# ── Composer ────────────────────────────────────────────────────────
log "→ composer install (production)"
run "composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader"

# ── Permissions ────────────────────────────────────────────────────
log "→ Permissions storage + bootstrap/cache"
run "chmod -R ug+rw storage bootstrap/cache"

# ── Cache wipe + warm ───────────────────────────────────────────────
log "→ Cache config + routes + views"
run "php artisan config:clear"
run "php artisan route:clear"
run "php artisan view:clear"
run "php artisan event:clear || true"
run "php artisan cache:clear || true"
run "php artisan config:cache"
run "php artisan route:cache"
run "php artisan view:cache"
run "php artisan event:cache || true"

# ── Migracje ───────────────────────────────────────────────────────
log "→ Migracje central"
run "php artisan migrate --force"

if ! $SKIP_TENANTS; then
    log "→ Migracje tenantów (wszystkie aktywne)"
    run "php artisan tenants:migrate"
else
    warn "Pominięto migracje tenantów (--skip-tenants)"
fi

# ── Storage symlink ────────────────────────────────────────────────
if [[ ! -L public/storage ]]; then
    log "→ storage:link (pierwszy raz)"
    run "php artisan storage:link"
fi

# ── Filament assets ─────────────────────────────────────────────────
log "→ filament:assets"
run "php artisan filament:assets"

# ── Restart workerów (jeśli queue worker jest uruchomiony) ──────────
log "→ queue:restart (sygnał do running workerów)"
run "php artisan queue:restart"

# ── Maintenance OFF ─────────────────────────────────────────────────
trap - EXIT
log "→ Maintenance mode OFF"
run "php artisan up"

# ── Smoke test ──────────────────────────────────────────────────────
log "→ Smoke test"
run "php artisan about --only=environment"

log "✓ Deploy OK — rewizja $CURRENT_REV"
log "Sprawdź ręcznie:"
log "   curl -I https://app.hovera.app          # 200 / 302"
log "   curl -I https://app.hovera.app/admin    # 302 → /admin/login"
