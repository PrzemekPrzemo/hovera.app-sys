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
command -v php >/dev/null 2>&1 || fail "Brak PHP CLI 8.3+ — zainstaluj PHP zanim ruszysz."
ok "git $(git --version | awk '{print $3}')"

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
