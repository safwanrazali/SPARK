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
        <section class="laporan-seksyen laporan-seksyen--mula-halaman">
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
        <section class="laporan-seksyen laporan-seksyen--mula-halaman">
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

        <h3 class="laporan-seksyen__subtajuk laporan-seksyen__subtajuk--mula-halaman">2. Algoritma Kriptografi</h3>

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

        <h3 class="laporan-seksyen__subtajuk laporan-seksyen__subtajuk--mula-halaman">3. Protokol Kriptografi</h3>

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
        <h3 class="laporan-seksyen__subtajuk laporan-seksyen__subtajuk--mula-halaman">4. Pustaka dan Modul Kriptografi</h3>

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
        <h3 class="laporan-seksyen__subtajuk laporan-seksyen__subtajuk--mula-halaman">5. Maklumat Vendor</h3>

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


        <section class="laporan-seksyen laporan-seksyen--mula-halaman">
            <h2 class="laporan-seksyen__tajuk">Cadangan Tindakan Susulan</h2>

            @if (count($tindakan))
                <p class="laporan-seksyen__perenggan">
                    Berdasarkan dapatan analisis, entiti disarankan untuk melaksanakan tindakan berikut:
                </p>

                {{-- SATU <ol> sahaja: memecahkannya kepada beberapa senarai akan
                     menyebabkan nombor bermula semula dari 1 apabila laporan terbelah
                     antara muka surat PDF. --}}
                <ol class="laporan-seksyen__senarai-tindakan">
                    @foreach ($tindakan as $satu)
                        <li>{{ $satu }}</li>
                    @endforeach
                </ol>
            @else
                <p class="laporan-seksyen__perenggan">
                    Tiada cadangan tindakan susulan direkodkan berdasarkan dapatan analisis.
                </p>
            @endif
        </section>

        <section class="laporan-seksyen laporan-seksyen--mula-halaman">
            <h2 class="laporan-seksyen__tajuk">Kesimpulan</h2>

            @if (count($kesimpulan))
                @foreach ($kesimpulan as $blok)
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
            @else
                <p class="laporan-seksyen__perenggan">
                    Tiada kesimpulan direkodkan berdasarkan dapatan analisis.
                </p>
            @endif
        </section>

        <section class="laporan-seksyen laporan-seksyen--mula-halaman">
            <h2 class="laporan-seksyen__tajuk">Pengesahan Laporan</h2>

            <table class="laporan-pengesahan">
                {{-- Lebar lajur MESTI di sini: dengan `table-layout: fixed`, hanya baris
                     pertama menentukan lebar lajur. --}}
                <colgroup>
                    <col class="laporan-pengesahan__lajur-peranan">
                    <col class="laporan-pengesahan__lajur-nama">
                    <col class="laporan-pengesahan__lajur-tandatangan">
                    <col class="laporan-pengesahan__lajur-tarikh">
                </colgroup>
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
                        {{-- Baris tanpa nama bercetak ditandatangani sepenuhnya dengan
                             tangan. Teksnya dijajarkan ke ATAS supaya ruang kosong di
                             bawah label kekal untuk menulis nama dan tandatangan pada
                             salinan bercetak. --}}
                        <tr @class(['laporan-pengesahan__baris-manual' => $baris['nama'] === ''])>
                            <td class="laporan-pengesahan__peranan">{{ $baris['peranan'] }}</td>
                            <td class="laporan-pengesahan__nama">{{ $baris['nama'] }}</td>
                            {{-- Sel tandatangan dan tarikh sengaja KOSONG: laporan
                                 dicetak untuk ditandatangani. Tiada kawalan interaktif
                                 diletakkan di sini kerana ia tidak berfungsi dalam PDF. --}}
                            <td class="laporan-pengesahan__tandatangan"></td>
                            <td class="laporan-pengesahan__tarikh">{{ $baris['tarikh'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <p class="laporan-seksyen__penafian-tajuk">Penafian dan Had Penggunaan Laporan:</p>

            <p class="laporan-seksyen__perenggan">
                Laporan ini disediakan berdasarkan data dan maklumat yang dikemukakan oleh entiti
                melalui NACSA serta analisis yang dilaksanakan oleh Bahagian Migrasi PQC, PTPKM.
                Ketepatan dapatan bergantung pada kelengkapan, ketepatan, konsistensi dan
                kebolehgunaan data yang diterima.
            </p>

            <p class="laporan-seksyen__perenggan">
                Laporan ini merupakan <strong>penilaian inventori kriptografi bagi tujuan pelaksanaan
                Klinik Migrasi PQC</strong> berdasarkan rekod yang dikemukakan. Laporan ini tidak
                menggantikan audit teknikal, semakan kod sumber, pengesahan konfigurasi sistem,
                <em>vulnerability scanning</em>, <em>penetration testing</em> atau pensijilan
                keselamatan kriptografi.
            </p>

            <p class="laporan-seksyen__perenggan">
                Dapatan atau isu yang memerlukan pengesahan hendaklah disemak bersama pemilik sistem,
                pegawai teknikal atau vendor yang berkaitan sebelum sebarang perubahan konfigurasi,
                penggantian komponen atau pelaksanaan tindakan migrasi dibuat.
            </p>
        </section>
        <div class="laporan-rasmi__footer">
            <span>{{ $analisis->kod_rujukan ?? '[KOD RUJUKAN FAIL]' }}</span>
            <span>1</span>
        </div>
    </div>

@endsection
