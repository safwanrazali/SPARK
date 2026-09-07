<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    {{--
        CSS SENGAJA DITERAP DI SINI, BUKAN DALAM SCSS.

        Dokumen ini diserahkan kepada Browsershot::html() dalam
        LaporanController::unduh() dan dipaparkan oleh Chrome tanpa tanpa
        kehadiran pelayan HTTP. Tiada @vite, tiada manifes dan tiada helaian
        gaya terkumpul yang boleh dicapai, jadi gaya laporan mesti dibawa
        bersama dokumen. Memindahkannya ke resources/scss/ akan menghasilkan
        PDF tanpa gaya sama sekali.

        Warna, sempadan, lebar lajur dan pemisah halaman KEKAL SELARAS dengan
        blok .laporan-rasmi dalam resources/scss/laporan-print.scss (sempadan
        #333, th #eff1f5, biru #1f6091, kelabu #ededed).

        TIPOGRAFI SENGAJA BERBEZA dan TIDAK boleh disalin balik ke SCSS:
        PDF sahaja menggunakan Aptos (dibenamkan oleh laporan.pdf.fonts) pada
        1rem untuk seluruh kandungan laporan dan 1.333rem untuk tiga baris
        sepanduk tajuk. Paparan WebView kekal pada fon dan saiz sedia ada —
        menukar laporan-print.scss akan mengubah pratonton skrin.

        Perbezaan lain hanya pemilih: fail di sini menggunakan pemilih elemen
        kerana dokumen ini hanya mengandungi laporan, manakala SCSS mesti
        menyaringnya di bawah .laporan-rasmi supaya tidak bocor ke aplikasi.
    --}}

    {{-- @font-face Aptos terbenam (base64). MESTI mendahului <style> di bawah
         supaya fon telah diisytiharkan sebelum peraturan yang menggunakannya. --}}
    @include('laporan.pdf.fonts')

    <style>
        body {
            /* Aptos dibenamkan sebagai data URI oleh laporan.pdf.fonts; Arial
               hanya sandaran jika fail fon hilang daripada public/fonts/aptos.
               Diisytiharkan pada <body> sahaja supaya seluruh kandungan
               laporan mewarisinya — kepala/kaki halaman ialah dokumen
               berasingan (laporan.pdf.header / laporan.pdf.footer) yang
               dilukis oleh Chrome dan TIDAK tersentuh oleh peraturan ini. */
            font-family: 'Aptos', Arial, sans-serif;
            /* 1rem = 16px (saiz akar lalai dokumen ini; tiada html{font-size}
               diisytiharkan). Semua kandungan laporan 1rem; hanya tiga baris
               sepanduk tajuk 1.333rem. */
            font-size: 1rem;
            line-height: 1.6;
            color: #111;
            /* Jangan letak margin atas di sini: margin pada <body> hanya
               digunakan SEKALI pada permulaan aliran kandungan, jadi ia
               menolak muka surat pertama sahaja dan muka surat kedua ke
               atas akan melekat pada header. Jarak header->kandungan
               dikawal oleh margin atas halaman dalam
               LaporanController::unduh(). */
            margin: 0;
        }

        h1 {
            font-size: 1rem;
            text-transform: uppercase;
            text-align: center;
            font-weight: 800;
        }

        h2 {
            font-size: 1rem;
            text-transform: uppercase;
            font-weight: 700;
            border-bottom: 2px solid #111;
            padding-bottom: 4px;
            margin: 20px 0 8px;
        }

        p {
            text-align: justify;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        th,
        td {
            border: 1px solid #333;
            padding: 5px 8px;
            vertical-align: top;
            font-size: 1rem;
        }

        th {
            background: #eff1f5;
            text-align: left;
        }

        /* Kelas berikut menggantikan atribut gaya sebaris pada elemen di bawah. */
        .penafian {
            font-size: 1rem;
        }

        /* Pengenalan laporan — sepanduk tajuk + jadual maklumat laporan.
           SALINAN blok `.laporan-id` dalam resources/scss/laporan-print.scss;
           kekalkan warna dan bentuk selaras (biru #1f6091, kelabu #ededed,
           sempadan putih). SAIZ FON TIDAK diselaraskan: PDF menggunakan
           1.333rem untuk tajuk dan baris sepanduk, 1rem untuk sel jadual.

           Setiap pemilih berkembar `.laporan-id` + kelas anak supaya ia
           mengatasi pemilih elemen h1/p/table/th/td di atas. */
        .laporan-id .laporan-id__banner {
            background: #1f6091;
            padding: 16px 20px;
            margin: 0 0 14px;
            text-align: center;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .laporan-id .laporan-id__tajuk,
        .laporan-id .laporan-id__baris {
            margin: 0;
            color: #ffffff;
            font-weight: 800;
            text-transform: uppercase;
            text-align: center;
            letter-spacing: 0.03em;
            line-height: 1.35;
            /* Nama sektor/entiti panjang membalut, bukan melimpah. */
            overflow-wrap: break-word;
            word-wrap: break-word;
        }

        .laporan-id .laporan-id__tajuk {
            font-size: 1.333rem;
        }

        .laporan-id .laporan-id__baris {
            margin-top: 6px;
            font-size: 1.333rem;
            font-weight: 700;
        }

        .laporan-id .laporan-id__jadual {
            width: 100%;
            /* Mengunci lajur 32%/68% supaya nilai panjang membalut dalam sel
               dan jadual tidak melebihi lebar kandungan A4. */
            table-layout: fixed;
            border-collapse: collapse;
            margin: 0 0 18px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .laporan-id .laporan-id__label,
        .laporan-id .laporan-id__nilai {
            border: 1px solid #fff;
            padding: 7px 10px;
            /* Tiada height tetap — sel meninggi mengikut teks. */
            vertical-align: middle;
            font-size: 1rem;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }

        .laporan-id .laporan-id__label {
            width: 32%;
            background: #1f6091;
            color: #ffffff;
            font-weight: 700;
            text-transform: uppercase;
            text-align: left;
        }

        .laporan-id .laporan-id__nilai {
            background: #ededed;
            color: #111;
            font-weight: 400;
        }

        /* Seksyen bergaya templat baharu — bar tajuk biru muda + perenggan.
           SALINAN blok `.laporan-seksyen` dalam resources/scss/laporan-print.scss;
           kekalkan warna selaras (bar #deeaf6, teks #1f6091; saiz fon PDF 1rem,
           perenggan line-height 1.55).

           Berkembar `.laporan-seksyen` + kelas anak supaya ia mengatasi pemilih
           elemen h2/p di atas — termasuk border-bottom hitam pada h2. Seksyen
           laporan yang BELUM ditukar kepada gaya baharu tidak menggunakan kelas
           ini dan kekal seperti sedia ada. */
        .laporan-seksyen .laporan-seksyen__tajuk {
            background: #deeaf6;
            color: #1f6091;
            font-size: 1rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 6px 10px;
            margin: 20px 0 10px;
            border: 0;
            page-break-after: avoid;
            break-after: avoid;
        }

        .laporan-seksyen .laporan-seksyen__perenggan {
            margin: 0 0 10px;
            text-align: justify;
            line-height: 1.55;
            /* Perenggan dibenarkan terbelah antara muka surat; orphans/widows
               menghalang baris tunggal tergantung pada sempadan halaman. */
            orphans: 2;
            widows: 2;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }

        .laporan-seksyen .laporan-seksyen__perenggan:last-child {
            margin-bottom: 0;
        }

        /* Jadual bergaya templat baharu — SALINAN blok `.laporan-jadual` dalam
           resources/scss/laporan-print.scss; kekalkan kedua-duanya selaras.
           Digunakan oleh seksyen Status Penerimaan dan Kebolehgunaan Data
           sahaja; jadual seksyen lain kekal pada gaya `table/th/td` di atas. */
        .laporan-seksyen .laporan-jadual {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            margin: 0 0 12px;
        }

        /* Kepala diulang apabila jadual terbelah antara muka surat. */
        .laporan-seksyen .laporan-jadual thead {
            display: table-header-group;
        }

        .laporan-seksyen .laporan-jadual tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .laporan-seksyen .laporan-jadual__kepala {
            background: #1f6091;
            color: #ffffff;
            border: 1px solid #fff;
            padding: 7px 10px;
            font-size: 1rem;
            font-weight: 700;
            text-transform: uppercase;
            text-align: center;
            vertical-align: middle;
        }

        .laporan-seksyen .laporan-jadual__bil,
        .laporan-seksyen .laporan-jadual__komponen,
        .laporan-seksyen .laporan-jadual__status {
            border: 1px solid #fff;
            padding: 8px 10px;
            font-size: 1rem;
            vertical-align: middle;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }

        /* Lebar lajur pada <col>, BUKAN pada <td>: dengan
           `table-layout: fixed` hanya baris pertama (<thead>) menentukan
           lebar, jadi `width` pada sel <tbody> diabaikan. */
        .laporan-seksyen .laporan-jadual__lajur-bil {
            width: 6%;
        }

        .laporan-seksyen .laporan-jadual__lajur-komponen {
            width: 36%;
        }

        .laporan-seksyen .laporan-jadual__lajur-status {
            width: 58%;
        }

        .laporan-seksyen .laporan-jadual__bil {
            /* Padding mendatar dikurangkan supaya nombor tidak terhimpit. */
            padding-left: 4px;
            padding-right: 4px;
            background: #1f6091;
            color: #ffffff;
            font-weight: 700;
            text-align: center;
        }

        .laporan-seksyen .laporan-jadual tbody tr:nth-child(odd) .laporan-jadual__komponen,
        .laporan-seksyen .laporan-jadual tbody tr:nth-child(odd) .laporan-jadual__status {
            background: #ededed;
        }

        .laporan-seksyen .laporan-jadual tbody tr:nth-child(even) .laporan-jadual__komponen,
        .laporan-seksyen .laporan-jadual tbody tr:nth-child(even) .laporan-jadual__status {
            background: #f6f7f9;
        }

        .laporan-seksyen .laporan-jadual__status-nilai {
            display: block;
            font-weight: 700;
            text-align: center;
            margin-bottom: 6px;
        }

        .laporan-seksyen .laporan-jadual__penerangan {
            margin: 0;
            padding-left: 20px;
            list-style-type: lower-roman;
        }

        .laporan-seksyen .laporan-jadual__penerangan li {
            margin-bottom: 2px;
        }

        .laporan-seksyen .laporan-jadual__penerangan li:last-child {
            margin-bottom: 0;
        }

        .laporan-seksyen .laporan-jadual__penerangan-tunggal {
            margin: 0;
            text-align: left;
        }

        .laporan-seksyen .laporan-seksyen__catatan-tajuk,
        .laporan-seksyen .laporan-seksyen__ulasan-tajuk,
        .laporan-seksyen .laporan-seksyen__penafian-tajuk {
            margin: 12px 0 4px;
            font-weight: 700;
            text-align: left;
        }

        .laporan-seksyen .laporan-seksyen__senarai-fail {
            margin: 4px 0 0;
            padding-left: 22px;
        }

        .laporan-seksyen .laporan-seksyen__senarai-fail li {
            margin-bottom: 3px;
            text-align: left;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }

        /* Subseksyen bernombor + jadual ringkas (3 lajur, berpusat).
           SALINAN blok dalam resources/scss/laporan-print.scss; kekalkan
           kedua-duanya selaras. Blok TERSENDIRI daripada `.laporan-jadual`
           kerana lajur Bil. di sini mengikut jalur baris biasa, bukan biru. */
        .laporan-seksyen .laporan-seksyen__subtajuk {
            margin: 14px 0 6px;
            font-size: 1rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            page-break-after: avoid;
            break-after: avoid;
        }

        /* Penomboran halaman mengikut templat rujukan 11 muka surat.
           SALINAN blok `--mula-halaman` dalam resources/scss/laporan-print.scss;
           kekalkan kedua-duanya selaras.

           Sifat page-break-* hanya berkesan pada media berhalaman (penjanaan
           PDF oleh Chrome/Browsershot). Pada paparan WebView yang berterusan
           ia diabaikan sepenuhnya, jadi WebView kekal tanpa jurang kosong.

           Seksyen yang bermula pada halaman baharu: Status Penerimaan,
           Ringkasan (membawa subseksyen 1 bersamanya), 2 Algoritma,
           3 Protokol, 4 Pustaka, 5 Vendor, Cadangan, Kesimpulan, Pengesahan.

           SENGAJA TIADA pemisah: Tujuan (kekal bersama blok pengenalan pada
           halaman 1), subseksyen 1 Profil (kekal bersama tajuk Ringkasan) dan
           Penafian (berada dalam seksyen Pengesahan). Kandungan yang melebihi
           satu halaman — contohnya jadual algoritma — dibiarkan mengalir ke
           halaman berikutnya secara semula jadi tanpa dikecilkan atau dipotong. */
        .laporan-seksyen.laporan-seksyen--mula-halaman,
        .laporan-seksyen .laporan-seksyen__subtajuk--mula-halaman {
            page-break-before: always;
            break-before: page;
        }

        .laporan-seksyen .laporan-jadual-ringkas {
            width: 68%;
            margin: 0 auto 12px;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .laporan-seksyen .laporan-jadual-ringkas__lajur-bil {
            width: 14%;
        }

        .laporan-seksyen .laporan-jadual-ringkas__lajur-perkara {
            width: 56%;
        }

        .laporan-seksyen .laporan-jadual-ringkas__lajur-jumlah {
            width: 30%;
        }

        .laporan-seksyen .laporan-jadual-ringkas thead {
            display: table-header-group;
        }

        .laporan-seksyen .laporan-jadual-ringkas tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .laporan-seksyen .laporan-jadual-ringkas th,
        .laporan-seksyen .laporan-jadual-ringkas td {
            border: 1px solid #fff;
            padding: 7px 10px;
            font-size: 1rem;
            /* Tiada height tetap — baris meninggi mengikut teks. */
            vertical-align: middle;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }

        .laporan-seksyen .laporan-jadual-ringkas th {
            background: #1f6091;
            color: #ffffff;
            font-weight: 700;
            text-transform: uppercase;
            text-align: center;
        }

        .laporan-seksyen .laporan-jadual-ringkas td {
            background: #ededed;
            color: #111;
        }

        .laporan-seksyen .laporan-jadual-ringkas tbody tr:nth-child(even) td {
            background: #f6f7f9;
        }

        .laporan-seksyen .laporan-jadual-ringkas__bil,
        .laporan-seksyen .laporan-jadual-ringkas__jumlah {
            text-align: center;
        }

        .laporan-seksyen .laporan-seksyen__senarai-ulasan {
            margin: 4px 0 0;
            padding-left: 22px;
        }

        .laporan-seksyen .laporan-seksyen__senarai-ulasan li {
            margin-bottom: 3px;
            text-align: left;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }

        .laporan-seksyen ul.laporan-seksyen__senarai-ulasan {
            list-style-type: disc;
        }


        /* ==========================================================================
           Jadual algoritma — 4 lajur dengan kategori dikumpulkan melalui rowspan
           --------------------------------------------------------------------------
           Digunakan oleh subseksyen "2. Algoritma Kriptografi" sahaja.

           Sel Bil. dan Kategori merentangi semua algoritma dalam kumpulannya
           (rowspan), jadi kategori tidak berulang pada setiap baris manakala setiap
           algoritma mengekalkan lajur Bilangan tersendiri — susunan yang tidak boleh
           dicapai dengan satu baris per kategori tanpa nombor tersasar apabila nama
           algoritma membalut ke baris kedua. */
        .laporan-seksyen .laporan-jadual-algo,
        .laporan-seksyen .laporan-jadual-protokol,
        .laporan-seksyen .laporan-jadual-pustaka,
        .laporan-seksyen .laporan-jadual-vendor {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            margin: 0 0 12px;
        }

        /* 8% dan bukan 7%: pada 7% kotak kandungan lajur ini tinggal lebih
           kurang 25px (180mm x 7% tolak padding 20px dan sempadan 2px),
           sedangkan "BIL." tebal huruf besar pada 1rem Aptos memerlukan
           kira-kira 29px — jadi kepala membalut menjadi "BIL" + ".".
           8% ialah lebar yang SAMA seperti jadual protokol dan pustaka di
           bawah, yang menggunakan fon, padding dan teks kepala yang sama
           dan memang tidak pernah membalut. 1% diambil daripada lajur
           algoritma (lajur terluas) supaya jumlahnya kekal 100%. */
        .laporan-seksyen .laporan-jadual-algo__lajur-bil {
            width: 8%;
        }

        .laporan-seksyen .laporan-jadual-algo__lajur-kategori {
            width: 30%;
        }

        .laporan-seksyen .laporan-jadual-algo__lajur-algoritma {
            width: 40%;
        }

        .laporan-seksyen .laporan-jadual-algo__lajur-bilangan {
            width: 22%;
        }

        /* Kepala diulang pada setiap muka surat apabila jadual terbelah. */
        .laporan-seksyen .laporan-jadual-algo thead,
        .laporan-seksyen .laporan-jadual-protokol thead,
        .laporan-seksyen .laporan-jadual-pustaka thead,
        .laporan-seksyen .laporan-jadual-vendor thead {
            display: table-header-group;
        }

        /* Baris tunggal tidak dibelah; kumpulan rowspan DIBENARKAN terbelah antara
           muka surat kerana satu kategori boleh mempunyai enam algoritma dan
           mengunci keseluruhan kumpulan akan meninggalkan ruang kosong besar. */
        .laporan-seksyen .laporan-jadual-algo tr,
        .laporan-seksyen .laporan-jadual-protokol tr,
        .laporan-seksyen .laporan-jadual-pustaka tr,
        .laporan-seksyen .laporan-jadual-vendor tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .laporan-seksyen .laporan-jadual-algo th,
        .laporan-seksyen .laporan-jadual-algo td,
        .laporan-seksyen .laporan-jadual-protokol th,
        .laporan-seksyen .laporan-jadual-protokol td,
        .laporan-seksyen .laporan-jadual-pustaka th,
        .laporan-seksyen .laporan-jadual-pustaka td,
        .laporan-seksyen .laporan-jadual-vendor th,
        .laporan-seksyen .laporan-jadual-vendor td {
            border: 1px solid #fff;
            padding: 7px 10px;
            font-size: 1rem;

            /* Tiada height tetap — sel meninggi mengikut teks. */
            vertical-align: middle;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }

        .laporan-seksyen .laporan-jadual-algo th,
        .laporan-seksyen .laporan-jadual-protokol th,
        .laporan-seksyen .laporan-jadual-pustaka th,
        .laporan-seksyen .laporan-jadual-vendor th {
            background: #1f6091;
            color: #ffffff;
            font-weight: 700;
            text-transform: uppercase;
            text-align: center;
        }

        .laporan-seksyen .laporan-jadual-algo td,
        .laporan-seksyen .laporan-jadual-protokol td,
        .laporan-seksyen .laporan-jadual-pustaka td,
        .laporan-seksyen .laporan-jadual-vendor td {
            background: #ededed;
            color: #111;
        }

        .laporan-seksyen .laporan-jadual-algo__bil,
        .laporan-seksyen .laporan-jadual-algo__bilangan {
            text-align: center;
        }

        /* Nombor "Bil." ialah DATA, bukan penekanan: ia dipaparkan pada berat
           biasa seperti setiap sel <td> lain, dan seperti lajur Bil. jadual
           ringkas "1. Profil Sistem dan Aset". Kepala <th> tidak tersentuh —
           ia membawa kelas jadualnya sendiri dan kekal tebal.

           Kelas ini dikongsi oleh subseksyen 2, 3, 4 dan 5; ia sengaja
           dibiarkan dikongsi supaya penomboran seragam merentas keempat-empat
           jadual. Lajur Bil. jadual "Status Penerimaan dan Kebolehgunaan Data"
           (.laporan-jadual__bil) TIDAK berkaitan: nombornya putih di atas
           latar biru dan kekal tebal dengan sengaja. */
        .laporan-seksyen .laporan-jadual-algo__bil {
            font-weight: 400;
        }

        /* Angka romawi dipisahkan supaya nama algoritma yang membalut sejajar. */
        .laporan-seksyen .laporan-jadual-algo__label {
            display: inline-block;
            min-width: 20px;
        }


        /* Jadual protokol berkongsi rupa jadual algoritma (pemilihnya ditambah pada
           peraturan di atas); hanya lebar lajur dan penjajaran versi berbeza. */
        .laporan-seksyen .laporan-jadual-protokol__lajur-bil {
            width: 8%;
        }

        .laporan-seksyen .laporan-jadual-protokol__lajur-nama {
            width: 34%;
        }

        .laporan-seksyen .laporan-jadual-protokol__lajur-versi {
            width: 26%;
        }

        .laporan-seksyen .laporan-jadual-protokol__lajur-bilangan {
            width: 32%;
        }

        .laporan-seksyen .laporan-jadual-protokol__versi {
            text-align: center;
        }


        /* Jadual pustaka berkongsi rupa dan bentuk lajur jadual protokol; pemilihnya
           ditambah pada peraturan di atas dan hanya lebarnya diisytiharkan di sini. */
        .laporan-seksyen .laporan-jadual-pustaka__lajur-bil {
            width: 8%;
        }

        .laporan-seksyen .laporan-jadual-pustaka__lajur-nama {
            width: 34%;
        }

        .laporan-seksyen .laporan-jadual-pustaka__lajur-versi {
            width: 26%;
        }

        .laporan-seksyen .laporan-jadual-pustaka__lajur-bilangan {
            width: 32%;
        }


        /* Jadual vendor menggunakan pengumpulan rowspan yang sama seperti jadual
           algoritma: satu vendor boleh mempunyai beberapa produk. Pemilihnya ditambah
           pada peraturan di atas; hanya lebar lajur diisytiharkan di sini. */
        /* 8%/40% atas sebab yang sama seperti jadual algoritma di atas: pada
           7% kepala "BIL." membalut kepada dua baris. */
        .laporan-seksyen .laporan-jadual-vendor__lajur-bil {
            width: 8%;
        }

        .laporan-seksyen .laporan-jadual-vendor__lajur-nama {
            width: 30%;
        }

        .laporan-seksyen .laporan-jadual-vendor__lajur-produk {
            width: 40%;
        }

        .laporan-seksyen .laporan-jadual-vendor__lajur-bilangan {
            width: 22%;
        }


        /* Senarai bernombor "Cadangan Tindakan Susulan".
           Setiap tindakan boleh menjadi perenggan panjang, jadi item DIBENARKAN
           terbelah antara muka surat; orphans/widows menghalang baris tunggal
           tergantung. `break-inside: avoid` akan menolak tindakan panjang secara
           keseluruhan ke muka surat berikut dan meninggalkan ruang kosong besar. */
        .laporan-seksyen .laporan-seksyen__senarai-tindakan {
            margin: 0;
            padding-left: 26px;
            list-style-type: decimal;
        }

        .laporan-seksyen .laporan-seksyen__senarai-tindakan li {
            margin-bottom: 8px;
            padding-left: 4px;
            text-align: justify;
            line-height: 1.55;
            orphans: 2;
            widows: 2;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }

        .laporan-seksyen .laporan-seksyen__senarai-tindakan li:last-child {
            margin-bottom: 0;
        }


        /* ==========================================================================
           Jadual pengesahan laporan
           --------------------------------------------------------------------------
           Sel Tandatangan dan Tarikh dibiarkan KOSONG untuk ditandatangani pada
           salinan bercetak. `height` pada sel bertindak sebagai tinggi MINIMUM dalam
           susun atur jadual, jadi ia memberi ruang menandatangani tanpa memotong
           nama atau peranan yang panjang — sel tetap meninggi mengikut teksnya. */
        .laporan-seksyen .laporan-pengesahan {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            margin: 0 0 14px;
        }

        .laporan-seksyen .laporan-pengesahan__lajur-peranan {
            width: 32%;
        }

        .laporan-seksyen .laporan-pengesahan__lajur-nama {
            width: 26%;
        }

        .laporan-seksyen .laporan-pengesahan__lajur-tandatangan {
            width: 25%;
        }

        .laporan-seksyen .laporan-pengesahan__lajur-tarikh {
            width: 17%;
        }

        .laporan-seksyen .laporan-pengesahan thead {
            display: table-header-group;
        }

        /* Satu baris tandatangan tidak boleh terbelah antara muka surat. */
        .laporan-seksyen .laporan-pengesahan tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .laporan-seksyen .laporan-pengesahan th,
        .laporan-seksyen .laporan-pengesahan td {
            border: 1px solid #fff;
            padding: 8px 10px;
            font-size: 1rem;
            vertical-align: middle;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }

        .laporan-seksyen .laporan-pengesahan th {
            background: #1f6091;
            color: #ffffff;
            font-weight: 700;
            text-transform: uppercase;
            text-align: center;
        }

        /* Lajur Peranan berlatar kelabu pada setiap baris, sama seperti sel
           Nama/Tandatangan/Tarikh di sebelahnya; hanya teksnya ditebalkan.
           Nilai MESTI kekal sama dengan blok pasangannya dalam
           resources/scss/laporan-print.scss. */
        .laporan-seksyen .laporan-pengesahan__peranan {
            background: #ededed;
            color: #111111;
            font-weight: 700;
        }

        .laporan-seksyen .laporan-pengesahan__nama,
        .laporan-seksyen .laporan-pengesahan__tandatangan,
        .laporan-seksyen .laporan-pengesahan__tarikh {
            background: #ededed;
            color: #111;

            /* Ruang menandatangani: kira-kira 16mm pada cetakan A4. */
            height: 60px;
        }

        .laporan-seksyen .laporan-pengesahan__tarikh {
            text-align: center;
        }


        /* Baris yang ditandatangani sepenuhnya dengan tangan: label dijajarkan ke atas
           supaya seluruh tinggi sel di bawahnya kekal kosong untuk ditulis pada
           salinan bercetak. */
        .laporan-seksyen .laporan-pengesahan__baris-manual td {
            vertical-align: top;
        }
    </style>
</head>

<body>

    {{-- Badan laporan dikongsi BAIT DEMI BAIT dengan pratonton skrin
         (resources/views/laporan/inventori.blade.php): kedua-duanya
         memasukkan partial yang SAMA di bawah.

         $widgetKomentar PALSU di sini — komentar KB/PPA TIDAK PERNAH masuk
         ke dalam PDF. Ia lapisan kedua di atas
         LaporanController@unduh yang sudah memanggil
         siapkanData(includeComments: false). --}}
    @php $widgetKomentar = false; @endphp

    @include('laporan.partials.pengenalan')
    @include('laporan.partials.tujuan')
    @include('laporan.partials.status-data')
    @include('laporan.partials.dapatan')
    @include('laporan.partials.tindakan')
    @include('laporan.partials.kesimpulan')
    @include('laporan.partials.pengesahan')

</body>

</html>
