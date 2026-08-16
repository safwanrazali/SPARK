# ADMIN & DEPLOYMENT GUIDE

## Sistem Pemantauan & Pelaporan Analisis Data Migrasi PQC — V1.0-RC1

Audience: system administrators and the deployment team.
For day-to-day usage see [`USER_GUIDE.md`](USER_GUIDE.md).

---

## 1. SYSTEM REQUIREMENTS

| Component | Requirement | Notes |
| --- | --- | --- |
| PHP | 8.3 or newer | Extensions: `pdo_sqlite`, `mbstring`, `openssl`, `zip`, `gd` |
| Composer | 2.x | Dependency installation |
| Node.js | 20 LTS or newer | Front-end build only; not needed at runtime except for PDF |
| SQLite | 3.27+ | 3.27 required for hot backups (`VACUUM INTO`) |
| Chrome / Chromium | Headless | **Required for PDF generation** (Spatie Browsershot + Puppeteer) |
| Web server | nginx, Apache or IIS | Document root must be `public/` |
| Disk | ~500 MB + database growth | Includes `node_modules` and the bundled Chromium |

> **PDF generation depends on a working headless Chrome.** Verify it on the
> server before UAT — see §9.3.

---

## 2. FRESH INSTALLATION

```bash
# 1. Obtain the code
git clone <repository-url> spark
cd spark

# 2. PHP dependencies (production: no dev packages)
composer install --no-dev --optimize-autoloader

# 3. Environment file
cp .env.production.example .env
# Edit .env and fill in every <ISI> value — see §3

# 4. Application key
php artisan key:generate

# 5. Database file (SQLite)
mkdir -p database
touch database/database.sqlite      # Windows: New-Item database\database.sqlite
# Ensure DB_DATABASE in .env points to the ABSOLUTE path of this file

# 6. Schema
php artisan migrate --force

# 7. Initial administrator account (reads ADMIN_* from .env)
php artisan db:seed --force

# 8. Front-end assets
npm install
npm run build

# 9. Production caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 10. First backup, before anyone logs in
php scripts/backup-database.php
```

**Verify the installation**

```bash
php artisan about              # confirm environment, debug mode, database
php artisan migrate:status     # every migration should be "Ran"
```

Then open the site: you should be redirected to `/login`.

---

## 3. ENVIRONMENT CONFIGURATION

Start from `.env.production.example`, which is already hardened. The values
that matter most:

| Key | Production value | Why |
| --- | --- | --- |
| `APP_ENV` | `production` | Enables framework production behaviour |
| `APP_DEBUG` | `false` | **Critical.** `true` exposes stack traces, file paths and config values to users |
| `APP_KEY` | generated | Sessions and encrypted data are unreadable without it |
| `APP_URL` | full `https://…` URL | Used for generated links and assets |
| `DB_DATABASE` | absolute path | Scheduled tasks and backup scripts must resolve the same file |
| `SESSION_SECURE_COOKIE` | `true` | Session cookie is never sent over plain HTTP |
| `SESSION_HTTP_ONLY` | `true` | JavaScript cannot read the session cookie |
| `SESSION_SAME_SITE` | `lax` | CSRF hardening |
| `SESSION_ENCRYPT` | `true` | Session payloads encrypted at rest |
| `LOG_LEVEL` | `warning` | `debug` writes far too much on a production box |
| `BCRYPT_ROUNDS` | `12` | Password hashing cost; do not lower |
| `ADMIN_USERNAME` / `ADMIN_PASSWORD` | set before seeding | Seeder aborts in production if `ADMIN_PASSWORD` is empty |

**After editing `.env` you must run** `php artisan config:cache` (or
`config:clear`) — cached config does not pick up `.env` changes.

### 3.1 Timezone — decide before go-live

`APP_TIMEZONE` defaults to **UTC**. All timestamps (workflow status dates, audit
trail, draft save times) are stored and displayed in that timezone.

> ⚠️ If you change `APP_TIMEZONE` to `Asia/Kuala_Lumpur` **after** data exists,
> older records were written in UTC and will be interpreted 8 hours earlier.
> Decide before UAT and keep it fixed afterwards. If you must switch later,
> back up first and plan a data conversion.

---

## 4. WEB SERVER CONFIGURATION

The document root **must** be the `public/` directory. Everything above it —
`.env`, `database/`, `storage/`, `vendor/` — must not be reachable over HTTP.

### nginx

```nginx
server {
    listen 443 ssl http2;
    server_name spark.example.gov.my;
    root /var/www/spark/public;

    ssl_certificate     /etc/ssl/certs/spark.crt;
    ssl_certificate_key /etc/ssl/private/spark.key;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "same-origin" always;

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
```

### Apache

Enable `mod_rewrite`; the shipped `public/.htaccess` handles routing. Point
`DocumentRoot` at `…/spark/public` and set `AllowOverride All`.

### IIS (Windows Server)

Install PHP via FastCGI and the URL Rewrite module, point the site root at
`…\spark\public`, and import Laravel's standard `web.config` rewrite rules.

### File permissions

```bash
# Linux
chown -R www-data:www-data storage bootstrap/cache database
chmod -R 775 storage bootstrap/cache
chmod 664 database/database.sqlite
chmod 750 database                 # directory must be writable for -wal/-shm
```

The web process must be able to write `database/database.sqlite` **and its
directory** (SQLite creates `-wal` / `-shm` files alongside it).

---

## 5. USER MANAGEMENT

Log in as the administrator and open **Pentadbiran → Pengguna**.

| Role (UI label) | Key | Grants |
| --- | --- | --- |
| Pentadbir Sistem | `administrator` | Everything, including user management |
| Pegawai Penyelaras Analisis | `coordinator` | Dashboard, all entities, assignment, workflow control, report status, audit trail |
| Pegawai Analisis | `analyst` | Assigned entities only: analysis input, drafts, report generation |
| Ketua Bahagian | `head_of_division` | Dashboard, all entities, audit trail (read-only) |
| Document Controller | `document_controller` | No entity access (permissions not yet defined) |
| Pegawai Rekod Analisis | `analysis_records_officer` | No entity access (permissions not yet defined) |

**Account rules**

- The username is the login credential and must be unique.
- Issue a temporary password and require the user to change it after first login.
- Deleting a user does **not** delete their audit-trail entries — history is
  preserved deliberately.
- Re-running `php artisan db:seed` never resets an existing administrator's
  password.

---

## 6. BACKUP AND RESTORE

### 6.1 Taking a backup

```bash
php scripts/backup-database.php                      # → storage/backups/backup-YYYYMMDD-HHMMSS.sqlite
php scripts/backup-database.php --target=/mnt/backup # custom directory
php scripts/backup-database.php --keep=14            # keep only the newest 14
```

The script:

1. verifies the integrity of the live database **before** copying it,
2. uses SQLite `VACUUM INTO` — safe while the application is running, no downtime,
3. verifies the resulting file opens and contains tables,
4. refuses to overwrite an existing file.

Exit code `0` = success, `1` = failure. Safe to use in cron/Task Scheduler.

> `storage/backups` is git-ignored. Backups contain live data — copy them to
> secured storage off the server and treat them as classified material.

### 6.2 Scheduling

**Linux (cron)** — daily at 01:00, keeping 30 copies:

```cron
0 1 * * * cd /var/www/spark && /usr/bin/php scripts/backup-database.php --keep=30 --quiet >> storage/logs/backup.log 2>&1
```

**Windows (Task Scheduler)**

```powershell
Program : C:\php\php.exe
Argument: scripts\backup-database.php --keep=30 --quiet
Start in: C:\inetpub\spark
```

### 6.3 Restoring

```bash
php scripts/restore-database.php --from=storage/backups/backup-20260817-010000.sqlite
```

The script verifies the backup first, copies the current database to
`pra-pemulihan-<timestamp>.sqlite` as a safety net, asks you to type `PULIH` to
confirm, restores, then verifies the result.

After restoring:

```bash
php artisan migrate --force      # if the backup predates a schema change
php artisan config:clear
php artisan cache:clear
```

Add `--yes` to skip the prompt in automated recovery, and `--database=PATH` to
restore into a scratch file for a rehearsal.

### 6.4 Restore rehearsal (do this before go-live)

```bash
php scripts/backup-database.php --target=/tmp/rehearsal.sqlite
cp database/database.sqlite /tmp/target.sqlite
php scripts/restore-database.php --from=/tmp/rehearsal.sqlite --database=/tmp/target.sqlite --yes
```

This exercises the whole path without touching production data.

---

## 7. UPGRADING AN EXISTING INSTALLATION

```bash
php scripts/backup-database.php            # 1. ALWAYS back up first
php artisan down                           # 2. Maintenance mode
git pull                                   # 3. New code
composer install --no-dev --optimize-autoloader
npm ci && npm run build                    # 4. Rebuild assets
php artisan migrate --force                # 5. Schema changes
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up                             # 6. Back online
```

If step 5 fails, restore the backup from step 1 before bringing the site up.

---

## 8. SECURITY CHECKLIST

Run through this before handing the system to users.

| # | Check | How to verify |
| --- | --- | --- |
| S1 | `APP_DEBUG=false` | `php artisan about` → Debug Mode: OFF |
| S2 | `APP_ENV=production` | `php artisan about` |
| S3 | `APP_KEY` is set and unique to this install | `.env` |
| S4 | Site served over HTTPS only | Browser; HTTP should redirect |
| S5 | `SESSION_SECURE_COOKIE=true` | `.env` |
| S6 | Document root is `public/` | Request `/.env` → must be 404/403 |
| S7 | Database file unreachable over HTTP | Request `/database/database.sqlite` → 404/403 |
| S8 | Default admin password changed | Log in and change it |
| S9 | No test/demo accounts exist | **Pentadbiran → Pengguna** |
| S10 | Login rate limiting works | 5 wrong passwords → blocked ~60 s |
| S11 | Analyst cannot reach unassigned entities | UAT scenario S6 |
| S12 | Audit trail restricted | Analyst opening `/jejak-audit` → 403 |
| S13 | File permissions minimal | `storage`, `bootstrap/cache`, `database` writable; nothing else |
| S14 | Backups stored off-server and access-controlled | Backup location |
| S15 | `.env` not committed to git | `git check-ignore .env` |

Automated coverage: `php artisan test --filter=Phase13ReleaseReadinessTest`
verifies S1/S2/S5 templates, S10, S12 and S15 mechanically.

### Known security gaps (accepted for V1.0-RC1)

- **No password policy** (minimum length/complexity) and no forced rotation.
- **No account lockout beyond rate limiting** — throttling is per
  username + IP, 5 attempts / 60 seconds.
- **No two-factor authentication.**
- **No password reset flow** — the administrator resets passwords manually.

These are deliberate scope decisions, not defects. Raise them as business rules
before adding them.

---

## 9. OPERATIONS

### 9.1 Logs

Application logs: `storage/logs/laravel-YYYY-MM-DD.log` (daily rotation).
Review them after each UAT session and weekly in production. Ship them to the
central log server if one exists.

### 9.2 Cache commands

```bash
php artisan config:cache   # after every .env change
php artisan route:cache
php artisan view:cache
php artisan optimize:clear # clear everything (troubleshooting)
```

### 9.3 Verifying PDF generation on the server

```bash
node -v                                     # Node must be on PATH
npx puppeteer browsers install chrome       # if Chrome is missing
php artisan test --filter=test_penjanaan_laporan_menghasilkan_fail_pdf
```

The test is skipped automatically when Chrome is unavailable — a skip here means
**PDF download will fail for users**, so treat it as a blocker.

### 9.4 Health check

`GET /up` returns HTTP 200 when the application boots. Use it for uptime
monitoring.

---

## 10. TROUBLESHOOTING

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| 500 on every page | Missing `APP_KEY` or unwritable `storage/` | `php artisan key:generate`; fix permissions |
| "database is locked" | Web user cannot write the DB **directory** (WAL files) | Make `database/` writable |
| "readonly database" | Wrong owner on `database.sqlite` | `chown www-data database/database.sqlite` |
| Changed `.env` has no effect | Config cached | `php artisan config:cache` |
| Blank/unstyled pages | Assets not built | `npm run build` |
| 404 on every route except `/` | Rewrite rules missing | Enable `mod_rewrite` / URL Rewrite |
| PDF download fails | Chrome/Node missing on server | §9.3 |
| Everyone sees "no entities" | Users have roles without entity access | Check roles in **Pentadbiran → Pengguna** |
| Login always fails after correct password | Rate limit still active | Wait 60 seconds |
| Dashboard numbers look stale | Browser cache | Hard refresh; numbers are computed per request |

---

## 11. WHAT IS NOT IN V1.0-RC1

| Capability | Status |
| --- | --- |
| Report review & approval workflow (Phase 10) | **Not implemented — release blocker for full V1.0** |
| Risk assessment / readiness modules | Roadmap (menu items disabled) |
| Email notifications | Out of scope |
| Automatic document extraction / OCR / AI analysis | Explicitly out of scope |
| External system integration | Out of scope |
| Password reset by user | Not implemented — administrator resets manually |

The upload module (`Muat Naik MasterTable`) still exists for the legacy
inventory flow and is available to Pentadbir and Penyelaras. **No part of the
reporting workflow depends on it** — this is verified automatically by
`Phase13ReleaseReadinessTest`. It is flagged for deprecation review, not deletion.
