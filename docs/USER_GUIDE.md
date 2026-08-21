# PANDUAN PENGGUNA

## Sistem Pemantauan & Pelaporan Analisis Data Migrasi PQC — V1.0-RC1

---

## 1. APA YANG SISTEM INI LAKUKAN

Sistem ini **memantau** proses analisis data migrasi PQC dan **menyokong
penjanaan laporan** hasil analisis tersebut.

| Sistem ini **melakukan**                                   | Sistem ini **tidak** melakukan                  |
| ---------------------------------------------------------- | ----------------------------------------------- |
| Merekod kedudukan setiap entiti dalam 7 peringkat workflow | Menjalankan analisis PQC secara automatik       |
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
| Pegawai Penyelaras Rekod    |      ✗       |             ✗             |        ✗        |      ✗      |      ✗       |      ✗      |
| Pegawai Kawalan Dokumen     |      ✗       |             ✗             |        ✗        |      ✗      |      ✗       |      ✗      |
| Ketua Bahagian              |      ✓       |             ✓             |        ✗        |      ✗      |      ✗       |      ✓      |
| Timbalan Pengarah II        |      ✓       |             ✓             |        ✗        |      ✗      |      ✗       |      ✗      |

> Pegawai Kawalan Dokumen dan Pegawai Penyelaras Rekod telah didaftarkan sebagai
> peranan, tetapi kebenaran sebenar mereka **belum ditetapkan** sebagai
> peraturan perniagaan. Buat masa ini mereka tiada akses entiti.
>
> Timbalan Pengarah II juga belum dimuktamadkan; buat sementara ia diberi
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
- Taburan entiti merentas 7 peringkat workflow
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

1. Klik **Daftar dalam workflow** untuk memulakan pada peringkat 1.
2. Gunakan **Kemas Kini Peringkat** untuk maju **satu peringkat pada satu masa**.
3. Gunakan **Kemas Kini Status** untuk menukar status dalam peringkat semasa
   (Belum Bermula / Dalam Proses / Siap) tanpa menukar peringkat.

**7 peringkat**

| #   | Peringkat                     |
| --- | ----------------------------- |
| 1   | Penerimaan & Pendaftaran Data |
| 2   | Semakan Awal Data             |
| 3   | Penyediaan & Pengesahan Data  |
| 4   | Pelaksanaan Analisis          |
| 5   | Penjanaan Laporan             |
| 6   | Semakan & Kelulusan           |
| 7   | Penyerahan & Penutupan        |

**Peraturan peralihan**

- Peringkat mesti dilalui **berturutan** — melompat (contoh 2 → 5) ditolak.
- **Mengundur** ke peringkat sebelumnya dibenarkan, tetapi **sebab wajib
  diberikan** dan direkodkan dalam jejak audit.
- Setiap perubahan menyimpan tarikh status dan nama pegawai yang mengemas kini.

### 4.4 Status Tiga Laporan

**Pemantauan → Status Tiga Laporan** memaparkan status bagi laporan Inventori,
Risiko PQC dan Kesiapsiagaan setiap entiti. Klik status untuk mengitarnya:

`Belum Bermula → Dalam Proses → Siap → Belum Bermula`

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

> Dalam V1.0-RC1, tindakan **semakan dan kelulusan laporan** belum tersedia
> (lihat Bahagian 8). Peringkat 6 workflow digerakkan oleh Pegawai Penyelaras.

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

Semasa menunggu Fasa 10, peringkat **6 — Semakan & Kelulusan** dikendalikan
sebagai peringkat workflow biasa: Pegawai Penyelaras memajukan entiti ke
peringkat 6, dan mengundurkannya ke peringkat 5 **berserta sebab** jika laporan
perlu dibetulkan.

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
