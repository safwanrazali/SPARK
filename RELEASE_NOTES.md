# RELEASE NOTES

## Sistem Pemantauan & Pelaporan Analisis Data Migrasi PQC

---

# V1.0-RC1 — Release Candidate 1

**Date**: 2026-08-17
**Status**: Ready for UAT
**Not yet**: full V1.0 — see [Release blocker](#release-blocker) below

This is the first candidate build of the centralised monitoring and reporting
platform for PQC migration data analysis. It replaces log-based and manual
tracking with a single system covering sector → entity → assignment → workflow →
status → dashboard, and the structured analysis report flow from findings entry
through to a generated PDF.

---

## Release blocker

**Phase 10 — Review & Approval is not implemented.** There is no
"submit for review" action, no reviewer screen, no approval record, and no
report-level review statuses. The `approval_logs` table and the
`review-report` / `approve-report` permissions exist but are unused.

Consequence: UAT scenarios 16–18 cannot be fully executed, and the Approval
acceptance criteria (specification §34) remain unmet. **Full V1.0 cannot be
signed off until Phase 10 is built and a second UAT round covers those
scenarios.** In the meantime, stage 6 (_Semakan & Kelulusan_) behaves as an
ordinary workflow stage driven by the Coordinator.

---

## What is included

### Monitoring

- **Sector → entity navigation** across the master list of 252 entities.
- **Entity assignment**: Coordinator assigns an entity to an Analyst, with
  reassignment, withdrawal, and full assignment history. One active assignment
  per entity is enforced at database level.
- **7-stage workflow** (Penerimaan & Pendaftaran Data → Penyerahan & Penutupan)
  with sequential-forward transitions, backward transitions that require a
  recorded reason, per-stage work status, status date and updating officer.
- **Entity information hub**: assignment, workflow progress, analysis findings,
  report status and history in one page.
- **Management dashboard**: sector/entity counts, in-progress and completed
  totals, report counts, overall progress and the 7-stage distribution — every
  figure computed from records, never stored. Sector and date-range filters.

### Reporting

- **Structured findings entry** across 9 sections, using checkboxes, dropdowns,
  radios, number and date inputs — no free-text where a structured choice exists.
- **Cryptographic algorithm checkboxes** based on the AKSA MySEAL categories.
  Ticked means the entity uses that algorithm; unticked means it does not.
  Deprecated and quantum-at-risk algorithms are flagged in the UI and drive the
  report's automatic conclusions.
- **Draft save and resume**: section-by-section persistence with version
  numbering, last-saved time and saving officer, plus silent autosave every
  3 minutes and an unsaved-work warning on page exit.
- **Validation** at both form and server level; drafts are deliberately exempt
  so partial work is never lost.
- **Preview and PDF generation** of the Laporan Analisis Inventori Kriptografi
  following the official template, with repeating header (NACSA + PTPKM logos,
  RAHSIA marking) and footer (reference code, page number).
- **No document upload is required anywhere in the reporting flow.**

### Security and traceability

- Seven roles: Pentadbir Sistem, Pegawai Analisis, Pegawai Penyelaras Analisis,
  Pegawai Penyelaras Rekod, Pegawai Kawalan Dokumen, Ketua Bahagian,
  Timbalan Pengarah II. A user may hold more than one role; permissions combine.
- **Assigned-only access for Analysts**, enforced at route, controller, service,
  query and API layers — not merely by hiding buttons.
- **Audit trail** of every important change (assignment, workflow, drafts,
  analysis saves, report status) with old value, new value, user, timestamp and
  metadata. Records are append-only and cannot be edited or deleted.
- Analysis findings content is never written into the audit trail.

---

## Changes in this build (Phase 13)

### Security fixes

- **Login rate limiting** — 5 failed attempts per username + IP within
  60 seconds now blocks further attempts, with a clear message. A successful
  login clears the counter, and other accounts from the same IP are unaffected.
- **Seeder no longer ships a credential.** The initial administrator password
  now comes from `ADMIN_PASSWORD` in `.env`. On a production server the seeder
  aborts (exit code 1) if it is not set; on development machines it generates a
  random password and prints it once.
- **Re-running the seeder no longer resets the administrator password.**
  Previously `php artisan db:seed` reverted the account to the password stored
  in the repository.
- **Removed the scaffold test account** (`testuser` / `password`) from
  `DatabaseSeeder`, which would otherwise be created on any server where
  `db:seed` ran.
- `storage/backups` added to `.gitignore` so database copies containing live
  data cannot be committed.

### Operations

- **`scripts/backup-database.php`** — hot backup with no downtime, integrity
  check before and after, optional retention (`--keep=N`), non-zero exit code on
  failure so it is safe to schedule.
- **`scripts/restore-database.php`** — verifies the backup before writing
  anything, snapshots the current database first, requires typed confirmation,
  and re-verifies the restored file.
- **`.env.production.example`** — hardened production template
  (`APP_DEBUG=false`, encrypted and secure session cookies, `LOG_LEVEL=warning`,
  absolute database path, admin credentials).
- **`config/pentadbir.php`** — installation account settings read through the
  config layer so they keep working with a cached config.

### Fixed

- `MuatNaikSeeder` was missing its model import and crashed if run.

### Documentation

- `docs/UAT_CHECKLIST.md` — 21 scenarios with steps, expected results, defect
  log and sign-off sheet.
- `docs/USER_GUIDE.md` — role-by-role usage guide in Malay.
- `docs/ADMIN_GUIDE.md` — installation, environment, web server, permissions,
  backup/restore, upgrade, security checklist, troubleshooting.
- `PHASE_13_RELEASE_READINESS_REPORT.md` — verification evidence and risks.

---

## Verification performed

| Check                                             | Result                       |
| ------------------------------------------------- | ---------------------------- |
| Automated tests                                   | 455 passed, 0 failed         |
| Clean install rehearsal (migrate → seed → verify) | Passed on a scratch database |
| Migration rollback and re-run                     | Passed                       |
| Backup → simulated data loss → restore            | Passed                       |
| Production build (`npm run build`)                | Passed                       |
| Code style (`vendor/bin/pint`)                    | Passed on all changed files  |
| PHP syntax check across the codebase              | Passed                       |
| Blade template compilation                        | Passed                       |
| Route, config and view caching                    | Passed                       |
| Real PDF generation                               | Passed (`%PDF-1.4`)          |

---

## Upgrading

This is the first release; there is no upgrade path from an earlier version.
For an existing development database, follow `docs/ADMIN_GUIDE.md` §7 — back up
first, then `migrate --force`.

**Action required after deploying this build**

1. Add `ADMIN_USERNAME` and `ADMIN_PASSWORD` to `.env` before running
   `php artisan db:seed`.
2. If the account `AdminMpq` already exists with the previously committed
   password, **change that password immediately** — it was in version control.
3. Confirm no `testuser` account exists in production.

---

## Known limitations

| Area                                | Limitation                                                    |
| ----------------------------------- | ------------------------------------------------------------- |
| Review & approval                   | Not implemented (Phase 10) — release blocker                  |
| Risk assessment / readiness modules | Roadmap; menu entries are disabled                            |
| Notifications                       | Not in scope                                                  |
| Password self-service               | No reset flow; administrators reset manually                  |
| Password policy                     | No complexity or rotation rules                               |
| Two-factor authentication           | Not implemented                                               |
| Timezone                            | Records are stored in UTC by default — decide before go-live  |
| Upload module                       | Retained for the legacy inventory flow; not used by reporting |
| Automated UI testing                | None; UAT covers the interface manually                       |

---

## Compatibility

| Component             | Version                       |
| --------------------- | ----------------------------- |
| PHP                   | 8.3+                          |
| Laravel               | 13.x                          |
| Database              | SQLite 3.27+                  |
| Node.js (build + PDF) | 20 LTS+                       |
| Browsers              | Current Chrome, Edge, Firefox |
