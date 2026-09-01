@extends('layouts.app')

@section('title', 'Borang Analisis — ' . $agensi['code'])

@section('page-title', 'Input Analisis Berstruktur')

@section('content')

    <div class="report-card mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="section-title mb-1">{{ $agensi['code'] }}</h4>
                <span class="text-secondary">Sektor {{ $sectorCode }}</span>
            </div>
            @if ($analisis?->selesai)
                <span class="status-badge status-rendah">Analisis Selesai</span>
            @endif
        </div>
    </div>

    {{-- FASA 6 — keadaan draf: sambung semula, versi dan masa simpanan terakhir. --}}
    <div class="report-card mb-4 draft-bar" id="draft-bar">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">

            <div>
                <h4 class="section-title mb-1">Draf Laporan</h4>
                <p class="text-secondary mb-0" id="draft-status">
                    @if ($draf['ada_draf'])
                        Draf versi {{ $draf['versi'] }} disambung semula — disimpan
                        {{ $draf['disimpan_pada']?->format('d/m/Y H:i') }}
                        @if ($draf['disimpan_oleh'])
                            oleh {{ $draf['disimpan_oleh'] }}
                        @endif
                    @elseif ($draf['ada_rekod'])
                        {{-- Tiada draf terbuka kerana dapatan telah dimuktamadkan;
                             borang dimuatkan daripada rekod tersimpan. --}}
                        Dapatan tersimpan dimuatkan — dikemas kini
                        {{ $draf['dikemas_kini_pada']?->format('d/m/Y H:i') }}.
                        Sebarang perubahan boleh disimpan sebagai draf sebelum dimuktamadkan.
                    @else
                        Belum ada draf disimpan. Kerja anda boleh disimpan pada bila-bila masa
                        dan disambung semula kemudian.
                    @endif
                </p>
            </div>

            <div class="draft-bar__meta">
                @php
                    $semuaSeksyenDiisi = $draf['seksyen_selesai'] === $draf['jumlah_seksyen'];
                    $badgeSeksyen = match (true) {
                        $semuaSeksyenDiisi => 'status-rendah',
                        $draf['seksyen_selesai'] > 0 => 'status-sederhana',
                        default => 'status-tinggi',
                    };
                @endphp
                <span class="status-badge {{ $badgeSeksyen }}">
                    {{ $draf['seksyen_selesai'] }} / {{ $draf['jumlah_seksyen'] }} seksyen diisi
                </span>
                <button type="submit" form="borang-analisis" formaction="{{ route('analisis.draf') }}"
                    class="btn btn-sm btn-outline-light" id="btn-simpan-draf">
                    <i class="bi bi-journal-arrow-down"></i> Simpan Draf
                </button>
            </div>

        </div>

        <div class="draft-sections mt-3">
            @foreach ($draf['seksyen'] as $kunci => $seksyen)
                <span class="draft-chip {{ $seksyen['selesai'] ? 'is-selesai' : ($seksyen['ada_draf'] ? 'is-draf' : '') }}"
                    title="{{ $seksyen['disimpan_pada'] ? 'Draf v' . $seksyen['versi'] . ' — ' . $seksyen['disimpan_pada']->format('d/m/Y H:i') : 'Belum disimpan' }}">
                    @if ($seksyen['selesai'])
                        <i class="bi bi-check-circle-fill"></i>
                    @elseif ($seksyen['ada_draf'])
                        <i class="bi bi-pencil"></i>
                    @else
                        <i class="bi bi-circle"></i>
                    @endif
                    {{ $seksyen['label'] }}
                </span>
            @endforeach
        </div>

    </div>

    <form action="{{ route('analisis.simpan') }}" method="POST" id="borang-analisis">
        @csrf
        <input type="hidden" name="sector_code" value="{{ $sectorCode }}">
        <input type="hidden" name="agency_code" value="{{ $agensi['code'] }}">
        <input type="hidden" name="seksyen" id="seksyen-semasa" value="">

        {{-- 1 · Maklumat laporan --}}
        <div class="report-card mb-4" data-seksyen="maklumat">
            <h4 class="section-title">1 · Maklumat Laporan</h4>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Tarikh Laporan</label>
                    <input type="date" name="tarikh_laporan" class="form-control"
                        value="{{ old('tarikh_laporan', $borang['tarikh_laporan'] ?? null) }}">
                </div>
                <div class="col-md-4 mb-3">
                    {{-- Format kod rujukan dan senarai status diambil daripada
                         config/kriptografi.php — sumber yang SAMA digunakan oleh
                         pengesahan dalam AnalisisInventoriController@simpan. Jangan
                         tulis semula nilainya di sini supaya borang dan pengesahan
                         tidak terpesong. --}}
                    <label class="form-label" for="kod_rujukan">Kod Rujukan Laporan</label>
                    <input type="text" id="kod_rujukan" name="kod_rujukan"
                        class="form-control @error('kod_rujukan') is-invalid @enderror"
                        placeholder="cth. {{ config('kriptografi.kod_rujukan.contoh') }}"
                        pattern="{{ config('kriptografi.kod_rujukan.corak') }}"
                        value="{{ old('kod_rujukan', $borang['kod_rujukan'] ?? null) }}">
                    <div class="form-text">
                        Format: {{ config('kriptografi.kod_rujukan.format') }}
                    </div>
                    @error('kod_rujukan')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="status_laporan">Status Laporan</label>
                    <select id="status_laporan" name="status_laporan"
                        class="form-select @error('status_laporan') is-invalid @enderror">
                        @foreach (config('kriptografi.status_laporan') as $status)
                            <option value="{{ $status }}" @selected(old('status_laporan', $borang['status_laporan'] ?? null) === $status)>
                                {{ $status }}
                            </option>
                        @endforeach
                    </select>
                    @error('status_laporan')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        {{-- 2 · Status data diterima --}}
        <div class="report-card mb-4" data-seksyen="data_status">
            <h4 class="section-title">2 · Status Data Diterima (Jadual 0–2)</h4>

            @foreach (['j0' => 'Jadual 0 : Inventori', 'j1' => 'Jadual 1 : SBOM', 'j2' => 'Jadual 2 : CBOM'] as $kunci => $nama)
                @php $sedia = $data['data_status'][$kunci] ?? []; @endphp
                {{-- SATU status sahaja bagi setiap jadual. Medan "Penerimaan"
                     yang terdahulu telah dibuang: selepas kebolehgunaan
                     diselaraskan kepada Lengkap/Tidak Lengkap, kedua-duanya
                     menanyakan soalan yang sama, dan hanya kebolehgunaan yang
                     dipaparkan dalam laporan. --}}
                <div class="row align-items-end mb-2">
                    <div class="col-md-5"><strong>{{ $nama }}</strong></div>
                    <div class="col-md-4">
                        <label class="form-label">Status Kebolehgunaan</label>
                        <select name="data_status[{{ $kunci }}][kebolehgunaan]" class="form-select">
                            @foreach (config('kriptografi.kebolehgunaan_data') as $k)
                                <option @selected(($sedia['kebolehgunaan'] ?? config('kriptografi.kebolehgunaan_data')[0]) === $k)>{{ $k }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Penerangan boleh berbilang: templat laporan memaparkannya
                     sebagai senarai bernombor (i, ii, iii) bagi setiap jadual.
                     Nilai lama yang disimpan sebagai satu rentetan dinormalkan
                     oleh BorangAnalisis::senaraiTeks(), jadi rekod sedia ada
                     terus dimuatkan sebagai satu baris. --}}
                @php $penerangan = \App\Support\BorangAnalisis::senaraiTeks($sedia['nota'] ?? null) ?: ['']; @endphp
                <div class="row mb-3">
                    <div class="col-md-12">
                        <label class="form-label">Penerangan Status</label>
                        <div class="penerangan-senarai" data-penerangan="{{ $kunci }}">
                            @foreach ($penerangan as $titik)
                                <div class="input-group mb-2 penerangan-baris">
                                    <input type="text" name="data_status[{{ $kunci }}][nota][]"
                                        class="form-control" value="{{ $titik }}"
                                        placeholder="cth. Medan kategori aset tidak diisi sepenuhnya.">
                                    <button type="button" class="btn btn-outline-light penerangan-buang"
                                        aria-label="Buang penerangan ini">
                                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-light penerangan-tambah"
                            data-sasaran="{{ $kunci }}">
                            <i class="bi bi-plus-lg" aria-hidden="true"></i> Tambah Penerangan
                        </button>
                    </div>
                </div>
            @endforeach

            {{-- Fail rujukan yang menjadi sumber analisis. Direkodkan di sini,
                 BUKAN diambil daripada modul muat naik: spesifikasi bahagian 3
                 menetapkan aliran pelaporan tidak bergantung pada modul itu,
                 dan sistem tidak mewajibkan sebarang muat naik dokumen. --}}
            @php $failSumber = \App\Support\BorangAnalisis::senaraiTeks($data['fail_sumber'] ?? null) ?: ['']; @endphp
            <div class="mt-3">
                <label class="form-label">Fail Sumber (dipaparkan di bawah "Catatan:" dalam laporan)</label>
                <div class="penerangan-senarai" data-penerangan="fail_sumber">
                    @foreach ($failSumber as $fail)
                        <div class="input-group mb-2 penerangan-baris">
                            <input type="text" name="fail_sumber[]" class="form-control" value="{{ $fail }}"
                                placeholder="cth. LAMPIRAN A – BUKU KERJA PELAKSANAAN MIGRASI PQC">
                            <button type="button" class="btn btn-outline-light penerangan-buang"
                                aria-label="Buang fail sumber ini">
                                <i class="bi bi-x-lg" aria-hidden="true"></i>
                            </button>
                        </div>
                    @endforeach
                </div>
                <button type="button" class="btn btn-sm btn-outline-light penerangan-tambah" data-sasaran="fail_sumber">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i> Tambah Fail Sumber
                </button>
            </div>
        </div>

        {{-- 3 · Profil sistem dan aset --}}
        <div class="report-card mb-4" data-seksyen="profil">
            <h4 class="section-title">3 · Profil Sistem dan Aset (Jadual 0)</h4>
            @foreach (config('kriptografi.kategori_profil') as $kategori)
                @php
                    $k = md5($kategori);
                    $sedia = $data['profil'][$kategori] ?? [];
                @endphp
                <div class="row align-items-center mb-2">
                    <div class="col-md-4"><strong>{{ $kategori }}</strong></div>
                    <div class="col-md-3">
                        <input type="number" min="0" name="profil[{{ $k }}][jumlah]"
                            class="form-control" placeholder="Jumlah" value="{{ $sedia['jumlah'] ?? '' }}">
                    </div>
                </div>
            @endforeach

            {{-- Ulasan ditaip sendiri oleh pegawai. Perenggan dan senarai
                 bernombor dihasilkan daripada konvensyen menaip biasa oleh
                 App\Support\TeksBerformat — tiada markup khas perlu dipelajari. --}}
            <div class="mt-3">
                <label class="form-label" for="ulasan_profil">Ulasan</label>
                <textarea name="ulasan_profil" id="ulasan_profil" class="form-control" rows="6"
                    placeholder="cth. Sebanyak 48 rekod sistem dan aset direkodkan dalam Jadual 0.">{{ $data['ulasan_profil'] ?? '' }}</textarea>
                <div class="form-text">
                    Tinggalkan satu baris kosong untuk memulakan perenggan baharu.
                    Mulakan baris dengan <code>1.</code> <code>2.</code> (atau <code>-</code>)
                    untuk menghasilkan senarai bernombor.
                </div>
            </div>
        </div>

        {{-- 4 · Algoritma kriptografi --}}
        <div class="report-card mb-4" data-seksyen="algoritma">
            <h4 class="section-title">4 · Algoritma Kriptografi Dikenal Pasti Digunakan</h4>
            <p class="text-secondary">
                Hanya algoritma yang ditanda dipaparkan dalam kandungan laporan.
            </p>

            {{-- Katalog kini bertingkat: kategori => sub-kumpulan => algoritma.
                 Sub-kumpulan '' bermakna kategori itu tiada sub-kumpulan pada
                 laman AKSA MySEAL, jadi tajuk kecilnya dilangkau. Kunci yang
                 disimpan kekal "Kategori|Algoritma" — sub-kumpulan TIDAK masuk
                 ke dalam kunci. --}}
            @foreach (config('kriptografi.kategori_algoritma') as $kategori => $subKumpulan)
                <div class="border rounded p-3 mb-3">
                    <strong class="d-block mb-2">{{ $kategori }}</strong>
                    @foreach ($subKumpulan as $subTajuk => $senarai)
                        @if ($subTajuk !== '')
                            <div class="text-secondary small mt-3 mb-1">{{ $subTajuk }}</div>
                        @endif
                        @foreach ($senarai as $algo)
                            @php
                                $id = $kategori . '|' . $algo;
                                $k = md5($id);
                                $sedia = $data['algoritma'][$id] ?? null;
                            @endphp
                            <div class="row align-items-center mb-2">
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input algo-toggle" type="checkbox"
                                            id="algo-{{ $k }}" name="algoritma[{{ $k }}][dipilih]"
                                            value="1" data-target="{{ $k }}"
                                            @checked($sedia !== null)>
                                        <input type="hidden" name="algoritma[{{ $k }}][id]"
                                            value="{{ $id }}">
                                        <label class="form-check-label" for="algo-{{ $k }}">
                                            {{ $algo }}
                                            @if (in_array($algo, config('kriptografi.tidak_disyorkan')))
                                                <span class="text-danger" title="Tidak lagi disyorkan">▲</span>
                                            @endif
                                            @if (in_array($algo, config('kriptografi.risiko_kuantum')))
                                                <strong title="Berisiko kuantum">Q</strong>
                                            @endif
                                        </label>
                                    </div>
                                </div>
                                <div @class([
                                    'col-md-3',
                                    'algo-medan-' . $k,
                                    'is-hidden' => $sedia === null,
                                ])>
                                    <input type="number" min="0" name="algoritma[{{ $k }}][bilangan]"
                                        class="form-control form-control-sm" placeholder="Bil. sistem/aset"
                                        value="{{ $sedia['bilangan'] ?? '' }}">
                                </div>
                            </div>
                        @endforeach
                    @endforeach
                </div>
            @endforeach

            {{-- Katalog di atas mengandungi algoritma AKSA MySEAL (Approved)
                 sahaja. Algoritma lapuk (3DES, RC4, MD5, SHA-1) dan klasik
                 (RSA, DSA, ElGamal) direkodkan di sini, dan tetap dikesan oleh
                 AnalisisInventori::algoritmaLapuk()/algoritmaKuantum(). --}}
            @php $algoLain = \App\Support\BorangAnalisis::algoritmaLain($data['algoritma_lain'] ?? null) ?: [['nama' => '', 'bilangan' => '']]; @endphp
            <label class="form-label">Lain-lain (nyatakan, jika berkaitan)</label>
            {{-- `data-berindeks` memberitahu skrip supaya menomborkan semula
                 nama medan selepas baris ditambah atau dibuang. Setiap baris
                 mempunyai DUA medan, jadi `algoritma_lain[]` tidak boleh
                 digunakan — PHP akan mencipta elemen berasingan bagi nama dan
                 bilangan, lalu memutuskan pasangannya. --}}
            <div class="penerangan-senarai" data-penerangan="algoritma_lain" data-berindeks>
                @foreach ($algoLain as $i => $satu)
                    <div class="input-group mb-2 penerangan-baris">
                        <input type="text" data-nama="nama" name="algoritma_lain[{{ $i }}][nama]"
                            class="form-control" value="{{ $satu['nama'] }}" placeholder="cth. 3DES">
                        <input type="number" min="0" data-nama="bilangan"
                            name="algoritma_lain[{{ $i }}][bilangan]" class="form-control algo-lain-bilangan"
                            value="{{ $satu['bilangan'] }}" placeholder="Bil. sistem/aset">
                        <button type="button" class="btn btn-outline-light penerangan-buang"
                            aria-label="Buang algoritma ini">
                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                        </button>
                    </div>
                @endforeach
            </div>
            <button type="button" class="btn btn-sm btn-outline-light penerangan-tambah" data-sasaran="algoritma_lain">
                <i class="bi bi-plus-lg" aria-hidden="true"></i> Tambah Algoritma
            </button>

            {{-- Ulasan ditaip sendiri oleh pegawai; perenggan dan senarai
                 bernombor dihasilkan oleh App\Support\TeksBerformat. --}}
            <div class="mt-3">
                <label class="form-label" for="ulasan_algoritma">Ulasan</label>
                <textarea name="ulasan_algoritma" id="ulasan_algoritma" class="form-control" rows="6"
                    placeholder="cth. Analisis mengenal pasti penggunaan algoritma yang tidak lagi disyorkan.">{{ $data['ulasan_algoritma'] ?? '' }}</textarea>
                <div class="form-text">
                    Tinggalkan satu baris kosong untuk memulakan perenggan baharu.
                    Mulakan baris dengan <code>1.</code> <code>2.</code> (atau <code>-</code>)
                    untuk menghasilkan senarai bernombor.
                </div>
            </div>
        </div>

        {{-- 5–7 · Protokol / Pustaka / Vendor --}}
        @foreach ([
            'protokol' => ['5 · Protokol Kriptografi', ['nama' => 'Nama protokol', 'versi' => 'Versi', 'bilangan' => 'Bil. sistem/aset']],
            'pustaka' => ['6 · Pustaka dan Modul Kriptografi', ['nama' => 'Nama pustaka/modul', 'versi' => 'Versi', 'bilangan' => 'Bil. sistem/aset']],
            'vendor' => ['7 · Maklumat Vendor', ['nama' => 'Nama vendor', 'produk' => 'Produk/Komponen', 'bilangan' => 'Bil. sistem/aset']],
        ] as $medan => [$tajuk, $kolum])
            <div class="report-card mb-4" data-senarai="{{ $medan }}" data-seksyen="{{ $medan }}">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="section-title mb-0">{{ $tajuk }}</h4>
                    <button type="button" class="btn btn-sm btn-outline-light tambah-baris"
                        data-medan="{{ $medan }}">
                        <i class="bi bi-plus-lg"></i> Tambah Baris
                    </button>
                </div>

                <div class="senarai-baris" id="senarai-{{ $medan }}">
                    @foreach ($data[$medan] ?? [] as $i => $baris)
                        <div class="row align-items-center mb-2 baris-item">
                            @foreach ($kolum as $k => $label)
                                <div class="col">
                                    <input type="text"
                                        name="{{ $medan }}[{{ $i }}][{{ $k }}]"
                                        class="form-control form-control-sm" placeholder="{{ $label }}"
                                        value="{{ $baris[$k] ?? '' }}">
                                </div>
                            @endforeach
                            <div class="col-auto">
                                <button type="button" class="btn btn-sm btn-danger padam-baris">✕</button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <template id="templat-{{ $medan }}">
                    <div class="row align-items-center mb-2 baris-item">
                        @foreach ($kolum as $k => $label)
                            <div class="col">
                                <input type="text" data-nama="{{ $k }}"
                                    class="form-control form-control-sm" placeholder="{{ $label }}">
                            </div>
                        @endforeach
                        <div class="col-auto">
                            <button type="button" class="btn btn-sm btn-danger padam-baris">✕</button>
                        </div>
                    </div>
                </template>

                @if (in_array($medan, ['protokol', 'pustaka', 'vendor'], true))
                    {{-- Ulasan ditaip sendiri oleh pegawai; perenggan dan
                         senarai bernombor dihasilkan oleh TeksBerformat. --}}
                    @php $medanUlasan = 'ulasan_'.$medan; @endphp
                    <div class="mt-3">
                        <label class="form-label" for="{{ $medanUlasan }}">Ulasan</label>
                        <textarea name="{{ $medanUlasan }}" id="{{ $medanUlasan }}" class="form-control" rows="6"
                            placeholder="cth. Maklumat versi tidak direkodkan secara konsisten bagi sebahagian rekod.">{{ $data[$medanUlasan] ?? '' }}</textarea>
                        <div class="form-text">
                            Tinggalkan satu baris kosong untuk memulakan perenggan baharu.
                            Mulakan baris dengan <code>1.</code> <code>2.</code> (atau <code>-</code>)
                            untuk menghasilkan senarai bernombor.
                        </div>
                    </div>
                @endif

                <p @class([
                    'text-secondary',
                    'mb-0',
                    'nota-kosong',
                    'is-hidden' => count($data[$medan] ?? []) > 0,
                ])>
                    Tiada rekod. Baris yang tidak digunakan tidak akan dipaparkan dalam laporan muktamad.
                </p>
            </div>
        @endforeach

        {{-- 8 · Tindakan susulan --}}
        <div class="report-card mb-4" data-seksyen="tindakan">
            <h4 class="section-title">8 · Cadangan Tindakan Susulan</h4>
            @foreach (config('kriptografi.tindakan_susulan') as $i => $tindakan)
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="tindakan-{{ $i }}" name="tindakan[]"
                        value="{{ $i }}" @checked(in_array($i, $data['tindakan'] ?? []))>
                    <label class="form-check-label" for="tindakan-{{ $i }}">
                        {{ $tindakan['tindakan'] }}
                    </label>
                </div>
            @endforeach
            {{-- Tindakan tambahan ditaip sendiri oleh pegawai dan boleh
                 sebanyak mana yang diperlukan; setiap baris menjadi satu item
                 bernombor dalam senarai cadangan laporan. --}}
            @php $tindakanLain = \App\Support\BorangAnalisis::senaraiTeks($data['tindakan_lain'] ?? null) ?: ['']; @endphp
            <label class="form-label mt-2">Tindakan Tambahan (jika berkaitan)</label>
            <div class="penerangan-senarai" data-penerangan="tindakan_lain">
                @foreach ($tindakanLain as $satu)
                    <div class="input-group mb-2 penerangan-baris">
                        <input type="text" name="tindakan_lain[]" class="form-control"
                            value="{{ $satu }}" placeholder="Nyatakan tindakan susulan tambahan">
                        <button type="button" class="btn btn-outline-light penerangan-buang"
                            aria-label="Buang tindakan ini">
                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                        </button>
                    </div>
                @endforeach
            </div>
            <button type="button" class="btn btn-sm btn-outline-light penerangan-tambah"
                data-sasaran="tindakan_lain">
                <i class="bi bi-plus-lg" aria-hidden="true"></i> Tambah Tindakan
            </button>
        </div>

        {{-- 9 · Kesimpulan --}}
        <div class="report-card mb-4" data-seksyen="kesimpulan">
            <h4 class="section-title">9 · Kesimpulan</h4>
            {{-- Kesimpulan ditaip sepenuhnya oleh pegawai: ia berbeza bagi
                 setiap entiti, jadi tiada bank ayat atau kotak semak. Perenggan
                 dan senarai bernombor dihasilkan oleh TeksBerformat. --}}
            <textarea name="kesimpulan" id="kesimpulan" class="form-control" rows="10"
                placeholder="Nyatakan kesimpulan analisis bagi entiti ini.">{{ $data['kesimpulan'] ?? '' }}</textarea>
            <div class="form-text">
                Tinggalkan satu baris kosong untuk memulakan perenggan baharu.
                Mulakan baris dengan <code>1.</code> <code>2.</code> (atau <code>-</code>)
                untuk menghasilkan senarai bernombor.
            </div>
        </div>

        {{--
            Tiada kotak semak "tanda analisis selesai": menekan "Simpan
            Dapatan" itu sendirilah pengisytiharan siap bagi borang ini.

            Penyerahan laporan kepada PPA TIDAK berlaku di sini: ia milik
            peringkat 4 dan 5, yang belum dibina. Peringkat 3.1 ditutup
            melalui tindakan "Selesai" pada halaman Kemajuan Analisis Entiti.
        --}}
        <div class="report-card mb-4 d-flex align-items-center gap-3 flex-wrap">
            <button type="submit" formaction="{{ route('analisis.draf') }}" class="btn btn-outline-light">
                <i class="bi bi-journal-arrow-down"></i> Simpan Draf
            </button>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check2-circle"></i> Simpan Dapatan
            </button>
            <span class="text-secondary draft-hint">
                <i class="bi bi-info-circle"></i>
                Simpan Draf menyimpan kerja separa siap tanpa pengesahan penuh.
                Simpan Dapatan memuktamadkan borang; peringkat 3.1 ditutup
                melalui tindakan "Selesai" pada halaman Kemajuan Analisis Entiti.
            </span>
        </div>

    </form>

    <script>
        // Papar / sembunyi medan bilangan algoritma.
        // Kelas .is-hidden (resources/scss/states.scss) ialah keadaan yang sama
        // yang dipaparkan oleh Blade melalui @class, jadi togol di sini
        // menyambung terus daripada keadaan awal pelayan.
        document.querySelectorAll('.algo-toggle').forEach(cb => {
            cb.addEventListener('change', function() {
                document.querySelectorAll('.algo-medan-' + this.dataset.target)
                    .forEach(el => el.classList.toggle('is-hidden', !this.checked));
            });
        });

        // Baris dinamik protokol / pustaka / vendor.
        document.querySelectorAll('.tambah-baris').forEach(btn => {
            btn.addEventListener('click', function() {
                const medan = this.dataset.medan;
                const senarai = document.getElementById('senarai-' + medan);
                const templat = document.getElementById('templat-' + medan);
                const indeks = senarai.querySelectorAll('.baris-item').length + Date.now() % 1000;
                const klon = templat.content.cloneNode(true);

                klon.querySelectorAll('[data-nama]').forEach(input => {
                    input.name = `${medan}[${indeks}][${input.dataset.nama}]`;
                });

                senarai.appendChild(klon);
                this.closest('[data-senarai]').querySelector('.nota-kosong')
                    .classList.add('is-hidden');
            });
        });

        document.addEventListener('click', function(e) {
            if (e.target.closest('.padam-baris')) {
                e.target.closest('.baris-item').remove();
            }
        });

        // ── FASA 6 — draf: jejak seksyen, elak kehilangan data, autosimpan ──
        (function() {
            const borang = document.getElementById('borang-analisis');
            const status = document.getElementById('draft-status');
            const medanSeksyen = document.getElementById('seksyen-semasa');

            if (!borang) return;

            let kotor = false; // ada perubahan belum disimpan
            let menghantar = false; // borang sedang dihantar

            // Seksyen terakhir yang disentuh pengguna disimpan bersama draf.
            borang.addEventListener('focusin', function(e) {
                const kad = e.target.closest('[data-seksyen]');
                if (kad) medanSeksyen.value = kad.dataset.seksyen;
            });

            borang.addEventListener('input', () => kotor = true);
            borang.addEventListener('change', () => kotor = true);
            borang.addEventListener('submit', () => menghantar = true);

            // Amaran sebelum meninggalkan halaman dengan kerja belum disimpan.
            window.addEventListener('beforeunload', function(e) {
                if (!kotor || menghantar) return;
                e.preventDefault();
                e.returnValue = '';
            });

            // Autosimpan draf secara senyap. Tidak mengganggu paparan dan
            // hanya berjalan apabila ada perubahan sebenar.
            const SELANG = 180000; // 3 minit

            async function autosimpan() {
                if (!kotor || menghantar) return;

                try {
                    const jawapan = await fetch('{{ route('analisis.draf') }}', {
                        method: 'POST',
                        body: new FormData(borang),
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!jawapan.ok) return;

                    const hasil = await jawapan.json();
                    kotor = false;

                    if (status) {
                        status.textContent = 'Draf disimpan automatik pada ' + hasil.disimpan_pada + '.';
                    }
                } catch (e) {
                    // Kegagalan rangkaian dibiarkan senyap; amaran beforeunload
                    // kekal melindungi kerja pengguna.
                }
            }

            setInterval(autosimpan, SELANG);
            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState === 'hidden') autosimpan();
            });
        })();
    </script>

@endsection
