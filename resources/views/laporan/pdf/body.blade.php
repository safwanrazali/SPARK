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
        .laporan-meta {
            text-align: center;
        }

        .penafian {
            font-size: 10px;
        }

        .lajur-label {
            width: 220px;
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

    <h1>Laporan Analisis Inventori Kriptografi</h1>
    <p class="laporan-meta">
        <strong>SEKTOR:</strong> {{ $analisis->sector_name }} ·
        <strong>ENTITI:</strong> {{ $analisis->agency_name }}
    </p>

    <table>
        <tr>
            <td class="lajur-label"><strong>KLASIFIKASI</strong></td>
            <td>RAHSIA</td>
        </tr>
        <tr>
            <td><strong>TARIKH LAPORAN</strong></td>
            <td>{{ $analisis->tarikh_laporan?->format('d/m/Y') ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>KOD RUJUKAN LAPORAN</strong></td>
            <td>{{ $analisis->kod_rujukan ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>STATUS LAPORAN</strong></td>
            <td>{{ $analisis->status_laporan }}</td>
        </tr>
    </table>

    <h2>Tujuan</h2>
    <p>
        Laporan ini disediakan bagi membentangkan dapatan Analisis Inventori Kriptografi
        <strong>{{ $analisis->agency_name }}</strong> berdasarkan data dan maklumat yang
        dikemukakan selaras dengan Arahan Ketua Eksekutif NACSA No. 9. Analisis ini memberi
        fokus kepada data yang dikemukakan melalui Jadual 0: Inventori, Jadual 1:
        <em>Software Bill of Materials</em> (SBOM) dan Jadual 2: <em>Cryptographic Bill of
            Materials</em> (CBOM), yang selepas ini dirujuk secara kolektif sebagai Jadual 0–2.
    </p>
    <p>
        Laporan ini digunakan sebagai dokumen rujukan rasmi dalam pelaksanaan Klinik Migrasi
        Kriptografi Pasca-Kuantum (PQC) bagi membincangkan isu inventori, mendapatkan
        pengesahan entiti dan mengenal pasti tindakan susulan yang diperlukan untuk menyokong
        analisis risiko serta perancangan migrasi PQC.
    </p>

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
