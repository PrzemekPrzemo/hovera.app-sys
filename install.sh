#!/usr/bin/env bash
# Hovera installer — interaktywne wdrożenie świeżej instancji.
#
# Co robi (idempotentne):
#   1. Pre-flight: PHP 8.3+, Composer, ext-pdo_mysql, ext-bcmath
#   2. Pyta o domenę, bazy, mail, master admina
#   3. Generuje .env (jeśli istnieje — robi backup .env.{timestamp})
#   4. composer install --no-dev --optimize-autoloader (chyba że --skip-deps)
#   5. php artisan key:generate (tylko jeśli APP_KEY pusty)
#   6. php artisan migrate --force (central DB)
#   7. Tworzy master admina (php artisan hovera:admin:create)
#   8. Cache config/route/view
#
# Użycie:
#   bash install.sh                       # interaktywnie
#   bash install.sh --non-interactive     # czyta zmienne z env (HOVERA_*)
#   bash install.sh --skip-deps           # nie odpalaj composer install
#   bash install.sh --dry-run             # tylko pokaż co by się stało

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

NON_INTERACTIVE=false
SKIP_DEPS=false
DRY_RUN=false
for arg in "$@"; do
    case "$arg" in
        --non-interactive) NON_INTERACTIVE=true ;;
        --skip-deps) SKIP_DEPS=true ;;
        --dry-run) DRY_RUN=true ;;
        -h|--help)
            sed -n '2,22p' "$0" | sed 's/^# \?//'
            exit 0 ;;
    esac
done

# Gdy skrypt jest pipowany (`curl ... | bash` albo `exec`-iony z bootstrapa),
# stdin nie jest terminalem — `read` zwraca EOF i prompty się nie pokazują.
# Przekieruj stdin na /dev/tty żeby pytania działały.
if ! $NON_INTERACTIVE && [[ ! -t 0 ]] && [[ -r /dev/tty ]]; then
    exec < /dev/tty
fi

# ── Helpery ─────────────────────────────────────────────────────────
c_blue()  { printf '\033[36m%s\033[0m' "$*"; }
c_green() { printf '\033[32m%s\033[0m' "$*"; }
c_yel()   { printf '\033[33m%s\033[0m' "$*"; }
c_red()   { printf '\033[31m%s\033[0m' "$*"; }

log()  { printf '%s %s\n' "$(c_blue '[install]')" "$*"; }
ok()   { printf '%s %s\n' "$(c_green '  ✓')" "$*"; }
warn() { printf '%s %s\n' "$(c_yel '[warn]')" "$*"; }
fail() { printf '%s %s\n' "$(c_red '[fail]')" "$*" >&2; exit 1; }

run() {
    if $DRY_RUN; then
        printf '%s %s\n' "$(c_yel '[dry]')" "$*"
    else
        eval "$@"
    fi
}

# Pyta o wartość z domyślną. Użycie: ask VAR "Pytanie?" "default"
ask() {
    local __var="$1" __prompt="$2" __default="${3:-}"
    if $NON_INTERACTIVE; then
        eval "$__var=\"\${$__var:-$__default}\""
        return
    fi
    local __input
    if [[ -n "$__default" ]]; then
        read -r -p "$(c_blue '?') $__prompt [$__default]: " __input || true
        eval "$__var=\"\${__input:-$__default}\""
    else
        read -r -p "$(c_blue '?') $__prompt: " __input || true
        eval "$__var=\"\$__input\""
    fi
}

ask_secret() {
    local __var="$1" __prompt="$2"
    if $NON_INTERACTIVE; then
        eval "$__var=\"\${$__var:-}\""
        return
    fi
    local __input
    read -r -s -p "$(c_blue '?') $__prompt: " __input || true
    echo
    eval "$__var=\"\$__input\""
}

ask_choice() {
    local __var="$1" __prompt="$2" __default="$3"
    shift 3
    local __opts="$*"
    if $NON_INTERACTIVE; then
        eval "$__var=\"\${$__var:-$__default}\""
        return
    fi
    local __input
    read -r -p "$(c_blue '?') $__prompt ($__opts) [$__default]: " __input || true
    eval "$__var=\"\${__input:-$__default}\""
}

ask_yes_no() {
    local __var="$1" __prompt="$2" __default="${3:-y}"
    if $NON_INTERACTIVE; then
        eval "$__var=\"\${$__var:-$__default}\""
        return
    fi
    local __input
    read -r -p "$(c_blue '?') $__prompt [y/N]: " __input || true
    __input="${__input:-$__default}"
    case "$__input" in
        y|Y|yes|Yes|YES) eval "$__var=true" ;;
        *) eval "$__var=false" ;;
    esac
}

escape_env() {
    # Escapuje wartość do bezpiecznego zapisu w .env (otacza " i escapes \" $)
    local v="$1"
    v="${v//\\/\\\\}"
    v="${v//\"/\\\"}"
    printf '"%s"' "$v"
}

# ── 1. Pre-flight ───────────────────────────────────────────────────
log "Sprawdzam wymagania…"

# Auto-detect najnowszego PHP (Plesk-aware) i ustaw shim w PATH
[[ -f scripts/detect-php.sh ]] || fail "Brak scripts/detect-php.sh w repo."
# shellcheck source=scripts/detect-php.sh
. scripts/detect-php.sh
HOVERA_MIN_PHP=8.3 hovera_setup_php || fail "Wymagane PHP 8.3+ nie znalezione (sprawdź /opt/plesk/php/8.X/bin/php)."
ok "PHP $PHP_VERSION ($PHP_BIN)"

REQUIRED_EXT=(pdo_mysql mbstring openssl tokenizer xml ctype json)
RECOMMENDED_EXT=(bcmath gd intl curl fileinfo)
for ext in "${REQUIRED_EXT[@]}"; do
    "$PHP_BIN" -r "exit(extension_loaded('$ext') ? 0 : 1);" \
        || fail "Brakuje wymaganego rozszerzenia PHP: $ext"
done
for ext in "${RECOMMENDED_EXT[@]}"; do
    "$PHP_BIN" -r "exit(extension_loaded('$ext') ? 0 : 1);" \
        || warn "Zalecane rozszerzenie PHP nie jest załadowane: $ext (faktury / KSeF / GUS mogą działać niepoprawnie)"
done
ok "Rozszerzenia PHP OK"

if ! $SKIP_DEPS; then
    hovera_detect_composer || fail "Composer nie znaleziony."
    ok "Composer: $COMPOSER_BIN"
fi

[[ -f composer.json ]] || fail "Brak composer.json — uruchom skrypt z katalogu projektu."
[[ -f .env.example ]] || fail "Brak .env.example."

# ── 2. Pytania ──────────────────────────────────────────────────────
echo
log "Konfiguracja — odpowiedz na pytania (Enter = domyślne)."
echo

# Domena
HOVERA_APP_URL="${HOVERA_APP_URL:-}"
ask HOVERA_APP_URL "Domena aplikacji (z https://)" "https://app.hovera.app"
HOVERA_DOMAIN="${HOVERA_APP_URL#https://}"
HOVERA_DOMAIN="${HOVERA_DOMAIN#http://}"
HOVERA_DOMAIN="${HOVERA_DOMAIN%%/*}"

ask_choice HOVERA_APP_ENV "Środowisko" "production" "local|staging|production"
HOVERA_APP_DEBUG="false"
[[ "$HOVERA_APP_ENV" = "local" ]] && HOVERA_APP_DEBUG="true"

# DB central
echo
log "Baza danych centralna (hovera_core) — przechowuje listę stajni, użytkowników, plany."
ask HOVERA_DB_HOST "Host MySQL" "127.0.0.1"
ask HOVERA_DB_PORT "Port MySQL" "3306"
ask HOVERA_DB_CENTRAL_DB "Nazwa bazy centralnej" "hovera_core"
ask HOVERA_DB_CENTRAL_USER "Użytkownik bazy centralnej" "hovera_core"
ask_secret HOVERA_DB_CENTRAL_PASS "Hasło użytkownika centralnej bazy"
[[ -n "$HOVERA_DB_CENTRAL_PASS" ]] || fail "Hasło bazy centralnej jest wymagane."

# DB provisioner
echo
log "Provisioner — użytkownik MySQL z prawami CREATE/DROP DATABASE + GRANT (do tworzenia DB per stajnia)."
log "Możesz pominąć (provisionować ręcznie). Bez tego nie utworzysz nowej stajni z panelu."
ask_yes_no HOVERA_HAS_PROVISIONER "Skonfigurować provisionera teraz?" "y"
HOVERA_DB_PROV_USER=""
HOVERA_DB_PROV_PASS=""
if $HOVERA_HAS_PROVISIONER; then
    ask HOVERA_DB_PROV_USER "Użytkownik provisionera" "hovera_provisioner"
    ask_secret HOVERA_DB_PROV_PASS "Hasło provisionera"
fi

# Tenant DB prefiksy
ask HOVERA_TENANT_DB_PREFIX "Prefiks nazw baz tenantów" "hovera_t_"
ask HOVERA_TENANT_USER_PREFIX "Prefiks użytkowników tenantów" "hovera_t_"

# Mail
echo
log "Wysyłka maila."
ask_choice HOVERA_MAIL_DRIVER "Driver" "log" "log|smtp|resend"
HOVERA_MAIL_HOST="" HOVERA_MAIL_PORT="" HOVERA_MAIL_USER="" HOVERA_MAIL_PASS=""
HOVERA_MAIL_ENC="" HOVERA_RESEND_KEY=""
case "$HOVERA_MAIL_DRIVER" in
    smtp)
        ask HOVERA_MAIL_HOST "SMTP host" "smtp.postmarkapp.com"
        ask HOVERA_MAIL_PORT "SMTP port" "587"
        ask HOVERA_MAIL_USER "SMTP login"
        ask_secret HOVERA_MAIL_PASS "SMTP hasło"
        ask_choice HOVERA_MAIL_ENC "SMTP encryption" "tls" "tls|ssl|null"
        ;;
    resend)
        ask_secret HOVERA_RESEND_KEY "Resend API key (re_…)"
        ;;
esac
ask HOVERA_MAIL_FROM "Adres nadawcy" "no-reply@${HOVERA_DOMAIN}"
ask HOVERA_MAIL_FROM_NAME "Nazwa nadawcy" "Hovera"

# Master admin
echo
log "Master admin — pełen dostęp do panelu /admin (zarządzanie stajniami)."
ask HOVERA_ADMIN_EMAIL "Email master admina"
ask HOVERA_ADMIN_NAME "Imię i nazwisko" "Master Admin"
ask_secret HOVERA_ADMIN_PASS "Hasło (min. 12 znaków)"
[[ -n "$HOVERA_ADMIN_EMAIL" && -n "$HOVERA_ADMIN_PASS" ]] || fail "Email i hasło master admina są wymagane."
[[ ${#HOVERA_ADMIN_PASS} -ge 12 ]] || fail "Hasło master admina musi mieć min. 12 znaków."

# Podsumowanie
echo
log "Podsumowanie:"
echo "  Domena:           $HOVERA_APP_URL"
echo "  Środowisko:       $HOVERA_APP_ENV"
echo "  DB centralna:     $HOVERA_DB_CENTRAL_USER@$HOVERA_DB_HOST:$HOVERA_DB_PORT/$HOVERA_DB_CENTRAL_DB"
echo "  Provisioner:      $($HOVERA_HAS_PROVISIONER && echo "$HOVERA_DB_PROV_USER" || echo '— pominięty —')"
echo "  Mail:             $HOVERA_MAIL_DRIVER (from $HOVERA_MAIL_FROM)"
echo "  Master admin:     $HOVERA_ADMIN_EMAIL"
echo

if ! $NON_INTERACTIVE; then
    ask_yes_no HOVERA_CONFIRM "Kontynuować?" "y"
    $HOVERA_CONFIRM || { warn "Anulowano."; exit 0; }
fi

# ── 3. .env ─────────────────────────────────────────────────────────
echo
log "Generuję .env…"
if [[ -f .env ]]; then
    BACKUP=".env.$(date +%Y%m%d_%H%M%S).bak"
    run "cp .env $BACKUP"
    ok "Backup poprzedniego .env → $BACKUP"
fi

ENV_TMP="$(mktemp)"
cat > "$ENV_TMP" <<EOF
APP_NAME=Hovera
APP_ENV=${HOVERA_APP_ENV}
APP_KEY=
APP_DEBUG=${HOVERA_APP_DEBUG}
APP_TIMEZONE=Europe/Warsaw
APP_URL=${HOVERA_APP_URL}

APP_LOCALE=pl
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=pl_PL

APP_MAINTENANCE_DRIVER=file
PHP_CLI_SERVER_WORKERS=4
BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=$([[ "$HOVERA_APP_ENV" = "production" ]] && echo "warning" || echo "debug")

DB_CONNECTION=central

DB_CENTRAL_DRIVER=mysql
DB_CENTRAL_HOST=${HOVERA_DB_HOST}
DB_CENTRAL_PORT=${HOVERA_DB_PORT}
DB_CENTRAL_DATABASE=${HOVERA_DB_CENTRAL_DB}
DB_CENTRAL_USERNAME=${HOVERA_DB_CENTRAL_USER}
DB_CENTRAL_PASSWORD=$(escape_env "$HOVERA_DB_CENTRAL_PASS")

DB_TENANT_HOST=${HOVERA_DB_HOST}
DB_TENANT_PORT=${HOVERA_DB_PORT}

DB_PROVISIONER_HOST=${HOVERA_DB_HOST}
DB_PROVISIONER_PORT=${HOVERA_DB_PORT}
DB_PROVISIONER_USERNAME=${HOVERA_DB_PROV_USER}
DB_PROVISIONER_PASSWORD=$(escape_env "$HOVERA_DB_PROV_PASS")

HOVERA_TENANT_DB_PREFIX=${HOVERA_TENANT_DB_PREFIX}
HOVERA_TENANT_USER_PREFIX=${HOVERA_TENANT_USER_PREFIX}
HOVERA_TENANT_DB_HOST=${HOVERA_DB_HOST}

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=${HOVERA_DOMAIN}
SESSION_SECURE_COOKIE=$([[ "${HOVERA_APP_URL}" =~ ^https:// ]] && echo "true" || echo "false")
SESSION_SAME_SITE=lax

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database

CACHE_STORE=database
CACHE_PREFIX=hovera

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=${HOVERA_MAIL_DRIVER}
MAIL_HOST=${HOVERA_MAIL_HOST:-127.0.0.1}
MAIL_PORT=${HOVERA_MAIL_PORT:-2525}
MAIL_USERNAME=$(escape_env "${HOVERA_MAIL_USER}")
MAIL_PASSWORD=$(escape_env "${HOVERA_MAIL_PASS}")
MAIL_ENCRYPTION=${HOVERA_MAIL_ENC:-null}
MAIL_FROM_ADDRESS="${HOVERA_MAIL_FROM}"
MAIL_FROM_NAME="${HOVERA_MAIL_FROM_NAME}"
RESEND_KEY=$(escape_env "${HOVERA_RESEND_KEY}")

HOVERA_ADMIN_PATH=admin
HOVERA_ADMIN_REQUIRE_2FA=true
HOVERA_PUBLIC_SITE_PREFIX=s

VITE_APP_NAME=Hovera
EOF

if $DRY_RUN; then
    log "[dry] zapisałbym .env z $(wc -l < "$ENV_TMP") liniami"
else
    mv "$ENV_TMP" .env
    chmod 640 .env
    ok ".env zapisany"
fi

# ── 4. Composer ─────────────────────────────────────────────────────
if ! $SKIP_DEPS; then
    echo
    log "Instaluję zależności (composer install)…"
    if [[ "$HOVERA_APP_ENV" = "production" ]]; then
        run "$COMPOSER_BIN install --no-dev --optimize-autoloader --no-interaction"
    else
        run "$COMPOSER_BIN install --no-interaction"
    fi
    ok "Zależności zainstalowane"
fi

# ── 5. APP_KEY ──────────────────────────────────────────────────────
if ! $DRY_RUN && [[ -z "$(grep '^APP_KEY=' .env | cut -d= -f2-)" ]]; then
    echo
    log "Generuję APP_KEY…"
    run "$PHP_BIN artisan key:generate --force"
    ok "APP_KEY ustawiony"
fi

# ── 6. Migrations ───────────────────────────────────────────────────
echo
log "Uruchamiam migracje (centralna baza)…"
run "$PHP_BIN artisan migrate --force"
ok "Migracje zakończone"

# ── 7. Storage link ─────────────────────────────────────────────────
if [[ ! -L public/storage ]]; then
    log "Tworzę symlink public/storage…"
    run "$PHP_BIN artisan storage:link" || warn "Nie udało się — utwórz ręcznie."
fi

# ── 8. Master admin ─────────────────────────────────────────────────
echo
log "Tworzę master admina…"
if $DRY_RUN; then
    log "[dry] php artisan hovera:admin:create $HOVERA_ADMIN_EMAIL ... --update"
else
    "$PHP_BIN" artisan hovera:admin:create \
        "$HOVERA_ADMIN_EMAIL" \
        "$HOVERA_ADMIN_NAME" \
        --password="$HOVERA_ADMIN_PASS" \
        --update \
        || fail "Nie udało się utworzyć master admina."
fi

# ── 9. Cache ─────────────────────────────────────────────────────────
if [[ "$HOVERA_APP_ENV" = "production" ]] || [[ "$HOVERA_APP_ENV" = "staging" ]]; then
    echo
    log "Buduję cache…"
    run "$PHP_BIN artisan config:cache"
    run "$PHP_BIN artisan route:cache"
    run "$PHP_BIN artisan view:cache"
    run "$PHP_BIN artisan event:cache"
    ok "Cache gotowy"
fi

# ── Done ────────────────────────────────────────────────────────────
echo
ok "Instalacja zakończona."
echo
echo "Następne kroki:"
echo "  1. Zaloguj się: ${HOVERA_APP_URL}/admin (login: ${HOVERA_ADMIN_EMAIL})"
echo "  2. Włącz 2FA na koncie master admina."
echo "  3. Utwórz pierwszą stajnię: php artisan tenant:create <slug> \"Nazwa\" --owner-email=…"
echo "  4. Po dodaniu stajni odpal migracje tenantów: php artisan tenants:migrate"
echo "  5. Skonfiguruj cron (kolejka + cyklicze zadania):"
echo "     * * * * * cd $SCRIPT_DIR && php artisan schedule:run >> /dev/null 2>&1"
echo
echo "Aktualizacje (pull z gita + migracje + cache):"
echo "  ./update.sh                  # pull main + pełen rollout"
echo "  ./update.sh v1.2.3           # konkretny tag"
echo "  ./update.sh --dry-run        # zobacz co by się stało"
echo
