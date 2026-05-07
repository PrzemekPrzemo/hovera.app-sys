# Hovera — wdrożenie na app.hovera.app (VPS Debian + Plesk)

> Krótki przewodnik: co przygotować w Plesku **zanim** odpalisz `./deploy.sh`, a potem jak deployować codziennie.

## TL;DR

```bash
# Jednorazowo (po Plesk setupie z sekcji „Pre-flight")
ssh hovera_app@vps
cd ~/httpdocs
git clone git@github.com:PrzemekPrzemo/hovera.app-sys.git .
cp .env.example .env && nano .env          # uzupełnij sekrety
php artisan key:generate
php artisan migrate --force                 # central
./deploy.sh --skip-tenants                  # nie ma jeszcze tenantów

# Później, na każdy release:
./deploy.sh                                 # latest origin/main
./deploy.sh v1.2.3                          # konkretny tag
```

---

## 1. Pre-flight w Plesku — co ustawić **przed** pierwszym deployem

### 1.1 Domena
- **Domains → Add Domain**: `app.hovera.app`
- DNS A-record `app.hovera.app` → IP serwera (jeśli DNS nie jest pod Pleskiem, ustaw u rejestratora)
- Subscription: dedykowany użytkownik systemowy, np. `hovera_app` (Plesk tworzy go automatycznie). Wszystkie polecenia odpalamy jako on, **nie jako root**.

### 1.2 PHP
- **Domain → PHP Settings**:
  - PHP version: **8.3** (8.2+ minimum)
  - Run PHP as: **FPM application served by Apache** (lub Nginx — bez różnicy)
  - PHP CLI: ta sama wersja (Plesk → Tools & Settings → PHP)
- Wymagane rozszerzenia (większość jest domyślnie):
  ```
  pdo_mysql, mbstring, bcmath, intl, openssl, tokenizer,
  xml, ctype, json, fileinfo, curl, gd
  ```
- `php.ini` (Plesk pozwala na overrides per domena):
  ```ini
  memory_limit = 256M
  max_execution_time = 120
  upload_max_filesize = 20M
  post_max_size = 20M
  date.timezone = Europe/Warsaw
  ```

### 1.3 Document root
- **Domain → Hosting Settings → Document root**: `httpdocs/public`
- Apache config (Domain → Apache & nginx Settings → Additional directives for HTTPS):
  ```apache
  <Directory /var/www/vhosts/hovera.app/app.hovera.app/httpdocs/public>
      AllowOverride All
      Require all granted
  </Directory>
  ```
  (Laravel ma własny `.htaccess` w `public/` — Plesk go uszanuje.)

### 1.4 SSL / HTTPS
- **Domain → SSL/TLS Certificates → Install Free Certificate (Let's Encrypt)**
- Włącz **HSTS** (Plesk: SSL/TLS → Advanced)
- Wymuś HTTPS: **Domain → Hosting Settings → Permanent SEO-safe 301 redirect from HTTP to HTTPS**

### 1.5 MySQL — trzy "role"
Hovera używa **trzech** logicznych połączeń DB. Wszystkie wskazują ten sam MySQL, ale na różnych userach:

| Connection name  | Database         | User                  | Uprawnienia                                                                                    |
|------------------|------------------|-----------------------|------------------------------------------------------------------------------------------------|
| `central`        | `hovera_core`    | `hovera_core`         | ALL na `hovera_core`                                                                           |
| `tenant`         | (per stajnia)    | (per stajnia)         | ALL na własnej DB — tworzony automatycznie przez `provisioner`                                 |
| `provisioner`    | —                | `hovera_provisioner`  | **CREATE/DROP DATABASE, CREATE USER, GRANT OPTION** (potrzebuje superusera-light)              |

#### Krok 1 — Central
W Plesku: **Domains → app.hovera.app → Databases → Add Database**:
- Database name: `hovera_core`
- User: `hovera_core` z silnym hasłem (zapisz do password managera)
- Privileges: ALL na `hovera_core`

#### Krok 2 — Provisioner (jednorazowo, **manualnie z SSH/CLI**)
Plesk nie pozwala stworzyć usera z `CREATE DATABASE` przez UI, więc:

```bash
ssh root@vps   # tylko ten krok jako root, potem już nie
mysql -u root -p
```

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

> **Bezpieczeństwo**: provisioner ma silne uprawnienia. Trzymaj jego hasło tylko w `.env` na serwerze i w password managerze. Nie używaj go nigdzie indziej.

#### Krok 3 — sanity check
```bash
mysql -u hovera_provisioner -p -h 127.0.0.1 -e "CREATE DATABASE _hovera_test; DROP DATABASE _hovera_test;"
# Jeśli ok, provisioner działa.
```

### 1.6 Mail (SMTP)
Wybierz jedną opcję i uzupełnij `.env`:

**Postmark / Mailgun / SES** — najpewniejsze dla transactional:
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

**Plesk Mail (built-in)** — działa, ale gorsza deliverability:
- Plesk → Mail → utwórz `no-reply@hovera.app`
- W `.env`:
  ```env
  MAIL_MAILER=smtp
  MAIL_HOST=mail.hovera.app
  MAIL_PORT=587
  MAIL_USERNAME=no-reply@hovera.app
  MAIL_PASSWORD=<hasło>
  MAIL_ENCRYPTION=tls
  ```

> **DNS**: dla każdej opcji pamiętaj o **SPF + DKIM + DMARC** dla `hovera.app` w panelu DNS (Plesk → DNS Settings, jeśli hostujesz DNS u siebie).

### 1.7 Cron — Laravel scheduler
**Domain → Scheduled Tasks → Add task**:
- Run: **Run a PHP script**
- Script path: `/var/www/vhosts/hovera.app/app.hovera.app/httpdocs/artisan`
- Arguments: `schedule:run`
- Run as: `hovera_app`
- Frequency: **Every minute** (`* * * * *`)
- Włącz "Send notifications" jeśli chcesz mailem o błędach.

To uruchamia automatycznie:
- `bookings:send-reminders` co godzinę
- `tenants:snapshot-health` codziennie o 03:30

### 1.8 Queue worker (rekomendowane, ale opcjonalne)
Maile wysyłają się synchronicznie z requestu — działa, ale spowalnia. Lepiej supervisor + queue worker:

**Plesk nie zarządza supervisorem przez UI** — zrób to z SSH (jednorazowo):
```bash
sudo apt install supervisor
sudo nano /etc/supervisor/conf.d/hovera-worker.conf
```

```ini
[program:hovera-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/vhosts/hovera.app/app.hovera.app/httpdocs/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=hovera_app
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/vhosts/hovera.app/app.hovera.app/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start hovera-worker:*
```

`./deploy.sh` automatycznie wywołuje `php artisan queue:restart` — workery łapią nową wersję kodu po max ~3s.

### 1.9 Tools — co doinstalować po stronie OS (jednorazowo, jako root)
```bash
sudo apt update
sudo apt install -y git unzip mysql-client redis-tools

# Composer 2.x
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Sanity check
composer --version
git --version
php --version
```

### 1.10 Dostęp SSH dla `hovera_app`
- Plesk → **Subscriptions → app.hovera.app → Web Hosting Access** → Access to the server over SSH: **/bin/bash** (lub `/bin/sh`)
- Dodaj swój SSH public key: **Subscriptions → app.hovera.app → SSH Keys**
- Zaloguj się: `ssh hovera_app@vps` (powinno działać bez hasła)

### 1.11 Pierwsze .env
Po SSH:
```bash
cd ~/httpdocs
git clone git@github.com:PrzemekPrzemo/hovera.app-sys.git .
cp .env.example .env
nano .env
```

Uzupełnij minimum:
```env
APP_NAME=Hovera
APP_ENV=production
APP_KEY=                                  # wygenerujemy za chwilę
APP_DEBUG=false
APP_TIMEZONE=Europe/Warsaw
APP_URL=https://app.hovera.app

DB_CENTRAL_DATABASE=hovera_core
DB_CENTRAL_USERNAME=hovera_core
DB_CENTRAL_PASSWORD=<z punktu 1.5>

DB_PROVISIONER_USERNAME=hovera_provisioner
DB_PROVISIONER_PASSWORD=<z punktu 1.5>

MAIL_MAILER=smtp
# ... reszta z punktu 1.6

SESSION_SECURE_COOKIE=true                # bo HTTPS
```

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force                # central tylko
```

---

## 2. Codzienny deploy

### Bezpieczny rollout (recommended)

```bash
ssh hovera_app@vps
cd ~/httpdocs
./deploy.sh
```

Co robi skrypt:
1. `php artisan down` (maintenance mode — strona zwraca 503)
2. `git fetch + git pull origin main` (lub `git checkout <tag>`)
3. `composer install --no-dev --optimize-autoloader`
4. Czyści i odbudowuje cache (config, routes, views, events)
5. `php artisan migrate --force` (central)
6. `php artisan tenants:migrate` (każdy aktywny tenant)
7. `php artisan storage:link` (jeśli pierwszy raz)
8. `php artisan filament:assets` (publish CSS/JS Filamenta)
9. `php artisan queue:restart` (workery łapią nowy kod)
10. `php artisan up` (maintenance OFF)
11. Smoke test (`php artisan about`)

### Wdrożenie konkretnego tagu / hotfix

```bash
./deploy.sh v1.2.3          # checkout taga
./deploy.sh main            # to samo co bez argumentu
./deploy.sh feature-branch  # ad-hoc branch
```

### Pomijanie kroków (rzadko)

```bash
./deploy.sh --skip-tenants  # tylko central — gdy tenanty padają
./deploy.sh --no-pull       # po manualnym `git checkout`
./deploy.sh --dry-run       # tylko wypisz co by się stało
```

### Rollback

```bash
ssh hovera_app@vps
cd ~/httpdocs
git log --oneline -10        # znajdź poprzednią rewizję
./deploy.sh <hash-lub-tag>   # deploy starszej wersji

# Migracje rollback (UWAŻAJ — ostatnia partia):
php artisan migrate:rollback --force                              # central
php artisan tenants:migrate --tenant=<slug> --rollback             # nie ma flagi --rollback w tenants:migrate, użyj per-DB:
# Alternatywnie z mysql client zaloguj się do hovera_t_<slug> i ręcznie cofnij konkretny migrate.
```

> Większość migracji jest backwards-compatible (dodajemy kolumny nullable). Nie ma potrzeby rollbacku w 95% przypadków — wystarczy redeploy poprzedniej wersji.

---

## 3. Tworzenie nowej stajni (tenant)

Z poziomu master admin panelu (`/admin → Stajnie → Add tenant`) — to wystarcza.

Albo z CLI:

```bash
php artisan tenants:create stajnia-wisla "Stajnia Wisła" \
    --owner-email=admin@stajnia-wisla.pl \
    --plan=stable
```

Komenda:
- Tworzy DB `hovera_t_stajnia-wisla` + usera `hovera_t_stajnia-wisla` z losowym hasłem (zaszyfrowanym w `tenants.db_password_encrypted`)
- Migruje schema tenanta
- Wysyła zaproszenie do ownera (magic link)

Pełna lista artisan commands:

```bash
php artisan tenants:list                    # wszystkie zarejestrowane stajnie
php artisan tenants:migrate                 # migruje wszystkie aktywne
php artisan tenants:migrate --tenant=slug   # jeden konkretny
php artisan tenants:snapshot-health         # ad-hoc snapshot zdrowia
php artisan bookings:send-reminders         # ad-hoc przypomnienia 24h
```

---

## 4. Monitoring + healthcheck

### Loglines
```bash
tail -f storage/logs/laravel.log
```

### Failed queue jobs
```bash
php artisan queue:failed
php artisan queue:retry all                 # ponów wszystkie
```

### Healthcheck (np. dla Uptime Robot)
- `GET https://app.hovera.app/`              → 302 do panelu admina
- `GET https://app.hovera.app/admin/login`   → 200 (lub 302 do challenge 2FA)
- `GET https://app.hovera.app/app/login`     → 200

### Audit log (tenant-side)
```sql
USE hovera_t_<slug>;
SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 50;
```

---

## 5. Backup

Plesk → **Backup Manager → Schedule**:
- **Frequency**: daily 04:00
- **Content**:
  - User files (httpdocs)
  - **Databases** ✓ (Plesk złapie central + wszystkie tenant DB-ki w hostowanym MySQL)
- **Retention**: 14 days
- **Storage**: zewnętrzny (S3 / FTP / rsync) — **nie na ten sam serwer**

Restore test: raz na kwartał odtwórz backup na staging i sprawdź czy `hovera_t_<slug>` da się przywrócić.

---

## 6. Troubleshooting

| Objaw                                       | Sprawdź                                                                                       |
|---------------------------------------------|-----------------------------------------------------------------------------------------------|
| `500` na każdej stronie                     | `tail -50 storage/logs/laravel.log` · uprawnienia `storage/` (`chmod -R ug+rw`)               |
| `permission denied` na `storage/logs/*`     | `chown -R hovera_app:psaserv storage bootstrap/cache`                                         |
| Maile nie wychodzą                          | `php artisan tinker → Mail::raw('test', fn($m)=>$m->to('ty@gmail.com')->subject('t'))`        |
| `tenants:create` failuje na CREATE DATABASE | `mysql -u hovera_provisioner -p -e "CREATE DATABASE _t; DROP DATABASE _t"`                    |
| Filament `/admin` zwraca 404                | `php artisan filament:assets` + `php artisan optimize:clear` + `php artisan optimize`         |
| Cron nie odpala scheduled tasks             | Plesk → Scheduled Tasks → upewnij się że "Run as" = `hovera_app` i jest `* * * * *`          |
| Sesja klienta wygasa od razu                | `SESSION_SECURE_COOKIE=true` + HTTPS musi być włączone (cookie nie wyśle się przez HTTP)      |

---

## 7. Co **nie** jest w skrypcie (świadomie)

- **NPM build** — Filament v3 ma swoje skompilowane assets, nie potrzebujemy `npm run build`. Jeśli kiedyś dojdzie własny frontend, dorzuć w `deploy.sh` blok `npm ci && npm run build`.
- **Zero-downtime deploy** — robimy `down/up`, max 5–10 sekund 503. Dla horse stable to akceptowalne. Jeśli kiedyś będzie ruch który tego nie znosi: deployuj do nowego katalogu i przełącz symlink (Envoyer-style).
- **Automatyczny rollback** — celowo, żeby nie ukrywać błędów. Człowiek ma decydować.
