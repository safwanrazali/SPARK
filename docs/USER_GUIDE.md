# PANDUAN PENGGUNA

> ## ⚠️ RESTRUKTUR ALIRAN KERJA — 7 PERINGKAT → 5 PERINGKAT
>
> Aliran kerja sistem telah distruktur semula daripada **7 peringkat rata**
> kepada **5 peringkat utama dengan sub-peringkat**. Struktur rasmi kini:
>
> | Peringkat | Proses                                     | Peranan  | Fasa         |
> | --------- | ------------------------------------------ | -------- | ------------ |
> | 1         | Penerimaan & Semakan Awal Data             |          |              |
> | 1.1       | Penerimaan Data                            | KB / PPA | Semasa       |
> | 1.2       | Pendaftaran Data                           | PPA      | Semasa       |
> | 1.3       | Semakan Awal Data                          | PA       | Semasa       |
> | 2         | Penyediaan & Pengesahan Data               | PA       | Semasa       |
> | 3         | Analisis Data                              |          |              |
> | 3.1       | Analisis Inventori Kriptografi             | PA       | Semasa       |
> | 3.2       | Analisis Risiko Migrasi PQC                | —        | Akan datang  |
> | 4         | Penjanaan Laporan                          | —        | Akan datang  |
> | 5         | Semakan, Kelulusan & Penyerahan Laporan    | —        | Akan datang  |
>
> **SETIAP No. Rujukan** — keempat-empatnya — dimasukkan oleh **Pegawai
> Kawalan Dokumen (PKD)**, walaupun peringkatnya dilaksanakan oleh KB, PPA
> dan PA. Itulah keseluruhan tanggungjawab PKD, dan satu-satunya kuasa
> menulis yang dimilikinya:
>
> | No. Rujukan                          | Peringkat |
> | ------------------------------------ | --------- |
> | No. Rujukan Borang Penerimaan Data   | 1.1       |
> | No. Rujukan Borang Pendaftaran Data  | 1.2       |
> | No. Rujukan Borang Semakan Awal Data | 1.3       |
> | No. Rujukan Laporan                  | 3.1       |
>
> **Pegawai Penyelaras Rekod (PPR)** tiada tugas khusus dalam fasa ini — ia
> boleh melihat, tetapi tidak memasukkan No. Rujukan.
>
> **Fasa semasa berakhir pada peringkat 3.1.** Peringkat 3.2, 4 dan 5 telah
> ditakrifkan dalam struktur tetapi prosesnya belum ditentukan; ia tidak
> menerima sebarang tindakan.
>
> Takrifan tunggal struktur ini ialah `app/Support/AliranKerja.php`.
>
> **Bahagian di bawah yang masih menerangkan aliran 7 peringkat sudah lapuk dan
> perlu ditulis semula bersama spesifikasi peringkat 4 dan 5.**


## Sistem Pemantauan & Pelaporan Analisis Data Migrasi PQC — V1.0-RC1

---

## 1. APA YANG SISTEM INI LAKUKAN

Sistem ini **memantau** proses analisis data migrasi PQC dan **menyokong
penjanaan laporan** hasil analisis tersebut.

| Sistem ini **melakukan**                                   | Sistem ini **tidak** melakukan                  |
| ---------------------------------------------------------- | ----------------------------------------------- |
| Merekod kedudukan setiap entiti dalam 5 peringkat aliran kerja | Menjalankan analisis PQC secara automatik   |
| Menyimpan penugasan entiti kepada Pegawai Analisis         | Membaca atau mentafsir dokumen secara automatik |
| Menerima dapatan analisis melalui borang berstruktur       | Mengira risiko PQC secara automatik             |
| Menyimpan draf supaya kerja tidak hilang                   | Memerlukan muat naik Buku Kerja Migrasi PQC     |
| Menjana Laporan Analisis Inventori Kriptografi (PDF)       | Menghantar e-mel atau notifikasi                |
| Mengira statistik papan pemuka daripada rekod sebenar      | Menyimpan peratusan kemajuan secara manual      |
| Merekod jejak audit setiap perubahan penting               |                                                 |

> **Penting**: Pegawai Analisis tetap menjalankan kerja analisis secara manual
> (pembersihan data, semakan, interpretasi) di luar sistem, kemudian
> **memasukkan dapatan** ke dalam sistem.

---

## 2. LOG MASUK DAN KESELAMATAN AKAUN

1. Buka URL sistem yang diberikan oleh Pentadbir.
2. Masukkan **nama pengguna** (bukan e-mel) dan **kata laluan**.
3. Klik **Log Masuk**.

**Perkara yang perlu diketahui**

- Selepas **5 percubaan gagal**, akaun anda disekat selama **60 saat**. Tunggu
  sebentar dan cuba semula; jika terlupa kata laluan, hubungi Pentadbir Sistem.
- Sesi tamat selepas **120 minit** tanpa aktiviti. Simpan draf dengan kerap.
- Klik **Log Keluar** apabila selesai, terutamanya pada komputer yang dikongsi.
- Jangan kongsi akaun. Setiap tindakan direkodkan atas nama pengguna yang log
  masuk dan kekal dalam jejak audit.

---

## 3. PERANAN DAN AKSES

| Peranan                     | Papan pemuka |       Semua entiti        | Tugaskan entiti | Isi dapatan | Jana laporan | Jejak audit |
| --------------------------- | :----------: | :-----------------------: | :-------------: | :---------: | :----------: | :---------: |
| Pentadbir Sistem            |      ✓       |             ✓             |        ✓        |      ✓      |      ✓       |      ✓      |
| Pegawai Analisis            |      ✗       | **hanya yang ditugaskan** |        ✗        |      ✓      |      ✓       |      ✗      |
| Pegawai Penyelaras Analisis |      ✓       |             ✓             |        ✓        |      ✗      |      ✗       |      ✓      |
| Pegawai Penyelaras Rekod    |      ✓       |             ✓             |        ✗        |      ✗      |      ✗       |      ✓      |
| Pegawai Kawalan Dokumen     |      ✓       |  **telah bermula sahaja** |        ✗        |      ✗      |      ✗       |      ✓      |
| Ketua Bahagian              |      ✓       |             ✓             |        ✗        |      ✗      |      ✗       |      ✓      |
| Timbalan Pengarah II        |      ✓       |             ✓             |        ✗        |      ✗      |      ✗       |      ✗      |

> **Pegawai Kawalan Dokumen (PKD)** memasukkan SETIAP No. Rujukan (peringkat
> 1.1, 1.2, 1.3 dan 3.1) — itulah satu-satunya kuasa menulisnya. PKD hanya
> melihat entiti yang telah memulakan Penerimaan Data.
>
> **Pegawai Penyelaras Rekod (PPR)** tiada tugas khusus dalam fasa ini: ia
> melihat semua entiti secara **baca sahaja**.
>
> Timbalan Pengarah II belum dimuktamadkan; buat sementara ia diberi
> akses **baca sahaja** kepada papan pemuka dan semua entiti.

**Peraturan akses paling penting**: Pegawai Analisis hanya boleh melihat dan
menyunting entiti yang **ditugaskan kepadanya**. Cuba membuka entiti lain —
walaupun melalui URL terus — akan ditolak.

---

## 4. PANDUAN PEGAWAI PENYELARAS ANALISIS

### 4.1 Papan Pemuka

Menu **Papan Pemuka** memaparkan gambaran keseluruhan:

- Jumlah Sektor, Jumlah Entiti, Dalam Proses, Selesai
- Jumlah Laporan dan Laporan Siap
- Kemajuan Keseluruhan (%)
- Taburan entiti merentas peringkat aliran kerja
- Aktiviti terkini

Semua angka **dikira daripada rekod sebenar** setiap kali halaman dibuka.
Gunakan penapis **Sektor** dan **julat tarikh** untuk menyempitkan paparan.

### 4.2 Menugaskan entiti kepada Pegawai Analisis

1. Buka **Pemantauan → Penugasan Entiti**.
2. Pilih **sektor**. Semua entiti dalam sektor tersebut dipaparkan.
3. Klik entiti yang dikehendaki.
4. Pilih **Pegawai Analisis**, isi catatan (jika ada), klik **Tugaskan**.

**Peraturan**

- Satu entiti hanya boleh mempunyai **satu penugasan aktif**.
- Menugaskan kepada pegawai baharu akan **menukar ganti** penugasan lama secara
  automatik; rekod lama kekal dalam sejarah.
- Entiti hanya boleh ditugaskan kepada **Pegawai Analisis**.
- Menugaskan kepada pegawai yang sama sekali lagi akan ditolak.

**Menarik balik penugasan**: klik **Tarik Balik** dan nyatakan sebab. Pegawai
berkenaan akan kehilangan akses kepada entiti tersebut serta-merta.

### 4.3 Menggerakkan entiti melalui workflow

Buka **Pemantauan → Kemajuan Analisis**, pilih entiti.

Entiti memasuki aliran kerja melalui **Penetapan Entiti**, apabila KB atau PPA
menandakan peringkat **1.1 Penerimaan Data**. Selepas itu setiap peringkat
dikendalikan pada halaman Kemajuan Analisis Entiti oleh peranan yang
memilikinya.

**5 peringkat utama**

| #   | Peringkat                               | Peranan  | Fasa        |
| --- | --------------------------------------- | -------- | ----------- |
| 1   | Penerimaan & Semakan Awal Data          |          |             |
| 1.1 | Penerimaan Data                         | KB / PPA | Semasa      |
| 1.2 | Pendaftaran Data                        | PPA      | Semasa      |
| 1.3 | Semakan Awal Data                       | PA       | Semasa      |
| 2   | Penyediaan & Pengesahan Data            | PA       | Semasa      |
| 3   | Analisis Data                           |          |             |
| 3.1 | Analisis Inventori Kriptografi          | PA       | Semasa      |
| 3.2 | Analisis Risiko Migrasi PQC             | —        | Akan datang |
| 4   | Penjanaan Laporan                       | —        | Akan datang |
| 5   | Semakan, Kelulusan & Penyerahan Laporan | —        | Akan datang |

**Maklumat yang direkod pada setiap peringkat**

| Peringkat | Medan                                                                         |
| --------- | ----------------------------------------------------------------------------- |
| 1.1       | Tarikh Terima · Status Borang Penerimaan Data · No. Rujukan Borang (PKD)       |
| 1.2       | Tarikh Terima · Status Borang Pendaftaran Data · No. Rujukan Borang (PKD)      |
| 1.3       | Tarikh Semakan · Status Borang Semakan Awal Data · No. Rujukan Borang (PKD)    |
| 2         | Tarikh Mula · Tarikh Tamat · Status Mastertable · Nama Fail                    |
| 3.1       | Tarikh Mula · Tarikh Tamat · Status Laporan Inventori · No. Rujukan Laporan (PKD) |

**Nilai Status Borang**

Medan *Status Borang* pada setiap peringkat (termasuk *Status Mastertable* dan
*Status Laporan Inventori Kriptografi*) menggunakan senarai yang sama:

| # | Status                 |
| - | ---------------------- |
| 1 | Belum Mula             |
| 2 | Dalam Proses           |
| 3 | Dalam Semakan          |
| 4 | Selesai                |
| 5 | Tidak Boleh Diteruskan |
| 6 | Tidak Berkaitan        |
| 7 | Telah Diserah          |

Senarai ini BERASINGAN daripada status peringkat (Belum Mula / Dalam Proses /
Selesai): yang itu menjejaki kedudukan peringkat dalam aliran kerja, yang ini
menjejaki keadaan borangnya.

**Peraturan peringkat**

- Peringkat mesti dilalui **berturutan**, termasuk sub-peringkat: 1.1 → 1.2 →
  1.3 → 2 → 3.1. Melangkau mana-mana satu ditolak.
- Peringkat hanya boleh ditandakan **Selesai** oleh peranan yang memilikinya.
- **Setiap No. Rujukan** dimasukkan oleh **PKD sahaja** — termasuk No. Rujukan
  Laporan peringkat 3.1, yang peringkatnya milik PA. Ia tidak menunggu giliran
  peringkat: nombor rujukan boleh direkodkan bila-bila masa sepanjang peringkat
  itu berjalan.
- Kemajuan diukur terhadap peringkat **fasa semasa** (1.1 hingga 3.1). Entiti
  menjadi **Siap** apabila kelima-limanya Selesai.
- Peringkat 3.2, 4 dan 5 **tidak menerima sebarang tindakan** dalam fasa ini.
- Setiap perubahan menyimpan tarikh dan nama pegawai dalam jejak audit.

### 4.4 Status Tiga Laporan

**Pemantauan → Status Tiga Laporan** memaparkan status bagi laporan Inventori,
Risiko PQC dan Kesiapsiagaan setiap entiti.

Halaman ini **paparan sahaja** — tiada status boleh diubah di sini. Setiap
status dikira daripada **Kemajuan Analisis Entiti**:

| Status            | Bila ia dipaparkan                                                |
| ----------------- | ----------------------------------------------------------------- |
| **Belum Bermula** | Entiti belum memasuki aliran kerja analisis                        |
| **Dalam Proses**  | Analisis sedang berjalan; laporan belum dihantar untuk semakan     |
| **Dalam Semakan** | Laporan sedang disemak PPA atau menunggu kelulusan Ketua Bahagian  |
| **Selesai**       | Ketua Bahagian telah menekan **Sahkan** pada laporan itu           |
| **N/A**           | Modul laporan itu belum tersedia dalam versi ini                   |

Dalam versi ini hanya **Laporan Inventori** mempunyai aliran kerja. **Risiko PQC**
dan **Kesiapsiagaan** dipaparkan sebagai **N/A** dan tidak dikira dalam sebarang
statistik papan pemuka.

Untuk menggerakkan status, gunakan tindakan pada **Kemajuan Analisis Entiti**
(PA **Hantar** → PPA **Hantar kepada KB** → KB **Sahkan**). Laporan yang
**Dikembalikan** kekal *Dalam Semakan* kerana ia masih berada dalam kitaran
PA → PPA → KB.

### 4.5 Jejak Audit

**Pemantauan → Jejak Audit** memaparkan setiap perubahan penting: penugasan,
peringkat workflow, draf, simpanan dapatan dan status laporan.

Tapis mengikut entiti, jenis tindakan, pengguna atau julat tarikh.

Rekod jejak audit **tidak boleh diubah atau dipadam** oleh sesiapa.

---

## 5. PANDUAN PEGAWAI ANALISIS

### 5.1 Senarai kerja anda

Selepas log masuk, anda dibawa terus ke **Analisis Inventori Kriptografi**.
Senarai ini hanya memaparkan **entiti yang ditugaskan kepada anda**.

Anda tidak mempunyai papan pemuka keseluruhan — ia disediakan untuk peranan
pengurusan sahaja.

### 5.2 Sebelum mengisi borang

Jalankan kerja analisis seperti biasa **di luar sistem**:

- semak borang semakan awal data
- semak Buku Kerja Migrasi PQC yang dihantar entiti
- lakukan pembersihan data dan analisis
- tentukan dapatan anda

Sistem **tidak** memerlukan anda memuat naik sebarang dokumen.

### 5.3 Mengisi borang dapatan

1. Pada senarai **Analisis Inventori Kriptografi**, pilih sektor dan entiti anda.
2. Klik **Isi Borang**.
3. Isi mengikut seksyen:

| Seksyen                       | Kandungan                                    |
| ----------------------------- | -------------------------------------------- |
| 1 · Maklumat Laporan          | Tarikh laporan, kod rujukan, status laporan  |
| 2 · Status Data Diterima      | Status penerimaan & kebolehgunaan Jadual 0–2 |
| 3 · Profil Sistem dan Aset    | Bilangan aset mengikut kategori              |
| 4 · Algoritma Kriptografi     | **Checkbox** algoritma yang digunakan        |
| 5 · Protokol Kriptografi      | Baris protokol (boleh tambah/buang)          |
| 6 · Pustaka dan Modul         | Baris pustaka                                |
| 7 · Maklumat Vendor           | Baris vendor                                 |
| 8 · Cadangan Tindakan Susulan | Pilihan daripada bank ayat rasmi             |
| 9 · Kesimpulan                | Pilihan daripada bank ayat rasmi             |

### 5.4 Checkbox algoritma — peraturan penting

Senarai algoritma mengikut kategori rujukan **AKSA MySEAL**.

| Keadaan checkbox    | Maksud                                                    |
| ------------------- | --------------------------------------------------------- |
| **Ditanda** ☑       | Entiti **menggunakan** algoritma tersebut                 |
| **Tidak ditanda** ☐ | Inventori entiti **tidak menggunakan** algoritma tersebut |

Menanda checkbox akan memaparkan medan **bilangan sistem/aset** dan
**pemerhatian** bagi algoritma tersebut.

Tanda pada label:

- <span>▲</span> — algoritma tidak lagi disyorkan
- **Q** — algoritma berisiko terhadap ancaman pengkomputeran kuantum

Sistem menggunakan pilihan ini untuk menjana kesimpulan laporan secara
automatik. **Jangan taip nama algoritma secara bebas** jika ia sudah ada dalam
senarai; gunakan medan "Lain-lain" hanya untuk algoritma yang tiada dalam
senarai.

### 5.5 Simpan draf dan sambung semula

Anda tidak perlu menyiapkan laporan dalam satu sesi.

- Klik **Simpan Draf** pada bila-bila masa. Draf disimpan **tanpa pengesahan
  penuh** — borang separa siap dibenarkan.
- Sistem juga **menyimpan draf secara automatik** setiap 3 minit apabila terdapat
  perubahan, dan apabila anda meninggalkan tab.
- Panel draf menunjukkan versi, masa simpanan terakhir dan seksyen yang telah
  diisi.
- Untuk menyambung: log masuk semula, buka semula borang entiti yang sama.
  **Semua nilai yang telah disimpan akan dipaparkan semula.**

> Jika anda cuba menutup halaman dengan perubahan yang belum disimpan, pelayar
> akan memberi amaran.

### 5.6 Menyiapkan laporan

Apabila semua dapatan telah dimasukkan:

1. Pastikan medan wajib diisi: **status laporan** dan **ringkasan status data**.
2. Tanda **Analisis selesai**.
3. Klik **Simpan Dapatan**.

Status laporan Inventori bagi entiti tersebut akan dinaikkan kepada
**Dalam Proses** secara automatik.

### 5.7 Pratonton dan menjana laporan

1. Buka menu **Laporan → Laporan Inventori**.
2. Pilih entiti anda, klik **Pratonton** untuk melihat laporan mengikut templat
   rasmi.
3. Klik **Muat Turun PDF** untuk menjana fail PDF rasmi dengan kepala (logo
   NACSA & PTPKM, tanda RAHSIA) dan kaki (kod rujukan, nombor muka surat).

Semak pratonton sebelum menjana PDF. Jika ada kesilapan, kembali ke borang,
betulkan dan simpan semula.

---

## 6. PANDUAN KETUA BAHAGIAN

- **Papan Pemuka** — gambaran keseluruhan kemajuan semua sektor dan entiti.
- **Kemajuan Analisis** — kedudukan setiap entiti.
- **Pusat Maklumat Entiti** — himpunan maklumat satu entiti.
- **Laporan Inventori** — pratonton dan muat turun laporan mana-mana entiti.
- **Jejak Audit** — rekod penuh perubahan.

> Tindakan **semakan, kelulusan dan penyerahan laporan** belum tersedia: ia
> milik peringkat 4 dan 5, yang prosesnya belum ditentukan (lihat Bahagian 8).

---

## 7. PANDUAN PENTADBIR SISTEM

Selain semua fungsi di atas:

**Pentadbiran → Pengguna** — cipta, sunting dan padam akaun pengguna serta
tetapkan peranan.

Semasa mencipta pengguna:

- **Nama pengguna** mesti unik — inilah kelayakan log masuk.
- Pilih **peranan** yang betul; peranan menentukan keseluruhan akses.
- Berikan kata laluan sementara dan minta pengguna menukarnya.

Untuk tugas pemasangan, sandaran dan penyelenggaraan, rujuk
`docs/ADMIN_GUIDE.md`.

---

## 8. BATASAN VERSI V1.0-RC1

| Perkara                                  | Status                                |
| ---------------------------------------- | ------------------------------------- |
| Serah laporan untuk semakan              | **Belum tersedia** (Fasa 10)          |
| Skrin semakan dan komen penyemak         | **Belum tersedia** (Fasa 10)          |
| Kelulusan / pemulangan laporan           | **Belum tersedia** (Fasa 10)          |
| Penilaian Risiko PQC                     | Modul akan datang (menu dilumpuhkan)  |
| Laporan Risiko                           | Modul akan datang (menu dilumpuhkan)  |
| Laporan Kesiapsiagaan                    | Modul akan datang (menu dilumpuhkan)  |
| Notifikasi e-mel                         | Tidak dalam skop                      |
| Muat naik dokumen dalam aliran pelaporan | Tidak diperlukan mengikut reka bentuk |

Aliran kerja fasa semasa berakhir pada peringkat **3.1 — Analisis Inventori
Kriptografi**. Peringkat **3.2**, **4** dan **5** wujud dalam struktur tetapi
tidak menerima sebarang tindakan sehingga prosesnya ditetapkan.

Laporan **Analisis Inventori Kriptografi** boleh dimuat turun sebagai PDF sebaik
peringkat 3.1 ditandakan Selesai.

---

## 9. MASALAH BIASA

| Masalah                                              | Punca dan penyelesaian                                                               |
| ---------------------------------------------------- | ------------------------------------------------------------------------------------ |
| "Anda tidak mempunyai akses kepada entiti ini" (403) | Entiti tersebut tidak ditugaskan kepada anda. Hubungi Pegawai Penyelaras.            |
| Dialihkan ke halaman log masuk semasa bekerja        | Sesi tamat tempoh (120 minit). Log masuk semula — draf yang telah disimpan kekal.    |
| "Terlalu banyak percubaan log masuk"                 | 5 percubaan gagal. Tunggu 60 saat.                                                   |
| Draf tidak muncul semasa disambung                   | Pastikan anda membuka entiti yang **sama** dan log masuk dengan akaun yang sama.     |
| Butang ubah peringkat tiada                          | Kawalan peringkat hanya untuk Penyelaras dan Pentadbir.                              |
| "Peringkat mesti dilalui secara berturutan"          | Anda cuba melompat peringkat. Maju satu peringkat pada satu masa.                    |
| "Sebab wajib diberikan"                              | Pengunduran peringkat memerlukan sebab.                                              |
| PDF tidak dijana                                     | Isu pelayan (komponen penjanaan PDF). Hubungi Pentadbir Sistem.                      |
| Papan pemuka tidak berubah                           | Angka dikira daripada rekod. Pastikan perubahan telah disimpan; muat semula halaman. |

Untuk masalah lain, hubungi Pentadbir Sistem dan sertakan: nama pengguna, masa
kejadian, entiti terlibat dan langkah yang dilakukan.
