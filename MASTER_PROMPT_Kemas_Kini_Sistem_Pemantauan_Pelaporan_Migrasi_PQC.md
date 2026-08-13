# MASTER PROMPT — Kemas Kini Keseluruhan Sistem
## Sistem Pemantauan & Pelaporan Analisis Data Migrasi PQC

> **Tujuan fail ini:** Gunakan kandungan ini sebagai arahan utama kepada GitHub Copilot / Copilot Chat dalam VS Code untuk memahami semula skop dan konsep sistem sebelum membuat perubahan kod.
>
> **PENTING:** Jangan terus mengubah kod secara membuta tuli. Mula dengan audit keseluruhan repository, fahami struktur sedia ada, petakan fungsi semasa kepada keperluan di bawah, kemudian cadangkan pelan perubahan. Selepas pelan dipersetujui / konteks mencukupi, laksanakan perubahan secara berperingkat.

---

# 1. KONTEKS SISTEM

Sistem yang sedang dibangunkan ialah:

**Sistem Pemantauan & Pelaporan Analisis Data Migrasi PQC**

Sistem ini bukan sekadar sistem penjana laporan. **Fungsi utama sistem ialah PEMANTAUAN**, manakala **PELAPORAN ialah fungsi kedua / sub-fungsi utama**.

Sistem perlu memantau perjalanan setiap entiti melalui keseluruhan proses analisis data migrasi PQC, bermula daripada:

1. Penerimaan & Pendaftaran Data
2. Semakan Awal Data
3. Penyediaan & Pengesahan Data
4. Pelaksanaan Analisis
5. Penjanaan Laporan
6. Semakan & Kelulusan
7. Penyerahan & Penutupan

Sistem perlu menggantikan konsep pemantauan melalui log/manual tracking kepada satu platform berpusat yang merekod status setiap entiti, tarikh, pegawai yang bertanggungjawab dan perkembangan proses.

---

# 2. MATLAMAT UTAMA

Sistem mempunyai dua matlamat utama.

## 2.1 Matlamat 1 — Pemantauan

Mengurus dan memantau keseluruhan proses analisis data migrasi PQC secara:

- tersusun
- terkawal
- berpusat
- boleh dijejak
- berasaskan status setiap entiti

Sistem perlu membolehkan pihak yang diberi kuasa mengetahui:

- sektor
- entiti
- pegawai yang ditugaskan
- status semasa entiti
- tarikh status
- peringkat workflow
- kemajuan keseluruhan
- status laporan
- sejarah perubahan status jika diperlukan

## 2.2 Matlamat 2 — Pelaporan

Menyokong penjanaan dan pengurusan laporan berdasarkan dapatan analisis yang dimasukkan oleh Pegawai Analisis.

Laporan perlu dijana mengikut:

**Templat Laporan Analisis Inventori Kriptografi**

---

# 3. PRINSIP PALING PENTING

## 3.1 Sistem tidak menggantikan kerja analisis

Pegawai Analisis masih menjalankan analisis secara manual.

Pelaksanaan analisis termasuk aktiviti seperti:

- data cleaning
- pemeriksaan data
- analisis berdasarkan Buku Kerja Migrasi PQC
- penilaian dan interpretasi dapatan
- aktiviti analisis lain yang diperlukan oleh proses kerja

Sistem hanya:

- memantau progress
- merekod status
- menyimpan dapatan yang dimasukkan
- menyokong penjanaan laporan

**Jangan bina sistem yang cuba melakukan analisis secara automatik melainkan diminta kemudian.**

---

# 4. TIADA MODUL MUAT NAIK DOKUMEN UNTUK FASA SEMASA

Ini ialah keperluan penting.

## JANGAN bina:

- document upload module
- drag & drop upload
- upload Buku Kerja Migrasi PQC
- upload borang semakan awal
- OCR dokumen
- ekstrak data daripada DOCX/PDF
- parsing dokumen untuk menghasilkan laporan

## Sebaliknya:

Pegawai Analisis akan memasukkan dapatan secara manual ke dalam sistem.

Input boleh menggunakan:

- select
- dropdown
- radio button
- checkbox
- text field
- textarea
- numeric field
- date field
- komponen input lain yang sesuai

---

# 5. STRUKTUR DATA PEMANTAUAN

Struktur utama pemantauan ialah:

**SEKTOR → ENTITI → ASSIGNMENT → WORKFLOW STATUS**

## 5.1 Sektor

Sistem mempunyai senarai sektor.

Pengguna yang mempunyai akses pemantauan boleh memilih sektor terlebih dahulu.

Contoh:

- Sektor A
- Sektor B
- Sektor C

Nama sebenar sektor hendaklah datang daripada database / data sebenar sistem.

## 5.2 Entiti

Selepas sektor dipilih:

> Sistem memaparkan semua entiti yang berada di bawah sektor tersebut.

Jangan reka struktur baru yang mengabaikan hubungan sektor → entiti.

Setiap entiti perlu mempunyai sekurang-kurangnya konsep:

- ID
- nama entiti
- sektor
- pegawai analisis yang diassign
- status workflow
- tarikh status
- status laporan
- maklumat audit / sejarah jika tersedia

---

# 6. ASSIGNMENT PEGAWAI ANALISIS

Pegawai Penyelaras Analisis boleh assign mana-mana entiti kepada Pegawai Analisis.

Contoh:

```text
Sektor A
├── Entiti Alpha → Pegawai Analisis A
├── Entiti Beta  → Pegawai Analisis B
└── Entiti Gamma → Pegawai Analisis A
```

## Peraturan akses

### Pegawai Penyelaras Analisis

Boleh:

- melihat keseluruhan entiti
- memilih sektor
- melihat progress
- assign entiti
- memantau status
- melihat dashboard
- menyemak progress
- mengurus tindakan yang diberikan kepadanya

### Pegawai Analisis

Hanya boleh:

- melihat entiti yang diassign kepadanya
- mengemas kini maklumat yang dibenarkan
- memasukkan dapatan analisis
- menyimpan draf laporan
- menyambung semula proses laporan
- mengemas kini progress yang berada dalam skopnya

**Pegawai Analisis tidak sepatutnya melihat keseluruhan database entiti jika entiti tersebut tidak diassign kepadanya.**

---

# 7. DASHBOARD

Dashboard ialah fungsi pemantauan.

## 7.1 Siapa boleh melihat dashboard?

Dashboard keseluruhan hanya untuk:

- Pegawai Penyelaras Analisis
- Ketua Bahagian
- pengguna pada tahap akses pengurusan / lebih tinggi yang dibenarkan

**Pegawai Analisis biasa tidak mendapat dashboard keseluruhan.**

## 7.2 Sumber statistik dashboard

Dashboard **TIDAK** menggunakan angka yang dimasukkan secara manual.

Statistik dashboard perlu dikira berdasarkan rekod sistem.

Contoh:

Jika terdapat 100 entiti:

- 20 di peringkat 1
- 15 di peringkat 2
- 25 di peringkat 3
- 20 di peringkat 4
- 10 di peringkat 5
- 5 di peringkat 6
- 5 di peringkat 7

Sistem mengira statistik berdasarkan status sebenar setiap entiti.

## 7.3 Dashboard boleh menunjukkan

Sekurang-kurangnya konsep berikut:

### KPI

- jumlah sektor
- jumlah entiti
- jumlah entiti dalam proses
- jumlah entiti selesai
- jumlah laporan
- jumlah laporan siap
- peratus kemajuan keseluruhan

### Kemajuan workflow

Paparkan progress mengikut:

1. Penerimaan & Pendaftaran Data
2. Semakan Awal Data
3. Penyediaan & Pengesahan Data
4. Pelaksanaan Analisis
5. Penjanaan Laporan
6. Semakan & Kelulusan
7. Penyerahan & Penutupan

### Penapisan

Dashboard sepatutnya boleh ditapis berdasarkan perkara yang sesuai seperti:

- sektor
- entiti
- pegawai
- tempoh
- status

Jangan tambah filter yang tidak diperlukan tanpa sebab.

---

# 8. STATUS WORKFLOW

Setiap entiti mempunyai status semasa.

Workflow:

```text
01 Penerimaan & Pendaftaran Data
        ↓
02 Semakan Awal Data
        ↓
03 Penyediaan & Pengesahan Data
        ↓
04 Pelaksanaan Analisis
        ↓
05 Penjanaan Laporan
        ↓
06 Semakan & Kelulusan
        ↓
07 Penyerahan & Penutupan
```

## Contoh

Jika Entiti Alpha sudah selesai peringkat 1:

```text
Status:
Data Diterima & Didaftarkan

Tarikh:
20/08/2026
```

Apabila entiti bergerak ke peringkat seterusnya:

```text
Status:
Semakan Awal Data

Tarikh:
23/08/2026
```

Sistem perlu menyimpan status semasa.

Jika architecture semasa menyokong audit trail / status history, gunakan struktur tersebut untuk menyimpan sejarah perubahan.

---

# 9. STATUS MENGHASILKAN STATISTIK

Konsep utama:

```text
Pegawai kemas kini status
        ↓
Sistem simpan status + tarikh
        ↓
Sistem kira statistik
        ↓
Dashboard dikemas kini
```

Jangan buat:

```text
Pegawai masukkan 56%
```

Sebaliknya:

```text
Sistem mengira 56%
berdasarkan rekod entiti
```

---

# 10. FUNGSI PENJANAAN LAPORAN

Penjanaan laporan ialah sub-fungsi sistem.

Flow utama:

```text
Dapatan Analisis
       ↓
Kemasukan Data Berstruktur
       ↓
Simpan Draf
       ↓
Sambung Semula
       ↓
Validasi Kelengkapan
       ↓
Pratonton
       ↓
Jana Laporan
       ↓
Semakan
       ↓
Kelulusan
```

---

# 11. DAPATAN ANALISIS

Pegawai Analisis menjalankan analisis berdasarkan:

- Borang Semakan Awal Data
- Buku Kerja Migrasi PQC yang telah diisi dan dianalisis oleh entiti
- hasil data cleaning
- dapatan analisis lain yang berkaitan

Pegawai Analisis kemudian memasukkan dapatan yang diperlukan ke dalam sistem semasa proses penjanaan laporan.

**Sistem tidak mengambil data terus daripada dokumen.**

Data dimasukkan semula secara manual / berstruktur oleh Pegawai Analisis.

---

# 12. SIMPAN & SAMBUNG SEMULA LAPORAN

Ini ialah requirement penting.

Pegawai Analisis **tidak semestinya dapat menyiapkan laporan dalam satu sesi**.

Sistem mesti menyokong konsep:

```text
Mulakan laporan
      ↓
Isi beberapa bahagian
      ↓
Simpan Draf
      ↓
Keluar sistem
      ↓
Datang semula
      ↓
Sambung laporan
      ↓
Lengkapkan
      ↓
Pratonton
      ↓
Jana
```

## Keperluan

Sistem perlu menyimpan:

- status draf
- input yang telah dimasukkan
- entiti
- pegawai
- tarikh / masa
- bahagian yang telah lengkap
- bahagian yang belum lengkap

Jangan hilangkan input apabila pengguna keluar daripada halaman.

---

# 13. LAPORAN ANALISIS INVENTORI KRIPTOGRAFI

Untuk Fasa semasa, laporan utama ialah:

**Laporan Analisis Inventori Kriptografi**

Laporan perlu mengikut templat rasmi yang telah diberikan.

Jangan reka format laporan baru tanpa sebab.

Struktur UI penjanaan laporan hendaklah dibina berdasarkan kandungan templat laporan.

Antara jenis maklumat yang perlu disokong ialah:

- profil sistem / aset
- algoritma kriptografi
- protokol
- pustaka / modul
- vendor
- kebergantungan kriptografi
- pemerhatian
- tindakan susulan
- kesimpulan
- maklumat lain yang terdapat dalam templat rasmi

**Template rasmi ialah sumber rujukan utama untuk kandungan laporan.**

---

# 14. ALGORITMA KRIPTOGRAFI

Untuk bahagian algoritma kriptografi:

**Gunakan rujukan MySEAL / AKSA MySEAL yang telah ditetapkan oleh projek.**

UI yang dirancang ialah menggunakan checkbox.

Konsep:

```text
☐ AES
☐ RSA
☐ ECDSA
☐ SHA-256
☐ ...
```

Jika checkbox tidak dipilih:

> Maksudnya inventori entiti tersebut tidak menggunakan algoritma tersebut.

Jika dipilih:

> Maksudnya algoritma tersebut digunakan oleh entiti.

Jangan gunakan sistem yang memerlukan pengguna menaip nama algoritma secara bebas jika checkbox / pilihan berstruktur boleh digunakan.

**Senarai algoritma sebenar perlu dipadankan dengan rujukan MySEAL yang digunakan oleh projek.**

---

# 15. LAPORAN LAIN DALAM ROADMAP

Roadmap keseluruhan 2026 merangkumi tiga modul laporan:

## Modul 1
**Laporan Inventori Kriptografi**

Fokus:
- profil sistem / aset
- algoritma
- protokol
- pustaka / modul
- vendor
- kebergantungan

## Modul 2
**Laporan Analisis Risiko Migrasi PQC**

Fokus:
- kategori risiko
- tahap risiko
- faktor risiko
- aset berisiko
- keutamaan tindakan

## Modul 3
**Laporan Penilaian Kesiapsiagaan Migrasi PQC**

Fokus:
- rumusan inventori
- risiko
- crypto-agility / kelincahan kriptografi
- limitasi
- kesiapsiagaan
- tindakan migrasi

Namun:

> **Jangan anggap semua modul ini mesti sudah berfungsi sepenuhnya dalam fasa UI semasa.**

Roadmap menunjukkan urutan pembangunan 2026.

---

# 16. ROADMAP PEMBANGUNAN 2026

Cadangan urutan:

| Tempoh | Pembangunan |
|---|---|
| Ogos | Keperluan & reka bentuk |
| Ogos–September | Dashboard asas |
| Ogos–September | Modul Inventori Kriptografi |
| September–Oktober | Modul Risiko Migrasi PQC |
| November–Disember | Modul Kesiapsiagaan |
| Disember | Integrasi + UAT + Sistem V1.0 |

Sasaran:

**Dashboard pemantauan + tiga modul laporan + workflow & kawalan status → Sistem V1.0**

---

# 17. PERANAN PENGGUNA

Gunakan role architecture yang sedia ada dan jangan buang role tanpa sebab.

## Ketua Bahagian

Fokus:

- melihat keseluruhan
- pemantauan
- semakan
- kelulusan
- dashboard
- audit / rekod yang dibenarkan

## Pegawai Penyelaras Analisis

Fokus:

- mengurus tugasan
- assign entiti
- memantau progress
- melihat keseluruhan entiti
- semakan
- dashboard

## Pegawai Analisis

Fokus:

- entiti yang diassign
- semakan awal
- pelaksanaan analisis
- memasukkan dapatan
- penjanaan laporan
- kemas kini draf

## Document Controller

Fokus:

- kawalan nombor rujukan
- versi
- status dokumen
- rekod kelulusan

## Pegawai Rekod Analisis

Fokus:

- pengurusan rekod
- rekod analisis
- output analisis
- laporan

## Pentadbir Sistem

Fokus:

- akaun
- role
- konfigurasi
- template
- parameter workflow
- keselamatan
- log sistem

---

# 18. KAWALAN AKSES

Implement authorization berdasarkan role.

Jangan hanya sembunyikan UI.

Backend / API juga perlu menguatkuasakan permission.

Contoh:

```text
Pegawai Analisis A
    ↓
Hanya Entiti yang diassign kepada A

Pegawai Penyelaras Analisis
    ↓
Semua entiti dalam skop pemantauan

Ketua Bahagian
    ↓
Akses pengurusan mengikut permission
```

Jika sistem sudah menggunakan middleware / policy / permission layer, gunakan semula architecture tersebut.

Jangan duplicate authorization logic di banyak tempat.

---

# 19. STATUS LAPORAN

Selain workflow utama entiti, sistem juga perlu menyokong status laporan yang sesuai.

Contoh konsep:

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

Status sebenar hendaklah diselaraskan dengan workflow / role yang telah ada dalam codebase.

Jangan create duplicate status system jika sudah wujud.

---

# 20. UI/UX YANG DIKEHENDAKI

Sistem perlu kelihatan seperti platform pengurusan organisasi, bukan sekadar CRUD admin panel.

Keutamaan:

- clean
- professional
- modern
- responsive
- mudah difahami
- dashboard yang jelas
- status workflow yang visual
- hierarchy maklumat yang jelas
- form yang tidak terlalu padat
- penggunaan card/table/badge/progress bar yang sesuai

## Prinsip

Pengguna perlu boleh faham:

> “Entiti mana?”
>
> “Sektor mana?”
>
> “Siapa yang bertanggungjawab?”
>
> “Sekarang berada di peringkat mana?”
>
> “Apa tindakan seterusnya?”

tanpa perlu membuka terlalu banyak halaman.

---

# 21. CADANGAN STRUKTUR NAVIGASI

Gunakan struktur yang konsisten dengan codebase semasa, tetapi konsep navigasi hendaklah lebih kurang:

```text
Dashboard
│
├── Pemantauan
│   ├── Sektor
│   ├── Entiti
│   ├── Assignment
│   └── Status / Progress
│
├── Pelaporan
│   ├── Laporan Inventori Kriptografi
│   ├── Laporan Risiko Migrasi PQC
│   └── Laporan Kesiapsiagaan
│
├── Semakan / Kelulusan
│
└── Pentadbiran
    ├── Pengguna
    ├── Role & Permission
    ├── Template
    └── Konfigurasi
```

**Jangan create semua modul di atas jika backend / requirements semasa belum menyokongnya.**

Gunakan placeholder / disabled state untuk fungsi roadmap jika perlu.

---

# 22. HALAMAN UTAMA PEMANTAUAN

Cadangan flow:

```text
Pemantauan
    ↓
Pilih Sektor
    ↓
Senarai Entiti
    ↓
Pilih Entiti
    ↓
Paparan Progress
    ↓
Workflow 1–7
    ↓
Status + Tarikh + Pegawai
```

Paparan entiti sebaiknya menunjukkan:

- nama entiti
- sektor
- pegawai analisis
- status semasa
- tarikh status
- progress
- status laporan
- tindakan

---

# 23. HALAMAN DETAIL ENTITI

Detail entiti perlu menjadi pusat maklumat bagi satu entiti.

Cadangan tab / section:

```text
Maklumat Entiti
Progress Workflow
Assignment
Dapatan Analisis
Laporan
Sejarah Status
```

Tetapi gunakan tab hanya jika benar-benar membantu.

Jangan jadikan UI terlalu kompleks.

---

# 24. PEMANTAUAN WORKFLOW

Paparkan workflow secara visual:

```text
✓ Penerimaan & Pendaftaran
      ↓
✓ Semakan Awal
      ↓
● Penyediaan & Pengesahan
      ↓
○ Pelaksanaan Analisis
      ↓
○ Penjanaan Laporan
      ↓
○ Semakan & Kelulusan
      ↓
○ Penyerahan & Penutupan
```

Gunakan visual seperti:

- stepper
- timeline
- progress indicator
- status badge

Status perlu mudah difahami.

---

# 25. PENJANAAN LAPORAN — UX

Jangan jadikan penjanaan laporan sebagai satu form panjang yang terlalu besar.

Jika templat laporan mempunyai banyak bahagian, pecahkan kepada section / step.

Contoh:

```text
Laporan Inventori Kriptografi

[1] Maklumat Entiti
[2] Profil Sistem / Aset
[3] Algoritma
[4] Protokol
[5] Pustaka / Modul
[6] Vendor & Kebergantungan
[7] Pemerhatian
[8] Tindakan Susulan
[9] Kesimpulan
```

Setiap section perlu boleh disimpan.

---

# 26. DRAFT & AUTO-SAVE

Minimum requirement:

- Save Draft
- Continue Later
- Resume
- Save progress
- Prevent accidental data loss

Jika sesuai dengan architecture semasa, boleh tambah autosave tetapi:

> Jangan implement autosave secara agresif sehingga membebankan backend.

Pastikan pengguna tahu sama ada data sudah disimpan.

---

# 27. VALIDATION

Validation perlu berlaku pada dua tahap:

## Frontend

Untuk UX.

## Backend

Untuk keselamatan dan data integrity.

Jangan bergantung kepada frontend sahaja.

Required fields hendaklah berdasarkan keperluan sebenar templat / business rules.

---

# 28. REPORT PREVIEW

Sebelum laporan dijana:

```text
Input
 ↓
Preview
 ↓
Semak
 ↓
Generate
```

Preview perlu menyerupai struktur laporan sebenar.

Jika sistem sudah mempunyai library untuk DOCX/PDF generation, audit dahulu dan gunakan semula.

Jangan menukar library secara sembarangan.

---

# 29. AUDIT TRAIL

Sistem perlu bersedia untuk merekod perubahan penting.

Contoh:

```text
Entiti Alpha
Status berubah:
Semakan Awal → Penyediaan & Pengesahan

Oleh:
Pegawai A

Tarikh:
23/08/2026
```

Audit trail penting kerana sistem ialah sistem pemantauan.

Jika architecture semasa sudah mempunyai logging / activity log:

> Gunakan semula mekanisme tersebut.

---

# 30. DATA INTEGRITY

Pastikan:

- entiti tidak duplicate
- assignment tidak duplicate secara tidak sengaja
- status workflow valid
- tarikh status valid
- laporan berkait dengan entiti yang betul
- laporan berkait dengan pengguna yang betul
- data draft tidak hilang
- permission tidak boleh bypass melalui API

---

# 31. PERKARA YANG JANGAN DIBUAT SEKARANG

Jangan tambah tanpa arahan:

- upload dokumen
- OCR
- AI extraction daripada dokumen
- automatic PQC risk assessment
- automatic cryptographic analysis
- automatic crypto-agility calculation
- dashboard Pegawai Analisis
- integrasi MasterTable
- integrasi external data source
- notification system kompleks
- workflow approval yang tidak ditetapkan
- modul tambahan yang tiada dalam requirements

Jika sesuatu fungsi kelihatan berguna tetapi belum dipersetujui:

> Tandakan sebagai **Future Enhancement**, jangan terus implement.

---

# 32. PRINSIP PEMBANGUNAN KOD

Sebelum mengubah kod:

1. Audit repository.
2. Kenal pasti framework dan architecture.
3. Kenal pasti database schema.
4. Kenal pasti authentication.
5. Kenal pasti authorization / role.
6. Kenal pasti dashboard sedia ada.
7. Kenal pasti model / entity / user relationship.
8. Kenal pasti route.
9. Kenal pasti API.
10. Kenal pasti komponen UI.
11. Kenal pasti modul yang boleh digunakan semula.
12. Kenal pasti fungsi yang perlu dibuang / diubah.

**Jangan rebuild keseluruhan sistem dari kosong jika codebase sedia ada boleh digunakan.**

---

# 33. CARA COPILOT PERLU BEKERJA

Apabila diberikan arahan ini:

## Fasa A — Audit

Mula dengan:

```text
Audit keseluruhan codebase berdasarkan MASTER PROMPT ini.

Jangan ubah kod dahulu.

Kenal pasti:
- framework
- frontend
- backend
- database
- authentication
- authorization
- current dashboard
- current modules
- current routes
- current APIs
- current models
- current role structure
- current workflow
- current report generation
- upload/document functionality yang sedia ada

Kemudian hasilkan:
1. Current Architecture
2. Current Features
3. Gap Analysis
4. Required Changes
5. Potential Breaking Changes
6. Recommended Implementation Order
```

## Fasa B — Plan

Selepas audit:

```text
Berdasarkan audit, hasilkan implementation plan.

Pecahkan kepada:
Phase 1 — Foundation
Phase 2 — Monitoring
Phase 3 — Assignment & Access Control
Phase 4 — Dashboard
Phase 5 — Report Generation
Phase 6 — Review / Approval
Phase 7 — Testing & Refinement
```

Setiap phase perlu menyatakan:

- files yang terlibat
- database changes
- API changes
- UI changes
- dependencies
- risks

## Fasa C — Implement

Laksanakan secara berperingkat.

Jangan mengubah terlalu banyak perkara dalam satu masa.

Selepas setiap phase:

- run lint
- run tests
- run build
- check routes
- check database
- check authorization
- check UI

---

# 34. PRIORITI IMPLEMENTASI

Keutamaan:

## P0 — WAJIB

1. Sektor
2. Entiti
3. Assignment
4. Role & permission
5. Workflow 7 peringkat
6. Status + tarikh
7. Dashboard
8. Laporan Inventori Kriptografi
9. Save Draft
10. Resume Draft

## P1 — PENTING

11. Validation
12. Preview
13. Review
14. Approval
15. Status history / audit trail
16. Report versioning

## P2 — FUTURE

17. Risk module enhancement
18. Readiness module enhancement
19. Integration
20. Advanced analytics
21. Notifications
22. Other automation

---

# 35. ACCEPTANCE CRITERIA

Sistem dianggap memenuhi keperluan asas apabila:

### Monitoring

- [ ] User boleh memilih sektor.
- [ ] User boleh melihat entiti di bawah sektor.
- [ ] Penyelaras boleh assign entiti kepada Pegawai Analisis.
- [ ] Pegawai Analisis hanya melihat entiti yang diassign.
- [ ] Status workflow boleh dikemas kini.
- [ ] Tarikh status direkodkan.
- [ ] Dashboard mengira statistik daripada data sebenar.
- [ ] Dashboard boleh menunjukkan progress workflow.

### Reporting

- [ ] Pegawai Analisis boleh membuka laporan untuk entiti yang diassign.
- [ ] Input menggunakan komponen berstruktur.
- [ ] Checkbox algoritma berfungsi mengikut requirement.
- [ ] Input boleh disimpan sebagai draft.
- [ ] Draft boleh disambung kemudian.
- [ ] Validation berfungsi.
- [ ] Preview tersedia.
- [ ] Laporan dijana berdasarkan templat Inventori Kriptografi.
- [ ] Tiada upload dokumen diperlukan.

### Access

- [ ] Role permission dikuatkuasakan di backend.
- [ ] Pegawai Analisis tidak boleh melihat entiti yang tidak diassign.
- [ ] Dashboard keseluruhan tidak boleh diakses oleh role yang tidak dibenarkan.

---

# 36. ARAHAN TERAKHIR KEPADA COPILOT

Gunakan dokumen ini sebagai **single source of truth untuk skop sistem yang sedang dibincangkan**.

Jika codebase semasa bercanggah dengan requirement ini:

1. Jangan terus delete code.
2. Kenal pasti percanggahan.
3. Terangkan apa yang bercanggah.
4. Cadangkan perubahan.
5. Pastikan perubahan tidak merosakkan fungsi yang masih diperlukan.
6. Gunakan semula komponen / service / model yang sesuai.
7. Elakkan duplicate architecture.

Jika requirement tidak jelas:

> Jangan reka business rule sendiri.

Tandakan perkara tersebut sebagai:

**NEEDS CONFIRMATION**

dan teruskan bahagian lain yang sudah jelas.

---

# 37. RINGKASAN SISTEM

Secara keseluruhan:

```text
                    SISTEM PEMANTAUAN
                           │
             ┌─────────────┴─────────────┐
             │                           │
        PEMANTAUAN                  PELAPORAN
             │                           │
       Sektor → Entiti              Dapatan Analisis
             │                           │
        Assignment                  Input Berstruktur
             │                           │
       Workflow 1–7                 Save Draft
             │                           │
       Status + Tarikh              Resume
             │                           │
          Dashboard                Validation
                                         │
                                      Preview
                                         │
                                  Jana Laporan
                                         │
                              Inventori Kriptografi
```

**Konsep paling penting:**

> **Pegawai menjalankan kerja dan memasukkan maklumat → Sistem merekod dan mengurus proses → Sistem mengira kemajuan → Pengurusan memantau melalui dashboard → Sistem membantu menjana laporan berdasarkan dapatan analisis.**

