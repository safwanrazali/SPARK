# Panduan Catatan — Laporan Analisis Inventori Kriptografi

Catatan ialah mekanisme **maklum balas + pengakuan**, bukan kitaran kelulusan.

```
KB / PPA menulis catatan pada satu seksyen Borang Input
        ↓
PA melihat catatan itu
        ↓
PA mengambil tindakan yang perlu
        ↓
PA klik ✓ Tindakan Diambil
        ↓
KB / PPA melihat bahawa tindakan telah diambil
```

Tiada penyerahan untuk semakan, tiada kelulusan, tiada penolakan, tiada
pemulangan, dan **tiada modul notifikasi**. "Tindakan Diambil" ialah satu
STATUS pada catatan, bukan pemberitahuan.

---

## 1. Siapa boleh buat apa

| Peranan | Lihat catatan | Tulis | Sunting sendiri | Padam sendiri | Tanda Tindakan Diambil |
| ------- | -------------- | ----- | --------------- | ------------- | ---------------------- |
| PA      | ✓ semua        | ✗     | —               | ✗             | ✓                      |
| KB      | ✓ semua        | ✓     | ✓               | ✓             | ✗                      |
| PPA     | ✓ semua        | ✓     | ✓               | ✓             | ✗                      |
| PS      | ✗              | ✗     | ✗               | ✗             | ✗                      |
| TPII    | ✗              | ✗     | ✗               | ✗             | ✗                      |
| PPR     | ✗              | ✗     | ✗               | ✗             | ✗                      |
| PKD     | ✗              | ✗     | ✗               | ✗             | ✗                      |

**Penglihatan bukan pemilikan.** KB melihat catatan KB lain DAN catatan PPA;
PPA melihat catatan PPA lain DAN catatan KB; PA melihat kesemuanya. Yang
terhad kepada pengarang hanyalah **menyunting dan memadam**.

Contoh:

```
KB-1 menulis Catatan A
  KB-2 boleh LIHAT      PPA boleh LIHAT      PA boleh LIHAT
  KB-1 boleh PADAM      KB-2 tidak boleh     PPA tidak boleh     PA tidak boleh
```

Kesemua kebenaran ini dikuatkuasakan di **pelayan** (route middleware +
`LaporanCatatanPolicy`). Menyembunyikan butang bukan kawalan: memanggil
laluan secara terus dengan id catatan orang lain menerima **403**.

Setiap operasi juga tertakluk kepada kawalan akses entiti sedia ada. Pegawai
Analisis hanya boleh menyentuh catatan bagi entiti yang ditugaskan
kepadanya; menukar `agency_code` atau id catatan dalam permintaan tidak
membuka entiti lain.

---

## 2. Seksyen catatan

Catatan ditambat pada **sembilan seksyen Borang Input**, supaya PA tahu
dengan tepat bahagian mana yang perlu diberi perhatian:

| Kunci          | Seksyen                        |
| -------------- | ------------------------------ |
| `maklumat`     | 1 · Maklumat Laporan           |
| `data_status`  | 2 · Status Data Diterima       |
| `profil`       | 3 · Profil Sistem dan Aset     |
| `algoritma`    | 4 · Algoritma Kriptografi      |
| `protokol`     | 5 · Protokol Kriptografi       |
| `pustaka`      | 6 · Pustaka dan Modul          |
| `vendor`       | 7 · Maklumat Vendor            |
| `tindakan`     | 8 · Cadangan Tindakan Susulan  |
| `kesimpulan`   | 9 · Kesimpulan                 |

Senarai ini datang terus daripada `App\Support\SeksyenAnalisis` — nama
seksyen selain daripada sembilan ini ditolak oleh pelayan.

---

## 3. Cara menggunakannya

### KB / PPA

1. Buka **Laporan Inventori Kriptografi** bagi entiti berkenaan.
2. Setiap tajuk seksyen mempunyai butang 💬. Lencananya menunjukkan bilangan
   catatan: **kuning** jika ada yang masih terbuka, **hijau** jika semuanya
   telah ditindak.
3. Klik butang itu untuk membuka panel catatan seksyen tersebut.
4. Tulis catatan (maksimum 2000 aksara) dan hantar.
5. Catatan sendiri boleh disunting (✏) atau dipadam (✕) pada bila-bila masa.

### PA

1. Buka laporan yang sama — catatan muncul pada seksyen yang berkenaan.
2. Buat pembetulan yang perlu melalui **Borang Input**.
3. Klik **✓ Tindakan Diambil** pada catatan tersebut.

Selepas itu catatan dipaparkan seperti ini kepada semua pihak:

```
KB Satu (KB) · 03/09/2026 10:20
"Sila semak semula maklumat pemilik sistem."

[✓ Tindakan Diambil]  Tindakan oleh: PA Ahmad · 03/09/2026 10:32 AM
```

Teks asal **tidak diubah** dan catatan **tidak dipadam** — ia kekal dalam
sejarah bersama identiti PA dan cap masa tindakan.

Jika PA tersilap tanda, **Batal tanda** mengembalikan catatan kepada
*Terbuka*. Fungsi ini milik PA sahaja dan bukan penolakan maklum balas.

---

## 4. Status catatan vs status peringkat 3.1

Dua perkara yang **berasingan sepenuhnya**:

```
Catatan        :  Terbuka        →  Tindakan Diambil
Peringkat 3.1   :  Belum Selesai  →  Selesai
```

Catatan **tidak menyekat** peringkat 3.1 dan **tidak mengubah** statusnya.
PA tetap boleh menandakan peringkat 3.1 sebagai *Selesai* walaupun terdapat
catatan yang masih terbuka. Lencana dan kiraan catatan bersifat maklumat
semata-mata.

---

## 5. PDF

Catatan **tidak sekali-kali** muncul dalam PDF Laporan Analisis Inventori
Kriptografi — tidak teks catatan, tidak nama pengarang, tidak status, tidak
maklumat "Tindakan Diambil". PDF mengandungi kandungan laporan rasmi sahaja.

---

## 6. Jejak audit

Setiap tindakan catatan direkodkan dalam jejak audit sedia ada: catatan
ditambah, dikemas kini, dipadam, ditanda *Tindakan Diambil* dan tandanya
dibatalkan.

Mengikut konvensyen jejak audit aplikasi, ia merekod **perubahan, bukan
kandungan**: seksyen, pemilik, pelaku, peranan, cap masa dan peralihan status
disimpan — **teks catatan tidak**.

---

## 7. Masalah biasa

**Saya tidak nampak butang 💬 pada laporan.**
Modul catatan terbuka kepada PA, KB dan PPA sahaja. Peranan lain boleh
membuka laporan tetapi tidak modul catatannya.

**Saya tidak nampak butang padam pada catatan seseorang.**
Betul — hanya pengarang boleh memadam catatannya sendiri.

**Saya PA, tetapi tidak boleh menyunting catatan.**
Juga betul. PA hanya menanda *Tindakan Diambil*; maklum balas asal mesti
kekal seperti yang ditulis oleh pengomen.

**Catatan saya hilang.**
Ia telah dipadam oleh pengarangnya sendiri. Pemadaman bersifat lembut, jadi
rekodnya kekal dalam pangkalan data dan jejak audit.
