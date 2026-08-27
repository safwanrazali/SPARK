@extends('layouts.app')

@section('title', 'Laporan Analisis Inventori Kriptografi — ' . $analisis->agency_name)

@section('page-title', 'Laporan Inventori Kriptografi')

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
                <a class="btn btn-primary" href="{{ route('laporan.unduh', $analisis) }}">
                    <i class="bi bi-file-earmark-pdf"></i> Muat Turun PDF
                </a>
            </div>
        </div>
    </div>

    {{-- Gaya pratonton laporan: resources/scss/laporan-pratonton.scss --}}
    <div class="laporan-rasmi">
        <div class="laporan-rasmi__jata">
            <img class="laporan-rasmi__jata-nacsa" src="{{ asset('image/logo_nacsa.png') }}">
            <div class="klasifikasi mb-3">RAHSIA</div>
            <img class="laporan-rasmi__jata-ptpkm" src="{{ asset('image/logo_ptpkm.png') }}">
        </div>

        {{-- Pengenalan laporan — gaya dalam resources/scss/laporan-print.scss
             (ukuran A4) + laporan-pratonton.scss (saiz taip skrin). Struktur
             ini MESTI kekal sama dengan resources/views/laporan/pdf/body.blade.php. --}}
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
                Bahagian ini merumuskan status penerimaan dan kebolehgunaan data inventori kriptografi
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
                        <td class="laporan-rasmi__lajur-tandatangan"></td>
                        <td class="laporan-rasmi__lajur-tarikh"></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p class="laporan-rasmi__penafian">
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
        <div class="laporan-rasmi__footer">
            <span>{{ $analisis->kod_rujukan ?? '[KOD RUJUKAN FAIL]' }}</span>
            <span>1</span>
        </div>
    </div>

@endsection
