# AUDIT VISUAL REFERENCE GUIDE

## Gap Analysis with Visual Diagrams

---

## 1. CURRENT STATE vs REQUIRED STATE

### Current System (40% Complete)

```
┌─────────────────────────────────────────────────────────────┐
│                   RISK SCORE PLATFORM                       │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  📊 DASHBOARD                                                │
│  ├─ Basic KPIs (sectors, entities, reports)         ✓       │
│  ├─ Statistics from DB data                         ✓       │
│  ├─ Progress by sector                              ✓       │
│  ├─ Workflow stage distribution                     ✗       │
│  └─ Role-based filtering                            ✗       │
│                                                              │
│  📤 UPLOAD MODULE                                            │
│  ├─ Excel validation                                ✓       │
│  ├─ Metadata capture                                ✓       │
│  ├─ History tracking                                ✓       │
│  └─ File storage                                    ✓       │
│                                                              │
│  📝 ANALYSIS FORM                                            │
│  ├─ Sector selection                                ✓       │
│  ├─ Entity selection                                ✓       │
│  ├─ Structured input                                ✓       │
│  ├─ Algorithm checkboxes                            ✓       │
│  ├─ Save as draft (basic)                          ⚠️       │
│  ├─ Resume functionality                            ✗       │
│  └─ Auto-save                                        ✗       │
│                                                              │
│  📄 REPORT GENERATION                                        │
│  ├─ Template rendering                              ✓       │
│  ├─ PDF generation                                  ✓       │
│  ├─ Business logic                                  ✓       │
│  ├─ Approval workflow                               ✗       │
│  └─ Version tracking                                ✗       │
│                                                              │
│  🔐 ACCESS CONTROL                                           │
│  ├─ 3 roles (admin, coordinator, analyst)          ✓       │
│  ├─ Authorization gates                             ✓       │
│  ├─ Query-level filtering                           ✗ BUG   │
│  ├─ 6 roles total (missing 3)                       ✗       │
│  └─ Policy classes                                  ✗       │
│                                                              │
│  🔄 WORKFLOW SYSTEM                                          │
│  ├─ 7-step workflow                                 ✗       │
│  ├─ Status tracking                                 ✗       │
│  ├─ Visualization                                   ✗       │
│  └─ Audit trail                                     ✗       │
│                                                              │
│  👥 ASSIGNMENT SYSTEM                                        │
│  ├─ Entity assignment                               ✗       │
│  ├─ Coordinator interface                           ✗       │
│  ├─ Assignment history                              ✗       │
│  └─ Access filtering                                ✗       │
│                                                              │
└─────────────────────────────────────────────────────────────┘

Legend: ✓ = Complete, ⚠️ = Partial, ✗ = Missing/Broken
```

---

## 2. CRITICAL ISSUES VISUALIZATION

### Security Issue: Access Control

```
CURRENT (BROKEN):
┌──────────────────────────────────────────┐
│ GET /analisis (Analyst logged in)        │
├──────────────────────────────────────────┤
│ AnalisisInventori::latest()->paginate()  │
│          ↓                                │
│  Returns ALL entities (incorrect!)       │
│  ├─ Entity A (assigned to analyst)      │
│  ├─ Entity B (NOT assigned)             │ ⚠️ SECURITY BUG
│  ├─ Entity C (NOT assigned)             │
│  └─ ...50+ more                         │
└──────────────────────────────────────────┘

REQUIRED (FIXED):
┌──────────────────────────────────────────┐
│ GET /analisis (Analyst logged in)        │
├──────────────────────────────────────────┤
│ auth()->user()->assignedAnalyses()       │
│          ↓                                │
│  Returns ONLY assigned entities         │
│  └─ Entity A (assigned to analyst)      │ ✓ SECURE
└──────────────────────────────────────────┘
```

### Missing Workflow System

```
CURRENT STATE:
No workflow tracking

REQUIRED STATE:
┌────────────────────────────────────────────────────────────┐
│ 7-STEP WORKFLOW FOR EACH ENTITY                            │
├────────────────────────────────────────────────────────────┤
│                                                             │
│  ○ → ◉ → ○ → ○ → ○ → ○ → ○                                │
│  01    02    03   04   05   06   07                         │
│  Penerimaan                         Penyerahan              │
│  &              ...                 &                      │
│  Pendaftaran    Pelaksanaan         Penutupan              │
│  Data           Analisis                                   │
│                                                             │
│  Current Stage: 02 (Semakan Awal Data)                     │
│  Status Since: 2026-08-13 14:30:00                         │
│  Updated By: Pegawai A                                     │
│  Timeline:                                                  │
│    ├─ Stage 01: 2026-08-10 → 2026-08-12                   │
│    ├─ Stage 02: 2026-08-13 → [Current]                    │
│    └─ Stage 03-07: Pending                                 │
│                                                             │
└────────────────────────────────────────────────────────────┘
```

### Missing Assignment System

```
CURRENT STATE:
No assignment tracking
Analysts see all entities

REQUIRED STATE:
┌──────────────────────────────────────────────────────────┐
│ ENTITY ASSIGNMENT (Coordinator View)                      │
├──────────────────────────────────────────────────────────┤
│                                                            │
│ Sektor: Kerajaan                                         │
│ ┌────────────────────────────────────────────────────┐  │
│ │ Entity          │ Assigned To         │ Assigned At   │  │
│ ├─────────────────┼─────────────────────┼──────────────┤  │
│ │ JPM             │ Pegawai Analisis A  │ 2026-08-10   │  │
│ │ SPR             │ Pegawai Analisis B  │ 2026-08-10   │  │
│ │ SPRM            │ Pegawai Analisis A  │ 2026-08-11   │  │
│ │ JAKIM           │ [Unassigned]        │ -            │  │
│ └────────────────────────────────────────────────────┘  │
│                                                            │
│ ANALYST VIEW (Pegawai Analisis A):                        │
│ My Assignments:                                            │
│ ├─ JPM      (Assigned 2026-08-10)                         │
│ └─ SPRM     (Assigned 2026-08-11)                         │
│                                                            │
│ Note: Analyst A does NOT see SPR (assigned to B)          │
│                                                            │
└──────────────────────────────────────────────────────────┘
```

---

## 3. DATA MODEL EXPANSION

### Current Models (4)

```
User
├─ id, name, email, password
├─ username, role
└─ timestamps

MuatNaik
├─ id, nama_fail, lokasi_fail, status
├─ jumlah_rekod, tarikh_import
├─ sector_code, sector_name, agency_code, agency_name
├─ nama_helaian, jumlah_helaian, jumlah_baris
├─ soft deletes
└─ timestamps

AnalisisInventori
├─ id, sector_code, sector_name, agency_code, agency_name
├─ tarikh_laporan, kod_rujukan, status_laporan
├─ data (JSON), selesai (boolean)
├─ user_id (FK)
└─ timestamps

StatusLaporan
├─ id, sector_code, sector_name, agency_code, agency_name
├─ jenis, status
├─ user_id (FK)
├─ unique(agency_code, jenis)
└─ timestamps
```

### Required New Models (5)

```
EntitiAssignment (NEW)
├─ id, entity_code, entity_name, sector_code
├─ assigned_to_user_id (FK)
├─ assigned_by_user_id (FK)
├─ assigned_at, status (active|archived)
└─ timestamps + indexes

WorkflowStatus (NEW)
├─ id, entity_code, sector_code
├─ current_stage (1-7), stage_name
├─ status_since, updated_by_user_id (FK)
├─ notes (text)
├─ unique(entity_code)
└─ timestamps + indexes

ActivityLog (NEW - AUDIT TRAIL)
├─ id, entity_code, action
├─ old_value, new_value, changed_by_user_id (FK)
├─ changed_at, metadata (JSON)
└─ timestamps + indexes

AnalisDraftHistory (NEW)
├─ id, analisis_inventori_id (FK)
├─ version, section_name, section_data (JSON)
├─ completed, saved_at, saved_by_user_id (FK)
├─ is_current (boolean)
└─ timestamps + indexes

ApprovalLog (NEW)
├─ id, entity_code, report_type
├─ status_before, status_after
├─ approved_by_user_id (FK), approved_at
├─ comments (text)
└─ timestamps + indexes
```

### Modified Models

```
User (ADD)
├─ department, phone
├─ is_active, last_login_at

AnalisisInventori (ADD)
├─ workflow_stage, status_workflow
├─ assigned_to_user_id, assigned_by_user_id, assigned_at
├─ draft_version, last_saved_section, last_saved_at

StatusLaporan (RENAME/ADD)
├─ approval_status (was 'status')
├─ workflow_status, approved_by_user_id, approved_at
├─ review_notes
```

---

## 4. WORKFLOW STATE MACHINE

```
┌────────────────────────────────────────────────────────────────────┐
│                    ENTITY WORKFLOW STATE MACHINE                    │
├────────────────────────────────────────────────────────────────────┤
│                                                                      │
│   01                 02                03                04          │
│  START          SEMAKAN AWAL      PENYEDIAAN       PELAKSANAAN     │
│    │                 │                 │                │           │
│    ◉──────────────►◉                   ◉────────────►◉             │
│ Penerimaan &    ├─ Validate data    ├─ Clean       ├─ Run analysis│
│ Pendaftaran     ├─ Check format     ├─ Verify      ├─ Document   │
│                 └─ Mark status      └─ Prepare     └─ Review     │
│                                                      results        │
│    05                 06                07                          │
│ PENJANAAN         SEMAKAN &          PENYERAHAN                    │
│  LAPORAN          KELULUSAN          & PENUTUPAN                   │
│    │                 │                 │                           │
│    ◉──────────────►◉──────────────────►◉                           │
│ ├─ Generate       ├─ Review findings │ ├─ Deliver                 │
│ ├─ Compile        ├─ Approve status  │ ├─ Archive                 │
│ ├─ Format         └─ Update version  │ └─ Close case              │
│ └─ Preview                           │                             │
│                                       └────────────────────────►[END]
│                                                                      │
│ Business Rules:                                                     │
│ • Each stage has entry/exit criteria                               │
│ • Only coordinator can move entities between stages                │
│ • Each status change recorded with user + timestamp                │
│ • Can return to previous stage if needed (with reason)             │
│ • Stage duration tracked                                           │
│ • Audit trail maintains complete history                           │
│                                                                      │
└────────────────────────────────────────────────────────────────────┘
```

---

## 5. DATABASE MIGRATION SEQUENCE

```
Week 1: Phase 1 - Foundation
  Migration 1: entiti_assignment table
  Migration 2: workflow_status table
  Migration 3: activity_log table
  Migration 4: analisis_draft_history table
  Migration 5: approval_logs table
           ↓
Week 1: Phase 1 - Model Updates
  Modify:  User model
  Modify:  AnalisisInventori model
  Modify:  StatusLaporan model
  Create:  5 new models
           ↓
Week 1-2: Phase 2-4 - Core Features
  Add columns to existing tables
  Create foreign key constraints
  Add indexes
           ↓
Week 3-4: Phase 5-10 - Enhancement
  Add audit logging
  Add approval workflow
  Data backfill if needed
           ↓
Week 5-6: Phase 11-13 - Testing & Deployment
  Data validation
  Performance testing
  Security testing
```

---

## 6. ROLE PERMISSION MATRIX

```
┌──────────────────────────────────────────────────────────────────────┐
│                        ROLE PERMISSION MATRIX                        │
├───────────────────────┬─────────────┬──────────┬────────┬────────────┤
│ Capability            │ Pentadbir   │ Penyelaras│ Pegawai│ Ketua      │
│                       │ (Admin)     │ Analisis  │Analisis│ Bahagian   │
├───────────────────────┼─────────────┼──────────┼────────┼────────────┤
│ View Dashboard        │ ✓ (all)     │ ✓ (all)  │ ✗      │ ✓ (all)    │
│ Assign Entities       │ ✓           │ ✓        │ ✗      │ ✗          │
│ View All Entities     │ ✓           │ ✓        │ ✗      │ ✓          │
│ View Assigned Only    │ -           │ -        │ ✓      │ -          │
│ Input Analysis Data   │ ✓           │ ✗        │ ✓      │ ✗          │
│ Save Draft            │ ✓           │ ✗        │ ✓      │ ✗          │
│ Resume Draft          │ ✓           │ ✗        │ ✓      │ ✗          │
│ Generate Report       │ ✓           │ ✗        │ ✓      │ ✗          │
│ Change Report Status  │ ✓           │ ✓        │ ✗      │ ✗          │
│ Approve Reports       │ ✓           │ ✗        │ ✗      │ ✓          │
│ View Audit Trail      │ ✓           │ ✓        │ ✗      │ ✓          │
│ Manage Users          │ ✓           │ ✗        │ ✗      │ ✗          │
│ Upload Files          │ ✓           │ ✓        │ ✗      │ ✗          │
│ Manage Settings       │ ✓           │ ✗        │ ✗      │ ✗          │
└───────────────────────┴─────────────┴──────────┴────────┴────────────┘

Plus 3 additional roles:
  - Pegawai Kawalan Dokumen: Manage ref numbers, signatures, versions
  - Pegawai Rekod: View/manage record archive, read-only analysis
```

---

## 7. IMPLEMENTATION TIMELINE

```
WEEKS 1-2: FOUNDATION (Phase 1-3)
  ├─ Database: 5 new tables
  ├─ Models: 5 new models
  ├─ Controllers: AssignmentController, WorkflowController
  └─ Routes: +15 endpoints
  Status: READY FOR PHASE 2

WEEKS 2-3: CORE FEATURES (Phase 4-6)
  ├─ Access Control: Query filtering
  ├─ Entity Detail: Comprehensive page
  ├─ Draft Tracking: Auto-save + resume
  └─ Audit Trail: Activity logging
  Status: READY FOR PHASE 3

WEEKS 3-5: ENHANCEMENT (Phase 7-10)
  ├─ Dashboard: Enhanced with workflow view
  ├─ Approvals: Report approval workflow
  ├─ Missing Roles: 3 additional roles
  └─ UI: Professional styling
  Status: READY FOR TESTING

WEEKS 5-6: TESTING & DEPLOY (Phase 11-13)
  ├─ Integration testing
  ├─ UAT preparation
  ├─ Performance optimization
  └─ Deployment readiness
  Status: PRODUCTION READY

TOTAL: 6-8 WEEKS
```

---

## 8. QUICK REFERENCE: WHAT TO BUILD FIRST

```
🔴 CRITICAL PATH (Do first, blocks everything):
  1. Phase 1 - Database foundation
  2. Phase 2 - Workflow system
  3. Phase 4 - Access control (security critical)
  ↓
🟡 HIGH PRIORITY (Do next):
  4. Phase 3 - Assignment system
  5. Phase 5 - Entity detail page
  6. Phase 6 - Draft tracking
  ↓
🟢 IMPORTANT (Do after core):
  7. Phase 7 - Dashboard enhancement
  8. Phase 8 - Audit trail
  9. Phase 9 - Additional roles
  10. Phase 10 - Report approval
  ↓
🔵 POLISH & DEPLOY:
  11. Phase 11 - UI polish
  12. Phase 12 - Testing
  13. Phase 13 - UAT & Deploy
```

---

## 9. KEY METRICS TO TRACK

```
CODE METRICS:
  • Test coverage: Target 70%+
  • Code quality: Maintain current standard
  • Performance: Dashboard < 2s, API < 500ms

FUNCTIONAL METRICS:
  • Features complete: 13 phases
  • Bug severity: 0 critical before phase 12
  • Test pass rate: 100% before phase 13

BUSINESS METRICS:
  • Entities monitored: All required entities
  • Workflow compliance: 100% entities have stage
  • Audit trail coverage: All status changes logged
  • User adoption: 80%+ on first month

TIMELINE METRICS:
  • Schedule adherence: Stay within ±1 week per phase
  • Blocker resolution: < 1 day for critical issues
  • Stakeholder feedback loop: Weekly
```

---

## 10. FINAL CHECKLIST BEFORE STARTING PHASE 1

- [ ] This audit document reviewed and approved
- [ ] Stakeholders signed off on approach
- [ ] Database schema approved by DBA
- [ ] 7-stage workflow names confirmed
- [ ] All 6 roles and permissions documented
- [ ] Test data seeder prepared
- [ ] Staging environment ready
- [ ] Database backups automated
- [ ] Feature branch created in git
- [ ] Team members assigned to phases
- [ ] Communication plan established
- [ ] Risk mitigation plan approved
- [ ] Success criteria agreed upon
- [ ] Weekly meeting schedule set
- [ ] Demo schedule for stakeholders

---

**Status: Ready for Phase 1 Approval** ✅
