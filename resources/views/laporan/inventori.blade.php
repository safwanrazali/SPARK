@extends('layouts.app')

@section('title', 'Laporan Analisis Inventori Kriptografi — ' . $analisis->agency_name)

@section('page-title', 'Laporan Analisis Inventori Kriptografi')

@section('content')

    <div class="report-card mb-4 d-print-none">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="text-secondary">
                Templat + business rules + input berstruktur → laporan.
                Betulkan input melalui borang analisis sebelum laporan dimuktamadkan.
            </span>
            <div class="d-flex gap-2">
                @can('manage-analysis')
                    <a class="btn btn-outline-light"
                        href="{{ route('analisis.borang', ['sector_code' => $analisis->sector_code, 'agency_code' => $analisis->agency_code]) }}">
                        <i class="bi bi-pencil"></i> Betulkan Input
                    </a>
                @endcan
                <button class="btn btn-primary" onclick="window.print()">
                    <i class="bi bi-printer"></i> Cetak / Simpan PDF
                </button>
            </div>
        </div>
    </div>

    <style>
        .laporan-rasmi {
            background: #fff;
            color: #111;
            max-width: 900px;
            margin: 0 auto;
            padding: 40px 48px;
            font-size: .85rem;
            line-height: 1.65;
        }

        .laporan-rasmi h1 {
            font-size: 1.15rem;
            text-transform: uppercase;
            text-align: center;
            font-weight: 800;
        }

        .laporan-rasmi h2 {
            font-size: .95rem;
            text-transform: uppercase;
            font-weight: 700;
            border-bottom: 2px solid #111;
            padding-bottom: 4px;
            margin: 26px 0 10px;
        }

        .laporan-rasmi p {
            text-align: justify;
        }

        .laporan-rasmi table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .laporan-rasmi th,
        .laporan-rasmi td {
            border: 1px solid #333;
            padding: 5px 8px;
            vertical-align: top;
            font-size: .8rem;
        }

        .laporan-rasmi th {
            background: #eff1f5;
            text-align: left;
        }

        .klasifikasi {
            text-align: center;
            letter-spacing: .35em;
            color: #b3403a;
            font-weight: 700;
            font-size: .8rem;
        }

        @media print {

            .sidebar,
            .page-header,
            .d-print-none {
                display: none !important;
            }

            .main-content,
            .content-wrapper {
                margin: 0 !important;
                padding: 0 !important;
            }

            .laporan-rasmi {
                padding: 0;
            }
        }
    </style>

    <div class="laporan-rasmi">

        <div class="klasifikasi mb-3">RAHSIA</div>

        <h1>Laporan Analisis Inventori Kriptografi</h1>
        <p class="text-center mb-4">
            <strong>SEKTOR:</strong> {{ $analisis->sector_name }} ·
            <strong>ENTITI:</strong> {{ $analisis->agency_name }}
        </p>

        <table>
            <tr>
                <td style="width:220px"><strong>KLASIFIKASI</strong></td>
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
            Laporan ini disediakan bagi membentangkan dapatan analisis inventori kriptografi
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
                        <td>{{ $baris['nota'] ?: '—' }}</td>
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
                        <td>{{ $baris['jumlah'] ?: '—' }}</td>
                        <td>{{ $baris['nota'] ?: '—' }}</td>
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
                                    <td>{{ $baris[$k] ?: '—' }}</td>
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
                        <td style="width:120px"></td>
                        <td style="width:90px"></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p style="font-size:.75rem">
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

        <div class="klasifikasi mt-3">RAHSIA</div>

    </div>

@endsection
