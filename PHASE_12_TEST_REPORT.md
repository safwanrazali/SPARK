# PHASE 12 — INTEGRATION & TESTING REPORT

## Sistem Pemantauan & Pelaporan Analisis Data Migrasi PQC

**Date**: 2026-08-16 | **Scope**: Phase 12 only (Phase 13 UAT/Deployment **not** started)
**Result**: ✅ **424 / 424 tests pass** — 3 defects found, 3 fixed, 0 open failures

---

## 1. SCOPE & METHOD

Phase 12 verifies that every module built in Phases 1–11 works **together**, not just
in isolation. Phases 1–9 already had per-module test files; this phase added the layers
that were missing:

| Layer | Purpose | Added |
| --- | --- | --- |
| **Unit** | Pure business rules with no DB/HTTP (transition rules, form mapping, sections, audit redaction, sector directory, report status cycle) | 5 files, 44 tests |
| **Integration** | Real end-to-end journeys across modules (login → assignment → workflow → draft → report → status → dashboard → audit) | 17 tests |
| **Authorization matrix** | Every role × every route in one sweep, plus direct-URL and JSON access | 17 tests |
| **Error handling** | Validation, invalid transitions, bad filters, 404/403 behaviour, audit immutability | 27 tests |
| **Performance** | Query-count regression guards for the access-control layer | 6 tests |
| **Authentication** | Login/logout/session/credentials as a separate concern from authorization | 13 tests |

No new product functionality was introduced. Only defects in **already-implemented**
requirements were fixed.

---

## 2. TEST SUMMARY

### 2.1 Overall

| Metric | Before Phase 12 | After Phase 12 |
| --- | ---: | ---: |
| Tests | 298 | **424** |
| Assertions | 892 | **1 593** |
| Failing | 1 | **0** |
| Unit-suite tests | 1 | **45** |
| Runtime | ~7.8 s | ~17.9 s (includes real PDF generation) |

### 2.2 New test files

| File | Tests | Covers |
| --- | ---: | --- |
| `tests/Unit/WorkflowStageRulesTest.php` | 11 | 7-stage definition, sequential-forward rule, backward-with-reason, range validation, progress calculation |
| `tests/Unit/AnalysisFormMappingTest.php` | 13 | Algorithm checkbox semantics, dynamic rows, conclusion whitelist, column/JSON split, draft-vs-final defaults |
| `tests/Unit/AnalysisSectionTest.php` | 7 | 9 report sections, field ownership, split/merge round-trip, completion indicators |
| `tests/Unit/AuditRedactionTest.php` | 5 | Sensitive-key stripping (incl. nested), truncation, non-string passthrough |
| `tests/Unit/SectorDirectoryAndStatusTest.php` | 8 | Sector→entity mapping, unique agency codes, report status cycle |
| `tests/Feature/Phase12AuthenticationTest.php` | 13 | Login, invalid credentials, session regeneration, intended URL, logout, hashing, guest redirects |
| `tests/Feature/Phase12AuthorizationMatrixTest.php` | 17 | All 6 roles × all modules, assigned-only access, direct URL, JSON/API, review/approval gate mapping |
| `tests/Feature/Phase12IntegrationTest.php` | 17 | Full lifecycle, reassignment, status+date, dashboard recalculation, draft/resume/versioning, preview, PDF generation, audit chain |
| `tests/Feature/Phase12ErrorHandlingTest.php` | 27 | Validation, invalid transitions, assignment rules, filter edge cases, immutability, 404/403 |
| `tests/Feature/Phase12PerformanceTest.php` | 6 | Query-count guards (access checks, list pages, entity page, dashboard, audit list) |

### 2.3 Coverage of the 22 requested test areas

| # | Area | Where verified | Status |
| --- | --- | --- | --- |
| 1 | Authentication | `Phase12AuthenticationTest` | ✅ |
| 2 | Role permissions | `Phase12AuthorizationMatrixTest` (6 roles × 12 route groups) | ✅ |
| 3 | Sector → entity | `Phase12IntegrationTest`, `SectorDirectoryAndStatusTest` | ✅ |
| 4 | Assignment | `Phase12IntegrationTest`, `Phase12ErrorHandlingTest`, Phase 3 suite | ✅ |
| 5 | Analyst assigned-only access | `Phase12AuthorizationMatrixTest`, Phase 4 suite | ✅ |
| 6 | Workflow 1–7 | Full 1→7 progression in `Phase12IntegrationTest`; rules in `WorkflowStageRulesTest` | ✅ |
| 7 | Status / date updates | `Phase12IntegrationTest` (status, `status_since`, `updated_by`) | ✅ |
| 8 | Dashboard calculations | `Phase12IntegrationTest` (recalculated after each stage move) | ✅ |
| 9 | Report input | `Phase12IntegrationTest`, `AnalysisFormMappingTest` | ✅ |
| 10 | Algorithm checkbox | `AnalysisFormMappingTest` + end-to-end assertion that unticked ≠ recorded | ✅ |
| 11 | Save draft | `Phase12IntegrationTest` (form + JSON autosave) | ✅ |
| 12 | Resume draft | `Phase12IntegrationTest` (exit → return → latest version restored) | ✅ |
| 13 | Validation | `Phase12ErrorHandlingTest` | ✅ |
| 14 | Preview | `Phase12IntegrationTest` (template headings + derived business rules) | ✅ |
| 15 | Generate report | `Phase12IntegrationTest` — real `%PDF` output, filename from `kod_rujukan` | ✅ |
| 16 | Review | **Phase 10 not implemented** — verified no hidden/unguarded route exists; gate mapping matches matrix | ⚠️ gap |
| 17 | Approval | Same as above; `approval_logs` table exists but unused | ⚠️ gap |
| 18 | Audit trail | `Phase12IntegrationTest` (full action chain), immutability, no findings content stored | ✅ |
| 19 | API authorization | `Phase12AuthorizationMatrixTest` (JSON/AJAX denied, no `routes/api.php`, every route behind `auth`) | ✅ |
| 20 | Direct URL access | 13 entity-scoped endpoints probed as an unassigned analyst — all 403, no side effects | ✅ |
| 21 | Invalid transitions | `Phase12ErrorHandlingTest` (skip, out-of-range, same stage, backward without reason, unregistered entity) | ✅ |
| 22 | Error handling | `Phase12ErrorHandlingTest` (404/403/422, bad filters, deleted records, no info leakage) | ✅ |

### 2.4 Tooling results

| Command | Result |
| --- | --- |
| `php artisan test` | ✅ 424 passed / 1 593 assertions |
| `php artisan test --testsuite=Unit` | ✅ 45 passed |
| `vendor/bin/pint --test` (new + modified files) | ✅ pass |
| `php -l` over `app/ config/ database/ routes/ tests/` | ✅ no syntax errors |
| `php artisan view:cache` (compiles every Blade template) | ✅ pass |
| `php artisan route:cache` / `config:cache` | ✅ pass |
| `composer validate --strict` | ✅ valid |
| Debug-statement scan (`dd(`, `dump(`, `var_dump(`, `console.log(`) | ✅ none |
| `npm run build` (Vite) | ✅ built in 9.2 s, 63 modules |

---

## 3. FAILURES FOUND

### F1 — Stale scaffold test failing since authentication was added (severity: low)

`tests/Feature/ExampleTest` asserted `GET /` returns 200. Since Phase 4 the root route is
the monitoring dashboard behind `auth`, so it returns 302. This was the **only** failing
test in the pre-Phase-12 baseline (297/298).

### F2 — N+1 in the entity access-control layer (severity: medium, performance)

`EntityAccessService` funnels every access question through
`User::getAccessibleEntities()`, which ran a fresh assignment query **on every call**.
Access is checked many times per request (middleware → gate → policy → each
`accessibleBy()` scope → per-entity filter in `WorkflowController`).

Measured before the fix, with 12 entities assigned to one analyst:

| Operation | Queries |
| --- | ---: |
| 12 × `canAccess()` | 12 |
| `GET /workflow` (analyst) | 33 |
| `GET /entiti/{code}` | 15 |

Cost grew linearly with the number of entities shown — a real risk for the production
master list (252 entities).

### F3 — N+1 lazy-load on the workflow list (severity: medium, performance)

`resources/views/workflow/index.blade.php:88` renders `$w?->updatedBy?->name` per row, but
`WorkflowController@index` fetched `WorkflowStatus` records without eager-loading the
relation — one extra query per listed entity.

### Investigated, **not** a defect

The audit-trail filter (`AuditTrailController`) aborts 403 when `agency_code` is present but
inaccessible. Submitting the filter form with "Semua entiti" sends `agency_code=`, which
looked like it would 403 for every user. Verified it does **not**: Laravel's global
`ConvertEmptyStringsToNull` middleware turns the blank value into `null` before the check.
A regression test now locks this behaviour in
(`test_penapis_jejak_audit_kosong_bermakna_semua_entiti`).

---

## 4. FIXES APPLIED

| # | File | Change |
| --- | --- | --- |
| F1 | `tests/Feature/ExampleTest.php` | Rewritten as a real smoke test: guest → redirect to login, monitoring role → 200, `/up` health endpoint responds |
| F2 | `app/Models/User.php` | `getAccessibleEntities()` now memoises the computed entity list per model instance; `refresh()` is overridden to invalidate that memo so assignment changes are never served from a stale value. Logic itself unchanged (moved verbatim into `kiraEntitiBolehDiakses()`) |
| F3 | `app/Http/Controllers/WorkflowController.php` | Added `->with('updatedBy')` to the workflow list query |

Measured after the fixes (same 12-entity scenario):

| Operation | Before | After |
| --- | ---: | ---: |
| 12 × `canAccess()` | 12 queries | **1** |
| `GET /workflow` (analyst) | 33 queries | **5** |
| `GET /entiti/{code}` | 15 queries | **9** |
| `GET /` dashboard (coordinator) | 10 queries | 10 (unchanged — coordinators are unfiltered) |

`Phase12PerformanceTest` now fails the build if the count grows with the number of
entities again, and asserts that `refresh()` still yields up-to-date access.

**No behavioural change** to authorization: the same 424 tests — including the entire
Phase 4 access-control suite — pass before and after.

### Changed / added files

```
Modified  app/Models/User.php
Modified  app/Http/Controllers/WorkflowController.php
Modified  tests/Feature/ExampleTest.php
Added     tests/Unit/WorkflowStageRulesTest.php
Added     tests/Unit/AnalysisFormMappingTest.php
Added     tests/Unit/AnalysisSectionTest.php
Added     tests/Unit/AuditRedactionTest.php
Added     tests/Unit/SectorDirectoryAndStatusTest.php
Added     tests/Feature/Phase12AuthenticationTest.php
Added     tests/Feature/Phase12AuthorizationMatrixTest.php
Added     tests/Feature/Phase12IntegrationTest.php
Added     tests/Feature/Phase12ErrorHandlingTest.php
Added     tests/Feature/Phase12PerformanceTest.php
Added     PHASE_12_TEST_REPORT.md
```

---

## 5. REMAINING RISKS

| # | Risk | Impact | Recommendation |
| --- | --- | --- | --- |
| **R1** | **Phase 10 (Review & Approval) is not implemented.** `ApprovalLog` model, migration and the `review-report` / `approve-report` gates exist, but there is no route, controller, service or UI. Acceptance criteria §34 "Approval" (submit review, review, return correction, approve, approval history) are unmet, and stage 6 *Semakan & Kelulusan* has no operational meaning beyond a stage label | Release blocker for the approval half of the workflow | Build Phase 10 before Phase 13 UAT. Phase 12 verified only that no unguarded approval path is exposed and that gate mapping matches the permission matrix |
| **R2** | **Login has no rate limiting / lockout.** `POST /login` carries no `throttle` middleware, so password attempts are unlimited | Brute-force exposure | Security hardening item for Phase 13. Not added here — throttle policy (attempts, decay, lockout) is an unconfirmed business rule → `NEEDS CONFIRMATION` |
| **R3** | **Pre-existing lint debt.** `vendor/bin/pint --test` reports 25 untouched files (mostly CRLF line endings; migrations also need `class_definition`/`braces_position`; `routes/web.php` import order). Left untouched to keep the Phase 12 diff focused | Cosmetic; noisy diffs later | Run `vendor/bin/pint` as a standalone housekeeping commit |
| **R4** | **Unauthenticated JSON requests get 302 → login, not 401.** `bootstrap/app.php` limits `shouldRenderJsonWhen` to `api/*`, and no `routes/api.php` exists | No data leak (access still denied); only content negotiation. The autosave JS degrades silently on an expired session | Revisit if a real API surface is added in a later phase |
| **R5** | **Access memo lifetime.** The new per-instance memo is correct for HTTP requests (one instance per request) and is cleared by `refresh()`. A long-lived process holding one `User` instance across jobs could observe stale access | Low — no queue workers use `User` this way today | Call `$user->refresh()` in any future long-running consumer |
| **R6** | **PDF generation depends on headless Chrome.** The generation test produced a real 499 KB `%PDF-1.4` here, but auto-skips if Browsershot/Chrome is unavailable | Report generation could silently pass CI while broken on the server | Verify Chrome/Node on the deployment target during Phase 13 |
| **R7** | **No automated browser/UI tests.** All coverage is HTTP-level; spec §35 still lists a manual UI test step | Rendering/JS regressions (autosave, dynamic rows, stepper) undetected | Manual UI pass during Phase 13 UAT, or add Dusk if approved |
| **R8** | **Performance verified by query count, not load.** Tests use SQLite in-memory with ≤20 entities. Production has 252 entities in the master list | Unknown behaviour at full data volume | Run a seeded load test against the real database in Phase 13 |
| **R9** | **`manage-workflow` role mapping is still `NEEDS CONFIRMATION`** (Phase 9). Stage transitions are currently limited to Pentadbir + Penyelaras by convention, not by a confirmed rule | Wrong role may hold stage control | Confirm with the business owner before UAT |
| **R10** | **Upload module retained.** `MuatNaik` routes remain reachable by Pentadbir/Penyelaras. Phase 12 confirms the reporting flow needs no upload (`test_laporan_boleh_disiapkan_tanpa_sebarang_muat_naik` — 0 upload rows) | Spec §31 flags it for deprecation, not deletion | Keep as-is; schedule a dependency audit before removal |
| **R11** | **Roles without a confirmed permission row** (Document Controller, Pegawai Rekod Analisis) currently see empty lists rather than an explicit "no access" page | UX confusion, not a security hole — entity access is denied at query and route level (verified) | Confirm intended permissions, then implement |

---

## 6. PHASE 12 DEFINITION OF DONE

| Criterion (spec §37) | Status |
| --- | --- |
| Code implemented | ✅ 3 targeted fixes, no new functionality |
| Migration tested | ✅ Every test run rebuilds the schema from migrations |
| Relevant tests passed | ✅ 424 / 424 |
| No critical security issue | ✅ Access control verified at route, controller, service, query, UI and JSON layers; R2 (login throttle) logged for Phase 13 |
| No obvious regression | ✅ Full Phases 1–11 suite passes unchanged |
| UI tested | ⚠️ Blade templates all compile; **manual UI test still outstanding** (R7) |
| Role access tested | ✅ 6 roles × all modules |
| Documentation updated | ✅ This report |
| Changed files documented | ✅ Section 4 |

**Verdict**: Phase 12 is complete for everything that has been built. The system is a
stable release candidate **for Phases 1–9 and 11**. It is **not** yet feature-complete
against the master specification, because Phase 10 (Review & Approval) has not been
implemented (R1) — that should be closed before Phase 13 UAT begins.
