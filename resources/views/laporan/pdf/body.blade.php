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

        KEKALKAN SELARAS dengan blok .laporan-rasmi dalam
        resources/scss/laporan-print.scss — nilainya sepadan satu-satu
        (12px asas, h1 15px, h2 13px, th/td 11px, sempadan #333, th #eff1f5).
        Perbezaannya hanya pemilih: fail di sini menggunakan pemilih elemen
        kerana dokumen ini hanya mengandungi laporan, manakala SCSS mesti
        menyaringnya di bawah .laporan-rasmi supaya tidak bocor ke aplikasi.
    --}}
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
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
            font-size: 15px;
            text-transform: uppercase;
            text-align: center;
            font-weight: 800;
        }

        h2 {
            font-size: 13px;
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
            font-size: 11px;
        }

        th {
            background: #eff1f5;
            text-align: left;
        }

        /* Kelas berikut menggantikan atribut gaya sebaris pada elemen di bawah. */
        .penafian {
            font-size: 10px;
        }

        /* Pengenalan laporan — sepanduk tajuk + jadual maklumat laporan.
           SALINAN blok `.laporan-id` dalam resources/scss/laporan-print.scss;
           kekalkan kedua-duanya selaras (biru #1f6091, kelabu #ededed,
           sempadan putih, tajuk 17px, baris 14px, sel 11px).

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
            font-size: 17px;
        }

        .laporan-id .laporan-id__baris {
            margin-top: 6px;
            font-size: 14px;
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
            font-size: 11px;
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
           kekalkan kedua-duanya selaras (bar #deeaf6, teks #1f6091, tajuk 12px,
           perenggan line-height 1.55).

           Berkembar `.laporan-seksyen` + kelas anak supaya ia mengatasi pemilih
           elemen h2/p di atas — termasuk border-bottom hitam pada h2. Seksyen
           laporan yang BELUM ditukar kepada gaya baharu tidak menggunakan kelas
           ini dan kekal seperti sedia ada. */
        .laporan-seksyen .laporan-seksyen__tajuk {
            background: #deeaf6;
            color: #1f6091;
            font-size: 12px;
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
            font-size: 11px;
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
            font-size: 11px;
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
        .laporan-seksyen .laporan-seksyen__ulasan-tajuk {
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
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            page-break-after: avoid;
            break-after: avoid;
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
            font-size: 11px;
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

        .laporan-seksyen .laporan-jadual-algo__lajur-bil {
            width: 7%;
        }

        .laporan-seksyen .laporan-jadual-algo__lajur-kategori {
            width: 30%;
        }

        .laporan-seksyen .laporan-jadual-algo__lajur-algoritma {
            width: 41%;
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
            font-size: 11px;

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

        .laporan-seksyen .laporan-jadual-algo__bil {
            font-weight: 700;
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
        .laporan-seksyen .laporan-jadual-vendor__lajur-bil {
            width: 7%;
        }

        .laporan-seksyen .laporan-jadual-vendor__lajur-nama {
            width: 30%;
        }

        .laporan-seksyen .laporan-jadual-vendor__lajur-produk {
            width: 41%;
        }

        .laporan-seksyen .laporan-jadual-vendor__lajur-bilangan {
            width: 22%;
        }

        .lajur-tandatangan {
            width: 120px;
        }

        .lajur-tarikh {
            width: 90px;
        }
    </style>
</head>

<body>

    {{-- Pengenalan laporan — struktur MESTI kekal sama dengan
         resources/views/laporan/inventori.blade.php (pratonton skrin). --}}
    <div class="laporan-id">
        <div class="laporan-id__banner">
            <h1 class="laporan-id__tajuk">Laporan Analisis Inventori Kriptografi</h1>
            <div class="laporan-id__baris">Sektor : {{ $analisis->sector_name ?: '—' }}</div>
            <div class="laporan-id__baris">Entiti : {{ $analisis->agency_name ?: '—' }}</div>
        </div>

        <table class="laporan-id__jadual">
            <tbody>
                <tr>
                    <th scope="row" class="laporan-id__label">Klasifikasi</th>
                    <td class="laporan-id__nilai">{{ $klasifikasi }}</td>
                </tr>
                <tr>
                    <th scope="row" class="laporan-id__label">Tarikh Laporan</th>
                    <td class="laporan-id__nilai">{{ $analisis->tarikh_laporan?->format('d/m/Y') ?? '—' }}</td>
                </tr>
                <tr>
                    <th scope="row" class="laporan-id__label">Kod Rujukan Laporan</th>
                    <td class="laporan-id__nilai">{{ $analisis->kod_rujukan ?: '—' }}</td>
                </tr>
                <tr>
                    <th scope="row" class="laporan-id__label">Status Laporan</th>
                    <td class="laporan-id__nilai">{{ $analisis->status_laporan ?: '—' }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- TUJUAN — gaya dalam resources/scss/laporan-print.scss (.laporan-seksyen).
         Struktur dan teks MESTI kekal sama dengan pasangannya dalam
         resources/views/laporan/{inventori,pdf/body}.blade.php. --}}
    <section class="laporan-seksyen">
        <h2 class="laporan-seksyen__tajuk">Tujuan</h2>

        <p class="laporan-seksyen__perenggan">
            Laporan ini disediakan bagi membentangkan dapatan analisis inventori kriptografi
            <strong>{{ $analisis->agency_name ?: '—' }}</strong> berdasarkan data dan maklumat
            yang dikemukakan selaras dengan Arahan Ketua Eksekutif NACSA No. 9.
        </p>

        <p class="laporan-seksyen__perenggan">
            Analisis ini dilaksanakan terhadap data yang dikemukakan melalui Jadual 0: Inventori,
            Jadual 1: <em>Software Bill of Materials</em> (SBOM) dan Jadual 2:
            <em>Cryptographic Bill of Materials</em> (CBOM), yang selepas ini dirujuk secara
            kolektif sebagai Jadual 0–2. Skop analisis merangkumi maklumat aset, komponen
            perisian, algoritma dan protokol kriptografi, pustaka atau modul kriptografi serta
            maklumat vendor yang berkaitan.
        </p>

        <p class="laporan-seksyen__perenggan">
            Laporan ini digunakan sebagai dokumen rujukan rasmi dalam pelaksanaan Klinik Migrasi
            Kriptografi Pasca-Kuantum (PQC) bagi membincangkan dapatan, mendapatkan pengesahan
            daripada entiti serta mengenal pasti tindakan susulan yang diperlukan bagi menyokong
            penilaian risiko dan perancangan migrasi PQC.
        </p>
    </section>

    {{-- STATUS PENERIMAAN DAN KEBOLEHGUNAAN DATA — gaya .laporan-seksyen /
         .laporan-jadual dalam resources/scss/laporan-print.scss. Struktur dan
         teks MESTI kekal sama dengan pasangannya dalam
         resources/views/laporan/{inventori,pdf/body}.blade.php. --}}
    <section class="laporan-seksyen">
        <h2 class="laporan-seksyen__tajuk">Status Penerimaan dan Kebolehgunaan Data</h2>

        <p class="laporan-seksyen__perenggan">
            Jadual di bawah merumuskan status penerimaan dan kebolehgunaan data inventori kriptografi
            yang dikemukakan melalui Jadual 0–2. Penilaian dilaksanakan berdasarkan aspek
            kelengkapan, kejelasan dan konsistensi data bagi menentukan kesesuaiannya untuk tujuan
            analisis.
        </p>

        <table class="laporan-jadual">
            {{-- Lebar lajur MESTI diisytiharkan di sini. Dengan
                 `table-layout: fixed`, hanya BARIS PERTAMA yang menentukan lebar
                 lajur; meletakkan `width` pada <td> dalam <tbody> tidak memberi
                 kesan dan jadual akan terbahagi sama rata. --}}
            <colgroup>
                <col class="laporan-jadual__lajur-bil">
                <col class="laporan-jadual__lajur-komponen">
                <col class="laporan-jadual__lajur-status">
            </colgroup>
            <thead>
                <tr>
                    <th class="laporan-jadual__kepala">Bil.</th>
                    <th class="laporan-jadual__kepala">Komponen</th>
                    <th class="laporan-jadual__kepala">Status Kebolehgunaan</th>
                </tr>
            </thead>
            <tbody>
                @foreach (['j0' => 'Jadual 0 : Inventori', 'j1' => 'Jadual 1: Software Bill of Materials (SBOM)', 'j2' => 'Jadual 2: Cryptographic Bill of Materials (CBOM)'] as $kunci => $nama)
                    @php
                        $baris = $data['data_status'][$kunci] ?? [];
                        $penerangan = \App\Support\BorangAnalisis::senaraiTeks($baris['nota'] ?? null);
                    @endphp
                    <tr>
                        <td class="laporan-jadual__bil">{{ $loop->iteration }}.</td>
                        <td class="laporan-jadual__komponen">{{ $nama }}</td>
                        <td class="laporan-jadual__status">
                            <span class="laporan-jadual__status-nilai">{{ $baris['kebolehgunaan'] ?? '—' }}</span>
                            @if (count($penerangan) > 1)
                                <ol class="laporan-jadual__penerangan">
                                    @foreach ($penerangan as $titik)
                                        <li>{{ $titik }}</li>
                                    @endforeach
                                </ol>
                            @elseif (count($penerangan) === 1)
                                <p class="laporan-jadual__penerangan-tunggal">{{ $penerangan[0] }}</p>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>


        <p class="laporan-seksyen__catatan-tajuk">Catatan:</p>
        @if (count($failSumber))
            <p class="laporan-seksyen__perenggan">
                Maklumat diperoleh daripada {{ $bilanganFail }} fail berikut:
            </p>
            <ol class="laporan-seksyen__senarai-fail">
                @foreach ($failSumber as $fail)
                    <li>
                        {{ $fail }}
                        (dirujuk sebagai <strong>FAIL {{ $loop->iteration }}</strong> dalam laporan ini).
                    </li>
                @endforeach
            </ol>
        @else
            <p class="laporan-seksyen__perenggan">
                Tiada fail sumber direkodkan bagi entiti ini.
            </p>
        @endif
    </section>

    {{-- RINGKASAN DAPATAN ANALISIS INVENTORI KRIPTOGRAFI + subseksyen 1.
         Gaya dalam resources/scss/laporan-print.scss (.laporan-seksyen /
         .laporan-jadual-ringkas). Struktur dan teks MESTI kekal sama dengan
         pasangannya dalam resources/views/laporan/{inventori,pdf/body}.blade.php. --}}
    <section class="laporan-seksyen">
        <h2 class="laporan-seksyen__tajuk">Ringkasan Dapatan Analisis Inventori Kriptografi</h2>

        <p class="laporan-seksyen__perenggan">
            Bahagian ini merumuskan dapatan utama hasil analisis inventori kriptografi berdasarkan
            data dalam Jadual 0–2, merangkumi profil sistem dan aset, penggunaan algoritma dan
            protokol kriptografi, pustaka atau modul kriptografi serta maklumat vendor yang
            berkaitan.
        </p>

        <h3 class="laporan-seksyen__subtajuk">1. Profil Sistem dan Aset</h3>

        <p class="laporan-seksyen__perenggan">
            Jadual di bawah merumuskan profil sistem dan aset yang dikenal pasti berdasarkan data
            dalam Jadual 0, mengikut kategori utama yang digunakan bagi tujuan analisis inventori
            kriptografi.
        </p>

        <table class="laporan-jadual-ringkas">
            {{-- Lebar lajur MESTI di sini: dengan `table-layout: fixed`, hanya baris
                 pertama menentukan lebar lajur. --}}
            <colgroup>
                <col class="laporan-jadual-ringkas__lajur-bil">
                <col class="laporan-jadual-ringkas__lajur-perkara">
                <col class="laporan-jadual-ringkas__lajur-jumlah">
            </colgroup>
            <thead>
                <tr>
                    <th>Bil.</th>
                    <th>Perkara</th>
                    <th>Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($profil as $baris)
                    <tr>
                        <td class="laporan-jadual-ringkas__bil">{{ $loop->iteration }}.</td>
                        <td>{{ $baris['perkara'] }}</td>
                        <td class="laporan-jadual-ringkas__jumlah">{{ $baris['jumlah'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if (count($ulasanProfil))
            <p class="laporan-seksyen__ulasan-tajuk">Ulasan:</p>
            {{-- Perenggan dan senarai dihasilkan daripada teks yang ditaip pegawai;
                 lihat App\Support\TeksBerformat untuk konvensyennya. --}}
            @foreach ($ulasanProfil as $blok)
                @if ($blok['jenis'] === 'senarai')
                    @if ($blok['bernombor'])
                        <ol class="laporan-seksyen__senarai-ulasan">
                            @foreach ($blok['isi'] as $titik)
                                <li>{{ $titik }}</li>
                            @endforeach
                        </ol>
                    @else
                        <ul class="laporan-seksyen__senarai-ulasan">
                            @foreach ($blok['isi'] as $titik)
                                <li>{{ $titik }}</li>
                            @endforeach
                        </ul>
                    @endif
                @else
                    <p class="laporan-seksyen__perenggan">{{ $blok['isi'] }}</p>
                @endif
            @endforeach
        @endif

    <h3 class="laporan-seksyen__subtajuk">2. Algoritma Kriptografi</h3>

    {{-- Jadual hanya muncul apabila ada algoritma dikenal pasti, jadi ayat
         pembuka mesti mengikutinya: "Jadual di bawah" apabila jadual dipaparkan,
         "Bahagian ini" apabila tidak. Pembolehubah yang SAMA mengawal kedua-duanya
         supaya ayat dan jadual tidak boleh terpesong. --}}
    @php $adaJadualAlgoritma = count($algoritma) > 0; @endphp

    <p class="laporan-seksyen__perenggan">
        {{ $adaJadualAlgoritma ? 'Jadual di bawah' : 'Bahagian ini' }} merumuskan algoritma dan
        mekanisme kriptografi yang dikenal pasti berdasarkan data dalam Jadual 0–2, mengikut
        primitif atau kategori kriptografi serta bilangan sistem dan aset yang terlibat.
    </p>

    @if ($adaJadualAlgoritma)
        <table class="laporan-jadual-algo">
            {{-- Lebar lajur MESTI di sini: dengan `table-layout: fixed`, hanya baris
                 pertama menentukan lebar lajur. --}}
            <colgroup>
                <col class="laporan-jadual-algo__lajur-bil">
                <col class="laporan-jadual-algo__lajur-kategori">
                <col class="laporan-jadual-algo__lajur-algoritma">
                <col class="laporan-jadual-algo__lajur-bilangan">
            </colgroup>
            <thead>
                <tr>
                    <th>Bil.</th>
                    <th>Primitif/Kategori Kriptografi</th>
                    <th>Algoritma/Mekanisme Dikenal Pasti</th>
                    <th>Bilangan Sistem/Aset Terlibat</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($algoritma as $kategori)
                    @foreach ($kategori['item'] as $item)
                        <tr>
                            {{-- Bil. dan kategori ditulis SEKALI sahaja bagi setiap
                                 kumpulan; rowspan merentangi semua algoritmanya
                                 supaya kategori tidak berulang pada setiap baris. --}}
                            @if ($loop->first)
                                <td class="laporan-jadual-algo__bil" rowspan="{{ count($kategori['item']) }}">
                                    {{ $loop->parent->iteration }}.
                                </td>
                                <td class="laporan-jadual-algo__kategori" rowspan="{{ count($kategori['item']) }}">
                                    {{ $kategori['kategori'] }}
                                </td>
                            @endif
                            <td class="laporan-jadual-algo__algoritma">
                                <span class="laporan-jadual-algo__label">{{ $item['label'] }}.</span>
                                {{ $item['nama'] }}
                            </td>
                            <td class="laporan-jadual-algo__bilangan">{{ $item['bilangan'] !== '' ? $item['bilangan'] : '—' }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    @else
        <p class="laporan-seksyen__perenggan">
            Tiada algoritma atau mekanisme kriptografi dikenal pasti berdasarkan data yang dikemukakan.
        </p>
    @endif

    @if (count($ulasanAlgoritma))
        <p class="laporan-seksyen__ulasan-tajuk">Ulasan:</p>
        @foreach ($ulasanAlgoritma as $blok)
            @if ($blok['jenis'] === 'senarai')
                @if ($blok['bernombor'])
                    <ol class="laporan-seksyen__senarai-ulasan">
                        @foreach ($blok['isi'] as $titik)
                            <li>{{ $titik }}</li>
                        @endforeach
                    </ol>
                @else
                    <ul class="laporan-seksyen__senarai-ulasan">
                        @foreach ($blok['isi'] as $titik)
                            <li>{{ $titik }}</li>
                        @endforeach
                    </ul>
                @endif
            @else
                <p class="laporan-seksyen__perenggan">{{ $blok['isi'] }}</p>
            @endif
        @endforeach
    @endif

    <h3 class="laporan-seksyen__subtajuk">3. Protokol Kriptografi</h3>

    {{-- Lihat nota pada subseksyen 2: ayat pembuka mengikut kehadiran jadual. --}}
    @php $adaJadualProtokol = count($data['protokol'] ?? []) > 0; @endphp

    <p class="laporan-seksyen__perenggan">
        {{ $adaJadualProtokol ? 'Jadual di bawah' : 'Bahagian ini' }} merumuskan protokol
        kriptografi yang dikenal pasti berdasarkan maklumat yang direkodkan dalam Jadual 0–2.
        Pada masa ini, Buku Kerja Migrasi PQC tidak menyediakan medan khusus untuk merekodkan
        protokol kriptografi. Oleh itu, maklumat protokol dikenal pasti berdasarkan rekod yang
        dikemukakan oleh entiti, termasuk maklumat yang direkodkan pada medan komponen atau
        algoritma.
    </p>

    @if ($adaJadualProtokol)
        <table class="laporan-jadual-protokol">
            {{-- Lebar lajur MESTI di sini: dengan `table-layout: fixed`, hanya baris
                 pertama menentukan lebar lajur. --}}
            <colgroup>
                <col class="laporan-jadual-protokol__lajur-bil">
                <col class="laporan-jadual-protokol__lajur-nama">
                <col class="laporan-jadual-protokol__lajur-versi">
                <col class="laporan-jadual-protokol__lajur-bilangan">
            </colgroup>
            <thead>
                <tr>
                    <th>Bil.</th>
                    <th>Protokol Kriptografi</th>
                    <th>Versi</th>
                    <th>Bilangan Sistem/Aset Terlibat</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['protokol'] as $baris)
                    <tr>
                        <td class="laporan-jadual-algo__bil">{{ $loop->iteration }}.</td>
                        <td>{{ $baris['nama'] ?? '' ?: '—' }}</td>
                        <td class="laporan-jadual-protokol__versi">{{ $baris['versi'] ?? '' ?: '—' }}</td>
                        <td class="laporan-jadual-algo__bilangan">{{ $baris['bilangan'] ?? '' ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="laporan-seksyen__perenggan">
            Tiada protokol kriptografi dikenal pasti berdasarkan data yang dikemukakan.
        </p>
    @endif

    @if (count($ulasanProtokol))
        <p class="laporan-seksyen__ulasan-tajuk">Ulasan:</p>
        @foreach ($ulasanProtokol as $blok)
            @if ($blok['jenis'] === 'senarai')
                @if ($blok['bernombor'])
                    <ol class="laporan-seksyen__senarai-ulasan">
                        @foreach ($blok['isi'] as $titik)
                            <li>{{ $titik }}</li>
                        @endforeach
                    </ol>
                @else
                    <ul class="laporan-seksyen__senarai-ulasan">
                        @foreach ($blok['isi'] as $titik)
                            <li>{{ $titik }}</li>
                        @endforeach
                    </ul>
                @endif
            @else
                <p class="laporan-seksyen__perenggan">{{ $blok['isi'] }}</p>
            @endif
        @endforeach
    @endif
    <h3 class="laporan-seksyen__subtajuk">4. Pustaka dan Modul Kriptografi</h3>

    {{-- Lihat nota pada subseksyen 2: ayat pembuka mengikut kehadiran jadual. --}}
    @php $adaJadualPustaka = count($data['pustaka'] ?? []) > 0; @endphp

    <p class="laporan-seksyen__perenggan">
        {{ $adaJadualPustaka ? 'Jadual di bawah' : 'Bahagian ini' }} merumuskan maklumat pustaka
        dan modul kriptografi yang dikenal pasti berdasarkan data dalam Jadual 0–2, termasuk
        maklumat versi serta padanan dengan sistem atau aset yang berkaitan.
    </p>

    @if ($adaJadualPustaka)
        <table class="laporan-jadual-pustaka">
            {{-- Lebar lajur MESTI di sini: dengan `table-layout: fixed`, hanya baris
                 pertama menentukan lebar lajur. --}}
            <colgroup>
                <col class="laporan-jadual-pustaka__lajur-bil">
                <col class="laporan-jadual-pustaka__lajur-nama">
                <col class="laporan-jadual-pustaka__lajur-versi">
                <col class="laporan-jadual-pustaka__lajur-bilangan">
            </colgroup>
            <thead>
                <tr>
                    <th>Bil.</th>
                    <th>Pustaka/Modul Kriptografi</th>
                    <th>Versi</th>
                    <th>Bilangan Sistem/Aset Terlibat</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['pustaka'] as $baris)
                    <tr>
                        <td class="laporan-jadual-algo__bil">{{ $loop->iteration }}.</td>
                        <td>{{ $baris['nama'] ?? '' ?: '—' }}</td>
                        <td class="laporan-jadual-protokol__versi">{{ $baris['versi'] ?? '' ?: '—' }}</td>
                        <td class="laporan-jadual-algo__bilangan">{{ $baris['bilangan'] ?? '' ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="laporan-seksyen__perenggan">
            Tiada pustaka atau modul kriptografi dikenal pasti berdasarkan data yang dikemukakan.
        </p>
    @endif

    @if (count($ulasanPustaka))
        <p class="laporan-seksyen__ulasan-tajuk">Ulasan:</p>
        @foreach ($ulasanPustaka as $blok)
            @if ($blok['jenis'] === 'senarai')
                @if ($blok['bernombor'])
                    <ol class="laporan-seksyen__senarai-ulasan">
                        @foreach ($blok['isi'] as $titik)
                            <li>{{ $titik }}</li>
                        @endforeach
                    </ol>
                @else
                    <ul class="laporan-seksyen__senarai-ulasan">
                        @foreach ($blok['isi'] as $titik)
                            <li>{{ $titik }}</li>
                        @endforeach
                    </ul>
                @endif
            @else
                <p class="laporan-seksyen__perenggan">{{ $blok['isi'] }}</p>
            @endif
        @endforeach
    @endif
    <h3 class="laporan-seksyen__subtajuk">5. Maklumat Vendor</h3>

    {{-- Lihat nota pada subseksyen 2: ayat pembuka mengikut kehadiran jadual. --}}
    @php $adaJadualVendor = count($vendor) > 0; @endphp

    <p class="laporan-seksyen__perenggan">
        {{ $adaJadualVendor ? 'Jadual di bawah' : 'Bahagian ini' }} merumuskan maklumat vendor
        serta produk atau komponen yang dikenal pasti berdasarkan data dalam Jadual 0–2, termasuk
        padanan dengan sistem atau aset yang berkaitan.
    </p>

    @if ($adaJadualVendor)
        <table class="laporan-jadual-vendor">
            {{-- Lebar lajur MESTI di sini: dengan `table-layout: fixed`, hanya baris
                 pertama menentukan lebar lajur. --}}
            <colgroup>
                <col class="laporan-jadual-vendor__lajur-bil">
                <col class="laporan-jadual-vendor__lajur-nama">
                <col class="laporan-jadual-vendor__lajur-produk">
                <col class="laporan-jadual-vendor__lajur-bilangan">
            </colgroup>
            <thead>
                <tr>
                    <th>Bil.</th>
                    <th>Nama Vendor</th>
                    <th>Produk/Komponen</th>
                    <th>Bilangan Sistem/Aset Terlibat</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($vendor as $kumpulan)
                    @foreach ($kumpulan['item'] as $item)
                        <tr>
                            {{-- Bil. dan nama vendor ditulis SEKALI sahaja bagi setiap
                                 kumpulan; rowspan merentangi semua produknya. --}}
                            @if ($loop->first)
                                <td class="laporan-jadual-algo__bil" rowspan="{{ count($kumpulan['item']) }}">
                                    {{ $loop->parent->iteration }}.
                                </td>
                                <td class="laporan-jadual-vendor__nama" rowspan="{{ count($kumpulan['item']) }}">
                                    {{ $kumpulan['nama'] }}
                                </td>
                            @endif
                            <td class="laporan-jadual-vendor__produk">
                                @if ($item['label'] !== '')
                                    <span class="laporan-jadual-algo__label">{{ $item['label'] }}.</span>
                                @endif
                                {{ $item['produk'] !== '' ? $item['produk'] : '—' }}
                            </td>
                            <td class="laporan-jadual-algo__bilangan">{{ $item['bilangan'] !== '' ? $item['bilangan'] : '—' }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    @else
        <p class="laporan-seksyen__perenggan">
            Tiada maklumat vendor dikenal pasti berdasarkan data yang dikemukakan.
        </p>
    @endif

    @if (count($ulasanVendor))
        <p class="laporan-seksyen__ulasan-tajuk">Ulasan:</p>
        @foreach ($ulasanVendor as $blok)
            @if ($blok['jenis'] === 'senarai')
                @if ($blok['bernombor'])
                    <ol class="laporan-seksyen__senarai-ulasan">
                        @foreach ($blok['isi'] as $titik)
                            <li>{{ $titik }}</li>
                        @endforeach
                    </ol>
                @else
                    <ul class="laporan-seksyen__senarai-ulasan">
                        @foreach ($blok['isi'] as $titik)
                            <li>{{ $titik }}</li>
                        @endforeach
                    </ul>
                @endif
            @else
                <p class="laporan-seksyen__perenggan">{{ $blok['isi'] }}</p>
            @endif
        @endforeach
    @endif
    </section>


    <h2>Cadangan Tindakan Susulan</h2>
    <table>
        <thead>
            <tr>
                <th>Bil.</th>
                <th>Cadangan Tindakan Susulan</th>
                <th>Kategori Tindakan</th>
            </tr>
        </thead>
        <tbody>
            @php $bil = 0; @endphp
            @foreach (collect($data['tindakan'] ?? [])->sort() as $indeks)
                @if (isset($tindakanBank[$indeks]))
                    <tr>
                        <td>{{ ++$bil }}.</td>
                        <td>{{ $tindakanBank[$indeks]['tindakan'] }}</td>
                        <td>{{ $tindakanBank[$indeks]['kategori'] }}</td>
                    </tr>
                @endif
            @endforeach
            @if ($data['tindakan_lain'] ?? false)
                <tr>
                    <td>{{ ++$bil }}.</td>
                    <td>{{ $data['tindakan_lain'] }}</td>
                    <td>Lain-lain</td>
                </tr>
            @endif
        </tbody>
    </table>

    <h2>Kesimpulan</h2>
    @foreach ($data['kesimpulan'] ?? [] as $id)
        @if (isset($kesimpulanBank[$id]))
            <p>
                <strong>{{ $loop->iteration }}. {{ $kesimpulanBank[$id]['nama'] }}.</strong>
                {{ $id === 'lapuk' ? $kesimpulanLapuk : $kesimpulanBank[$id]['teks'] }}
            </p>
        @endif
    @endforeach
    @if ($data['kesimpulan_lain'] ?? false)
        <p>{{ $data['kesimpulan_lain'] }}</p>
    @endif

    <h2>Pengesahan Laporan</h2>
    <table>
        <thead>
            <tr>
                <th>Peranan</th>
                <th>Nama</th>
                <th>Tandatangan</th>
                <th>Tarikh</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pengesahan as $baris)
                <tr>
                    <td>{{ $baris['peranan'] }}</td>
                    <td>{{ $baris['nama'] }}</td>
                    <td class="lajur-tandatangan"></td>
                    <td class="lajur-tarikh"></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="penafian">
        <strong>PENAFIAN DAN HAD PENGGUNAAN LAPORAN:</strong>
        Laporan ini disediakan berdasarkan data dan maklumat yang dikemukakan oleh entiti
        melalui NACSA serta analisis yang dilaksanakan oleh Bahagian Migrasi PQC, PTPKM.
        Ketepatan dapatan bergantung pada kelengkapan, ketepatan, konsistensi dan
        kebolehgunaan data yang diterima. Laporan ini merupakan penilaian inventori
        kriptografi bagi tujuan pelaksanaan Klinik Migrasi PQC berdasarkan rekod yang
        dikemukakan dan tidak menggantikan audit teknikal, semakan kod sumber, pengesahan
        konfigurasi sistem, <em>vulnerability scanning</em>, <em>penetration testing</em>
        atau pensijilan keselamatan kriptografi. Dapatan atau isu yang memerlukan pengesahan
        hendaklah disemak bersama pemilik sistem, pegawai teknikal atau vendor yang berkaitan
        sebelum sebarang perubahan dibuat.
    </p>

</body>

</html>
