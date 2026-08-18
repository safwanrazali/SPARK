# PHASE 13 — UAT, DEPLOYMENT & V1.0 RELEASE PREPARATION

## Sistem Pemantauan & Pelaporan Analisis Data Migrasi PQC

**Date**: 2026-08-17 | **Build**: V1.0-RC1
**Verdict**: ✅ **GO for UAT** · ⛔ **NO-GO for full V1.0 sign-off** (Phase 10 missing)
**Tests**: 455 passed / 1 818 assertions / 0 failed

---

## 1. DECISIONS TAKEN BEFORE STARTING

Two conflicts between the requested UAT script and the implemented system were
resolved with the project owner before any work began:

| #   | Conflict                                                                                                                                                                                         | Decision                                                                                                                                                                                                                       |
| --- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| 1   | UAT scenario 7 has the **Analyst** moving the workflow, but `manage-workflow` allows only Pentadbir + Penyelaras (§26 has no row for stage transitions; flagged `NEEDS CONFIRMATION` in Phase 9) | **Keep Penyelaras/Pentadbir only.** No code change. UAT scenario 7 rewritten so the Coordinator moves stages while the Analyst does the analysis work.                                                                         |
| 2   | UAT scenarios 16–18 need Phase 10 Review & Approval, which is not implemented                                                                                                                    | **Document as a release blocker.** No new module built. Scenarios 16–18 marked BLOCKED in the checklist and tested in the reduced form the workflow engine already supports (stage 5→6→7, backward with reason, audit record). |

No new major features, upload capability, AI analysis, automatic risk assessment
or external integrations were added, and the architecture was not rewritten.

---

## 2. THE 13 REQUESTED TASKS

| #   | Task                                 | Status | Evidence                                                                                             |
| --- | ------------------------------------ | ------ | ---------------------------------------------------------------------------------------------------- |
| 1   | Prepare UAT checklist                | ✅     | `docs/UAT_CHECKLIST.md` — 21 scenarios, setup steps, 9 extra checks, defect log, sign-off sheet      |
| 2   | Fix UAT-critical defects             | ✅     | 4 defects found and fixed (§4)                                                                       |
| 3   | Run security checks                  | ✅     | 15-point checklist in `docs/ADMIN_GUIDE.md` §8; mechanical coverage in `Phase13ReleaseReadinessTest` |
| 4   | Run database migration checks        | ✅     | Clean-install rehearsal + rollback/re-run + column/constraint assertions (§5.2)                      |
| 5   | Verify backup/restore                | ✅     | Scripts written **and executed** end-to-end, incl. failure paths (§5.3)                              |
| 6   | Verify environment configuration     | ✅     | `.env.production.example` + `config/pentadbir.php`; template asserted by tests                       |
| 7   | Verify production build              | ✅     | `npm run build` + view/route/config caching (§5.4)                                                   |
| 8   | Verify routes                        | ✅     | Route inventory locked to 35 named routes; every write route requires a gate or `entity.access`      |
| 9   | Verify permissions                   | ✅     | Phase 12 role×route matrix still green; role table published in both guides                          |
| 10  | Verify report generation             | ✅     | Real PDF produced (`%PDF-1.4`, 499 KB); template assets asserted                                     |
| 11  | Verify no workflow depends on upload | ✅     | Reporting classes and views asserted free of upload references; 0 upload rows in the full journey    |
| 12  | Prepare release notes                | ✅     | `RELEASE_NOTES.md`                                                                                   |
| 13  | Prepare user/admin documentation     | ✅     | `docs/USER_GUIDE.md`, `docs/ADMIN_GUIDE.md`                                                          |

---

## 3. UAT READINESS

| Scenario                                                                             | Coverage                                                                           |
| ------------------------------------------------------------------------------------ | ---------------------------------------------------------------------------------- |
| 1–6 Login, sector, entities, assignment, analyst login, assigned-only access         | ✅ Full                                                                            |
| 7 Entity progresses through workflow                                                 | ⚠️ **Modified** — performed by Coordinator, not Analyst (decision 1)               |
| 8–15 Manual analysis, findings entry, draft, resume, completion, preview, generation | ✅ Full                                                                            |
| 16 Submit for review                                                                 | 🚫 **Blocked** — reduced form: stage 5 → 6                                         |
| 17 Reviewer reviews                                                                  | 🚫 **Blocked** — reduced form: Ketua Bahagian reads entity, report and audit trail |
| 18 Reviewer returns or approves                                                      | 🚫 **Blocked** — reduced form: stage 6 → 5 with reason / 6 → 7                     |
| 19–21 Submission & closure, dashboard, audit trail                                   | ✅ Full                                                                            |

**17 of 21 scenarios are fully testable, 1 is modified, 3 are blocked.**

A detail worth flagging to the UAT team: in the reduced form of scenarios 16–18,
the actions are performed by the **Coordinator, not the reviewer**. Ketua
Bahagian holds the `review-report` and `approve-report` permissions, but no route
consumes them and the role cannot move workflow stages. This is the clearest
practical symptom of the missing Phase 10.

---

## 4. DEFECTS FOUND AND FIXED

### P13-1 — Scaffold account with a known password seeded into any environment (severity: high, security)

`DatabaseSeeder` created a `testuser` account through the factory, which uses the
password `password`. Because `php artisan db:seed` is part of a normal install,
this account would exist on the production server with an analyst role.

**Fixed** — `DatabaseSeeder` now seeds only the administrator account.
Test data is generated by factories inside the test suite alone.

### P13-2 — Administrator password committed to the repository, and reset on every seed (severity: high, security)

`AdminUserSeeder` contained a literal password and used `updateOrCreate`, so
every `db:seed` run reverted the administrator account to the password stored in
version control — silently undoing any password change made by the operator.

**Fixed** — the password now comes from `ADMIN_PASSWORD` via
`config/pentadbir.php` (config layer, so it survives `config:cache`). On
`APP_ENV=production` the seeder aborts with exit code 1 if it is unset; elsewhere
it generates a random 16-character password and prints it once. If the account
already exists, **the password is never touched**.

> ⚠️ Operational follow-up: the previously committed password is in git history.
> Change the `AdminMpq` password on any existing installation.

### P13-3 — No rate limiting on login (severity: medium, security)

`POST /login` had no throttling, allowing unlimited password guessing. This was
raised as risk R2 in the Phase 12 report.

**Fixed** — 5 failed attempts per username + IP within 60 seconds blocks further
attempts with a clear Malay message. A successful login clears the counter.
Keying on username + IP means an attacker cannot lock a colleague out of their
account from a different address.

### P13-4 — `MuatNaikSeeder` crashed when run (severity: low)

The seeder referenced the `MuatNaik` model without importing it, so it fatally
errored on invocation. It is not called by `DatabaseSeeder`, but it is a trip
hazard during deployment.

**Fixed** — import added.

### P13-5 — Database backups could be committed to git (severity: medium, data protection)

Nothing prevented a backup file containing live entity data from being added to
version control.

**Fixed** — `/storage/backups` added to `.gitignore`; the admin guide instructs
that backups be treated as classified and stored off-server.

### Observation, not fixed — timestamps are recorded in UTC

`APP_TIMEZONE` defaults to UTC, so workflow status dates, audit timestamps and
draft save times are stored and displayed in UTC. Switching to
`Asia/Kuala_Lumpur` **after** data exists would make historical records read 8
hours earlier. This is a business decision, not a defect: it is documented in
`docs/ADMIN_GUIDE.md` §3.1 as a go-live decision point and left unchanged.
`NEEDS CONFIRMATION`.

---

## 5. VERIFICATION EVIDENCE

### 5.1 Automated tests

| Suite                               |                            Tests |
| ----------------------------------- | -------------------------------: |
| Total after Phase 13                | **455** (Phase 12 baseline: 424) |
| `Phase13ReleaseReadinessTest` (new) |                               24 |
| `Phase13BackupRestoreTest` (new)    |                                7 |
| Assertions                          |                            1 818 |
| Failures                            |                            **0** |

The new suites cover: login throttling (4 tests), seeder credential rules
(5), environment templates and git hygiene (4), session hardening (1), migration
integrity including a full rollback and re-run (4), route inventory and write-route
authorization (3), report template assets and build manifest (2), upload
independence (2), and the backup/restore round trip with failure paths (7).

### 5.2 Database migration checks

Rehearsed on a scratch database — the live development database was never touched.

```
DB_DATABASE=<temp> php artisan migrate --force        → all migrations DONE
DB_DATABASE=<temp> ADMIN_PASSWORD=… php artisan db:seed --force
                                                      → users=1, role=administrator
                                                      → password hash verified
re-run db:seed with a DIFFERENT ADMIN_PASSWORD        → "Kata laluan tidak diubah"
                                                      → original password still valid, users=1
APP_ENV=production, no ADMIN_PASSWORD                 → exit code 1, users=0
```

Rollback integrity is asserted in-suite: `migrate:reset` drops all 12 application
tables, `migrate` restores every one of them. Every migration file is checked for
a `down()` method, key columns are asserted per table, and the unique constraint
that enforces one active assignment per entity is verified at index level.

### 5.3 Backup and restore

Executed against the real development database (read-only) and a scratch target:

| Step                                      | Result                                                                                        |
| ----------------------------------------- | --------------------------------------------------------------------------------------------- |
| Backup of live database (248 KB)          | ✅ 244 KB, 17 tables, integrity `ok`, source unchanged                                        |
| Restore into scratch target               | ✅ safety copy written first, 4 users / 5 workflow rows restored, pre-existing table replaced |
| Restore from a corrupted file             | ✅ refused, exit 1, destination untouched                                                     |
| Backup onto an existing filename          | ✅ refused, exit 1                                                                            |
| Retention (`--keep=2` with 3 old backups) | ✅ oldest removed, 2 kept                                                                     |

### 5.4 Build, style and static checks

| Command                                                          | Result                    |
| ---------------------------------------------------------------- | ------------------------- |
| `php artisan test`                                               | ✅ 455 passed             |
| `npm run build`                                                  | ✅ 63 modules transformed |
| `vendor/bin/pint` (changed files)                                | ✅ pass                   |
| `php -l` across `app/ config/ database/ routes/ tests/ scripts/` | ✅ no syntax errors       |
| `php artisan view:cache` (compiles every Blade template)         | ✅ pass                   |
| `php artisan route:cache` / `config:cache` / `optimize:clear`    | ✅ pass                   |
| `composer validate --strict`                                     | ✅ valid                  |

---

## 6. REMAINING RISKS

Carried forward from Phase 12 and updated. R2 (login throttling) is now closed.

| #       | Risk                                                                                                                                                             | Impact                                                                    | Action                                                                             |
| ------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------- | ---------------------------------------------------------------------------------- |
| **R1**  | **Phase 10 Review & Approval not implemented.** No submit/review/approve path; `approval_logs` unused; `review-report` / `approve-report` permissions unconsumed | **Release blocker.** UAT 16–18 blocked; §34 Approval criteria unmet       | Build Phase 10, then run a second UAT round for scenarios 16–18                    |
| **R2**  | ~~No login rate limiting~~                                                                                                                                       | —                                                                         | ✅ Closed in this phase (P13-3)                                                    |
| **R3**  | Pre-existing lint debt in 25 untouched files (CRLF endings, migration brace style, import order)                                                                 | Cosmetic; noisy diffs                                                     | Standalone `vendor/bin/pint` housekeeping commit                                   |
| **R4**  | Unauthenticated JSON requests redirect (302) rather than returning 401                                                                                           | No data leak; content negotiation only                                    | Revisit if a real API surface is added                                             |
| **R5**  | Access-control memo is per `User` instance; a long-lived process holding one instance could see stale access                                                     | Low — no such consumer exists                                             | Call `$user->refresh()` in any future worker                                       |
| **R6**  | **PDF generation requires headless Chrome on the server.** The test skips silently when Chrome is missing                                                        | Report download would fail for users while CI stays green                 | Verify per `docs/ADMIN_GUIDE.md` §9.3 during deployment; treat a skip as a blocker |
| **R7**  | No automated browser/UI tests                                                                                                                                    | Rendering and JS regressions (autosave, dynamic rows, stepper) undetected | Covered manually by UAT; consider Dusk if approved                                 |
| **R8**  | Performance verified by query counts on SQLite with ≤20 entities; production has 252                                                                             | Behaviour at full volume unproven                                         | Seeded load test during UAT window                                                 |
| **R9**  | Stage-transition permission was `NEEDS CONFIRMATION`; now confirmed as Penyelaras/Pentadbir                                                                      | Resolved for V1.0                                                         | Revisit only if the operating model changes                                        |
| **R10** | Upload module still reachable by Pentadbir/Penyelaras                                                                                                            | Spec §31 flags it for deprecation, not deletion                           | Dependency audit before any removal                                                |
| **R11** | Pegawai Kawalan Dokumen and Pegawai Penyelaras Rekod have no confirmed permissions and see empty lists                                                             | UX confusion, not a security hole                                         | Confirm intended permissions, then implement                                       |
| **R12** | Previously committed administrator password remains in git history                                                                                               | Credential exposure on existing installs                                  | Change the password on every existing installation                                 |
| **R13** | No password policy, no self-service reset, no 2FA                                                                                                                | Accepted scope decision                                                   | Raise as business rules if required                                                |
| **R14** | Timestamps recorded in UTC                                                                                                                                       | Reports may show unexpected times to local users                          | Decide `APP_TIMEZONE` before go-live (§4)                                          |

---

## 7. GO / NO-GO

### ✅ GO for UAT

The build is installable from scratch, hardened for a production environment,
backed by a verified backup/restore procedure, and documented for both end users
and administrators. All 455 automated tests pass. 17 of 21 UAT scenarios are
fully executable today, with one modified and three clearly bounded.

### ⛔ NO-GO for full V1.0 sign-off

Phase 10 (Review & Approval) is not built. Until it is:

- UAT scenarios 16–18 cannot be completed,
- the Approval acceptance criteria in specification §34 remain unmet,
- stage 6 _Semakan & Kelulusan_ has no report-level meaning.

**Recommended sequence**: run this UAT round → fix any defects found → build
Phase 10 → run a second, focused UAT round on scenarios 16–18 → declare V1.0.

---

## 8. CHANGED AND ADDED FILES

```
Modified  .env.example                                   Admin account keys, timezone guidance
Modified  .gitignore                                     Ignore /storage/backups
Modified  app/Http/Controllers/Auth/LoginController.php  Login rate limiting
Modified  database/seeders/AdminUserSeeder.php           Credentials from env; never resets existing password
Modified  database/seeders/DatabaseSeeder.php            Removed scaffold test account
Modified  database/seeders/MuatNaikSeeder.php            Missing model import

Added     .env.production.example                        Hardened production template
Added     config/pentadbir.php                           Installation account configuration
Added     scripts/backup-database.php                    Hot backup with integrity checks
Added     scripts/restore-database.php                   Guarded restore with safety copy
Added     scripts/lib/sqlite-support.php                 Shared backup/restore helpers
Added     tests/Feature/Phase13ReleaseReadinessTest.php  24 release-readiness tests
Added     tests/Feature/Phase13BackupRestoreTest.php     7 backup/restore tests
Added     docs/UAT_CHECKLIST.md                          21 UAT scenarios + sign-off
Added     docs/USER_GUIDE.md                             Role-by-role user guide (Malay)
Added     docs/ADMIN_GUIDE.md                            Install, deploy, backup, security, troubleshooting
Added     RELEASE_NOTES.md                               V1.0-RC1 release notes
Added     PHASE_13_RELEASE_READINESS_REPORT.md           This report
```

No application feature code was modified in this phase: the only runtime change
is the login rate limiter, and the only data-layer changes are to seeders.
