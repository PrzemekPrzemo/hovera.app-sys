#!/usr/bin/env bash
# Hovera — wykrywanie środowiska deploy'u.
#
# Sourceable: `. scripts/detect-env.sh; hovera_detect_environment`
#
# Po wywołaniu eksportuje:
#   $HOVERA_ENV           plesk / cpanel / vps
#   $HOVERA_DB_CMD        komenda MySQL CLI z root access
#                         (np. "plesk db", "mysql -uroot -p...", lub pusta)
#   $HOVERA_VHOST_USER    user UNIX który uruchamia PHP-FPM dla domeny
#                         (np. app.hovera.app_cvpjl9sbri7 na Plesku)
#   $HOVERA_VHOST_GROUP   grupa (psaserv / psacln itp.)
#   $HOVERA_FPM_SERVICE   nazwa systemd unitu do restartu pula FPM
#                         (np. plesk-php84-fpm_<domain>_<id>.service)

_he_log()  { printf '\033[36m[env]\033[0m %s\n' "$*"; }
_he_warn() { printf '\033[33m[env]\033[0m %s\n' "$*"; }

hovera_detect_environment() {
    HOVERA_ENV=""
    HOVERA_DB_CMD=""
    HOVERA_VHOST_USER=""
    HOVERA_VHOST_GROUP=""
    HOVERA_FPM_SERVICE=""

    # 1. Plesk
    if command -v plesk >/dev/null 2>&1 && [[ -f /etc/psa/.psa.shadow ]]; then
        HOVERA_ENV="plesk"
        HOVERA_DB_CMD="plesk db"
        _he_log "Wykryto: Plesk"

        # Wyciągnij vhost user/group z parent ścieżki (jeśli jesteśmy w vhost)
        local pwd_real
        pwd_real="$(pwd -P)"
        if [[ "$pwd_real" =~ ^/var/www/vhosts/([^/]+) ]]; then
            local vhost="${BASH_REMATCH[1]}"
            local stat_user stat_group
            stat_user="$(stat -c '%U' "/var/www/vhosts/$vhost" 2>/dev/null || true)"
            stat_group="$(stat -c '%G' "/var/www/vhosts/$vhost" 2>/dev/null || true)"
            if [[ -n "$stat_user" && "$stat_user" != "root" ]]; then
                HOVERA_VHOST_USER="$stat_user"
                HOVERA_VHOST_GROUP="$stat_group"
                _he_log "  vhost user: ${HOVERA_VHOST_USER}:${HOVERA_VHOST_GROUP}"

                # Znajdź dedicated FPM pool (Plesk numeruje per-domain)
                local svc
                svc="$(systemctl list-units --type=service --no-legend 2>/dev/null \
                    | awk '{print $1}' \
                    | grep -E "plesk-php[0-9]+-fpm_${vhost//./\.}_[0-9]+\.service" \
                    | head -1)"
                if [[ -n "$svc" ]]; then
                    HOVERA_FPM_SERVICE="$svc"
                    _he_log "  FPM service: ${HOVERA_FPM_SERVICE}"
                else
                    HOVERA_FPM_SERVICE="plesk-php84-fpm.service"  # fallback
                    _he_warn "  Nie znaleziono dedicated pool; fallback na shared: ${HOVERA_FPM_SERVICE}"
                fi
            fi
        fi
        return 0
    fi

    # 2. cPanel
    if [[ -d /usr/local/cpanel ]]; then
        HOVERA_ENV="cpanel"
        _he_log "Wykryto: cPanel (basic support — niektóre auto-setupy mogą wymagać interakcji)"
        if command -v whmapi1 >/dev/null 2>&1; then
            HOVERA_DB_CMD="" # cPanel nie ma natywnego "root db" CLI bez API
        fi
        # Standardowy cPanel FPM
        HOVERA_FPM_SERVICE="ea-php84-php-fpm.service"
        return 0
    fi

    # 3. Plain VPS
    HOVERA_ENV="vps"
    _he_log "Wykryto: standalone VPS (brak Plesk/cPanel)"

    # MySQL CLI root — sprawdź typowe metody
    if command -v mysql >/dev/null 2>&1; then
        if mysql -uroot -e "SELECT 1" >/dev/null 2>&1; then
            HOVERA_DB_CMD="mysql -uroot"
            _he_log "  MySQL: dostęp root bez hasła (sock auth)"
        else
            _he_warn "  MySQL: root wymaga hasła (auto-setup MySQL provisionera będzie wymagał interakcji)"
        fi
    fi

    # Owner — kto uruchamia PHP-FPM. Spróbuj wykryć po typowych user-ach.
    for u in www-data nginx php-fpm apache; do
        if id "$u" >/dev/null 2>&1; then
            HOVERA_VHOST_USER="$u"
            HOVERA_VHOST_GROUP="$u"
            break
        fi
    done

    # FPM service
    for svc in php8.4-fpm php-fpm php8.4 php-fpm84; do
        if systemctl list-units --type=service --no-legend 2>/dev/null | grep -q "^${svc}\.service"; then
            HOVERA_FPM_SERVICE="${svc}.service"
            break
        fi
    done

    [[ -n "$HOVERA_VHOST_USER" ]] && _he_log "  PHP-FPM user: ${HOVERA_VHOST_USER}:${HOVERA_VHOST_GROUP}"
    [[ -n "$HOVERA_FPM_SERVICE" ]] && _he_log "  FPM service: ${HOVERA_FPM_SERVICE}"

    export HOVERA_ENV HOVERA_DB_CMD HOVERA_VHOST_USER HOVERA_VHOST_GROUP HOVERA_FPM_SERVICE
}

# Auto-setup MySQL provisionera. Jeśli mamy $HOVERA_DB_CMD (root access),
# tworzy hovera_provisioner@'localhost' z wymaganymi grants.
# Idempotentny — można wywołać wielokrotnie.
#
# Argumenty:
#   $1 — username provisionera (np. hovera_provisioner)
#   $2 — password provisionera (z .env)
hovera_setup_provisioner() {
    local user="${1:-hovera_provisioner}"
    local pass="$2"

    if [[ -z "$HOVERA_DB_CMD" ]]; then
        _he_warn "Brak MySQL root access — pomijam auto-setup provisionera. Wykonaj ręcznie:"
        _he_warn "  GRANT ALL PRIVILEGES ON *.* TO '${user}'@'localhost' WITH GRANT OPTION;"
        return 1
    fi

    if [[ -z "$pass" ]]; then
        _he_warn "Brak hasła provisionera — pomijam auto-setup."
        return 1
    fi

    _he_log "Konfiguruję MySQL provisionera '${user}'…"

    # Escape password dla SQL
    local escaped_pass="${pass//\'/\\\'}"

    $HOVERA_DB_CMD <<SQL
CREATE USER IF NOT EXISTS '${user}'@'localhost' IDENTIFIED BY '${escaped_pass}';
ALTER USER '${user}'@'localhost' IDENTIFIED BY '${escaped_pass}';
GRANT ALL PRIVILEGES ON *.* TO '${user}'@'localhost' WITH GRANT OPTION;
FLUSH PRIVILEGES;
SQL

    _he_log "  ✓ Provisioner '${user}' gotowy z WITH GRANT OPTION"
}
