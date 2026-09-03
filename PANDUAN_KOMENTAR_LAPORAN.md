# Panduan Komentar — Laporan Analisis Inventori Kriptografi

Komentar ialah mekanisme **maklum balas + pengakuan**, bukan kitaran kelulusan.

```
KB / PPA menulis komentar pada satu seksyen Borang Input
        ↓
PA melihat komentar itu
        ↓
PA mengambil tindakan yang perlu
        ↓
PA klik ✓ Tindakan Diambil
        ↓
KB / PPA melihat bahawa tindakan telah diambil
```

Tiada penyerahan untuk semakan, tiada kelulusan, tiada penolakan, tiada
pemulangan, dan **tiada modul notifikasi**. "Tindakan Diambil" ialah satu
STATUS pada komentar, bukan pemberitahuan.

---

## 1. Siapa boleh buat apa

| Peranan | Lihat komentar | Tulis | Sunting sendiri | Padam sendiri | Tanda Tindakan Diambil |
| ------- | -------------- | ----- | --------------- | ------------- | ---------------------- |
| PA      | ✓ semua        | ✗     | —               | ✗             | ✓                      |
| KB      | ✓ semua        | ✓     | ✓               | ✓             | ✗                      |
| PPA     | ✓ semua        | ✓     | ✓               | ✓             | ✗                      |
| PS      | ✗              | ✗     | ✗               | ✗             | ✗                      |
| TPII    | ✗              | ✗     | ✗               | ✗             | ✗                      |
| PPR     | ✗              | ✗     | ✗               | ✗             | ✗                      |
| PKD     | ✗              | ✗     | ✗               | ✗             | ✗                      |

**Penglihatan bukan pemilikan.** KB melihat komentar KB lain DAN komentar PPA;
PPA melihat komentar PPA lain DAN komentar KB; PA melihat kesemuanya. Yang
terhad kepada pengarang hanyalah **menyunting dan memadam**.

Contoh:

```
KB-1 menulis Komentar A
  KB-2 boleh LIHAT      PPA boleh LIHAT      PA boleh LIHAT
  KB-1 boleh PADAM      KB-2 tidak boleh     PPA tidak boleh     PA tidak boleh
```

Kesemua kebenaran ini dikuatkuasakan di **pelayan** (route middleware +
`LaporanKomentarPolicy`). Menyembunyikan butang bukan kawalan: memanggil
laluan secara terus dengan id komentar orang lain menerima **403**.

Setiap operasi juga tertakluk kepada kawalan akses entiti sedia ada. Pegawai
Analisis hanya boleh menyentuh komentar bagi entiti yang ditugaskan
kepadanya; menukar `agency_code` atau id komentar dalam permintaan tidak
membuka entiti lain.

---

## 2. Seksyen komentar

Komentar ditambat pada **sembilan seksyen Borang Input**, supaya PA tahu
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
   komentar: **kuning** jika ada yang masih terbuka, **hijau** jika semuanya
   telah ditindak.
3. Klik butang itu untuk membuka panel komentar seksyen tersebut.
4. Tulis komentar (maksimum 2000 aksara) dan hantar.
5. Komentar sendiri boleh disunting (✏) atau dipadam (✕) pada bila-bila masa.

### PA

1. Buka laporan yang sama — komentar muncul pada seksyen yang berkenaan.
2. Buat pembetulan yang perlu melalui **Borang Input**.
3. Klik **✓ Tindakan Diambil** pada komentar tersebut.

Selepas itu komentar dipaparkan seperti ini kepada semua pihak:

```
KB Satu (KB) · 03/09/2026 10:20
"Sila semak semula maklumat pemilik sistem."

[✓ Tindakan Diambil]  Tindakan oleh: PA Ahmad · 03/09/2026 10:32 AM
```

Teks asal **tidak diubah** dan komentar **tidak dipadam** — ia kekal dalam
sejarah bersama identiti PA dan cap masa tindakan.

Jika PA tersilap tanda, **Batal tanda** mengembalikan komentar kepada
*Terbuka*. Fungsi ini milik PA sahaja dan bukan penolakan maklum balas.

---

## 4. Status komentar vs status peringkat 3.1

Dua perkara yang **berasingan sepenuhnya**:

```
Komentar        :  Terbuka        →  Tindakan Diambil
Peringkat 3.1   :  Belum Selesai  →  Selesai
```

Komentar **tidak menyekat** peringkat 3.1 dan **tidak mengubah** statusnya.
PA tetap boleh menandakan peringkat 3.1 sebagai *Selesai* walaupun terdapat
komentar yang masih terbuka. Lencana dan kiraan komentar bersifat maklumat
semata-mata.

---

## 5. PDF

Komentar **tidak sekali-kali** muncul dalam PDF Laporan Analisis Inventori
Kriptografi — tidak teks komentar, tidak nama pengarang, tidak status, tidak
maklumat "Tindakan Diambil". PDF mengandungi kandungan laporan rasmi sahaja.

---

## 6. Jejak audit

Setiap tindakan komentar direkodkan dalam jejak audit sedia ada: komentar
ditambah, dikemas kini, dipadam, ditanda *Tindakan Diambil* dan tandanya
dibatalkan.

Mengikut konvensyen jejak audit aplikasi, ia merekod **perubahan, bukan
kandungan**: seksyen, pemilik, pelaku, peranan, cap masa dan peralihan status
disimpan — **teks komentar tidak**.

---

## 7. Masalah biasa

**Saya tidak nampak butang 💬 pada laporan.**
Modul komentar terbuka kepada PA, KB dan PPA sahaja. Peranan lain boleh
membuka laporan tetapi tidak modul komentarnya.

**Saya tidak nampak butang padam pada komentar seseorang.**
Betul — hanya pengarang boleh memadam komentarnya sendiri.

**Saya PA, tetapi tidak boleh menyunting komentar.**
Juga betul. PA hanya menanda *Tindakan Diambil*; maklum balas asal mesti
kekal seperti yang ditulis oleh pengomen.

**Komentar saya hilang.**
Ia telah dipadam oleh pengarangnya sendiri. Pemadaman bersifat lembut, jadi
rekodnya kekal dalam pangkalan data dan jejak audit.
