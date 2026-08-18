# MASTER PROMPT V2

# Sistem Pemantauan & Pelaporan Analisis Data Migrasi PQC

## 0. ARAHAN UTAMA KEPADA GITHUB COPILOT

Gunakan dokumen ini sebagai **master specification** untuk mengemas kini codebase sedia ada.

Sistem semasa telah diaudit. Jangan bina semula sistem dari kosong jika komponen sedia ada masih boleh digunakan.

### WAJIB sebelum mengubah kod

1. Audit codebase semasa.
2. Kenal pasti framework, database, authentication, authorization, models, controllers, routes, views/components dan report generation.
3. Bandingkan current state dengan specification ini.
4. Kenal pasti apa yang:
    - sudah siap
    - separa siap
    - rosak
    - perlu diubah
    - perlu dibina
    - perlu dinyahaktifkan
5. Jangan delete atau rewrite fungsi sedia ada tanpa justifikasi.
6. Jangan reka business rule baru jika requirement belum ditetapkan.
7. Jika requirement bercanggah dengan codebase, utamakan specification ini tetapi nyatakan konflik tersebut.
8. Jika sesuatu perkara masih tidak pasti, tandakan `NEEDS CONFIRMATION`.
9. Implement secara berfasa dan uji setiap fasa.

---

# 1. IDENTITI SISTEM

Nama sistem:

**Sistem Pemantauan & Pelaporan Analisis Data Migrasi PQC**

Sistem mempunyai dua fungsi utama:

### Fungsi Utama

**Pemantauan proses analisis data migrasi PQC**

### Fungsi Kedua / Sub-fungsi

**Penjanaan & pengurusan laporan analisis**

Sistem perlu menggantikan konsep pemantauan menggunakan log/manual tracking kepada platform berpusat.

---

# 2. PRINSIP ASAS SISTEM

## 2.1 Sistem bukan sistem analisis automatik

Pegawai Analisis masih menjalankan analisis secara manual.

Contohnya:

- data cleaning
- pemeriksaan data
- semakan
- analisis berdasarkan Buku Kerja Migrasi PQC
- interpretasi dapatan
- penyediaan dapatan analisis

Sistem hanya:

- memantau progress
- merekod status
- menyimpan dapatan
- menyokong penjanaan laporan
- menyokong semakan dan kelulusan

Jangan bina automatic PQC analysis tanpa arahan tambahan.

---

# 3. TIADA MODUL MUAT NAIK DOKUMEN DALAM FASA SEMASA

Ini ialah requirement yang sangat penting.

Walaupun codebase semasa mempunyai fungsi upload, fungsi tersebut **bukan sebahagian daripada workflow utama Fasa semasa**.

## Jangan gunakan sebagai proses utama:

- upload Buku Kerja Migrasi PQC
- upload borang semakan awal
- upload DOCX
- upload PDF
- OCR
- document extraction
- automatic document parsing

## Sebaliknya

Pegawai Analisis memasukkan dapatan secara manual melalui:

- select
- dropdown
- checkbox
- radio
- input field
- textarea
- numeric input
- date input

Jika fungsi upload lama perlu dikekalkan kerana dependency teknikal atau backward compatibility, jangan jadikan ia sebahagian daripada workflow baharu.

---

# 4. MATLAMAT SISTEM

## Matlamat 1 — Pemantauan

Mengurus dan memantau keseluruhan proses analisis data migrasi PQC secara:

- tersusun
- terkawal
- berpusat
- boleh dijejak

## Matlamat 2 — Pelaporan

Menyokong penjanaan dan pengurusan laporan berdasarkan dapatan analisis yang dimasukkan oleh Pegawai Analisis.

---

# 5. STRUKTUR UTAMA SISTEM

Struktur utama:

```text
SEKTOR
   ↓
ENTITI
   ↓
ASSIGNMENT
   ↓
WORKFLOW
   ↓
STATUS + TARIKH
   ↓
DASHBOARD
```

Untuk pelaporan:

```text
DAPATAN ANALISIS
   ↓
INPUT BERSTRUKTUR
   ↓
SAVE DRAFT
   ↓
RESUME
   ↓
VALIDATION
   ↓
PREVIEW
   ↓
GENERATE REPORT
   ↓
REVIEW
   ↓
APPROVAL
```

---

# 6. 7 PERINGKAT WORKFLOW

Setiap entiti perlu dipantau melalui:

### 01 — Penerimaan & Pendaftaran Data

### 02 — Semakan Awal Data

### 03 — Penyediaan & Pengesahan Data

### 04 — Pelaksanaan Analisis

### 05 — Penjanaan Laporan

### 06 — Semakan & Kelulusan

### 07 — Penyerahan & Penutupan

Pada tahap high-level, sistem perlu menunjukkan kedudukan semasa entiti dalam workflow.

Jangan bina perincian operasi setiap peringkat melainkan requirement telah ditetapkan.

---

# 7. SEKTOR DAN ENTITI

Pegawai yang mempunyai akses pemantauan:

```text
Pilih Sektor
    ↓
Paparkan semua Entiti
    ↓
Pilih Entiti
```

Entiti perlu dikaitkan dengan sektor.

Contoh:

```text
Sektor A
├── Entiti Alpha
├── Entiti Beta
└── Entiti Gamma
```

Jangan ubah struktur sektor/entiti yang telah tersedia dalam database tanpa sebab.

---

# 8. ENTITY ASSIGNMENT

Pegawai Penyelaras Analisis boleh assign entiti kepada Pegawai Analisis.

Contoh:

```text
Sektor A

Entiti Alpha → Pegawai Analisis A
Entiti Beta  → Pegawai Analisis B
Entiti Gamma → Pegawai Analisis A
```

Maklumat assignment perlu menyokong konsep:

- entity
- assigned_to_user
- assigned_by_user
- assigned_at
- assignment status
- timestamps

Jika perlu reassign:

```text
Pegawai A → Pegawai B
```

sistem perlu menyimpan sejarah assignment jika architecture menyokongnya.

---

# 9. AKSES PEGAWAI ANALISIS

Ini ialah security requirement kritikal.

Pegawai Analisis:

**HANYA BOLEH MELIHAT ENTITI YANG DIASSIGN KEPADANYA.**

Contoh:

```text
Pegawai A

Entity Alpha ✓
Entity Gamma ✓
Entity Beta  ✗
```

Jika Entity Beta diassign kepada Pegawai B, Pegawai A tidak boleh:

- melihat entity tersebut
- membuka detail page
- mengedit data
- melihat report
- mengakses API entity tersebut

Authorization mesti dikuatkuasakan di:

- UI
- route
- controller
- service
- query
- API

Jangan hanya hide button.

---

# 10. DASHBOARD

Dashboard keseluruhan hanya untuk:

- Pegawai Penyelaras Analisis
- Ketua Bahagian
- Pentadbir / role lain yang diberi permission pemantauan

Pegawai Analisis biasa **tidak mempunyai dashboard keseluruhan**.

## Dashboard perlu mengira statistik daripada database.

Contoh:

```text
Jumlah Sektor
Jumlah Entiti
Jumlah Dalam Proses
Jumlah Selesai
Jumlah Laporan
Laporan Siap
Kemajuan Keseluruhan
```

## Progress workflow

Dashboard boleh menunjukkan:

```text
Penerimaan & Pendaftaran
Semakan Awal
Penyediaan & Pengesahan
Pelaksanaan Analisis
Penjanaan Laporan
Semakan & Kelulusan
Penyerahan & Penutupan
```

## Prinsip penting

Jangan simpan `56%` sebagai nilai manual.

Sistem mesti mengira:

```text
Entity records
     ↓
Status records
     ↓
Calculation
     ↓
Dashboard
```

---

# 11. STATUS WORKFLOW

Setiap entiti mempunyai:

- current stage
- stage name
- status
- status date
- updated by

Contoh:

```text
Entiti Alpha

Status:
Semakan Awal Data

Tarikh:
20/08/2026

Dikemas kini oleh:
Pegawai A
```

Status history perlu tersedia jika audit trail diaktifkan.

---

# 12. STATUS TRANSITION

Sistem perlu mempunyai kawalan terhadap perubahan peringkat.

Contoh:

```text
01 → 02 → 03 → 04 → 05 → 06 → 07
```

Jangan benarkan pengguna melompat secara rawak jika business rule tidak membenarkannya.

Namun:

> Jangan hard-code rule seperti "hanya coordinator boleh move stage" tanpa mengesahkan permission sebenar.

Gunakan role/permission architecture.

Jika entity perlu kembali ke peringkat sebelumnya:

- mesti ada reason
- mesti direkodkan
- mesti masuk audit trail

---

# 13. ENTITY DETAIL PAGE

Setiap entiti perlu mempunyai pusat maklumat.

Cadangan:

```text
Entity Detail

├── Maklumat Entiti
├── Assignment
├── Workflow Progress
├── Dapatan Analisis
├── Laporan
└── Sejarah
```

Paparkan sekurang-kurangnya:

- nama entiti
- sektor
- pegawai
- current stage
- current status
- tarikh status
- report status
- action yang tersedia

---

# 14. PENJANAAN LAPORAN

Ini ialah sub-fungsi utama sistem.

Laporan Fasa semasa:

**Laporan Analisis Inventori Kriptografi**

Laporan mesti berdasarkan template laporan rasmi yang telah diberikan.

Jangan reka template laporan baru tanpa arahan.

---

# 15. SUMBER DATA LAPORAN

Pegawai Analisis akan menjalankan analisis secara manual berdasarkan:

- borang semakan awal data
- Buku Kerja Migrasi PQC yang telah diisi oleh entiti
- hasil data cleaning
- hasil analisis
- dapatan lain yang berkaitan

Kemudian Pegawai Analisis memasukkan dapatan ke dalam sistem.

Sistem:

**TIDAK membaca dokumen secara automatik.**

---

# 16. INPUT BERSTRUKTUR

Gunakan komponen yang sesuai:

- checkbox
- radio
- select
- dropdown
- text field
- textarea
- number
- date

Tujuannya:

- mengurangkan input tidak konsisten
- memudahkan validation
- memudahkan report generation
- memudahkan future analytics

---

# 17. ALGORITMA KRIPTOGRAFI

Bahagian algoritma perlu menggunakan senarai yang dirujuk oleh projek daripada **MySEAL / AKSA MySEAL**.

UI menggunakan checkbox.

Contoh:

```text
☐ AES
☐ RSA
☐ ECDSA
☐ SHA-256
☐ ...
```

Peraturan:

### Checkbox dipilih

→ Entiti menggunakan algoritma tersebut.

### Checkbox tidak dipilih

→ Inventori entiti tersebut tidak menggunakan algoritma tersebut.

Jangan minta pengguna menaip algoritma secara bebas jika pilihan berstruktur telah disediakan.

Senarai algoritma sebenar perlu dipadankan dengan sumber MySEAL yang digunakan oleh projek.

---

# 18. SAVE DRAFT & RESUME

Ini requirement wajib.

Pegawai Analisis mungkin tidak dapat menyiapkan laporan dalam satu sesi.

Sistem mesti menyokong:

```text
Start Report
   ↓
Fill Section
   ↓
Save Draft
   ↓
Exit
   ↓
Return
   ↓
Resume
   ↓
Continue
   ↓
Complete
```

Simpan sekurang-kurangnya:

- entity
- report
- user
- current section
- input data
- draft status
- last saved time
- version

Jika auto-save digunakan, pastikan ia tidak menjejaskan performance dan UX.

---

# 19. REPORT SECTIONS

Jangan buat satu form yang terlalu panjang.

Gunakan section/step jika sesuai.

Contoh:

```text
1. Maklumat Entiti
2. Profil Sistem / Aset
3. Algoritma Kriptografi
4. Protokol
5. Pustaka / Modul
6. Vendor & Kebergantungan
7. Pemerhatian
8. Tindakan Susulan
9. Kesimpulan
```

Section sebenar hendaklah dipadankan dengan template rasmi.

---

# 20. VALIDATION

Validation di:

### Frontend

Untuk UX.

### Backend

Untuk integrity dan security.

Required fields mesti berdasarkan keperluan sebenar laporan.

Jangan duplicate validation logic secara tidak terkawal.

---

# 21. PREVIEW & REPORT GENERATION

Flow:

```text
Input
 ↓
Save
 ↓
Validate
 ↓
Preview
 ↓
Generate
```

Gunakan report generation library yang telah ada jika sesuai.

Jangan menggantikan library sedia ada tanpa sebab.

Output mesti mengikut template laporan.

---

# 22. REPORT STATUS

Gunakan status yang sesuai dengan workflow.

Contoh:

```text
Belum Dimulakan
      ↓
Draf
      ↓
Lengkap
      ↓
Untuk Semakan
      ↓
Perlu Pembetulan
      ↓
Diluluskan
      ↓
Selesai
```

Status sebenar perlu diselaraskan dengan architecture sedia ada.

Jangan create duplicate status system.

---

# 23. REVIEW & APPROVAL

Peringkat 6:

**Semakan & Kelulusan**

Flow:

```text
Pegawai Analisis
       ↓
Generate
       ↓
Submit for Review
       ↓
Reviewer
       ↓
 ┌─────────────┐
 ↓             ↓
Approve     Return
 ↓             ↓
Complete      Draft
```

Simpan:

- reviewer
- status before
- status after
- comments
- timestamp

---

# 24. AUDIT TRAIL

Setiap perubahan penting perlu boleh dijejak.

Contoh:

```text
Entity Alpha

Status:
Penerimaan & Pendaftaran
        ↓
Semakan Awal

Changed By:
Pegawai A

Changed At:
20/08/2026 14:30
```

Audit log boleh menyimpan:

- entity
- action
- old value
- new value
- user
- timestamp
- metadata

Gunakan logging architecture sedia ada jika boleh.

---

# 25. ROLE

Role sasaran:

1. Pentadbir Sistem
2. Pegawai Penyelaras Analisis
3. Pegawai Analisis
4. Ketua Bahagian
5. Pegawai Kawalan Dokumen
6. Pegawai Penyelaras Rekod

Permission sebenar perlu disahkan berdasarkan architecture dan business rules.

---

# 26. PERMISSION MATRIX ASAS

| Fungsi                | Admin |      Penyelaras |        Analisis | Ketua |
| --------------------- | ----: | --------------: | --------------: | ----: |
| Dashboard keseluruhan |     ✓ |               ✓ |               ✗ |     ✓ |
| Lihat semua entiti    |     ✓ |               ✓ |               ✗ |     ✓ |
| Lihat assigned entity |     ✓ |               ✓ |               ✓ |     ✓ |
| Assign entity         |     ✓ |               ✓ |               ✗ |     ✗ |
| Input analysis        |     ✓ | ikut permission |               ✓ |     ✗ |
| Save draft            |     ✓ | ikut permission |               ✓ |     ✗ |
| Resume draft          |     ✓ | ikut permission |               ✓ |     ✗ |
| Generate report       |     ✓ | ikut permission |               ✓ |     ✗ |
| Review                |     ✓ |               ✓ | ikut permission |     ✓ |
| Approve               |     ✓ | ikut permission |               ✗ |     ✓ |
| Audit trail           |     ✓ |               ✓ | ikut permission |     ✓ |

Jangan implement permission tambahan yang belum disahkan sebagai business rule.

---

# 27. 13 FASA PEMBANGUNAN

---

## FASA 1 — FOUNDATION, DATABASE & CLEANUP

### Objektif

Sediakan asas database dan architecture.

### Kerja

- audit models
- audit migrations
- database cleanup
- tambah assignment table
- tambah workflow table
- tambah activity log
- tambah draft history
- tambah approval log
- tambah indexes
- foreign keys
- relationships
- cleanup modul upload daripada workflow baharu

### Output

Database foundation.

---

## FASA 2 — 7-STEP WORKFLOW

### Objektif

Membina workflow pemantauan.

### Kerja

- workflow state
- stage
- status
- status date
- updated by
- transition
- stage history
- workflow visualization

### Output

Setiap entiti mempunyai current stage.

---

## FASA 3 — ENTITY ASSIGNMENT

### Objektif

Coordinator assign entity.

### Kerja

- assignment model
- controller/service
- UI
- assign
- reassign
- unassign jika diperlukan
- assignment history

### Output

Entity → Analyst.

---

## FASA 4 — ROLE-BASED ACCESS CONTROL

### Objektif

Betulkan security.

### Kerja

- policies
- middleware
- query filtering
- API authorization
- assigned-only access
- coordinator all-entity access

### Output

Analyst tidak boleh melihat entity yang tidak diassign.

---

## FASA 5 — ENTITY DETAIL & PROGRESS HUB

### Objektif

Satu pusat maklumat entity.

### Kerja

- entity detail
- workflow stepper
- assignment
- status
- progress
- report
- history

### Output

Comprehensive entity view.

---

## FASA 6 — DRAFT / SAVE / RESUME

### Objektif

Elakkan kehilangan kerja.

### Kerja

- draft persistence
- section saving
- resume
- last saved
- version
- optional autosave

### Output

Laporan boleh disambung semula.

---

## FASA 7 — DASHBOARD PEMANTAUAN

### Objektif

Dashboard berdasarkan data sebenar.

### Kerja

- KPI
- sector count
- entity count
- workflow distribution
- progress
- report status
- filters
- role filtering

### Output

Management dashboard.

---

## FASA 8 — AUDIT TRAIL

### Objektif

Kebolehjejakan.

### Kerja

- activity log
- status history
- assignment history
- report changes
- user
- timestamp
- metadata

### Output

Complete traceability.

---

## FASA 9 — COMPLETE ROLES & PERMISSIONS

### Objektif

Lengkapkan role architecture.

### Kerja

- tambah missing roles
- permission mapping
- policies
- UI visibility
- API authorization

### Output

Role-based system.

---

## FASA 10 — REVIEW & APPROVAL

### Objektif

Lengkapkan workflow laporan.

### Kerja

- submit review
- reviewer
- approval
- reject/return
- comments
- version
- approval history

### Output

Controlled report approval.

---

## FASA 11 — UI/UX POLISH

### Objektif

Professional production UI.

### Kerja

- dashboard polish
- workflow UI
- forms
- table
- cards
- badges
- responsive
- empty/loading/error states
- accessibility

### Output

Production-quality interface.

---

## FASA 12 — INTEGRATION & TESTING

### Objektif

Pastikan semua modul berfungsi bersama.

### Test

- workflow
- assignment
- access control
- report
- draft
- dashboard
- approval
- audit
- performance
- security

### Output

Stable release candidate.

---

## FASA 13 — UAT & DEPLOYMENT

### Objektif

Sistem sedia digunakan.

### Kerja

- UAT
- bug fixing
- security testing
- performance testing
- backup
- deployment
- documentation
- user guide
- admin guide

### Output

**Sistem Pemantauan & Pelaporan Analisis Data Migrasi PQC V1.0**

---

# 28. CRITICAL PATH

Utamakan:

```text
Fasa 1
  ↓
Fasa 2
  ↓
Fasa 3
  ↓
Fasa 4
  ↓
Fasa 5
  ↓
Fasa 6
  ↓
Fasa 7
```

Ini membentuk core:

```text
Database
 ↓
Workflow
 ↓
Assignment
 ↓
Security
 ↓
Entity Monitoring
 ↓
Report Draft
 ↓
Dashboard
```

Selepas core stabil:

```text
Audit
 ↓
Roles
 ↓
Approval
 ↓
UI Polish
 ↓
Testing
 ↓
UAT
```

---

# 29. APA YANG PERLU DIKEKALKAN DARIPADA SISTEM SEMASA

Audit menunjukkan sistem semasa sudah mempunyai asas:

- dashboard basic
- statistik daripada DB
- progress sektor
- structured analysis form
- algorithm checkboxes
- template rendering
- PDF generation
- business logic
- authentication / authorization asas

Jangan rebuild komponen tersebut dari kosong.

Audit codebase perlu menentukan implementation sebenar yang boleh digunakan semula.

---

# 30. APA YANG PERLU DIPERBAIKI

Antara gap utama:

- workflow 7 peringkat
- status tracking
- workflow visualization
- assignment
- assigned-only access
- query filtering
- missing roles
- policy classes
- draft resume
- audit trail
- approval workflow
- version tracking
- enhanced dashboard

---

# 31. UPLOAD MODULE — ARAHAN KHAS

Audit mungkin menemui:

```text
MuatNaik
File Storage
Excel Validation
Metadata
Import History
```

Jangan terus delete database/model lama.

Sebaliknya:

1. Kenal pasti dependency.
2. Kenal pasti siapa menggunakan module tersebut.
3. Pastikan workflow baharu tidak bergantung kepada upload.
4. Jika module tidak diperlukan lagi, tandakan untuk deprecation.
5. Jangan hapus data production tanpa migration plan.

**Required state:**

> Tiada upload diperlukan untuk proses penjanaan laporan Fasa semasa.

---

# 32. DATABASE TARGET

Cadangan conceptual models:

```text
User

EntitiAssignment
    ↓
User
    ↓
Entity

WorkflowStatus
    ↓
Entity
    ↓
User

ActivityLog
    ↓
Entity
    ↓
User

AnalisisInventori
    ↓
Entity
    ↓
User

AnalisDraftHistory
    ↓
AnalisisInventori
    ↓
User

ApprovalLog
    ↓
Entity
    ↓
Report
    ↓
User
```

Jika codebase mempunyai model entity yang berbeza, gunakan model sedia ada dan jangan duplicate entity table tanpa sebab.

---

# 33. DATA MIGRATION

Sebelum migration:

- backup database
- inspect current data
- identify duplicate entities
- identify current reports
- identify existing users
- identify existing role values
- identify upload dependencies

Kemudian:

```text
Backup
 ↓
Migration
 ↓
Backfill
 ↓
Validate
 ↓
Test
```

Jangan run destructive migration terus pada production.

---

# 34. ACCEPTANCE CRITERIA

## Monitoring

- [ ] User boleh pilih sektor.
- [ ] User boleh lihat entity.
- [ ] Coordinator boleh assign entity.
- [ ] Analyst hanya lihat assigned entity.
- [ ] Workflow 1–7 tersedia.
- [ ] Status boleh dikemas kini.
- [ ] Tarikh status direkod.
- [ ] Dashboard mengira statistik.
- [ ] Workflow progress dipaparkan.

## Reporting

- [ ] Analyst boleh buka report untuk assigned entity.
- [ ] Input berstruktur.
- [ ] Algorithm checkbox.
- [ ] Save draft.
- [ ] Resume draft.
- [ ] Validation.
- [ ] Preview.
- [ ] Generate report.
- [ ] Report mengikut template.
- [ ] Tiada upload diperlukan.

## Approval

- [ ] Submit review.
- [ ] Review.
- [ ] Return correction.
- [ ] Approve.
- [ ] Approval history.

## Security

- [ ] Analyst tidak boleh access unassigned entity.
- [ ] API filtering.
- [ ] Route authorization.
- [ ] Policy authorization.
- [ ] Dashboard role protection.

---

# 35. TESTING RULE

Selepas setiap fasa:

```text
Code
 ↓
Lint
 ↓
Unit Test
 ↓
Feature Test
 ↓
Build
 ↓
Manual UI Test
 ↓
Security Test
```

Jangan tunggu sehingga Fasa 13 untuk mencari semua bug.

---

# 36. COPILOT IMPLEMENTATION PROTOCOL

## Step 1 — AUDIT

Prompt:

```text
Read MASTER_PROMPT_V2.md completely.

Do not modify code.

Audit the repository against this specification.

Return:
1. Current architecture
2. Current features
3. Existing reusable components
4. Database structure
5. Authentication
6. Authorization
7. Current roles
8. Current report generation
9. Current upload dependencies
10. Gap analysis
11. Security issues
12. Recommended implementation sequence

Do not implement anything yet.
```

## Step 2 — PHASE PLAN

```text
Using the audit and MASTER_PROMPT_V2.md,
produce a detailed implementation plan for Phase 1.

List:
- files
- migrations
- models
- controllers
- services
- routes
- views/components
- tests
- risks
- dependencies

Do not implement yet.
```

## Step 3 — IMPLEMENT ONE PHASE

```text
Implement Phase X only.

Do not implement future phases.

Reuse existing architecture where possible.

After implementation:
- run tests
- run lint
- run build
- verify database
- verify authorization
- summarize changed files
- summarize remaining issues
```

---

# 37. DEFINITION OF DONE

Sesuatu fasa hanya dianggap selesai apabila:

- code implemented
- migration tested
- relevant tests passed
- no critical security issue
- no obvious regression
- UI tested
- role access tested
- documentation updated
- changed files documented

---

# 38. JANGAN LAKUKAN

Jangan:

- rebuild entire app
- delete existing database blindly
- delete upload module without dependency audit
- create duplicate entity architecture
- bypass authorization
- hard-code dashboard statistics
- hard-code user IDs
- hard-code sector data
- hard-code analyst assignments
- hard-code report content
- add unapproved business rules
- implement future modules prematurely
- add unnecessary dependencies
- change framework
- change database engine
- replace existing report library without reason

---

# 39. FUTURE / OUT OF SCOPE

Jangan implement kecuali diarahkan:

- automatic document extraction
- OCR
- AI analysis
- automatic PQC risk calculation
- automatic crypto-agility calculation
- MasterTable integration
- external data integration
- advanced notifications
- advanced cross-sector analytics
- automated risk assessment

Tandakan sebagai:

`FUTURE ENHANCEMENT`

---

# 40. FINAL SYSTEM CONCEPT

Keseluruhan sistem:

```text
                    SISTEM PEMANTAUAN
                           │
              ┌────────────┴────────────┐
              │                         │
        PEMANTAUAN                 PELAPORAN
              │                         │
       Sektor → Entiti             Dapatan Analisis
              │                         │
          Assignment              Input Berstruktur
              │                         │
       Workflow 1–7                Save Draft
              │                         │
       Status + Tarikh             Resume
              │                         │
          Dashboard                Validation
                                       │
                                    Preview
                                       │
                                  Generate
                                       │
                             Inventori Kriptografi
                                       │
                                    Review
                                       │
                                   Approval
```

## Prinsip paling penting

> **Pegawai menjalankan kerja analisis dan memasukkan maklumat → Sistem merekod serta mengurus proses → Sistem mengira kemajuan → Pengurusan memantau melalui dashboard → Sistem menyokong penjanaan dan pengurusan laporan.**

---

# 41. FINAL INSTRUCTION

**Do not implement everything in one operation.**

Work incrementally.

Start with:

**Phase 1 → Audit/plan → Migration → Test**

Then:

**Phase 2 → Test**

Then:

**Phase 3 → Test**

and continue until Phase 13.

Jika sesuatu perubahan berpotensi memusnahkan data atau fungsi sedia ada:

**STOP and report the risk before making the destructive change.**

If requirements are ambiguous:

**mark as `NEEDS CONFIRMATION` instead of inventing a business rule.**

The target is not merely a working CRUD application.

The target is:

# Sistem Pemantauan & Pelaporan Analisis Data Migrasi PQC V1.0

yang mempunyai:

**Pemantauan berpusat + Workflow 7 peringkat + Assignment + Role-based access + Dashboard + Penjanaan laporan + Draft/Resume + Review/Approval + Audit Trail.**
