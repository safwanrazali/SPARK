# CLAUDE.md — Peraturan Pembangunan RiskScorePlatform (SPARK)

Fail ini ialah rujukan tetap bagi setiap sesi Claude Code pada repositori ini.
Baca sepenuhnya sebelum menulis kod.

Bahasa domain projek ini ialah **Bahasa Melayu**: nama kelas, kaedah, pemboleh
ubah, partial dan komen semuanya dalam BM. Ikut konvensyen itu — jangan
memperkenalkan penamaan Inggeris pada kod baharu.

---

## 1. Seni bina

Laravel 13.8 / PHP 8.3. SQLite. Vite + SCSS. Tiada rangka kerja frontend.

```
app/
├── Console/Commands/       arahan artisan
├── Exceptions/             pengecualian domain
├── Http/
│   ├── Controllers/        + Admin/, Auth/
│   ├── Middleware/
│   └── Requests/           + Admin/, Auth/
├── Models/                 + Concerns/ (trait model)
├── Policies/
├── Providers/              AppServiceProvider — SEMUA Gate::define ada di sini
├── Services/               logik perniagaan
└── Support/                jadual takrifan + penolong tulen (tanpa keadaan)

resources/
├── css/                    hanya titik masuk
├── js/                     app.js (titik masuk) + modul kecil per ciri
├── scss/                   17 partial + app.scss (indeks @import)
│   └── laporan-print/      partial gaya cetakan laporan
└── views/
    ├── layouts/            app, header, sidebar
    ├── components/         komponen Blade <x-...>
    ├── admin/users/
    ├── analisis/           + partials/
    ├── audit/
    ├── auth/
    ├── dashboard/          + partials/
    ├── entiti/             + partials/
    ├── laporan/            + partials/ (DIKONGSI skrin+PDF), pdf/
    ├── profil/
    ├── status/
    └── workflow/           + partials/

scripts/                    operasi pelayan (bukan kod aplikasi)
├── deploy.sh               tarik + bina semula cache; SATU-SATUNYA cara menempatkan
├── backup-database.php     sandaran panas SQLite
├── restore-database.php    pemulihan dengan pengesahan
└── lib/

tests/
├── Concerns/               trait perkongsian senario (MelaluiAliranKerja)
├── Feature/                ujian HTTP + integrasi (32 fail)
└── Unit/                   (8 fail)
```

### Fail penempatan (deployment)

| Fail | Peranan |
|---|---|
| `.puppeteerrc.cjs` | Menetapkan cache Chrome ke `<projek>/.cache` supaya `www-data` DAN pengguna penyelenggara menyelesaikan laluan yang sama. Tanpanya, penjanaan PDF lulus pada CLI tetapi gagal dalam pelayar. |
| `scripts/deploy.sh` | Satu-satunya cara menempatkan. `git push` TIDAK mengemas kini pelayan. |
| `.env.production.example` | Templat pelayan; disalin kepada `.env`, bukan disunting terus. |

> **Cache aplikasi bersifat senyap.** Dengan `config:cache`, `route:cache` dan
> `view:cache` aktif, kod yang ditarik TIDAK berkuat kuasa sehingga cache dibina
> semula — tanpa sebarang ralat. `scripts/deploy.sh` sentiasa membinanya semula;
> jangan gantikan dengan `git pull` sahaja.

### Modul utama

| Modul | Controller | Servis |
|---|---|---|
| Papan pemuka | `DashboardController` | `DashboardStatistikService` + `DashboardKadEntitiService` + `DashboardTaburanService` |
| Kemajuan analisis | `KemajuanAnalisisController` | `KemajuanAnalisisService` + `KemajuanAnalisisGating` + `KemajuanAnalisisRingkasan` |
| Aliran kerja | `WorkflowController` | `WorkflowTransitionService`, `EntityAssignmentService` |
| Analisis inventori | `AnalisisInventoriController` | `AnalisisDraftService`, `AnalisisSimpananService` |
| Laporan | `LaporanController` | `LaporanPenyediaanService`, `LaporanSemakanService` |
| Catatan laporan | `LaporanCatatanController` | — (dasar: `LaporanCatatanPolicy`) |
| Audit | `AuditTrailController` | `AuditTrailService` |

### Sumber kebenaran tunggal

Jangan menduakan perkara ini — baca daripada tempatnya:

- **Struktur peringkat aliran kerja** → `App\Support\AliranKerja`
  (jadualnya dalam `App\Support\AliranKerjaDefinisi`; baca melalui `AliranKerja`).
- **Syarat medan peringkat** → `App\Support\SyaratPeringkat`.
- **Peraturan pengesahan medan peringkat** → `App\Support\PeraturanPeringkat`.
- **Syarat pendahulu peringkat** → `App\Services\KemajuanAnalisisGating`.
- **Capaian entiti mengikut peranan** → `App\Services\EntityAccessService`.
- **Gate kebenaran** → `AppServiceProvider::boot()`.
- **Pemasuk No. Rujukan** → Pegawai Kawalan Dokumen (PKD):
  `AliranKerja::PERANAN_RUJUKAN` + gate `record-stage-reference`. Pegawai
  Penyelaras Rekod (PPR) **tiada tugas khusus** dalam fasa ini (baca sahaja) —
  jangan kelirukan kedua-duanya.
- **Katalog algoritma / sektor** → `config/kriptografi.php`, `config/sektor.php`.
- **Kandungan laporan** → `resources/views/laporan/partials/` (dikongsi oleh
  pratonton skrin DAN PDF).

---

## 2. Peraturan saiz fail

```
Sasaran: ≤ 300 baris bagi setiap fail sumber.
```

300 baris ialah **sasaran kebolehselenggaraan, bukan alasan untuk
memecah-belahkan fail**. Jangan sekali-kali mencipta `bahagian-1`,
`bahagian-2`, `bahagian-3` yang tiada pemisahan tanggungjawab yang bermakna.

Keutamaan apabila keduanya berlanggar:

```
1. Kekalkan fungsi
2. Kekalkan UI/UX
3. Kekalkan integriti data
4. Kekalkan keselamatan/kebenaran
5. Kekalkan kontrak awam sedia ada
6. Perbaiki kebolehselenggaraan
7. Kurangkan saiz fail
8. Sasaran ≤300 baris
```

Fail yang **SENGAJA** melebihi 300 baris — jangan pecahkan tanpa sebab kukuh:

| Fail | Baris | Sebab |
|---|---|---|
| `resources/views/laporan/pdf/body.blade.php` | ~790 | Sebahagian besarnya `<style>` sebaris. Browsershot merender tanpa pelayan HTTP: tiada `@vite`, tiada manifes. Memindahkannya ke SCSS menghasilkan PDF tanpa gaya. |
| `app/Services/KemajuanAnalisisService.php` | ~1040 | Terasnya ialah SATU unit transaksi — "satu-satunya tempat status peringkat boleh berubah". Memecahkan aliran transaksi/audit merentas fail menjadikan invarian itu lebih sukar disemak. |
| `config/kriptografi.php`, `config/sektor.php` | 395 / 311 | Katalog data rata. |
| `database/migrations/*` | — | **Migrasi tidak pernah dipecahkan.** |
| `tests/Feature/*Test.php` (32 fail) | 300–1795 | Suite senario yang padu; memecahkannya menyerakkan persediaan kongsi dan menyukarkan pengesanan kegagalan. |

---

## 3. Peraturan Blade

- Halaman besar dipecahkan kepada `<modul>/partials/` dengan nama bermakna
  mengikut **tanggungjawab UI**, bukan mengikut kiraan baris.
- **`@php use ...;` TIDAK diwarisi oleh `@include`.** Setiap partial yang
  merujuk kelas dengan nama pendek MESTI mengulang `use`-nya sendiri. Ini
  punca kegagalan paling biasa semasa memecahkan paparan.
- `@include` mewarisi **pemboleh ubah** induk; pemboleh ubah yang ditakrifkan
  DI DALAM partial TIDAK kembali kepada induk. Blok `@php` yang mentakrifkan
  penutupan paparan mesti kekal dalam templat induk.
- Jangan memecahkan tag HTML merentas sempadan partial. Satu partial membuka
  dan menutup elemennya sendiri.
- Elakkan logik perniagaan dalam Blade. Jika pengiraan perlu dipindahkan ke
  servis, **pindahkan tanpa mengubahnya** dan sahkan keluaran sebelum/selepas.
- Komponen `<x-...>` untuk elemen boleh guna semula merentas modul; `partials/`
  untuk bahagian satu halaman.

### Laporan — berhati-hati

`resources/views/laporan/partials/` dikongsi **bait demi bait** oleh pratonton
skrin (`laporan/inventori.blade.php`) dan badan PDF (`laporan/pdf/body.blade.php`).

- `$widgetCatatan` ditetapkan SEKALI oleh setiap templat induk: benar untuk
  skrin, **palsu untuk PDF**. Catatan KB/PPA tidak pernah masuk ke dalam PDF.
- Sebarang perubahan pada partial ini mengubah KEDUA-DUA saluran. Sahkan
  dengan merender kedua-duanya sebelum dan selepas, lalu bandingkan HTML.
- Jangan mengubah struktur jadual, `<colgroup>`, pemisah halaman atau kepala/
  kaki: laporan ini dicetak dan ditandatangani.

---

## 4. Peraturan controller

Controller melakukan ini sahaja:

```
Request → Authorize → Validate → panggil servis/action → Response
```

- Logik perniagaan yang besar → `app/Services/`.
- Jangan cipta servis hanya untuk memindahkan 10 baris.
- Beberapa ujian mencapai kaedah `private` controller melalui **Reflection**
  (contohnya `LaporanController::siapkanData()`, `::pengesahan()`). Semak
  `grep -rn "ReflectionMethod" tests/` sebelum membuang atau menamakan semula
  kaedah private controller.

---

## 5. Peraturan servis

- Satu tanggungjawab jelas bagi setiap servis jika praktikal.
- Elakkan "god service". Jika satu servis mengandungi kluster tanggungjawab
  yang berasingan (pertanyaan / syarat / ringkasan / mutasi), asingkan yang
  **tulen dan tanpa keadaan** terlebih dahulu — itu yang paling selamat.
- Kekalkan kaedah awam sedia ada sebagai pembungkus yang mewakilkan, supaya
  pemanggil dan ujian tidak berubah.
- Elakkan kebergantungan berpusing: hantar data yang telah dimuatkan sebagai
  parameter, bukan menyuntik servis induk kembali ke dalam kolaboratornya.

---

## 6. Peraturan JavaScript

- `resources/js/app.js` ialah satu-satunya titik masuk Vite. Ciri yang
  berdiri sendiri diletakkan dalam modulnya sendiri (contoh:
  `catatan-seksyen.js`) dan diimport dari app.js, supaya app.js kekal
  di bawah 300 baris.
- Bootstrap diimport sebagai **ESM** (`import "bootstrap"` ->
  `dist/js/bootstrap.esm.js`), jadi `window.bootstrap` TIDAK wujud.
  Jangan `import ... from "bootstrap/js/dist/<komponen>"` untuk mengawal
  elemen yang sudah dipacu oleh data-api: ia menghasilkan salinan kedua
  kelas itu dengan simpanan instance tersendiri. Pacu data-api sedia ada
  (contohnya klik butang togolnya) atau dedahkan instance secara sedar.
- Skrip sebaris dalam Blade (contohnya `analisis/partials/skrip.blade.php`)
  **kekal sebaris**. Memindahkannya ke modul Vite mengubah masa pelaksanaan
  (defer modul) dan memerlukan binaan — itu perubahan tingkah laku.
- Kekalkan: pemilih DOM, id elemen, kelas CSS, pendengar peristiwa, hujung
  API, pengendalian CSRF, masa debounce, pemulaan pustaka pihak ketiga.
- Jangan memperkenalkan rangka kerja frontend baharu tanpa permintaan jelas.

---

## 7. Peraturan CSS

- SCSS dipecahkan kepada partial mengikut modul dan diimport oleh
  `resources/scss/app.scss`.
- **Susunan `@import` bermakna.** `laporan-pratonton` MESTI selepas
  `laporan-print` (kedua-duanya mentakrifkan `.laporan-rasmi` pada kekhususan
  yang sama). Jangan susun semula secara abjad.
- Sahkan perubahan SCSS dengan `npm run build` dan bandingkan CSS terhasil.
- Jangan mengubah nilai gaya semasa menyusun semula fail.

---

## 8. Peraturan UI/UX

- **Jangan mengubah UI/UX melainkan diminta secara jelas.** Susun atur, jarak,
  tipografi, warna, ikon, butang, kad, jadual, borang, modal, navigasi, keadaan
  kosong, keadaan memuat, mesej ralat dan teks semuanya kekal.
- Komponen boleh guna semula MESTI mengekalkan sistem reka bentuk sedia ada.
- Memecahkan templat tidak boleh mengubah HTML terhasil.

---

## 9. Peraturan refaktor

1. Refaktor **mengekalkan tingkah laku**. Titik.
2. Jangan mengubah peraturan perniagaan semasa menyusun semula kod.
3. Rekod garis dasar ujian **sebelum** memulakan; bezakan kegagalan sedia ada
   daripada kegagalan baharu.
4. Sahkan dengan membandingkan keluaran sebenar, bukan dengan membaca sahaja:
   render HTML sebelum/selepas, `npm run build` + banding CSS, buang lajur
   data servis sebelum/selepas.
5. Semak `php artisan route:list` dan gate tidak berubah.
6. Sifar perubahan pangkalan data untuk kerja penyusunan kod.
7. Jangan mengubah suai ujian untuk membuatkannya lulus.

### Garis dasar ujian semasa

```
php artisan test
→ 772 ujian, 763 lulus, 8 gagal, 1 ralat
```

Sembilan masalah SEDIA ADA (bukan regresi — jangan andaikan kod anda puncanya):

| Ujian | Isu |
|---|---|
| `KemajuanAnalisisAliranTest::test_muat_turun_ditolak_sebelum_peringkat_analisis_selesai:1427` | jangkaan 403, dapat 200 |
| `RbacMatriksTest::test_muat_turun_ditolak_sebelum_peringkat_analisis_selesai:356` | jangkaan 403, dapat 200 |
| `Phase12IntegrationTest::test_pusat_maklumat_entiti_memaparkan_hasil_semua_modul:572` | penegasan HTML |
| `Phase5EntityDetailTest::test_halaman_memaparkan_kesemua_seksyen_yang_ditetapkan:108` | penegasan HTML |
| `Phase5EntityDetailTest::test_halaman_memaparkan_stepper_lima_peringkat_utama:147` | penegasan HTML |
| `Phase5EntityDetailTest::test_halaman_memaparkan_sejarah_workflow_dan_penugasan:199` | penegasan HTML |
| `DashboardKadEntitiTest::test_papan_pemuka_memaparkan_tajuk_dan_nota_kad_yang_dikemas_kini:472` | penegasan HTML — teks kad diubah tanpa ujian dikemas kini |
| `Phase7DashboardTest::test_papan_pemuka_kosong_memaparkan_keadaan_kosong:683` | penegasan HTML — teks keadaan kosong diubah tanpa ujian dikemas kini |
| `PenomboranHalamanTest::test_jadual_sejarah_dinomborkan_sepuluh_baris` (set "pusat maklumat entiti"):81 | ralat: `Undefined array key "sejarah"` |

**Dua kegagalan papan pemuka terakhir** berpunca daripada commit `53d451c`
(*"improve text clarity in … dashboard views"*): teks kad diubah, ujiannya tidak.
Contohnya ujian menjangkakan `Buku Kerja MPQ Diterima` sedangkan paparan hanya
mengandungi teks itu di dalam KOMEN Blade (huruf kecil), jadi penegasan itu
tidak mungkin lulus. Ia bukan kecacatan produk — ujian perlu diselaraskan
dengan teks semasa, atau teks dikembalikan. Putuskan secara sedar; jangan
sekadar menukar ujian supaya hijau.

> **Ujian PDF boleh MELANGKAU secara senyap.**
> `Phase12IntegrationTest::test_penjanaan_laporan_menghasilkan_fail_pdf` melangkau
> apabila Chrome tiada, dan PHPUnit tetap melaporkan `passed`. Langkauan di sini
> bermakna muat turun PDF GAGAL untuk pengguna. Pasang pelayar dahulu:
> `npx puppeteer browsers install chrome-headless-shell` (lihat `.puppeteerrc.cjs`).

**Ujian goyah (flaky) yang diketahui:**
`TetapSemulaKataLaluanTest::test_kata_laluan_sementara_cukup_kuat` gagal kira-kira
**1 daripada 2,350 larian**. `Str::password(16, symbols: false)` menjamin
sekurang-kurangnya satu HURUF tetapi bukan satu huruf besar DAN satu huruf
kecil. Ini bukan regresi — jalankan semula untuk mengesahkan.

---

## 10. Sebelum menulis kod baharu

- **Sebelum mencipta fail baharu**, semak sama ada komponen/servis/modul sedia
  ada boleh diguna semula.
- **Sebelum menambah kod pada fail sedia ada**, semak kiraan barisnya dan
  tanggungjawabnya. `wc -l <fail>`.
- **Jangan biarkan fail melebihi ~300 baris** apabila pengekstrakan yang
  bermakna masih mungkin.
- Fungsi baharu MESTI mengikut seni bina sedia ada di atas.
- **Jangan menduakan** komponen atau logik perniagaan. Semak "Sumber kebenaran
  tunggal" (§1).
- **Jangan mengubah UI/UX** melainkan diminta.
- **Jangan mengubah fungsi sedia ada** semasa melakukan penyusunan kod.

---

## 11. Kontrak awam — jangan ubah

```
Nama route            Struktur URL          Tandatangan kaedah controller
Endpoint API          Parameter request     Struktur respons
Atribut model         Nama lajur DB         Nama pemboleh ubah Blade
Kontrak peristiwa JS  id/kelas elemen       Nilai status sedia ada
```

Kaedah dalaman boleh direfaktor **setelah semua pemanggil disemak**
(termasuk paparan Blade dan ujian).

---

## 12. Pangkalan data

- `database/database.sqlite` mengandungi kerja pengguna sebenar. **Jangan
  sentuh.** Sahkan pada salinan berpagar.
- Ujian menggunakan SQLite `:memory:` (lihat `phpunit.xml`) — terasing
  sepenuhnya dan selamat.
- Jangan ubah skema, migrasi, kunci asing, indeks atau perhubungan untuk kerja
  penyusunan kod.

---

## 13. Alatan

```bash
composer test          # php artisan test
php artisan test --filter=NamaUjian
npm run build          # binaan aset produksi
npm run dev            # vite mod pembangunan
composer dev           # server + queue + pail + vite serentak
```

**Laravel Pint dipasang tetapi kod sedia ada TIDAK mematuhinya.** Jangan
jalankan Pint pada fail yang anda ubah — ia akan mencipta hingar diff yang
besar dan tidak berkaitan.
