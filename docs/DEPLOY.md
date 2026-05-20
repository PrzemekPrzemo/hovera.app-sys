# Hovera — wdrożenie

## Najszybsza droga: bootstrap script

Z czystego serwera (Plesk lub plain VPS) jednym poleceniem:

```bash
curl -sSL https://raw.githubusercontent.com/PrzemekPrzemo/hovera.app-sys/main/bootstrap.sh | bash
```

Skrypt sam:
- wykrywa środowisko (Plesk / cPanel / VPS) — `scripts/detect-env.sh`
- wybiera najnowszego PHP 8.4+ (Plesk: `/opt/plesk/php/8.4/bin/php`, VPS: `php` z PATH)
- klonuje repo do właściwej lokalizacji (Plesk: `/var/www/vhosts/<domain>/httpdocs`)
- odpala interaktywny `install.sh` — pyta o domenę, bazę, master admina
- na Plesku: auto-utwarza MySQL provisionera (przez `plesk db`)
- naprawia permissions (vhost user) + restartuje FPM pool

Po sukcesie sprawdź zdrowie: `./bin/php artisan hovera:doctor` — wyłapie wszystkie typowe pułapki (PHP version, provisioner grants, orphan tenants, Filament closure params, code smells).

## Częste pułapki — lessons learned

| Problem | Co zrobić |
|---|---|
| `Composer dependencies require PHP >= 8.4, you run 7.4` | Plesk domyślnie linkuje `php` → 7.4. NIE uruchamiaj `composer install` bezpośrednio — używaj `./update.sh` (auto-detekcja PHP 8.4 przez `scripts/detect-php.sh`). Jeśli musisz manualnie: `/opt/plesk/php/8.4/bin/php composer install --no-dev`. `composer.json` ma `config.platform.php=8.4` więc composer wybucha loud zamiast cicho psuć lock. |
| `Unable to find component: [...resources...relation-manager]` | Filament 3 component cache nie został odbudowany po deploy nowych klas. Odpal `./update.sh` (zawiera `php artisan filament:cache-components`). Manualnie: `/opt/plesk/php/8.4/bin/php artisan filament:cache-components`. |
| Brak zakładki/grupy w sidebar `/admin` mimo że Resource istnieje w repo | To samo co wyżej — stale Filament cache. `./update.sh` rebuilduje component map. |
| `Access denied for user ''@'localhost'` | Brakujące `WITH GRANT OPTION` na `hovera_t_%` LUB tenant connection nie zhydratowane przed query. `hovera:doctor` wykryje. |
| `Table already exists` przy `migrate:fresh` | Half-baked tenant z poprzedniej awarii. `php artisan hovera:tenant:cleanup-orphans`. |
| `BindingResolutionException ... [$q] was unresolvable` | Filament 3 closure resolver — używaj `$query`, `$record`, `$state`. Single-letter `$q/$s/$r` nie są rozpoznawane. |
| `Target class [config] does not exist` | `bootstrap/app.php`: `withMiddleware` callback NIE może wołać `config()` — użyj `env()`. |
| 504 Gateway Timeout przy demo seed z UI | Plesk: zwiększ `fastcgi_read_timeout 600s` w nginx directives, plus `max_execution_time 600`. ALBO odpal z CLI: `./bin/php artisan hovera:demo:seed --fresh`. |
| Po deploy stary kod się trzyma | OPcache shared memory. Restart FPM pool: `systemctl restart plesk-php84-fpm_<domain>_<id>.service`. `update.sh` robi to automatycznie. |
| Plesk PHP w panelu = 8.3 zamiast 8.4 | Plesk → Domain → PHP Settings → PHP version → 8.4.x. |
| `/admin/login` daje błąd dla owner stajni | Już poprawione — `/admin/login` redirectuje na `/app/login`. Tam logują się wszyscy. |

## TL;DR — Plesk UI checklist

1. Plesk → **Add Domain** `app.hovera.app`
2. Plesk → **PHP Settings** → 8.4 + memory_limit 256M + tz Warsaw + max_execution_time 600
3. Plesk → **Hosting Settings** → document root `httpdocs/public`
4. Plesk → **SSL/TLS** → Let's Encrypt + force HTTPS
5. Plesk → **Databases** → utwórz `hovera_core` + user `hovera_core`
6. Plesk → **phpMyAdmin** (na `hovera_core`) → wklej SQL z 1.5.B (provisioner)
7. Plesk → **Git** → repo + auto-deploy → wskaż `deploy.sh` jako "Additional deployment actions"
8. Plesk → **File Manager** → wklej `.env` (skopiuj z `.env.example`, uzupełnij sekrety)
9. Plesk → **Scheduled Tasks** → `* * * * * php artisan schedule:run`
10. Plesk → **Scheduled Tasks** → `* * * * * php artisan queue:work --stop-when-empty --max-time=55`
11. Plesk → **Backup Manager** → daily 04:00 + retention 14d
12. Pierwszy deploy: Plesk → Git → **Pull Updates Now** (uruchomi `deploy.sh` automatycznie)

To wszystko — możesz mieć pierwszą stajnię w 30 minut bez touchu na shellu.

---

## 1. Setup w Plesku — krok po kroku

### 1.1 Domena

**Domains → Add Domain**
- Domain name: `app.hovera.app`
- Subscription: dedykowana (Plesk utworzy usera, np. `hovera_app`)
- Hosting type: Website hosting

DNS — A-record `app.hovera.app` → IP serwera. Jeśli DNS hostujesz pod Pleskiem, wszystko już ustawione. Jeśli u rejestratora (cloudflare/home.pl/etc.), dorzuć ręcznie.

### 1.2 PHP

**Domain → PHP Settings**
- **PHP version**: 8.3 (jeśli brak — Plesk → Tools & Settings → Updates → "Add/Remove Components" → PHP 8.3)
- **Run PHP as**: FPM application served by Apache (default)
- **PHP-CLI**: ta sama wersja (Plesk → Tools & Settings → PHP → ustaw default-cli na 8.3)

**Performance / php.ini overrides** (tym samym ekranem PHP Settings → "Additional configuration directives"):

```ini
memory_limit = 256M
max_execution_time = 120
upload_max_filesize = 20M
post_max_size = 20M
date.timezone = Europe/Warsaw
```

Sprawdź czy są aktywne rozszerzenia (zazwyczaj wszystkie domyślne):
`pdo_mysql, mbstring, bcmath, intl, openssl, tokenizer, xml, ctype, json, fileinfo, curl, gd`. Plesk pokazuje listę na tym samym ekranie.

### 1.3 Document root + .htaccess

**Domain → Hosting Settings**
- **Document root**: `httpdocs/public` (Laravel)
- **Preferred domain**: `app.hovera.app` (z HSTS)
- **Permanent SEO-safe 301 redirect from HTTP to HTTPS**: ✓

`.htaccess` w `public/` przyjedzie z repo razem z aplikacją — nie musisz nic edytować.

### 1.4 SSL / HTTPS

**Domain → SSL/TLS Certificates**
- Klik **Install Free Certificate (Let's Encrypt)**
- Włącz dla domeny + webmail
- Włącz **HSTS** (Domain → SSL/TLS Certificates → Advanced settings)

### 1.5 MySQL — **3 role** (krytyczne!)

Hovera używa trzech logicznych ról DB. Wszystkie wskazują ten sam serwer MySQL/MariaDB, ale na różnych userach.

| Connection    | Database              | User                    | Uprawnienia                                                    |
|---------------|-----------------------|-------------------------|----------------------------------------------------------------|
| `central`     | `hovera_core`         | `hovera_core`           | ALL na `hovera_core`                                           |
| `tenant`      | (per stajnia)         | (per stajnia)           | ALL na własnej DB — tworzony **automatycznie** przez provisionera|
| `provisioner` | (n/a)                 | `hovera_provisioner`    | **CREATE/DROP DATABASE, CREATE USER, GRANT OPTION**            |

#### A. Central — przez Plesk UI

**Domain → Databases → Add Database**
- Database name: `hovera_core`
- User name: `hovera_core`
- Password: generuj silne (zapisz do password managera!)
- Privileges: ALL (default)
- Access: `127.0.0.1` (lokalny)

#### B. Provisioner — przez phpMyAdmin

Plesk UI nie pozwala na `CREATE USER ... WITH GRANT OPTION` na `*.*`. Robisz to przez **phpMyAdmin**.

**Domain → Databases → `hovera_core` → phpMyAdmin → SQL**

Wklej i odpal (zmień `<silne-hasło>` na własne):

```sql
CREATE USER 'hovera_provisioner'@'127.0.0.1' IDENTIFIED BY '<silne-hasło>';

GRANT CREATE, DROP, REFERENCES, INDEX, ALTER,
      CREATE TEMPORARY TABLES, LOCK TABLES,
      CREATE VIEW, SHOW VIEW, CREATE ROUTINE, ALTER ROUTINE, EXECUTE
      ON *.* TO 'hovera_provisioner'@'127.0.0.1';

GRANT CREATE USER ON *.* TO 'hovera_provisioner'@'127.0.0.1';

GRANT GRANT OPTION ON *.* TO 'hovera_provisioner'@'127.0.0.1';

FLUSH PRIVILEGES;
```

> ⚠️ Jeśli phpMyAdmin zwróci "Access denied" — Twój zalogowany user (utworzony przez Plesk dla `hovera_core`) **nie ma uprawnień** do tworzenia globalnych userów. W takim przypadku:
> - Albo poproś hosting o utworzenie usera `hovera_provisioner` z uprawnieniami z tabeli powyżej
> - Albo zaloguj się do phpMyAdmina **jako root** (jeśli masz dostęp — Plesk → Tools & Settings → Database Servers → "Webadmin" przy local server)

#### C. Sanity check — przez phpMyAdmin

Wciąż w phpMyAdmin → SQL:

```sql
-- Test: prowizjoner powinien móc utworzyć i usunąć dowolną DB
CREATE DATABASE _hovera_smoke_test;
DROP DATABASE _hovera_smoke_test;
```

Zaloguj się ponownie do phpMyAdmin **jako `hovera_provisioner`** i odpal powyższe — jeśli przejdzie, provisioner działa.

### 1.6 Mail

#### Wariant A — Resend / Postmark / Mailgun (rekomendowany)
Założ konto, weryfikuj domenę (DKIM/SPF), pobierz SMTP credentials.

W `.env` (krok 1.10):
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.postmarkapp.com
MAIL_PORT=587
MAIL_USERNAME=<token>
MAIL_PASSWORD=<token>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="no-reply@hovera.app"
MAIL_FROM_NAME="Hovera"
```

#### Wariant B — Plesk Mail (built-in, gorsza deliverability)
**Domain → Mail → Create Email Address** → `no-reply@hovera.app` z silnym hasłem.

```env
MAIL_MAILER=smtp
MAIL_HOST=app.hovera.app          # mail server name z Plesku
MAIL_PORT=587
MAIL_USERNAME=no-reply@hovera.app
MAIL_PASSWORD=<hasło>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="no-reply@hovera.app"
```

**DNS** (Domain → DNS Settings):
- Plesk z reguły sam dodaje SPF i DKIM jeśli używasz Plesk Mail
- Dla zewnętrznego SMTP — dodaj DKIM/SPF/DMARC zgodnie z dokumentacją providera. Plesk DNS Settings → Add Record (TXT)

### 1.7 Git auto-deploy (Plesk-native, **bez SSH**)

To jest sedno — Plesk ma natywne Git, które na każdy push robi `git pull` w `httpdocs` i może odpalić nasz `deploy.sh` automatycznie.

**Domain → Git → Add Repository**
- Repository name: `hovera`
- Repository type: **Pull updates from a remote Git repository**
- Repository URL: `git@github.com:PrzemekPrzemo/hovera.app-sys.git`
- Plesk wygeneruje **deploy key (public)** — skopiuj go i wklej do GitHub:
  GitHub repo → Settings → Deploy keys → Add deploy key (read-only wystarcza)
- Branch: `main`
- Deployment mode: **Automatic** (deploy on push) lub **Manual** (klikasz "Pull Updates Now")
- **Server path**: `httpdocs` (NIE `httpdocs/public` — repo idzie do roota, document root zostaje na `httpdocs/public`)
- **Additional deployment actions** (jeśli pole jest dostępne):
  ```bash
  bash deploy.sh --no-pull
  ```
  *(`--no-pull`, bo Plesk już pull-nął przed odpaleniem skryptu)*

Pierwszy deploy: klik **"Pull Updates Now"**. Plesk:
1. sklonuje repo do `httpdocs/`
2. odpali `bash deploy.sh --no-pull` jako użytkownik domeny

> Jeśli **"Additional deployment actions"** w Twojej wersji Pleska nie ma:
> - Klik **Pull Updates Now**, potem ręcznie odpal skrypt z **Domain → Web Hosting Access → Run Command** (jeśli włączone) lub przez SSH (krok 1.12)

### 1.8 .env — przez File Manager

**Domain → File Manager → `httpdocs/`** → klik prawym **`.env.example`** → **Copy** → zapisz jako **`.env`** → klik **`.env`** → **Edit**.

Minimum do uzupełnienia:

```env
APP_NAME=Hovera
APP_ENV=production
APP_KEY=                                  # wygenerujemy zaraz (krok 1.9)
APP_DEBUG=false
APP_TIMEZONE=Europe/Warsaw
APP_URL=https://app.hovera.app

DB_CENTRAL_DATABASE=hovera_core
DB_CENTRAL_USERNAME=hovera_core
DB_CENTRAL_PASSWORD=<z 1.5.A>

DB_PROVISIONER_USERNAME=hovera_provisioner
DB_PROVISIONER_PASSWORD=<z 1.5.B>

# Mail (z 1.6)
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="no-reply@hovera.app"
MAIL_FROM_NAME="Hovera"

# HTTPS
SESSION_SECURE_COOKIE=true
```

Zapisz.

### 1.9 APP_KEY i pierwsze migracje

`APP_KEY` jest wymagany — Laravel używa go do szyfrowania sesji i tenant DB credentials.

**Wariant A — Plesk PHP Console (preferowany, bez SSH)**

Niektóre wersje Pleska mają **Domain → PHP Console** (przy zainstalowanym extension). Jeśli widzisz to:
```bash
cd httpdocs
php artisan key:generate --force
php artisan migrate --force
```

**Wariant B — odpal raz przez Plesk Scheduled Tasks**

Jeśli nie ma PHP Console, dodaj **jednorazowy** Scheduled Task (Domain → Scheduled Tasks → Add Task):
- Task type: **Run a PHP script**
- Script path: `/var/www/vhosts/hovera.app/app.hovera.app/httpdocs/artisan`
- Arguments: `key:generate --force`
- Run: **Run Once** (klik Run Now)

Powtórz dla `migrate --force`.

**Wariant C — SSH (krok 1.12) jeśli A i B nie działają**

### 1.10 Cron — Laravel scheduler

**Domain → Scheduled Tasks → Add Task**
- Task type: **Run a PHP script**
- Script path: `/var/www/vhosts/hovera.app/app.hovera.app/httpdocs/artisan`
- Arguments: `schedule:run`
- Run: **Cron style** → `* * * * *` (co minutę)
- Run as: `hovera_app` (default)
- Description: "Laravel scheduler"
- "Send notifications": tylko on errors (opcja w Plesku)

Co to uruchamia automatycznie:
- `bookings:send-reminders` — co godzinę (przypomnienia 24h)
- `tenants:snapshot-health` — codziennie o 03:30 (cache health-score)

### 1.11 Queue worker — **bez supervisorska**, przez Plesk Scheduled Tasks

Klasyczny supervisor wymaga roota. Robimy to czyściej przez Plesk + `--stop-when-empty`:

**Domain → Scheduled Tasks → Add Task**
- Task type: **Run a PHP script**
- Script path: `/var/www/vhosts/hovera.app/app.hovera.app/httpdocs/artisan`
- Arguments: `queue:work --stop-when-empty --max-time=55 --tries=3`
- Run: **Cron style** → `* * * * *` (co minutę)
- Description: "Queue worker (cron mode)"

Jak to działa: każda minuta odpala jeden worker, który **zjada wszystkie pending joby z kolejki** i kończy. Gdy nie ma jobów, kończy od razu (`--stop-when-empty`). Bez supervisora, bez roota, bez ciągłego procesu — system Plesk zajmuje się "supervision" przez cron.

> 💡 W praktyce — większość maili Hovery idzie sync (przez `Notification::route('mail')->notify(...)`). Queue jest aktualnie głównie dla TenantAuditLogger i przyszłych integracji. Możesz pominąć ten krok i wrócić gdy obciążenie tego wymaga.

### 1.12 SSH — kiedy musisz, jak to zrobić bezpiecznie

SSH potrzebny jest tylko w **trzech** sytuacjach:
1. Brak składnika OS (Composer, Git, mysql-client) → **poproś hosting**
2. Plesk UI bez "Additional deployment actions" w Git → odpalasz `deploy.sh` ręcznie
3. Provisioner user nie da się utworzyć z phpMyAdmina (brak uprawnień) → **poproś hosting** żeby dał ten SQL z 1.5.B jako root

**Włączenie SSH dla `hovera_app`:**
**Subscription → Web Hosting Access**
- "Access to the server over SSH": `/bin/bash`
- Dodaj swój publiczny klucz SSH: **Subscription → SSH Keys**
- Test: `ssh hovera_app@vps`

**Po SSH** możesz uruchamiać `deploy.sh`, ale **nigdy** nie loguj się jako root z tego konta — operacje, które wymagają roota, zostaw hostingowi.

---

## 2. Codzienny deploy

### Z Plesk UI (zero SSH)

**Domain → Git → "Pull Updates Now"**

Plesk:
1. `git fetch + git pull` z `main`
2. odpala `bash deploy.sh --no-pull` (jeśli ustawione w "Additional deployment actions")

Status w **Git → Activity log**.

### Z SSH (jeśli wolisz)

```bash
ssh hovera_app@vps
cd ~/httpdocs
./deploy.sh                  # latest origin/main
./deploy.sh v1.2.3           # konkretny tag
./deploy.sh --dry-run        # preview
```

### Co `deploy.sh` robi

1. `php artisan down` (maintenance — strona zwraca 503)
2. `composer install --no-dev --optimize-autoloader`
3. Czyści i odbudowuje cache (config / route / view / event)
4. `php artisan migrate --force` (central)
5. `php artisan tenants:migrate` (każdy aktywny tenant)
6. `php artisan storage:link` (jeśli pierwszy raz)
7. `php artisan filament:assets` (publish CSS/JS)
8. `php artisan queue:restart` (workery łapią nowy kod)
9. `php artisan up` (maintenance OFF)
10. Smoke test (`php artisan about`)

### Rollback

**Z Plesk UI (preferowane):**
**Domain → Git → Repository → Pull Updates** → wpisz `<hash>` lub `<tag>` w polu "Branch/tag"
*(albo odpal SSH `./deploy.sh <hash>`)*

Migracje są w 95% backwards-compatible (dodajemy kolumny nullable). Większość rollbacków = sam redeploy starej wersji bez `migrate:rollback`.

---

## 3. Tworzenie nowej stajni (tenant)

### Z Filament UI (najprościej)
Zaloguj się na `https://app.hovera.app/admin` → **Stajnie → Add tenant**.

Co robi pod spodem:
- Tworzy DB `hovera_t_<slug>` (przez provisionera)
- Tworzy MySQL usera `hovera_t_<slug>` z losowym hasłem (encrypted-at-rest)
- Migruje schema tenanta
- (po podpięciu maila) wysyła zaproszenie do ownera

### Z SSH (jeśli wolisz CLI)
```bash
php artisan tenants:create stajnia-wisla "Stajnia Wisła" \
    --owner-email=admin@stajnia-wisla.pl \
    --plan=stable
```

Pełna lista artisan commands:
```bash
php artisan tenants:list                    # wszystkie stajnie
php artisan tenants:migrate                 # migruje wszystkie aktywne
php artisan tenants:migrate --tenant=slug   # jeden konkretny
php artisan tenants:snapshot-health         # ad-hoc snapshot
php artisan bookings:send-reminders         # ad-hoc 24h reminders
```

---

## 4. Monitoring i loglines (Plesk UI)

### Logi aplikacji
**Domain → File Manager → `httpdocs/storage/logs/laravel.log`** → klik **View** lub **Tail**.

### Logi PHP-FPM / Apache
**Domain → Logs** → wszystko klikalne (access / error / php / nginx).

### Uptime check (zewnętrzny)
- `GET https://app.hovera.app/`              → 302 do `/admin`
- `GET https://app.hovera.app/admin/login`   → 200
- `GET https://app.hovera.app/app/login`     → 200

Polecam Uptime Robot / Better Stack / cron-job.org — bezpłatny tier wystarcza.

### Audit log (per stajnia)
**Plesk → Databases → `hovera_t_<slug>` → phpMyAdmin → SQL**:
```sql
SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 50;
```

---

## 5. Backup

**Domain → Backup Manager → Schedule**
- Frequency: **Daily**, hour 04:00
- Content:
  - User files (`httpdocs`)
  - **Databases** ✓ (Plesk złapie central + wszystkie tenant DB-ki w jednym backupie)
- Retention: 14 days
- Storage: **zewnętrzny** (S3 / FTP / Backblaze) — **nie ten sam serwer**

Restore test raz na kwartał: odtwórz na staging i sprawdź czy `hovera_t_<slug>` poprawnie wstaje.

---

## 6. Troubleshooting (wszystko z Plesk UI / phpMyAdmin)

| Objaw                                       | Sprawdź / Napraw                                                                                       |
|---------------------------------------------|--------------------------------------------------------------------------------------------------------|
| `500` na każdej stronie                     | File Manager → `storage/logs/laravel.log` (klik View) · uprawnienia: kliknij prawym `storage/` → **Change Permissions** → `0775` rekursywnie |
| Maile nie wychodzą                          | Plesk → Mail → Mail Logs · w `.env` sprawdź `MAIL_*` · test: dodaj jednorazowy Scheduled Task `php artisan tinker` (lub przez SSH) |
| `tenants:create` failuje na CREATE DATABASE | Plesk → phpMyAdmin (jako `hovera_provisioner`) → `CREATE DATABASE _t; DROP DATABASE _t` — jeśli nie idzie, brakuje GRANT z 1.5.B |
| Filament `/admin` → 404                     | Scheduled Task one-shot: `php artisan filament:assets` + `php artisan optimize`                        |
| Cron nie odpala                             | Plesk → Scheduled Tasks → kliknij task → **Run Now** + Notifications: enable mail on errors          |
| Sesja klienta wygasa od razu                | `.env`: `SESSION_SECURE_COOKIE=true` + HTTPS musi być włączone (cookie nie wyśle się przez HTTP)      |
| Permission denied w `bootstrap/cache`       | File Manager → `bootstrap/cache/` → Change Permissions → `0775` recursive · właściciel: `hovera_app:psaserv` |
| `composer install` failuje na deploy        | Plesk → Composer extension (Tools & Settings → Composer) — odpal ręcznie z UI, klik **Run** w katalogu `httpdocs` |

---

## 7. Co `deploy.sh` zakłada (co musi być na serwerze)

| Komponent          | Skąd to dostać w Plesku                                                                       |
|--------------------|-----------------------------------------------------------------------------------------------|
| PHP 8.3 (CLI)      | Tools & Settings → Updates → Add Components → PHP 8.3                                          |
| Composer 2.x       | Tools & Settings → Composer (extension Plesk Composer) — **albo** Tools & Settings → Server-Wide Software → install via CLI (poproś hosting) |
| Git CLI            | Z reguły jest pre-installed. Sprawdź: w Plesk Git widzisz wersję                              |
| mysql-client       | Tylko jeśli chcesz robić ręczne `mysqldump` — Plesk Backup Manager już to ogarnia              |

**Najczęstszy brak:** Composer. Jeśli twój Plesk go nie ma:
1. Tools & Settings → Plesk Extensions → **Composer** (instaluje się klikiem)
2. Albo poproś hosting o `apt install composer` na poziomie OS (jednorazowo)

---

## 8. Co celowo **nie** wymaga roota

Wszystko z tego dokumentu **da się** zrobić z poziomu zwykłego usera domeny w Plesku. Jedyne wyjątki:
1. Tworzenie `hovera_provisioner` z `GRANT OPTION` — jeśli phpMyAdmin nie pozwala, **eskaluj do hostingu** (jednorazowy mail z SQL-em z 1.5.B)
2. Instalacja brakującego komponentu OS (Composer/git) — analogicznie

Po tych dwóch jednorazowych krokach całość operuje się klikalnie i CRON-ami z Plesku.

---

## 9. Quick reference — gdzie co kliknąć

| Co              | Gdzie w Plesku                                                            |
|-----------------|----------------------------------------------------------------------------|
| Logi Laravel    | Domain → File Manager → `storage/logs/laravel.log` → View                |
| Logi serwera    | Domain → Logs                                                              |
| phpMyAdmin      | Domain → Databases → `hovera_core` → phpMyAdmin                          |
| Crony           | Domain → Scheduled Tasks                                                   |
| Deploy ręczny   | Domain → Git → Pull Updates Now                                            |
| Edit `.env`     | Domain → File Manager → `httpdocs/.env` → Edit                            |
| Backup          | Domain → Backup Manager                                                    |
| SSL             | Domain → SSL/TLS Certificates                                              |
| Mail mailbox    | Domain → Mail → Email Addresses                                            |
| Composer        | Tools & Settings → Composer (extension)                                    |
| PHP wersja      | Domain → PHP Settings                                                      |
| Document root   | Domain → Hosting Settings                                                  |
