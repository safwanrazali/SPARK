# SPARK

**Sistem Pemantauan & Pelaporan Analisis Data Migrasi PQC**

Bahagian Migrasi PQC · Pusat Teknologi dan Pengurusan Kriptologi Malaysia (PTPKM)

Internal monitoring and reporting platform for post-quantum cryptography
migration analysis across CNII entities: sector → entity → assignment →
workflow → analysis → signed PDF report.

**Classification: RAHSIA.** Reports carry the marking on every page.

---

## Stack

| | |
|---|---|
| PHP | 8.3+ (staging runs 8.5) |
| Framework | Laravel 13 |
| Database | SQLite |
| Front-end | Blade + Bootstrap 5 + SCSS, built with Vite. No JS framework. |
| PDF | Spatie Browsershot → Puppeteer → headless Chrome |
| Tests | PHPUnit |

Entities and sectors live in `config/sektor.php` (11 sectors, 252 entities) —
**not** in the database.

---

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan db:seed                 # creates the initial administrator

npm install
npm run build

# required for PDF generation — see .puppeteerrc.cjs
npx puppeteer browsers install chrome-headless-shell

composer dev                        # serve + queue + logs + vite
```

Verify PDF works before trusting the suite:

```bash
php artisan test --filter=test_penjanaan_laporan_menghasilkan_fail_pdf
```

It must report **passed**, not skipped. The test skips when Chrome is missing
and PHPUnit still reports success — a skip means PDF download is broken.

---

## Tests

```bash
php artisan test
```

Current baseline and the list of known pre-existing failures are recorded in
[`CLAUDE.md`](CLAUDE.md) §9. Compare against it before assuming your change
caused a failure.

---

## Deployment

**`git push` does not deploy.** The server holds an independent working copy:

```bash
# on the server
cd /srv/projecta/spark
bash scripts/deploy.sh
```

`deploy.sh` pulls, runs only the steps the changed files require (Composer,
migrations, npm, asset build), and **always** rebuilds the config/route/view
caches — without which pulled code has no effect and produces no error.

Full server procedure, including the PDF prerequisites that are **not** carried
by git (Chrome binary, system libraries, AppArmor sysctl), is in
[`docs/ADMIN_GUIDE.md`](docs/ADMIN_GUIDE.md) §9.3 and §9.5.

---

## Documentation

| Document | Audience |
|---|---|
| [`CLAUDE.md`](CLAUDE.md) | Developers — architecture, conventions, single sources of truth, test baseline |
| [`docs/ADMIN_GUIDE.md`](docs/ADMIN_GUIDE.md) | Installation, environment, web server, backup/restore, security checklist, troubleshooting |
| [`docs/USER_GUIDE.md`](docs/USER_GUIDE.md) | End users, role by role (Bahasa Melayu) |
| [`docs/UAT_CHECKLIST.md`](docs/UAT_CHECKLIST.md) | Acceptance testing scenarios |
| [`PANDUAN_CATATAN_LAPORAN.md`](PANDUAN_CATATAN_LAPORAN.md) | Report comments module |
| [`RELEASE_NOTES.md`](RELEASE_NOTES.md) | Release history |

---

## Operations

```bash
php scripts/backup-database.php     # hot backup, integrity-checked
php scripts/restore-database.php    # verifies before writing; requires confirmation
```

`GET /up` is the health endpoint.

---

## Scope

Workflow stages **1.1 – 3.1** are implemented. Stages **3.2, 4 and 5** exist in
the structure but are reserved for a later phase and reject all actions.

The system does **not** accept file uploads, send notifications, or integrate
with external systems. Analysis findings are keyed in through the nine-section
structured form.
