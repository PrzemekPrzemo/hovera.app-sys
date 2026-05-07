#!/usr/bin/env bash
# Hovera bootstrap — pobiera świeży kod z gita i odpala interaktywny installer.
#
# Idea: jeden polecenie z czystego serwera → działająca instancja.
#
# Użycie (online, najprostsze):
#   curl -sSL https://raw.githubusercontent.com/PrzemekPrzemo/hovera.app-sys/main/bootstrap.sh | bash
#
# Użycie z parametrami (przez env):
#   HOVERA_INSTALL_DIR=/var/www/hovera \
#   HOVERA_GIT_REF=v1.2.3 \
#   bash <(curl -sSL https://raw.githubusercontent.com/PrzemekPrzemo/hovera.app-sys/main/bootstrap.sh)
#
# Jeśli skrypt jest już lokalnie:
#   bash bootstrap.sh

set -euo pipefail

REPO_URL="${HOVERA_REPO_URL:-https://github.com/PrzemekPrzemo/hovera.app-sys.git}"
INSTALL_DIR="${HOVERA_INSTALL_DIR:-}"
GIT_REF="${HOVERA_GIT_REF:-main}"

# `curl ... | bash` pipuje skrypt przez stdin, więc `read` nie ma do czego
# zaglądać. Otwórz stdin na /dev/tty żeby prompty działały (i exec do
# install.sh dziedziczył działający stdin).
if [[ ! -t 0 ]] && [[ -r /dev/tty ]]; then
    exec < /dev/tty
fi

# ── kolory ──────────────────────────────────────────────────────────
c_blue()  { printf '\033[36m%s\033[0m' "$*"; }
c_green() { printf '\033[32m%s\033[0m' "$*"; }
c_red()   { printf '\033[31m%s\033[0m' "$*"; }
log()  { printf '%s %s\n' "$(c_blue '[bootstrap]')" "$*"; }
ok()   { printf '%s %s\n' "$(c_green '  ✓')" "$*"; }
fail() { printf '%s %s\n' "$(c_red '[fail]')" "$*" >&2; exit 1; }

# ── pre-flight ──────────────────────────────────────────────────────
log "Sprawdzam wymagania bootstrap…"
command -v git >/dev/null 2>&1 || fail "Brak gita. Zainstaluj: apt install git (Debian/Ubuntu) lub yum install git."
ok "git $(git --version | awk '{print $3}')"

# Wstępne sprawdzenie czy GDZIEKOLWIEK jest PHP 8.2+ (przed klonem,
# żeby nie zaśmiecać dysku jeśli i tak zaraz padnie). Pełne wykrycie
# robi install.sh przez scripts/detect-php.sh.
_have_php=false
for cmd in php php8.5 php8.4 php8.3 php8.2; do
    if command -v "$cmd" >/dev/null 2>&1; then
        v="$("$cmd" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || true)"
        if [[ "$v" =~ ^8\.[2-9]$ ]] || [[ "$v" =~ ^[9-9]\. ]]; then
            _have_php=true
            log "PHP $v wykryty jako: $(command -v $cmd)"
            break
        fi
    fi
done
if ! $_have_php; then
    for v in 8.5 8.4 8.3 8.2; do
        for p in "/opt/plesk/php/$v/bin/php" "/usr/local/php$v/bin/php" "/usr/bin/php$v"; do
            if [[ -x "$p" ]]; then
                _have_php=true
                log "PHP $v wykryty jako: $p"
                break 2
            fi
        done
    done
fi
$_have_php || fail "Brak PHP 8.2+ na serwerze (sprawdziłem PATH + /opt/plesk/php/8.X/bin/php). Zainstaluj PHP."

# ── katalog docelowy ────────────────────────────────────────────────
if [[ -z "$INSTALL_DIR" ]]; then
    DEFAULT_DIR="$HOME/hovera"
    if [[ -t 0 ]]; then
        read -r -p "$(c_blue '?') Katalog instalacji [$DEFAULT_DIR]: " INSTALL_DIR
        INSTALL_DIR="${INSTALL_DIR:-$DEFAULT_DIR}"
    else
        INSTALL_DIR="$DEFAULT_DIR"
    fi
fi

# Rozwiń ~ jeśli ktoś podał z tyldą
INSTALL_DIR="${INSTALL_DIR/#\~/$HOME}"

log "Cel: $INSTALL_DIR  (ref: $GIT_REF)"

# ── klonowanie / aktualizacja ───────────────────────────────────────
if [[ -d "$INSTALL_DIR/.git" ]]; then
    log "Repo już istnieje — robię git fetch + checkout zamiast klonować."
    cd "$INSTALL_DIR"
    git fetch --tags --prune origin
    git checkout "$GIT_REF"
    git pull --ff-only origin "$GIT_REF" || true
    ok "Kod zaktualizowany do $(git rev-parse --short HEAD)"
elif [[ -d "$INSTALL_DIR" && -n "$(ls -A "$INSTALL_DIR" 2>/dev/null)" ]]; then
    fail "$INSTALL_DIR istnieje i nie jest puste (ani nie jest repo gita). Zwolnij katalog albo wybierz inny."
else
    log "Klonuję $REPO_URL → $INSTALL_DIR"
    git clone --branch "$GIT_REF" --single-branch "$REPO_URL" "$INSTALL_DIR"
    cd "$INSTALL_DIR"
    ok "Kod sklonowany ($(git rev-parse --short HEAD))"
fi

# ── installer ───────────────────────────────────────────────────────
[[ -f install.sh ]] || fail "Brak install.sh w pobranym repo (oczekiwane w $INSTALL_DIR)."
chmod +x install.sh

echo
log "Odpalam interaktywny installer (install.sh)…"
echo

exec bash install.sh "$@"
