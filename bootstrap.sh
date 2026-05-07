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
# Z flagami (czysta reinstalacja — wyczyść katalog + DROP all tables):
#   curl -sSL https://raw.githubusercontent.com/.../bootstrap.sh | bash -s -- --fresh
#
# Jeśli skrypt jest już lokalnie:
#   bash bootstrap.sh
#   bash bootstrap.sh --fresh

set -euo pipefail

REPO_URL="${HOVERA_REPO_URL:-https://github.com/PrzemekPrzemo/hovera.app-sys.git}"
INSTALL_DIR="${HOVERA_INSTALL_DIR:-}"
GIT_REF="${HOVERA_GIT_REF:-main}"
FRESH=false

# Parsuj nasze flagi (reszta jest forwardowana do install.sh)
for arg in "$@"; do
    case "$arg" in
        --fresh) FRESH=true ;;
    esac
done

# `curl ... | bash` — bash czyta CIAŁO SKRYPTU ze stdin (z pipe), więc
# globalne `exec < /dev/tty` zerwałoby dostęp do reszty skryptu (bash
# zaczynałby czytać komendy z klawiatury).
# Zamiast tego używamy `read ... < /dev/tty` per-prompt — pod warunkiem,
# że tty rzeczywiście da się otworzyć (samo `[[ -r /dev/tty ]]` kłamie
# w środowiskach bez controlling terminala — np. CI / cron).
TTY_INPUT=""
if (exec </dev/tty) 2>/dev/null; then
    TTY_INPUT="/dev/tty"
fi

# read_tty VAR "prompt"  — `read` z tty (nie ze stdin, bo tam leci kod skryptu)
read_tty() {
    local __var="$1" __prompt="$2"
    if [[ -n "$TTY_INPUT" ]]; then
        read -r -p "$__prompt" "$__var" < "$TTY_INPUT" 2>/dev/null || eval "$__var=\"\""
    elif [[ -t 0 ]]; then
        read -r -p "$__prompt" "$__var" || eval "$__var=\"\""
    else
        # Brak tty i stdin to pipe — nie ma jak pytać, użyj defaultów
        eval "$__var=\"\""
    fi
}

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
# Default detection priorytety:
#   1. HOVERA_INSTALL_DIR z env
#   2. Plesk: jeśli pwd jest /var/www/vhosts/<domena>[/httpdocs], sugeruj
#      /var/www/vhosts/<domena>/httpdocs jako default (Plesk webroot)
#   3. ~/hovera
PLESK_VHOST=""
if [[ -z "$INSTALL_DIR" ]]; then
    pwd_real="$(pwd -P)"
    if [[ "$pwd_real" =~ ^/var/www/vhosts/([^/]+)(/.*)?$ ]]; then
        PLESK_VHOST="${BASH_REMATCH[1]}"
        DEFAULT_DIR="/var/www/vhosts/$PLESK_VHOST/httpdocs"
        log "Wykryto Plesk vhost: $PLESK_VHOST → sugeruję $DEFAULT_DIR"
    elif [[ -d /var/www/vhosts && -d /opt/psa ]]; then
        # Plesk jest, ale nie jesteśmy w żadnym vhoście — pokaż listę
        DEFAULT_DIR="$HOME/hovera"
        log "Plesk wykryty. Dostępne vhosty:"
        for d in /var/www/vhosts/*/httpdocs; do
            [[ -d "$d" ]] && echo "    - $d"
        done
    else
        DEFAULT_DIR="$HOME/hovera"
    fi

    read_tty INSTALL_DIR "$(c_blue '?') Katalog instalacji [$DEFAULT_DIR]: "
    INSTALL_DIR="${INSTALL_DIR:-$DEFAULT_DIR}"
fi

# Rozwiń ~ jeśli ktoś podał z tyldą
INSTALL_DIR="${INSTALL_DIR/#\~/$HOME}"

log "Cel: $INSTALL_DIR  (ref: $GIT_REF)"

# ── klonowanie / aktualizacja ───────────────────────────────────────
if [[ -d "$INSTALL_DIR/.git" ]] && $FRESH; then
    log "Repo istnieje, ale --fresh: czyszczę $INSTALL_DIR i klonuję od zera."
    find "$INSTALL_DIR" -mindepth 1 -delete
    git clone --branch "$GIT_REF" --single-branch "$REPO_URL" "$INSTALL_DIR"
    cd "$INSTALL_DIR"
    ok "Kod sklonowany od zera ($(git rev-parse --short HEAD))"
elif [[ -d "$INSTALL_DIR/.git" ]]; then
    log "Repo już istnieje — robię git fetch + checkout zamiast klonować."
    cd "$INSTALL_DIR"
    git fetch --tags --prune origin
    git checkout "$GIT_REF"
    git pull --ff-only origin "$GIT_REF" || true
    ok "Kod zaktualizowany do $(git rev-parse --short HEAD)"
elif [[ -d "$INSTALL_DIR" && -n "$(ls -A "$INSTALL_DIR" 2>/dev/null)" ]]; then
    # Wykryj typowe Plesk welcome-files i zaproponuj cleanup
    PLESK_DEFAULT_PATTERN='^(index\.html|index\.htm|favicon\.ico|robots\.txt|.*plesk.*|.*\.png|.*\.jpg|.*\.css)$'
    has_only_default_files=true
    for f in "$INSTALL_DIR"/* "$INSTALL_DIR"/.[!.]*; do
        [[ -e "$f" ]] || continue
        bn="$(basename "$f")"
        if ! [[ "$bn" =~ $PLESK_DEFAULT_PATTERN ]]; then
            has_only_default_files=false
            break
        fi
    done

    if $has_only_default_files; then
        log "$INSTALL_DIR zawiera tylko domyślne pliki Pleska:"
        ls -la "$INSTALL_DIR" | tail -n +2
        read_tty __confirm "$(c_blue '?') Wyczyścić katalog i zainstalować Hoverę? [y/N]: "
        case "${__confirm:-n}" in
            y|Y|yes|Yes|YES)
                log "Czyszczę $INSTALL_DIR…"
                find "$INSTALL_DIR" -mindepth 1 -delete
                ;;
            *) fail "Anulowano. Wyczyść katalog ręcznie: rm -rf $INSTALL_DIR/{*,.[!.]*}" ;;
        esac
    else
        fail "$INSTALL_DIR istnieje i nie jest puste (zawiera nie-Pleskowe pliki). Zwolnij katalog albo wybierz inny."
    fi

    log "Klonuję $REPO_URL → $INSTALL_DIR"
    git clone --branch "$GIT_REF" --single-branch "$REPO_URL" "$INSTALL_DIR"
    cd "$INSTALL_DIR"
    ok "Kod sklonowany ($(git rev-parse --short HEAD))"
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
