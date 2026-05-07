# Hovera

Multi-tenant SaaS dla pensjonatów koni — booking, karnety, faktury, KSeF, portal właściciela.

Stack: Laravel 11 · Filament 3 · PHP 8.3 · MySQL 8 (per-tenant DB).

---

## Instalacja świeżej instancji

Jednolinikowy bootstrap (klonuje repo + odpala interaktywny installer):

```bash
curl -sSL https://raw.githubusercontent.com/PrzemekPrzemo/hovera.app-sys/main/bootstrap.sh | bash
```

Z parametrami:

```bash
HOVERA_INSTALL_DIR=/var/www/hovera \
HOVERA_GIT_REF=v1.0.0 \
bash <(curl -sSL https://raw.githubusercontent.com/PrzemekPrzemo/hovera.app-sys/main/bootstrap.sh)
```

Installer zapyta o:
- domenę aplikacji + środowisko (local/staging/production)
- dane bazy centralnej MySQL (host, port, baza, user, hasło)
- opcjonalnie provisionera (do tworzenia DB per stajnia z poziomu panelu)
- konfigurację mail (log/SMTP/Resend)
- master admina (email, imię, hasło)

Wygeneruje `.env`, odpali `composer install`, `migrate`, utworzy konto master admina i (w produkcji) zbuduje cache.

### Manualna instalacja

Jeśli wolisz krok po kroku:

```bash
git clone https://github.com/PrzemekPrzemo/hovera.app-sys.git
cd hovera.app-sys
bash install.sh
```

Flagi `install.sh`:
- `--non-interactive` — czyta zmienne `HOVERA_*` z env (do skryptów CI/Ansible)
- `--dry-run` — pokaż co by się stało, nic nie zmieniaj
- `--skip-deps` — pomiń `composer install`

---

## Aktualizacja istniejącej instalacji

```bash
./update.sh                # pull origin/main + pełen rollout
./update.sh v1.2.3         # konkretny tag
./update.sh --skip-tenants # bez migracji per stajnia
./update.sh --dry-run      # tylko pokaż
```

`update.sh` to alias na `deploy.sh` — robi: maintenance ON, `git pull`, `composer install --no-dev`, czyści + buduje cache, migruje central + tenanty, restartuje queue workery, maintenance OFF, smoke test.

---

## Polecenia artisan

```bash
php artisan tenant:create <slug> "Nazwa stajni" --owner-email=...   # nowa stajnia
php artisan tenants:list                                            # lista stajni
php artisan tenants:migrate                                         # migracje na wszystkich stajniach
php artisan hovera:admin:create <email> "<imię>" --password=...     # master admin
php artisan schedule:run                                            # cron entrypoint
```

---

## Cron

```cron
* * * * * cd /var/www/hovera && php artisan schedule:run >> /dev/null 2>&1
```

Scheduler odpala m.in.: przypomnienia o rezerwacjach, snapshoty health-score'ów stajni, automatyczne FV za karnety.

---

## Dokumentacja

- [docs/DEPLOY.md](docs/DEPLOY.md) — playbook wdrożenia na Plesku
- [docs/FEATURES.md](docs/FEATURES.md) — pełna lista funkcjonalności
- [hovera-spec.md](hovera-spec.md) — specyfikacja produktu
