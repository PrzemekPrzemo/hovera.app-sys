#!/usr/bin/env bash
# Hovera — auto-wykrywanie najwyższej wersji PHP 8.2+.
#
# Typowy hosting (zwłaszcza Plesk) ma w PATH starszego PHP (7.4 / 8.0)
# a 8.3 / 8.4 leżą w /opt/plesk/php/8.X/bin/. Ten skrypt znajduje
# najnowszego dostępnego PHP-a, exportuje:
#   $PHP_BIN        — pełna ścieżka
#   $PHP_VERSION    — np. "8.4"
#   $COMPOSER_BIN   — composer (detekcja Plesk + PATH)
# i prependuje shim dir do PATH (`php` → PHP_BIN), żeby composer
# i wszystkie subprocesy używały właściwej wersji.
#
# Używany przez: bootstrap.sh, install.sh, deploy.sh, update.sh.
# Sourceable: `. scripts/detect-php.sh`. Idempotentne — wielokrotne
# sourcowanie nie psuje PATH.

# Min. wymagana wersja
HOVERA_MIN_PHP="${HOVERA_MIN_PHP:-8.2}"

_hovera_php_log()  { printf '\033[36m[php]\033[0m %s\n' "$*"; }
_hovera_php_warn() { printf '\033[33m[php]\033[0m %s\n' "$*"; }
_hovera_php_fail() { printf '\033[31m[php]\033[0m %s\n' "$*" >&2; return 1; }

# version_gt A B → 0 jeśli A > B (semver-aware przez sort -V)
_hovera_version_gt() {
    [[ "$1" = "$2" ]] && return 1
    [[ "$(printf '%s\n%s\n' "$1" "$2" | sort -V | tail -1)" = "$1" ]]
}

# version_ge A B → 0 jeśli A >= B
_hovera_version_ge() {
    [[ "$1" = "$2" ]] && return 0
    _hovera_version_gt "$1" "$2"
}

_hovera_php_get_version() {
    "$1" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null
}

# Główna detekcja. Zwraca przez exporty PHP_BIN, PHP_VERSION.
hovera_detect_php() {
    local best_version=""
    local best_path=""
    local candidates=()

    # 1. php / php8.5 / php8.4 / ... w PATH
    for cmd in php php8.5 php8.4 php8.3 php8.2; do
        if command -v "$cmd" >/dev/null 2>&1; then
            candidates+=("$(command -v "$cmd")")
        fi
    done

    # 2. Typowe Pleskowe ścieżki
    local v
    for v in 8.5 8.4 8.3 8.2; do
        for p in \
            "/opt/plesk/php/$v/bin/php" \
            "/usr/local/php$v/bin/php" \
            "/usr/local/php${v//.}/bin/php" \
            "/usr/bin/php$v"; do
            [[ -x "$p" ]] && candidates+=("$p")
        done
    done

    # 3. Wybierz najnowszą wersję spośród kandydatów
    local path version
    for path in "${candidates[@]}"; do
        version="$(_hovera_php_get_version "$path")"
        [[ -z "$version" ]] && continue
        # Tylko 8.2+
        _hovera_version_ge "$version" "$HOVERA_MIN_PHP" || continue
        if [[ -z "$best_version" ]] || _hovera_version_gt "$version" "$best_version"; then
            best_version="$version"
            best_path="$path"
        fi
    done

    if [[ -z "$best_path" ]]; then
        _hovera_php_fail "Nie znalazłem PHP $HOVERA_MIN_PHP+ ani w PATH ani w typowych lokalizacjach (/opt/plesk/php/X.Y/bin/php, /usr/bin/phpX.Y)."
        return 1
    fi

    export PHP_BIN="$best_path"
    export PHP_VERSION="$best_version"

    return 0
}

# Wstawia shim dir z `php` → PHP_BIN na początek PATH. Dzięki temu
# composer (i każde inne narzędzie odpalające `php` z PATH) używa
# właściwej wersji bez potrzeby modyfikacji innych skryptów.
hovera_install_php_shim() {
    [[ -n "${PHP_BIN:-}" ]] || _hovera_php_fail "PHP_BIN nieustawiony — wywołaj hovera_detect_php najpierw" || return 1

    # Jeśli już shim założony i wskazuje na właściwego PHP — nic nie rób
    if [[ -n "${HOVERA_PHP_SHIM_DIR:-}" && -L "$HOVERA_PHP_SHIM_DIR/php" ]]; then
        local current_target
        current_target="$(readlink "$HOVERA_PHP_SHIM_DIR/php")"
        if [[ "$current_target" = "$PHP_BIN" ]]; then
            return 0
        fi
    fi

    HOVERA_PHP_SHIM_DIR="$(mktemp -d -t hovera-php-shim.XXXXXX)"
    export HOVERA_PHP_SHIM_DIR
    ln -sf "$PHP_BIN" "$HOVERA_PHP_SHIM_DIR/php"

    case ":$PATH:" in
        *":$HOVERA_PHP_SHIM_DIR:"*) ;;
        *) export PATH="$HOVERA_PHP_SHIM_DIR:$PATH" ;;
    esac
}

# Wykrywa composer. Próbuje:
#   1. composer w PATH (po założeniu shima — używa naszego PHP)
#   2. /opt/plesk/composer/composer.phar
#   3. lokalny composer.phar w katalogu projektu
# Exportuje COMPOSER_BIN jako pełne polecenie do exec'u (np. "composer"
# albo "/usr/bin/php /opt/plesk/composer/composer.phar").
hovera_detect_composer() {
    if command -v composer >/dev/null 2>&1; then
        export COMPOSER_BIN="composer"
        return 0
    fi
    local p
    for p in \
        /opt/plesk/composer/composer.phar \
        /usr/local/psa/admin/sbin/composer \
        /usr/local/bin/composer.phar \
        ./composer.phar; do
        if [[ -x "$p" || -f "$p" ]]; then
            export COMPOSER_BIN="${PHP_BIN:-php} $p"
            return 0
        fi
    done

    _hovera_php_fail "Nie znalazłem composera (PATH ani /opt/plesk/composer/composer.phar). Zainstaluj composer 2.x."
    return 1
}

# All-in-one — wywołaj raz na początku skryptu.
hovera_setup_php() {
    hovera_detect_php || return 1
    hovera_install_php_shim || return 1
    _hovera_php_log "PHP $PHP_VERSION → $PHP_BIN"
    return 0
}
