# SENARAI SEMAK UJIAN PENERIMAAN PENGGUNA (UAT)

## Sistem Pemantauan & Pelaporan Analisis Data Migrasi PQC — V1.0-RC1

| Perkara                 | Butiran                                                   |
| ----------------------- | --------------------------------------------------------- |
| Versi diuji             | V1.0-RC1 (Fasa 1–9, 11, 12 lengkap)                       |
| Tarikh disediakan       | 2026-08-17                                                |
| Bilangan senario        | 21 (17 boleh diuji sepenuhnya, 1 diubah suai, 3 tersekat) |
| Jangka masa dianggarkan | 3–4 jam untuk satu pusingan penuh                         |

---

## ⚠️ BACA DAHULU — BATASAN PUSINGAN UAT INI

**Senario 16, 17 dan 18 (Serah untuk Semakan → Semakan → Kelulusan) TIDAK boleh
diuji sepenuhnya.** Modul Semakan & Kelulusan peringkat laporan (Fasa 10) belum
dibina: tiada butang "Serah untuk Semakan", tiada skrin semakan, tiada rekod
kelulusan, dan tiada status laporan "Untuk Semakan / Perlu Pembetulan /
Diluluskan".

Yang **ada** ialah kawalan peringkat workflow. Senario 16–18 diuji dalam bentuk
terhad menggunakan peralihan peringkat 5 → 6 → 7 (dan pengunduran 6 → 5 dengan
sebab). Ini membuktikan aliran pemantauan berfungsi, tetapi **bukan** kelulusan
laporan seperti dalam spesifikasi bahagian 23.

> **Kesan kepada penerimaan**: V1.0 penuh tidak boleh ditandatangani sehingga
> Fasa 10 dibina dan pusingan UAT kedua dijalankan untuk senario 16–18.

**Senario 7 telah diubah suai.** Skrip asal menyatakan Pegawai Analisis
menggerakkan peringkat workflow. Kebenaran yang dilaksanakan (dan disahkan untuk
V1.0) meletakkan kawalan peringkat pada **Pegawai Penyelaras Analisis dan
Pentadbir sahaja**. Pegawai Analisis melihat kedudukan workflow tetapi tidak
boleh mengubahnya.

---

## A. PERSEDIAAN SEBELUM UAT

### A1. Persekitaran

| #    | Langkah                                                            | Selesai |
| ---- | ------------------------------------------------------------------ | :-----: |
| A1.1 | Pasang sistem mengikut `docs/ADMIN_GUIDE.md` bahagian "Pemasangan" |    ☐    |
| A1.2 | Sahkan `APP_ENV=production` dan `APP_DEBUG=false` dalam `.env`     |    ☐    |
| A1.3 | Jalankan `php artisan migrate --force` — semua migrasi DONE        |    ☐    |
| A1.4 | Jalankan `npm run build` — folder `public/build` dijana            |    ☐    |
| A1.5 | Buat sandaran awal: `php scripts/backup-database.php`              |    ☐    |
| A1.6 | Sahkan sistem boleh dicapai melalui HTTPS                          |    ☐    |

### A2. Akaun ujian

Log masuk sebagai Pentadbir, kemudian buka **Pentadbiran → Pengguna** dan cipta
akaun berikut. Gunakan kata laluan sementara yang berbeza bagi setiap akaun.

| #    | Nama pengguna    | Peranan                     | Selesai |
| ---- | ---------------- | --------------------------- | :-----: |
| A2.1 | `uat.penyelaras` | Pegawai Penyelaras Analisis |    ☐    |
| A2.2 | `uat.analisis.a` | Pegawai Analisis            |    ☐    |
| A2.3 | `uat.analisis.b` | Pegawai Analisis            |    ☐    |
| A2.4 | `uat.ketua`      | Ketua Bahagian              |    ☐    |

### A3. Entiti ujian

| Rujukan  | Kod       | Nama                                          | Digunakan untuk                    |
| -------- | --------- | --------------------------------------------- | ---------------------------------- |
| ENTITI-A | `A010101` | Suruhanjaya Pilihan Raya (SPR)                | Ditugaskan kepada `uat.analisis.a` |
| ENTITI-B | `A010102` | Suruhanjaya Pencegahan Rasuah Malaysia (SPRM) | Ditugaskan kepada `uat.analisis.b` |

Kedua-duanya berada dalam **Sektor 001 — Kerajaan**.

---

## B. SENARIO UJIAN

Isi lajur **Keputusan** dengan LULUS / GAGAL. Setiap kegagalan mesti direkodkan
dalam Bahagian D (Log Kecacatan).

---

### S1 — Pegawai Penyelaras log masuk

|                         |                                                |
| ----------------------- | ---------------------------------------------- |
| **Peranan**             | Pegawai Penyelaras Analisis (`uat.penyelaras`) |
| **Rujukan spesifikasi** | Bahagian 25, 26                                |

**Langkah**

1. Buka URL sistem. Sistem sepatutnya mengalihkan ke halaman log masuk.
2. Masukkan nama pengguna dan kata laluan yang salah.
3. Masukkan kelayakan yang betul.

**Jangkaan**

- Langkah 2: mesej "Nama pengguna atau kata laluan tidak sah." Tiada petunjuk
  sama ada nama pengguna itu wujud.
- Langkah 3: masuk ke Papan Pemuka Pemantauan.
- Papan pemuka memaparkan Jumlah Sektor, Jumlah Entiti, Dalam Proses, Selesai,
  Jumlah Laporan dan Kemajuan Keseluruhan.

**Keputusan**: ☐ LULUS ☐ GAGAL — Catatan: \***\*\*\*\*\***\_\_\***\*\*\*\*\***

---

### S2 — Penyelaras memilih sektor

|                         |                             |
| ----------------------- | --------------------------- |
| **Peranan**             | Pegawai Penyelaras Analisis |
| **Rujukan spesifikasi** | Bahagian 7                  |

**Langkah**

1. Buka menu **Penugasan Entiti**.
2. Pilih **Sektor 001 — Kerajaan** daripada penapis sektor.

**Jangkaan**

- Senarai menunjukkan entiti dalam sektor yang dipilih sahaja.
- Entiti sektor lain tidak muncul.
- Entiti yang belum mempunyai sebarang rekod turut dipaparkan.

**Keputusan**: ☐ LULUS ☐ GAGAL — Catatan: \***\*\*\*\*\***\_\_\***\*\*\*\*\***

---

### S3 — Penyelaras melihat senarai entiti

|                         |                             |
| ----------------------- | --------------------------- |
| **Peranan**             | Pegawai Penyelaras Analisis |
| **Rujukan spesifikasi** | Bahagian 7, 13              |

**Langkah**

1. Daripada senarai penugasan, klik **ENTITI-A (A010101)**.
2. Buka juga **Pusat Maklumat Entiti** bagi entiti yang sama.

**Jangkaan**

- Halaman entiti memaparkan: nama entiti, sektor, penugasan semasa, kedudukan
  workflow, dapatan analisis, status laporan dan sejarah.
- Ruangan yang belum mempunyai data memaparkan keadaan kosong yang jelas
  (bukan ralat).

**Keputusan**: ☐ LULUS ☐ GAGAL — Catatan: \***\*\*\*\*\***\_\_\***\*\*\*\*\***

---

### S4 — Penyelaras menugaskan entiti kepada Pegawai Analisis

|                         |                             |
| ----------------------- | --------------------------- |
| **Peranan**             | Pegawai Penyelaras Analisis |
| **Rujukan spesifikasi** | Bahagian 8                  |

**Langkah**

1. Pada halaman penugasan ENTITI-A, pilih **uat.analisis.a**, isi catatan, simpan.
2. Ulang bagi ENTITI-B → **uat.analisis.b**.
3. Cuba tugaskan semula ENTITI-A kepada **uat.analisis.a** (pegawai yang sama).
4. Cuba tugaskan ENTITI-A kepada **uat.ketua** (bukan Pegawai Analisis).

**Jangkaan**

- Langkah 1–2: mesej berjaya; penugasan dipaparkan sebagai **Aktif** dengan nama
  pegawai, pegawai yang menugaskan dan tarikh.
- Langkah 3: ditolak — "Tiada penugasan pendua dibenarkan."
- Langkah 4: ditolak — "Entiti hanya boleh ditugaskan kepada Pegawai Analisis."
- Sejarah penugasan kekal dipaparkan.

**Keputusan**: ☐ LULUS ☐ GAGAL — Catatan: \***\*\*\*\*\***\_\_\***\*\*\*\*\***

---

### S5 — Pegawai Analisis log masuk

|                         |                                     |
| ----------------------- | ----------------------------------- |
| **Peranan**             | Pegawai Analisis (`uat.analisis.a`) |
| **Rujukan spesifikasi** | Bahagian 10, 26                     |

**Langkah**

1. Log keluar daripada akaun Penyelaras.
2. Log masuk sebagai `uat.analisis.a`.

**Jangkaan**

- Pegawai Analisis **tidak** menerima papan pemuka keseluruhan; sistem
  mengalihkan ke senarai kerja (Analisis Inventori Kriptografi) dengan mesej penjelasan.
- Menu tidak memaparkan Penugasan Entiti, Jejak Audit atau Pentadbiran.

**Keputusan**: ☐ LULUS ☐ GAGAL — Catatan: \***\*\*\*\*\***\_\_\***\*\*\*\*\***

---

### S6 — Pegawai Analisis hanya melihat entiti yang ditugaskan

|                         |                                                 |
| ----------------------- | ----------------------------------------------- |
| **Peranan**             | Pegawai Analisis (`uat.analisis.a`)             |
| **Rujukan spesifikasi** | **Bahagian 9 — keperluan keselamatan kritikal** |

**Langkah**

1. Semak senarai Analisis, Laporan, Status Laporan dan Workflow.
2. Taip URL entiti yang **tidak** ditugaskan terus pada pelayar:
   `/entiti/A010102`
3. Ulang bagi `/workflow/A010102`
4. Ulang bagi `/analisis/borang?sector_code=001&agency_code=A010102`

**Jangkaan**

- Langkah 1: **ENTITI-B (SPRM) tidak muncul di mana-mana senarai.**
- Langkah 2–4: setiap capaian ditolak dengan ralat **403** — bukan sekadar
  butang tersembunyi, dan tiada maklumat entiti dipaparkan.

**Keputusan**: ☐ LULUS ☐ GAGAL — Catatan: \***\*\*\*\*\***\_\_\***\*\*\*\*\***

---

### S7 — Entiti digerakkan melalui workflow ⚠️ DIUBAH SUAI

|                         |                                                          |
| ----------------------- | -------------------------------------------------------- |
| **Peranan**             | Pegawai Penyelaras Analisis (**bukan** Pegawai Analisis) |
| **Rujukan spesifikasi** | Bahagian 6, 11, 12                                       |

> **Perubahan daripada skrip asal**: kawalan peringkat workflow dipegang oleh
> Penyelaras/Pentadbir. Pegawai Analisis melihat kedudukan workflow sahaja.
> Disahkan sebagai peraturan V1.0.

**Langkah**

1. Sebagai `uat.analisis.a`, buka `/workflow/A010101` — sahkan **tiada** butang
   ubah peringkat.
2. Log masuk sebagai `uat.penyelaras`; buka workflow ENTITI-A.
3. Klik **Daftar dalam workflow** (peringkat 1 — Penerimaan & Pendaftaran Data).
4. Majukan peringkat satu demi satu: 1 → 2 → 3 → 4.
5. Cuba lompat terus dari peringkat 4 ke peringkat 7.
6. Cuba undur ke peringkat 3 **tanpa** mengisi sebab.
7. Undur ke peringkat 3 **dengan** sebab "Data Jadual 1 perlu disemak semula".
8. Maju semula ke peringkat 4.

**Jangkaan**

- Langkah 1: paparan sahaja; tiada tindakan tersedia.
- Langkah 3–4: setiap peringkat direkod dengan nama peringkat yang betul.
- Langkah 5: ditolak — "Peringkat mesti dilalui secara berturutan…"
- Langkah 6: ditolak — sebab diwajibkan.
- Langkah 7: berjaya; sebab dipaparkan dalam sejarah.

**Keputusan**: ☐ LULUS ☐ GAGAL — Catatan: \***\*\*\*\*\***\_\_\***\*\*\*\*\***

---

### S8 — Analisis manual dijalankan di luar sistem

|                         |                   |
| ----------------------- | ----------------- |
| **Peranan**             | Pegawai Analisis  |
| **Rujukan spesifikasi** | Bahagian 2, 3, 15 |

**Langkah**

1. Pegawai Analisis menjalankan kerja analisis seperti biasa di luar sistem
   (pembersihan data, semakan Buku Kerja Migrasi PQC, interpretasi dapatan).
2. Semasa langkah ini, semak keseluruhan antara muka sistem.

**Jangkaan**

- Sistem **tidak** meminta sebarang muat naik dokumen untuk meneruskan aliran
  pelaporan.
- Tiada medan "pilih fail" pada borang dapatan analisis.
- Sistem tidak cuba membaca atau mentafsir dokumen secara automatik.

**Keputusan**: ☐ LULUS ☐ GAGAL — Catatan: \***\*\*\*\*\***\_\_\***\*\*\*\*\***

---

### S9 — Pegawai Analisis memasukkan dapatan ke dalam borang laporan

|                         |                                     |
| ----------------------- | ----------------------------------- |
| **Peranan**             | Pegawai Analisis (`uat.analisis.a`) |
| **Rujukan spesifikasi** | Bahagian 16, 17, 19                 |

**Langkah**

1. Buka **Analisis Inventori Kriptografi** → pilih Sektor 001 → ENTITI-A → **Isi Borang**.
2. Seksyen 1: isi tarikh laporan dan kod rujukan; pilih status laporan.
3. Seksyen 2: pilih status penerimaan/kebolehgunaan bagi Jadual 0–2.
4. Seksyen 3: masukkan bilangan aset bagi setiap kategori profil.
5. Seksyen 4 (**Algoritma**): tanda **AES** dan **RSA**; biarkan **MD5 tidak
   ditanda**. Isi bilangan aset bagi yang ditanda.
6. Seksyen 5–7: tambah satu baris protokol, satu pustaka, satu vendor.
7. Seksyen 8–9: pilih cadangan tindakan susulan dan kesimpulan.

**Jangkaan**

- Semua input berbentuk pilihan berstruktur (checkbox, dropdown, radio, medan
  angka, tarikh) — tiada taipan bebas untuk algoritma.
- Menanda checkbox algoritma memaparkan medan bilangan dan pemerhatian.
- Senarai algoritma mengikut kategori rujukan AKSA MySEAL.
- Baris protokol/pustaka/vendor boleh ditambah dan dibuang.

**Keputusan**: ☐ LULUS ☐ GAGAL — Catatan: \***\*\*\*\*\***\_\_\***\*\*\*\*\***

---

### S10 — Simpan draf

|                         |                                     |
| ----------------------- | ----------------------------------- |
| **Peranan**             | Pegawai Analisis (`uat.analisis.a`) |
| **Rujukan spesifikasi** | Bahagian 18                         |

**Langkah**

1. Dengan borang separa diisi (jangan lengkapkan semua medan), klik
   **Simpan Draf**.
2. Perhatikan panel status draf.

**Jangkaan**

- Draf disimpan **tanpa** ralat pengesahan walaupun borang belum lengkap.
- Sistem memaparkan masa simpanan terakhir, nombor versi dan seksyen yang telah
  mempunyai kandungan.

**Keputusan**: ☐ LULUS ☐ GAGAL — Catatan: \***\*\*\*\*\***\_\_\***\*\*\*\*\***

---

### S11 — Pegawai Analisis meninggalkan sistem

|             |                                     |
| ----------- | ----------------------------------- |
| **Peranan** | Pegawai Analisis (`uat.analisis.a`) |

**Langkah**

1. Ubah satu medan tanpa menyimpan, kemudian cuba tutup tab pelayar.
2. Batalkan amaran, klik **Simpan Draf**, kemudian **Log Keluar**.
3. Tekan butang "Back" pelayar selepas log keluar.

**Jangkaan**

- Langkah 1: pelayar memaparkan amaran kerja belum disimpan.
- Langkah 3: sistem meminta log masuk semula; kandungan halaman tidak dipaparkan.

**Keputusan**: ☐ LULUS ☐ GAGAL — Catatan: \***\*\*\*\*\***\_\_\***\*\*\*\*\***

---

### S12 — Pegawai Analisis menyambung semula draf

|                         |                                     |
| ----------------------- | ----------------------------------- |
| **Peranan**             | Pegawai Analisis (`uat.analisis.a`) |
| **Rujukan spesifikasi** | Bahagian 18                         |

**Langkah**

1. Log masuk semula.
2. Buka semula borang bagi ENTITI-A.

**Jangkaan**

- **Setiap nilai yang telah disimpan dipaparkan semula**, termasuk checkbox
  algoritma yang ditanda dan baris protokol/pustaka/vendor.
- Panel draf menunjukkan versi dan masa simpanan terakhir serta nama pegawai
  yang menyimpan.

**Keputusan**: ☐ LULUS ☐ GAGAL — Catatan: \***\*\*\*\*\***\_\_\***\*\*\*\*\***

---

### S13 — Pegawai Analisis melengkapkan laporan

|                         |                                     |
| ----------------------- | ----------------------------------- |
| **Peranan**             | Pegawai Analisis (`uat.analisis.a`) |
| **Rujukan spesifikasi** | Bahagian 20                         |

**Langkah**

1. Kosongkan medan wajib (status laporan / ringkasan data) dan klik
   **Simpan Dapatan**.
2. Isi semula semua medan wajib, tanda **Analisis selesai**, klik
   **Simpan Dapatan**.

**Jangkaan**

- Langkah 1: ditolak dengan mesej ralat pada medan berkenaan; tiada data rosak
  disimpan.
- Langkah 2: mesej berjaya; rekod dipaparkan dalam senarai analisis sebagai
  selesai; status laporan Inventori bagi entiti bertukar kepada **Dalam Proses**.

**Keputusan**: ☐ LULUS ☐ GAGAL — Catatan: \***\*\*\*\*\***\_\_\***\*\*\*\*\***

---

### S14 — Pratonton laporan

|                         |                                     |
| ----------------------- | ----------------------------------- |
| **Peranan**             | Pegawai Analisis (`uat.analisis.a`) |
| **Rujukan spesifikasi** | Bahagian 14, 21                     |

**Langkah**

1. Buka **Laporan** → ENTITI-A → **Pratonton**.
2. Semak kandungan berbanding dapatan yang dimasukkan dalam S9.

**Jangkaan**

- Tajuk **Laporan Analisis Inventori Kriptografi** dan susunan seksyen mengikut
  templat rasmi.
- **AES** dan **RSA** muncul; **MD5 tidak muncul** (kerana tidak ditanda).
- RSA dikenal pasti sebagai berisiko kuantum.
- Ayat ringkasan status data mengikut pilihan yang dibuat, bukan teks lalai.

**Keputusan**: ☐ LULUS ☐ GAGAL — Catatan: \***\*\*\*\*\***\_\_\***\*\*\*\*\***

---

### S15 — Jana laporan (PDF)

|                         |                                     |
| ----------------------- | ----------------------------------- |
| **Peranan**             | Pegawai Analisis (`uat.analisis.a`) |
| **Rujukan spesifikasi** | Bahagian 14, 21                     |

**Langkah**

1. Klik **Muat Turun PDF**.
2. Buka fail yang dimuat turun.

**Jangkaan**

- Fail PDF dimuat turun dengan nama mengikut kod rujukan laporan.
- Setiap muka surat mempunyai kepala (logo NACSA + PTPKM + tanda RAHSIA) dan
  kaki (kod rujukan + nombor muka surat).
- Kandungan PDF sepadan dengan pratonton.

**Keputusan**: ☐ LULUS ☐ GAGAL — Catatan: \***\*\*\*\*\***\_\_\***\*\*\*\*\***

---

### S16 — Serah untuk semakan 🚫 TERSEKAT (Fasa 10)

|            |                                                                                |
| ---------- | ------------------------------------------------------------------------------ |
| **Status** | **TIDAK BOLEH DIUJI SEPENUHNYA**                                               |
| **Sebab**  | Tiada tindakan "Serah untuk Semakan" pada laporan. Modul Fasa 10 belum dibina. |

**Ujian gantian (bentuk terhad)**

1. Sebagai `uat.penyelaras`, majukan ENTITI-A ke peringkat **5 — Penjanaan
   Laporan**, kemudian ke peringkat **6 — Semakan & Kelulusan**.

**Jangkaan (bentuk terhad)**

- Peringkat entiti bertukar kepada 6 dan direkodkan dalam jejak audit.

**Yang TIDAK diuji**: status laporan "Untuk Semakan", pengenalpastian penyerah,
cap masa penyerahan laporan, dan penguncian laporan semasa semakan.

**Keputusan**: ☐ LULUS (bentuk terhad) ☐ GAGAL ☐ TERSEKAT

---

### S17 — Pegawai yang diberi kuasa membuat semakan 🚫 TERSEKAT (Fasa 10)

|            |                                                            |
| ---------- | ---------------------------------------------------------- |
| **Status** | **TIDAK BOLEH DIUJI SEPENUHNYA**                           |
| **Sebab**  | Tiada skrin semakan laporan, tiada ruangan komen penyemak. |

**Ujian gantian (bentuk terhad)**

1. Log masuk sebagai `uat.ketua` (Ketua Bahagian).
2. Buka Pusat Maklumat Entiti dan pratonton laporan ENTITI-A.
3. Buka **Jejak Audit** dan tapis mengikut ENTITI-A.

**Jangkaan (bentuk terhad)**

- Ketua Bahagian boleh melihat entiti, laporan dan jejak audit.
- **Perhatikan**: Ketua Bahagian **tidak** mempunyai sebarang tindakan semakan
  atau kelulusan. Ini dijangka dalam V1.0-RC1.

**Yang TIDAK diuji**: komen penyemak, rekod "disemak oleh", dan status semakan.

**Keputusan**: ☐ LULUS (bentuk terhad) ☐ GAGAL ☐ TERSEKAT

---

### S18 — Penyemak memulangkan atau meluluskan 🚫 TERSEKAT (Fasa 10)

|            |                                                                                        |
| ---------- | -------------------------------------------------------------------------------------- |
| **Status** | **TIDAK BOLEH DIUJI SEPENUHNYA**                                                       |
| **Sebab**  | Tiada tindakan Lulus / Pulangkan pada laporan; jadual `approval_logs` tidak digunakan. |

**Ujian gantian (bentuk terhad)** — dilakukan oleh Penyelaras, bukan penyemak:

1. **Pulangkan**: undur ENTITI-A dari peringkat 6 ke peringkat 5 dengan sebab
   "Perlu pembetulan pada Seksyen 4".
2. Maju semula ke peringkat 6.

**Jangkaan (bentuk terhad)**

- Pengunduran memerlukan sebab dan direkodkan dalam jejak audit sebagai
  perubahan peringkat ke belakang berserta sebab.

**Yang TIDAK diuji**: kelulusan laporan, sejarah kelulusan, komen pemulangan
pada laporan, dan status laporan "Perlu Pembetulan" / "Diluluskan".

**Keputusan**: ☐ LULUS (bentuk terhad) ☐ GAGAL ☐ TERSEKAT

---

### S19 — Workflow bergerak ke penyerahan dan penutupan

|                         |                             |
| ----------------------- | --------------------------- |
| **Peranan**             | Pegawai Penyelaras Analisis |
| **Rujukan spesifikasi** | Bahagian 6                  |

**Langkah**

1. Majukan ENTITI-A dari peringkat 6 ke peringkat **7 — Penyerahan & Penutupan**.
2. Kemas kini status peringkat kepada **Siap**.
3. Buka **Status Laporan** dan kitarkan status laporan Inventori sehingga
   **Siap**.

**Jangkaan**

- Entiti berada pada peringkat 7 dan ditanda selesai pada paparan workflow.
- Tarikh status dan nama pegawai yang mengemas kini direkodkan.
- Status laporan Inventori = Siap.

**Keputusan**: ☐ LULUS ☐ GAGAL — Catatan: \***\*\*\*\*\***\_\_\***\*\*\*\*\***

---

### S20 — Papan pemuka mencerminkan keadaan terkini

|                         |                                              |
| ----------------------- | -------------------------------------------- |
| **Peranan**             | Pegawai Penyelaras Analisis / Ketua Bahagian |
| **Rujukan spesifikasi** | Bahagian 10                                  |

**Langkah**

1. Catat angka papan pemuka sebelum S19.
2. Buka semula papan pemuka selepas S19.
3. Gunakan penapis sektor dan julat tarikh.
4. Log masuk sebagai `uat.ketua` dan bandingkan.

**Jangkaan**

- **Selesai** bertambah 1; **Dalam Proses** berkurang 1.
- **Kemajuan Keseluruhan** berubah — dikira daripada peringkat sebenar, bukan
  nilai yang ditaip.
- Taburan 7 peringkat menunjukkan ENTITI-A pada peringkat 7.
- Ketua Bahagian melihat angka yang sama.
- Penapis tidak sah (sektor tiada, tarikh salah format) tidak menyebabkan ralat.

**Keputusan**: ☐ LULUS ☐ GAGAL — Catatan: \***\*\*\*\*\***\_\_\***\*\*\*\*\***

---

### S21 — Jejak audit merekod perubahan penting

|                         |                                         |
| ----------------------- | --------------------------------------- |
| **Peranan**             | Pegawai Penyelaras Analisis / Pentadbir |
| **Rujukan spesifikasi** | Bahagian 24                             |

**Langkah**

1. Buka **Jejak Audit**; tapis mengikut ENTITI-A.
2. Semak rekod dari awal hingga akhir pusingan UAT.
3. Tapis mengikut jenis tindakan dan mengikut pengguna.
4. Sebagai `uat.analisis.a`, cuba buka `/jejak-audit`.

**Jangkaan**

- Rekod meliputi sekurang-kurangnya: penugasan dibuat, entiti didaftarkan dalam
  workflow, draf dimulakan/disimpan, analisis disimpan, peringkat workflow
  berubah (termasuk pengunduran berserta sebab), status laporan berubah.
- Setiap rekod memaparkan nilai lama → nilai baharu, nama pegawai dan cap masa.
- **Kandungan dapatan analisis tidak dipaparkan dalam jejak audit** (hanya
  metadata perubahan).
- Langkah 4: Pegawai Analisis ditolak dengan 403.

**Keputusan**: ☐ LULUS ☐ GAGAL — Catatan: \***\*\*\*\*\***\_\_\***\*\*\*\*\***

---

## C. SEMAKAN TAMBAHAN (bukan senario, tetapi wajib sebelum pelepasan)

| #   | Semakan                 | Cara                                                               | Keputusan |
| --- | ----------------------- | ------------------------------------------------------------------ | :-------: |
| C1  | Had percubaan log masuk | Masukkan kata laluan salah 5 kali; percubaan ke-6 disekat ~60 saat |     ☐     |
| C2  | Peranan tanpa kebenaran | Log masuk sebagai Pegawai Kawalan Dokumen — tiada akses entiti     |     ☐     |
| C3  | Halaman ralat           | Buka URL yang tidak wujud — 404 kemas, tiada surih tindanan        |     ☐     |
| C4  | Sesi tamat tempoh       | Biarkan 2 jam tanpa aktiviti, cuba simpan — dialihkan ke log masuk |     ☐     |
| C5  | Sandaran                | `php scripts/backup-database.php` — SANDARAN BERJAYA               |     ☐     |
| C6  | Pemulihan               | Pulihkan ke pangkalan data ujian mengikut ADMIN_GUIDE              |     ☐     |
| C7  | Cetakan PDF             | Buka PDF dalam pembaca lain; sahkan kepala/kaki setiap muka surat  |     ☐     |
| C8  | Paparan mudah alih      | Semak papan pemuka dan borang pada tablet                          |     ☐     |
| C9  | Kebolehcapaian          | Navigasi borang menggunakan papan kekunci sahaja                   |     ☐     |

---

## D. LOG KECACATAN

| ID   | Senario | Keterukan                              | Keterangan | Status                         |
| ---- | ------- | -------------------------------------- | ---------- | ------------------------------ |
| D-01 |         | Kritikal / Tinggi / Sederhana / Rendah |            | Baharu / Diperbaiki / Diterima |
| D-02 |         |                                        |            |                                |
| D-03 |         |                                        |            |                                |

**Panduan keterukan**

- **Kritikal** — kehilangan data, pintasan kawalan akses, sistem tidak boleh digunakan. _Menghalang pelepasan._
- **Tinggi** — fungsi teras gagal tanpa jalan penyelesaian. _Menghalang pelepasan._
- **Sederhana** — fungsi gagal tetapi ada jalan penyelesaian.
- **Rendah** — kosmetik atau kesulitan kecil.

---

## E. RINGKASAN DAN PENGESAHAN

| Perkara                                   |      Bilangan |
| ----------------------------------------- | ------------: |
| Senario LULUS                             | \_\_\_\_ / 18 |
| Senario LULUS (bentuk terhad)             |  \_\_\_\_ / 3 |
| Senario GAGAL                             |      \_\_\_\_ |
| Kecacatan Kritikal / Tinggi belum selesai |      \_\_\_\_ |

### Syarat penerimaan V1.0-RC1

- [ ] Semua senario S1–S15, S19–S21 LULUS.
- [ ] Semua semakan C1–C9 LULUS.
- [ ] Tiada kecacatan Kritikal atau Tinggi yang belum selesai.
- [ ] Batasan senario S16–S18 difahami dan diterima sebagai skop Fasa 10.

### Syarat penerimaan V1.0 penuh

- [ ] Semua syarat V1.0-RC1 di atas dipenuhi.
- [ ] **Fasa 10 (Semakan & Kelulusan) dibina.**
- [ ] Pusingan UAT kedua bagi S16–S18 dijalankan dan LULUS.

### Tandatangan

| Peranan                        | Nama | Tandatangan | Tarikh |
| ------------------------------ | ---- | ----------- | ------ |
| Penguji UAT (Penyelaras)       |      |             |        |
| Penguji UAT (Pegawai Analisis) |      |             |        |
| Ketua Bahagian                 |      |             |        |
| Pentadbir Sistem               |      |             |        |
