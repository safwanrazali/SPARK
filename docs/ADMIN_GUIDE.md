# ADMIN & DEPLOYMENT GUIDE

## Sistem Pemantauan & Pelaporan Analisis Data Migrasi PQC — V1.0-RC1

Audience: system administrators and the deployment team.
For day-to-day usage see [`USER_GUIDE.md`](USER_GUIDE.md).

---

## 1. SYSTEM REQUIREMENTS

| Component         | Requirement               | Notes                                                            |
| ----------------- | ------------------------- | ---------------------------------------------------------------- |
| PHP               | 8.3 or newer              | Extensions: `pdo_sqlite`, `mbstring`, `openssl`, `zip`, `gd`     |
| Composer          | 2.x                       | Dependency installation                                          |
| Node.js           | 20 LTS or newer           | Front-end build only; not needed at runtime except for PDF       |
| SQLite            | 3.27+                     | 3.27 required for hot backups (`VACUUM INTO`)                    |
| Chrome / Chromium | Headless                  | **Required for PDF generation** (Spatie Browsershot + Puppeteer) |
| Web server        | nginx, Apache or IIS      | Document root must be `public/`                                  |
| Disk              | ~500 MB + database growth | Includes `node_modules` and the bundled Chromium                 |

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

| Key                                 | Production value     | Why                                                                              |
| ----------------------------------- | -------------------- | -------------------------------------------------------------------------------- |
| `APP_ENV`                           | `production`         | Enables framework production behaviour                                           |
| `APP_DEBUG`                         | `false`              | **Critical.** `true` exposes stack traces, file paths and config values to users |
| `APP_KEY`                           | generated            | Sessions and encrypted data are unreadable without it                            |
| `APP_URL`                           | full `https://…` URL | Used for generated links and assets                                              |
| `DB_DATABASE`                       | absolute path        | Scheduled tasks and backup scripts must resolve the same file                    |
| `SESSION_SECURE_COOKIE`             | `true`               | Session cookie is never sent over plain HTTP                                     |
| `SESSION_HTTP_ONLY`                 | `true`               | JavaScript cannot read the session cookie                                        |
| `SESSION_SAME_SITE`                 | `lax`                | CSRF hardening                                                                   |
| `SESSION_ENCRYPT`                   | `true`               | Session payloads encrypted at rest                                               |
| `LOG_LEVEL`                         | `warning`            | `debug` writes far too much on a production box                                  |
| `BCRYPT_ROUNDS`                     | `12`                 | Password hashing cost; do not lower                                              |
| `ADMIN_USERNAME` / `ADMIN_PASSWORD` | set before seeding   | Seeder aborts in production if `ADMIN_PASSWORD` is empty                         |

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

| Short | Role (UI label)             | Key                        | Grants                                                                            |
| ----- | --------------------------- | -------------------------- | --------------------------------------------------------------------------------- |
| PS    | Pentadbir Sistem            | `administrator`            | Everything, including user management                                             |
| PA    | Pegawai Analisis            | `analyst`                  | Assigned entities only: analysis input, drafts, report generation                 |
| PPA   | Pegawai Penyelaras Analisis | `coordinator`              | Dashboard, all entities, assignment, workflow control, report status, audit trail |
| PPR   | Pegawai Penyelaras Rekod    | `analysis_records_officer` | Dashboard, all entities, report status, audit trail (read-only; no task yet)      |
| PKD   | Pegawai Kawalan Dokumen     | `document_controller`      | Records every No. Rujukan (1.1–1.3, 3.1); sees entities once 1.1 has begun        |
| KB    | Ketua Bahagian              | `head_of_division`         | Dashboard, all entities, audit trail (read-only)                                  |
| TPII  | Timbalan Pengarah II        | `deputy_director_ii`       | Dashboard and all entities, read-only (permissions not yet finalised)             |

The short code is shown beside each role on the add/edit user form. Both the
full name and the code come from `User::roleDefinitions()` — edit that one
array to rename a role or change its code.

**Account rules**

- The username is the login credential and must be unique.
- A user may hold **more than one role**; the permissions of every selected
  role are combined. Adding a role never removes access the user already had.
- Passwords issued by an administrator are **temporary**. The account is
  flagged and locked to the "Tukar Kata Laluan" screen until the owner
  replaces it — see §5.2.
- Deleting a user does **not** delete their audit-trail entries — history is
  preserved deliberately.
- Re-running `php artisan db:seed` never resets an existing administrator's
  password — see §5.1.

### 5.1 Resetting a user's password

When a user asks for a reset, open **Pentadbiran → Pengguna** and press the
key icon on their row. Three things happen at once:

1. A 16-character temporary password is generated and shown **once** — copy it
   before leaving the page; it is not stored in readable form.
2. The account is flagged, so the user must replace it at next login.
3. **Every active session for that account is terminated.** This matters when
   the reset is prompted by a suspected compromise: without it the intruder's
   existing session survives, and because the change-password screen does not
   ask for the current password, they could simply set their own.

Administrators cannot reset their own password this way — use **Profil Saya**,
which avoids locking yourself onto the change screen.

> Session termination requires `SESSION_DRIVER=database` (the default). On a
> file or cache driver the reset still works, but existing sessions survive.

### 5.2 Forced password change on first login

Any account flagged by a reset or by user creation is redirected to
`/tukar-kata-laluan` on every request until the temporary password is replaced.
Only that screen and logout remain reachable. The replacement must meet the
same strength rules and cannot be the temporary password itself.

### 5.3 The default administrator account

Only `administrator` can add users, so the system guarantees that at least one
such account always exists.

`php artisan db:seed` creates it from the `ADMIN_*` values in `.env`. If
`ADMIN_PASSWORD` is blank, a random password is generated and printed **once**
on the console (non-production only; on production the seeder aborts instead).

**The system refuses to be left without an administrator.** Removing the
`administrator` role from the last remaining administrator is rejected with a
validation error, as is deleting that account. Appoint a second administrator
first if you need to hand the role over.

**If the administrator password is lost**, `db:seed` will not help — it never
resets an existing password. Use the recovery command instead:

```bash
# Report status; never changes an existing password
php artisan pentadbir:sedia

# Recreate the account if missing, or restore the administrator role if it was
# somehow removed — other roles on the account are kept
php artisan pentadbir:sedia

# Set a specific password (shown once, then store it safely)
php artisan pentadbir:sedia --kata-laluan='<KATA-LALUAN-BAHARU>'

# Reset using ADMIN_PASSWORD from .env, or a generated one outside production
php artisan pentadbir:sedia --tetap-semula
```

Run it from the application root as the same user that owns the database file.
Change the password again from **Profil Saya** after logging in.

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

| #   | Check                                           | How to verify                                                   |
| --- | ----------------------------------------------- | --------------------------------------------------------------- |
| S1  | `APP_DEBUG=false`                               | `php artisan about` → Debug Mode: OFF                           |
| S2  | `APP_ENV=production`                            | `php artisan about`                                             |
| S3  | `APP_KEY` is set and unique to this install     | `.env`                                                          |
| S4  | Site served over HTTPS only                     | Browser; HTTP should redirect                                   |
| S5  | `SESSION_SECURE_COOKIE=true`                    | `.env`                                                          |
| S6  | Document root is `public/`                      | Request `/.env` → must be 404/403                               |
| S7  | Database file unreachable over HTTP             | Request `/database/database.sqlite` → 404/403                   |
| S8  | Default admin password changed                  | Log in and change it                                            |
| S9  | No test/demo accounts exist                     | **Pentadbiran → Pengguna**                                      |
| S10 | Login rate limiting works                       | 5 wrong passwords → blocked ~60 s                               |
| S11 | Analyst cannot reach unassigned entities        | UAT scenario S6                                                 |
| S12 | Audit trail restricted                          | Analyst opening `/jejak-audit` → 403                            |
| S13 | File permissions minimal                        | `storage`, `bootstrap/cache`, `database` writable; nothing else |
| S14 | Backups stored off-server and access-controlled | Backup location                                                 |
| S15 | `.env` not committed to git                     | `git check-ignore .env`                                         |

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

### 9.3 PDF generation — server setup and verification

PDF download spawns headless Chrome through Browsershot on **every request**, so
Chrome must work for the **web server user** (`www-data`), not just your shell.
Four things have to be right. Verify in order.

**a. Chrome cache location — already handled**

`.puppeteerrc.cjs` (committed) pins the Chrome cache to `<project>/.cache`
instead of `~/.cache/puppeteer`. Without it, Chrome installed by your shell user
is invisible to `www-data`, and PDF succeeds on the CLI but fails in the browser.
Nothing to do — just don't delete that file.

**b. Install the browser binary**

```bash
cd <project root>
npx puppeteer browsers install chrome-headless-shell
```

Use **`chrome-headless-shell`**, not `chrome`. Browsershot launches with
`headless: 'shell'` (`vendor/spatie/browsershot/bin/browser.cjs`), so the plain
`chrome` build is not what it looks for.

**c. System libraries**

The binary links against GTK/ATK libraries that a minimal server image lacks.
Check what is missing:

```bash
ldd .cache/puppeteer/chrome-headless-shell/linux-*/chrome-headless-shell-linux64/chrome-headless-shell   | grep "not found"
```

On Ubuntu 24.04 and newer (note the `t64` suffixes):

```bash
sudo apt-get update
sudo apt-get install -y   libnss3 libnspr4 libdrm2 libgbm1 libxkbcommon0 libxcomposite1   libxdamage1 libxfixes3 libxrandr2 libpango-1.0-0 libcairo2   libatk1.0-0t64 libatk-bridge2.0-0t64 libcups2t64 libasound2t64 libatspi2.0-0t64
```

On older releases, drop the `t64` suffixes. Re-run the `ldd` check — it should
print nothing.

**d. Chrome sandbox (Ubuntu 23.10+)**

These releases restrict unprivileged user namespaces via AppArmor, which Chrome's
sandbox needs. Symptom: `No usable sandbox!`.

```bash
sudo sysctl -w kernel.apparmor_restrict_unprivileged_userns=0
echo 'kernel.apparmor_restrict_unprivileged_userns=0' | sudo tee /etc/sysctl.d/99-puppeteer.conf
```

This keeps Chrome's sandbox working. The alternative — running Chrome with
`--no-sandbox` — requires an application change and weakens process isolation;
prefer the sysctl.

**e. Verify as BOTH users**

```bash
# as the maintenance user
php artisan tinker --execute='$p=Spatie\Browsershot\Browsershot::html("<h1>x</h1>")->format("A4")->writeOptionsToFile()->pdf(); echo "OK ".strlen($p)." ".substr($p,0,5), PHP_EOL;'

# as the web user — this is the one that predicts browser behaviour
sudo -u www-data env HOME=/tmp php artisan tinker --execute='$p=Spatie\Browsershot\Browsershot::html("<h1>x</h1>")->format("A4")->writeOptionsToFile()->pdf(); echo "OK ".strlen($p)." ".substr($p,0,5), PHP_EOL;'
```

Both must print `OK <bytes> %PDF-`. **The first passing alone proves nothing** —
the cache-location and permission faults only show up under `www-data`.
`HOME=/tmp` just gives Tinker a writable config directory.

Finally, download a report through the browser. Only that exercises nginx and
php-fpm; the CLI checks bypass both.

> If dev dependencies are installed, `php artisan test --filter=test_penjanaan_laporan_menghasilkan_fail_pdf`
> also covers this. It **skips** rather than fails when Chrome is unavailable, so
> a skip must be treated as a blocker, not a pass.

### 9.4 Health check

`GET /up` returns HTTP 200 when the application boots. Use it for uptime
monitoring.

### 9.5 Deploying

```bash
cd /srv/projecta/spark
bash scripts/deploy.sh --dry-run    # show the plan, change nothing
bash scripts/deploy.sh
```

The script pulls the checked-out branch, runs only the steps the changed files
require (Composer, migrations, npm, asset build), and **always** rebuilds the
config/route/view caches. That last part is not optional: with caches active,
pulled code has no effect until they are rebuilt, and it fails silently.

It refuses to run against a dirty working tree. `package-lock.json` and
`composer.lock` are the usual culprits — both are generated, so discarding the
server's copy is safe. A modified *source* file means someone edited the server
directly; move that change into the repository instead.

**Unattended permission fixes (optional)**

After a pull, new files must stay readable by the web user. The script attempts
this with `sudo -n` (never prompts) and otherwise prints the commands for you to
run manually — so an unattended deploy never hangs on a password prompt.

To let it finish unaided, grant those three commands — and nothing else —
without a password. First confirm the binary paths, because sudoers matches
absolute paths:

```bash
command -v chgrp chmod
```

Then create a dedicated file. **Never edit `/etc/sudoers` with a normal editor**
— `visudo` validates syntax before saving, and a malformed sudoers file locks
you out of `sudo` completely:

```bash
sudo visudo -f /etc/sudoers.d/spark-deploy
```

Paste the following, adjusting the user, binary paths and project root:

```
safwan ALL=(root) NOPASSWD: /usr/bin/chgrp -R www-data /srv/projecta/spark, \
                            /usr/bin/chmod -R g+rX /srv/projecta/spark, \
                            /usr/bin/chmod -R g+w /srv/projecta/spark/storage /srv/projecta/spark/bootstrap/cache /srv/projecta/spark/database
```

Verify:

```bash
sudo -n chgrp -R www-data /srv/projecta/spark && echo "NOPASSWD active"
```

This grants no general root access — it matches those exact command lines only.
`deploy.sh` uses absolute paths deliberately so the invocation matches the rule.

---

## 10. TROUBLESHOOTING

| Symptom                                   | Likely cause                                           | Fix                                            |
| ----------------------------------------- | ------------------------------------------------------ | ---------------------------------------------- |
| 500 on every page                         | Missing `APP_KEY` or unwritable `storage/`             | `php artisan key:generate`; fix permissions    |
| "database is locked"                      | Web user cannot write the DB **directory** (WAL files) | Make `database/` writable                      |
| "readonly database"                       | Wrong owner on `database.sqlite`                       | `chown www-data database/database.sqlite`      |
| Changed `.env` has no effect              | Config cached                                          | `php artisan config:cache`                     |
| Blank/unstyled pages                      | Assets not built                                       | `npm run build`                                |
| 404 on every route except `/`             | Rewrite rules missing                                  | Enable `mod_rewrite` / URL Rewrite             |
| PDF download fails                        | Chrome/Node missing on server                          | §9.3                                           |
| Everyone sees "no entities"               | Users have roles without entity access                 | Check roles in **Pentadbiran → Pengguna**      |
| Login always fails after correct password | Rate limit still active                                | Wait 60 seconds                                |
| Dashboard numbers look stale              | Browser cache                                          | Hard refresh; numbers are computed per request |

### 10.1 Faults seen during real deployments

| Symptom                                                   | Likely cause                                                                                   | Fix                                                                     |
| --------------------------------------------------------- | ---------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------ |
| 500 on every page; log ends at `parseDatabasePath()`      | `DB_DATABASE` points **through a directory the web user cannot traverse** — typically `/home/<user>/…`, which is `drwxr-x---` | Host the application somewhere `www-data` can traverse (e.g. `/srv/…`), set `DB_DATABASE` to that path, then `config:cache` |
| Pulled new code but the site is unchanged                 | `config`/`route`/`view` caches still hold the previous build — fails **silently**              | `php artisan optimize:clear`, then re-cache. Use `bash scripts/deploy.sh` |
| PDF works on the CLI but fails in the browser             | Chrome cache not visible to `www-data`                                                          | §9.3a; always verify under `sudo -u www-data`                            |
| PDF: `Could not find chrome-headless-shell`               | Browser binary not installed into the project cache                                             | §9.3b                                                                     |
| PDF: `libatk-1.0.so.0: cannot open shared object file`    | Chrome's system libraries missing                                                               | §9.3c                                                                     |
| PDF: `No usable sandbox!`                                 | Ubuntu 23.10+ AppArmor restriction on unprivileged user namespaces                              | §9.3d                                                                     |
| PDF test reports success but never ran                    | It **skips** when Chrome is unavailable, and PHPUnit still reports `passed`                     | Treat a skip as a blocker; verify with §9.3e instead                      |
| Hostname unreachable from client machines                 | The server's hostname only resolves where a `hosts` entry exists                                 | Add an internal DNS A record; `hosts` edits are per-machine               |

> **Traversal, not permissions.** The first row catches people out because the
> database file itself is readable and writable — it is an intermediate
> *directory* that blocks `realpath()`. `sudo -u www-data test -w database/database.sqlite`
> passes from inside the project (relative path) while the absolute path in
> `.env` still fails.

---

## 11. WHAT IS NOT IN V1.0-RC1

| Capability                                        | Status                                              |
| ------------------------------------------------- | --------------------------------------------------- |
| Report review & approval workflow (Phase 10)      | **Not implemented — release blocker for full V1.0** |
| Risk assessment / readiness modules               | Roadmap (menu items disabled)                       |
| Email notifications                               | Out of scope                                        |
| Automatic document extraction / OCR / AI analysis | Explicitly out of scope                             |
| Excel upload / import of inventory workbooks      | Not part of V1.0 — findings are keyed in manually   |
| External system integration                       | Out of scope                                        |
| Password reset by user                            | Not implemented — administrator resets manually     |

SPARK V1.0 has **no file upload or import path**. Analysis findings reach the
system only through the nine-section structured form; the Excel upload module
that existed in earlier development builds has been removed in full — code,
routes, views, database table and the `maatwebsite/excel` dependency.
`Phase13ReleaseReadinessTest` verifies automatically that nothing reintroduces
it.
