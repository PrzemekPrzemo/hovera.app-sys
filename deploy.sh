#!/usr/bin/env bash
# Hovera deploy — bezpieczny, idempotentny rollout dla app.hovera.app.
#
# Użycie (SSH):
#   ./deploy.sh                     # pull origin/main + full deploy
#   ./deploy.sh v1.2.3              # checkout konkretnego tagu
#   ./deploy.sh --skip-tenants      # pomiń migrate na tenantach
#   ./deploy.sh --no-pull           # nie pull-uj (np. po manualnym checkout)
#   ./deploy.sh --dry-run           # tylko wypisz co by się stało
#
# Użycie (Plesk Git → "Additional deployment actions"):
#   bash deploy.sh --no-pull
#   (Plesk już zrobił git pull przed odpaleniem; dajemy --no-pull żeby nie ścierało
#    się z ich logiką, oraz --skip-permissions jeśli Plesk już to ogarnął.)
#
# Wymagania na serwerze (jednorazowo, patrz docs/DEPLOY.md):
#   - PHP 8.3 (CLI; Plesk → Tools & Settings → PHP)
#   - Composer 2.x (Plesk extension lub OS-level)
#   - Git CLI (zwykle pre-installed na Plesku)
#   - .env z prawidłowymi credentials
#
# Skrypt jest non-destructive — można odpalać wielokrotnie. Każdy krok jest idempotentny.

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

[[ -f .env ]] || fail "Brakuje .env — skopiuj .env.example, uzupełnij i ponów (Plesk → File Manager → Edit)."

# Auto-detect najnowszego PHP (Plesk-aware, /opt/plesk/php/8.X/bin/php) +
# composera. Wstawia shim w PATH żeby `php` w subprocesach był 8.3+.
[[ -f scripts/detect-php.sh ]] || fail "Brak scripts/detect-php.sh."
# shellcheck source=scripts/detect-php.sh
. scripts/detect-php.sh
HOVERA_MIN_PHP=8.4 hovera_setup_php || fail "Wymagany PHP 8.4+ — sprawdź /opt/plesk/php/8.X/bin/php (composer.lock wymaga 8.4)."
hovera_detect_composer || fail "Brak composera (PATH ani /opt/plesk/composer/composer.phar)."
COMPOSER="$COMPOSER_BIN"
log "Composer: $COMPOSER"

command -v git >/dev/null || fail "Brak git w PATH (eskaluj do hostingu — apt install git)."

# ── Maintenance mode ────────────────────────────────────────────────
log "→ Maintenance mode ON"
run "$PHP_BIN artisan down --render='errors::503' --retry=15 || true"

trap 'log "→ Maintenance mode OFF (cleanup)"; "$PHP_BIN" artisan up || true' EXIT

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
run "$COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader"

# ── Permissions ────────────────────────────────────────────────────
# Po `git pull` nowe pliki + katalogi należą do user'a SSH. PHP-FPM
# (np. `psaserv` na Plesku, `www-data` na VPS) musi mieć write żeby:
#   - kompilować Blade views → storage/framework/views/*.php
#   - cachować data → storage/framework/cache/*
#   - logować → storage/logs/*
# Stąd recursive chmod 775 + sticky bit (2755) na katalogach żeby nowe
# pliki dziedziczyły group ownership. Chown używa HOVERA_VHOST_USER/GROUP
# z scripts/detect-env.sh (Plesk) lub fallback www-data (VPS).
log "→ Permissions storage + bootstrap/cache"
HOVERA_VHOST_USER="${HOVERA_VHOST_USER:-www-data}"
HOVERA_VHOST_GROUP="${HOVERA_VHOST_GROUP:-www-data}"

# Chown wymaga roota lub sudo. Bez tego deploy "udaje sukces" ale PHP-FPM
# nadal nie może pisać — i potem klient dostaje "Permission denied" przy
# kompilacji Blade. Wyraźnie informujemy zamiast cichego `|| true`.
if [[ "$EUID" -eq 0 ]]; then
    CHOWN_CMD="chown"
elif command -v sudo >/dev/null 2>&1; then
    CHOWN_CMD="sudo -n chown"  # -n = non-interactive (fail jeśli wymaga hasła)
else
    CHOWN_CMD=""
fi

if [[ -n "$CHOWN_CMD" ]]; then
    if ! run "$CHOWN_CMD -R ${HOVERA_VHOST_USER}:${HOVERA_VHOST_GROUP} storage bootstrap/cache" 2>&1; then
        warn "chown failed — sprawdź czy uruchamiasz deploy jako root lub user z sudo NOPASSWD."
        warn "Bez chown PHP-FPM nie zapisze do storage/. Manual fix:"
        warn "  sudo chown -R ${HOVERA_VHOST_USER}:${HOVERA_VHOST_GROUP} storage bootstrap/cache"
    fi
else
    warn "Brak uprawnień do chown (nie root + brak sudo). Storage permissions mogą być błędne."
    warn "Manual fix po deploy: sudo chown -R ${HOVERA_VHOST_USER}:${HOVERA_VHOST_GROUP} storage bootstrap/cache"
fi

run "chmod -R u+rwX,g+rwX,o+rX storage bootstrap/cache"
run "find storage bootstrap/cache -type d -exec chmod 2775 {} \\; 2>/dev/null || true"

# ── Cache wipe + warm ───────────────────────────────────────────────
log "→ Cache config + routes + views"
# Fizyczne usunięcie compiled views — `view:clear` czasem zostawia pliki
# z starym ownerem (root) jeśli artisan był odpalany jako root przy
# pierwszym installu. Bez tego nowe compile trafiało na "permission
# denied" mimo że chown wyżej naprawił katalogi.
run "rm -rf storage/framework/views/*.php 2>/dev/null || true"
run "rm -rf storage/framework/cache/data/* 2>/dev/null || true"
run "$PHP_BIN artisan config:clear"
run "$PHP_BIN artisan route:clear"
run "$PHP_BIN artisan view:clear"
run "$PHP_BIN artisan event:clear || true"
run "$PHP_BIN artisan cache:clear || true"
run "$PHP_BIN artisan config:cache"
run "$PHP_BIN artisan route:cache"
run "$PHP_BIN artisan view:cache"
run "$PHP_BIN artisan event:cache || true"

# Filament 3 cachuje component map (Resource / Page / Widget classes) —
# bez tego po deploy nowych Resource klas Livewire mówi „Unable to find
# component" + brakuje ich w sidebar nav master admina. `|| true` bo
# w starszych wersjach Filament command może nie istnieć.
run "$PHP_BIN artisan filament:cache-components || true"

# ── Migracje ───────────────────────────────────────────────────────
log "→ Migracje central"
run "$PHP_BIN artisan migrate --force"

if ! $SKIP_TENANTS; then
    log "→ Migracje tenantów (wszystkie aktywne)"
    run "$PHP_BIN artisan tenants:migrate"

    # Regeneruj schema dump po migracjach — Provisioner użyje tego pliku
    # zamiast biegać przez migracje przy tworzeniu nowego tenanta (5min → 5s).
    log "→ Regeneruję database/tenant-schema.sql (dla szybkiego provisioning)"
    run "$PHP_BIN artisan hovera:tenant:dump-schema || true"
else
    warn "Pominięto migracje tenantów + schema dump (--skip-tenants)"
fi

# ── Storage symlink ────────────────────────────────────────────────
if [[ ! -L public/storage ]]; then
    log "→ storage:link (pierwszy raz)"
    run "$PHP_BIN artisan storage:link"
fi

# ── Filament assets ─────────────────────────────────────────────────
log "→ filament:assets"
run "$PHP_BIN artisan filament:assets"

# ── Restart workerów (jeśli queue worker jest uruchomiony) ──────────
log "→ queue:restart (sygnał do running workerów)"
run "$PHP_BIN artisan queue:restart"

# ── Restart FPM pool (auto-detect via detect-env.sh) ────────────────
# OPcache trzyma stary bytecode po deploy → restart pool jest WAŻNY,
# inaczej wprowadzone zmiany w kodzie mogą się nie aplikować.
if [[ -f scripts/detect-env.sh ]]; then
    # shellcheck source=scripts/detect-env.sh
    . scripts/detect-env.sh
    hovera_detect_environment 2>/dev/null || true
    if [[ -n "$HOVERA_FPM_SERVICE" ]]; then
        log "→ Restart FPM pool: $HOVERA_FPM_SERVICE"
        run "systemctl restart $HOVERA_FPM_SERVICE 2>/dev/null || true"
    fi
fi

# ── Maintenance OFF ─────────────────────────────────────────────────
trap - EXIT
log "→ Maintenance mode OFF"
run "$PHP_BIN artisan up"

# ── Smoke test ──────────────────────────────────────────────────────
log "→ Smoke test"
run "$PHP_BIN artisan about --only=environment"

log "✓ Deploy OK — rewizja $CURRENT_REV"
log "Sprawdź ręcznie:"
log "   curl -I https://app.hovera.app          # 200 / 302"
log "   curl -I https://app.hovera.app/admin    # 302 → /admin/login"
