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

    <h2>Status Data Diterima</h2>
    <table>
        <thead>
            <tr>
                <th>Bil.</th>
                <th>Komponen</th>
                <th>Status Penerimaan</th>
                <th>Status Kebolehgunaan</th>
                <th>Pemerhatian</th>
            </tr>
        </thead>
        <tbody>
            @foreach (['j0' => 'Jadual 0 : Inventori', 'j1' => 'Jadual 1 : SBOM', 'j2' => 'Jadual 2 : CBOM'] as $kunci => $nama)
                @php $baris = $data['data_status'][$kunci] ?? []; @endphp
                <tr>
                    <td>{{ $loop->iteration }}.</td>
                    <td>{{ $nama }}</td>
                    <td>{{ $baris['penerimaan'] ?? '—' }}</td>
                    <td>{{ $baris['kebolehgunaan'] ?? '—' }}</td>
                    <td>{{ $baris['nota'] ?? '' ?: '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p><strong>Ringkasan status data:</strong> {{ $ringkasanData }}</p>

    <h2>Ringkasan Dapatan Analisis Inventori Kriptografi</h2>

    <p><strong>a. Profil Sistem dan Aset</strong></p>
    <table>
        <thead>
            <tr>
                <th>Bil.</th>
                <th>Perkara</th>
                <th>Jumlah</th>
                <th>Pemerhatian</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data['profil'] ?? [] as $kategori => $baris)
                <tr>
                    <td>{{ $loop->iteration }}.</td>
                    <td>{{ $kategori }}</td>
                    <td>{{ $baris['jumlah'] ?? '' ?: '—' }}</td>
                    <td>{{ $baris['nota'] ?? '' ?: '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p>
        Sebanyak <strong>{{ $jumlahAset }}</strong> rekod sistem dan aset telah dikenal pasti
        untuk dianalisis.
    </p>

    <p><strong>b. Algoritma Kriptografi</strong></p>
    @if (count($ikutKategori))
        <table>
            <thead>
                <tr>
                    <th>Bil.</th>
                    <th>Primitif/Kategori</th>
                    <th>Algoritma/Mekanisme Dikenal Pasti</th>
                    <th>Bil. Sistem/Aset</th>
                    <th>Pemerhatian</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($ikutKategori as $kategori => $senarai)
                    <tr>
                        <td>{{ $loop->iteration }}.</td>
                        <td>{{ $kategori }}</td>
                        <td>{{ collect($senarai)->pluck('nama')->implode(', ') }}</td>
                        <td>{{ collect($senarai)->pluck('bilangan')->map(fn($b) => $b ?: '—')->implode(', ') }}</td>
                        <td>{{ collect($senarai)->pluck('nota')->filter()->implode('; ') ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p>Tidak dikenal pasti.</p>
    @endif
    <p>
        Berdasarkan analisis yang dilaksanakan, sebanyak
        <strong>{{ collect($ikutKategori)->flatten(1)->count() }}</strong> algoritma atau
        mekanisme kriptografi telah dikenal pasti.
        @if ($lapuk)
            Analisis mengenal pasti penggunaan algoritma yang tidak lagi disyorkan, iaitu
            <strong>{{ implode(', ', $lapuk) }}</strong>.
        @endif
        @if ($kuantum)
            Algoritma yang berisiko terhadap ancaman pengkomputeran kuantum turut dikenal
            pasti, iaitu <strong>{{ implode(', ', $kuantum) }}</strong>, dan perlu diberi
            keutamaan dalam perancangan migrasi PQC.
        @endif
        @if ($data['algoritma_lain'] ?? false)
            Lain-lain mekanisme yang dikenal pasti: {{ $data['algoritma_lain'] }}.
        @endif
    </p>

    @foreach ([
        'protokol' => ['c. Protokol Kriptografi', ['nama' => 'Protokol Kriptografi', 'versi' => 'Versi', 'bilangan' => 'Bil. Sistem/Aset', 'nota' => 'Pemerhatian']],
        'pustaka' => ['d. Pustaka dan Modul Kriptografi', ['nama' => 'Pustaka/Modul', 'versi' => 'Versi', 'bilangan' => 'Bil. Sistem/Aset', 'nota' => 'Pemerhatian']],
        'vendor' => ['e. Maklumat Vendor', ['nama' => 'Nama Vendor', 'produk' => 'Produk/Komponen', 'versi' => 'Versi', 'bilangan' => 'Bil. Sistem/Aset', 'nota' => 'Pemerhatian']],
    ] as $medan => [$tajuk, $kolum])
        <p><strong>{{ $tajuk }}</strong></p>
        @if (count($data[$medan] ?? []))
            <table>
                <thead>
                    <tr>
                        <th>Bil.</th>
                        @foreach ($kolum as $label)
                            <th>{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data[$medan] as $baris)
                        <tr>
                            <td>{{ $loop->iteration }}.</td>
                            @foreach ($kolum as $k => $label)
                                <td>{{ $baris[$k] ?? '' ?: '—' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p>Tidak dikenal pasti.</p>
        @endif
    @endforeach

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
