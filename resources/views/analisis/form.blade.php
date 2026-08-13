@extends('layouts.app')

@section('title', 'Borang Analisis — ' . $agensi['name'])

@section('page-title', 'Input Analisis Berstruktur')

@section('content')

    <div class="report-card mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="section-title mb-1">{{ $agensi['name'] }}</h4>
                <span class="text-secondary">{{ $sektor['name'] }} · {{ $agensi['code'] }}</span>
            </div>
            @if ($analisis?->selesai)
                <span class="status-badge status-rendah">Analisis Selesai</span>
            @endif
        </div>
    </div>

    <form action="{{ route('analisis.simpan') }}" method="POST">
        @csrf
        <input type="hidden" name="sector_code" value="{{ $sectorCode }}">
        <input type="hidden" name="agency_code" value="{{ $agensi['code'] }}">

        {{-- 1 · Maklumat laporan --}}
        <div class="report-card mb-4">
            <h4 class="section-title">1 · Maklumat Laporan</h4>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Tarikh Laporan</label>
                    <input type="date" name="tarikh_laporan" class="form-control"
                        value="{{ old('tarikh_laporan', $analisis?->tarikh_laporan?->format('Y-m-d')) }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Kod Rujukan Laporan</label>
                    <input type="text" name="kod_rujukan" class="form-control" placeholder="cth. PTPKM/INV/2026/001"
                        value="{{ old('kod_rujukan', $analisis?->kod_rujukan) }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Status Laporan</label>
                    <select name="status_laporan" class="form-select">
                        @foreach (['Muktamad', 'Muktamad dengan Catatan', 'Memerlukan Tindakan Susulan'] as $status)
                            <option value="{{ $status }}" @selected(old('status_laporan', $analisis?->status_laporan) === $status)>
                                {{ $status }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- 2 · Status data diterima --}}
        <div class="report-card mb-4">
            <h4 class="section-title">2 · Status Data Diterima (Jadual 0–2)</h4>

            @foreach (['j0' => 'Jadual 0 : Inventori', 'j1' => 'Jadual 1 : SBOM', 'j2' => 'Jadual 2 : CBOM'] as $kunci => $nama)
                @php $sedia = $data['data_status'][$kunci] ?? []; @endphp
                <div class="row align-items-end mb-2">
                    <div class="col-md-3"><strong>{{ $nama }}</strong></div>
                    <div class="col-md-2">
                        <label class="form-label">Penerimaan</label>
                        <select name="data_status[{{ $kunci }}][penerimaan]" class="form-select">
                            @foreach (['Lengkap', 'Tidak Lengkap', 'Tiada'] as $p)
                                <option @selected(($sedia['penerimaan'] ?? 'Lengkap') === $p)>{{ $p }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Kebolehgunaan</label>
                        <select name="data_status[{{ $kunci }}][kebolehgunaan]" class="form-select">
                            @foreach (['Boleh Digunakan', 'Boleh Digunakan dengan Catatan', 'Memerlukan Pengesahan', 'Tidak Boleh Digunakan'] as $k)
                                <option @selected(($sedia['kebolehgunaan'] ?? 'Boleh Digunakan') === $k)>{{ $k }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Pemerhatian</label>
                        <input type="text" name="data_status[{{ $kunci }}][nota]" class="form-control"
                            value="{{ $sedia['nota'] ?? '' }}">
                    </div>
                </div>
            @endforeach

            <div class="mt-3">
                <label class="form-label">Ringkasan Status Data (ayat piawai templat)</label>
                <select name="ringkasan_data" class="form-select">
                    <option value="lengkap" @selected(($data['ringkasan_data'] ?? '') === 'lengkap')>Data lengkap dan boleh digunakan</option>
                    <option value="catatan" @selected(($data['ringkasan_data'] ?? '') === 'catatan')>Data boleh digunakan dengan catatan</option>
                    <option value="pengesahan" @selected(($data['ringkasan_data'] ?? '') === 'pengesahan')>Data memerlukan pengesahan</option>
                    <option value="terhad" @selected(($data['ringkasan_data'] ?? '') === 'terhad')>Data sangat terhad / tidak boleh digunakan
                        sepenuhnya</option>
                </select>
            </div>
        </div>

        {{-- 3 · Profil sistem dan aset --}}
        <div class="report-card mb-4">
            <h4 class="section-title">3 · Profil Sistem dan Aset (Jadual 0)</h4>
            @foreach (config('kriptografi.kategori_profil') as $kategori)
                @php
                    $k = md5($kategori);
                    $sedia = $data['profil'][$kategori] ?? [];
                @endphp
                <div class="row align-items-center mb-2">
                    <div class="col-md-3"><strong>{{ $kategori }}</strong></div>
                    <div class="col-md-2">
                        <input type="number" min="0" name="profil[{{ $k }}][jumlah]"
                            class="form-control" placeholder="Jumlah" value="{{ $sedia['jumlah'] ?? '' }}">
                    </div>
                    <div class="col-md-7">
                        <input type="text" name="profil[{{ $k }}][nota]" class="form-control"
                            placeholder="Pemerhatian, jika berkaitan" value="{{ $sedia['nota'] ?? '' }}">
                    </div>
                </div>
            @endforeach
        </div>

        {{-- 4 · Algoritma kriptografi --}}
        <div class="report-card mb-4">
            <h4 class="section-title">4 · Algoritma Kriptografi Dikenal Pasti Digunakan</h4>
            <p class="text-secondary">
                Rujukan kategori: AKSA MySEAL. Tanda <span class="text-danger">▲</span> — tidak lagi disyorkan;
                tanda <strong>Q</strong> — berisiko terhadap ancaman pengkomputeran kuantum.
                Hanya item yang ditanda dipertimbangkan dalam kandungan laporan.
            </p>

            @foreach (config('kriptografi.kategori_algoritma') as $kategori => $senarai)
                <div class="border rounded p-3 mb-3">
                    <strong class="d-block mb-2">{{ $kategori }}</strong>
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
                                        value="1" data-target="{{ $k }}" @checked($sedia !== null)>
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
                            <div class="col-md-2 algo-medan-{{ $k }}" @style(['display:none' => $sedia === null])>
                                <input type="number" min="0" name="algoritma[{{ $k }}][bilangan]"
                                    class="form-control form-control-sm" placeholder="Bil. aset"
                                    value="{{ $sedia['bilangan'] ?? '' }}">
                            </div>
                            <div class="col-md-6 algo-medan-{{ $k }}" @style(['display:none' => $sedia === null])>
                                <input type="text" name="algoritma[{{ $k }}][nota]"
                                    class="form-control form-control-sm" placeholder="Pemerhatian"
                                    value="{{ $sedia['nota'] ?? '' }}">
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach

            <label class="form-label">Lain-lain (nyatakan, jika berkaitan)</label>
            <input type="text" name="algoritma_lain" class="form-control"
                value="{{ $data['algoritma_lain'] ?? '' }}">
        </div>

        {{-- 5–7 · Protokol / Pustaka / Vendor --}}
        @foreach ([
            'protokol' => ['5 · Protokol Kriptografi', ['nama' => 'Nama protokol', 'versi' => 'Versi', 'bilangan' => 'Bil. sistem/aset', 'nota' => 'Pemerhatian']],
            'pustaka' => ['6 · Pustaka dan Modul Kriptografi', ['nama' => 'Nama pustaka/modul', 'versi' => 'Versi', 'bilangan' => 'Bil. sistem/aset', 'nota' => 'Pemerhatian']],
            'vendor' => ['7 · Maklumat Vendor', ['nama' => 'Nama vendor', 'produk' => 'Produk/Komponen', 'versi' => 'Versi', 'bilangan' => 'Bil. sistem/aset', 'nota' => 'Pemerhatian']],
        ] as $medan => [$tajuk, $kolum])
            <div class="report-card mb-4" data-senarai="{{ $medan }}">
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

                <p class="text-secondary mb-0 nota-kosong" @style(['display:none' => count($data[$medan] ?? []) > 0])>
                    Tiada rekod. Baris yang tidak digunakan tidak akan dipaparkan dalam laporan muktamad.
                </p>
            </div>
        @endforeach

        {{-- 8 · Tindakan susulan --}}
        <div class="report-card mb-4">
            <h4 class="section-title">8 · Cadangan Tindakan Susulan</h4>
            @foreach (config('kriptografi.tindakan_susulan') as $i => $tindakan)
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="tindakan-{{ $i }}" name="tindakan[]"
                        value="{{ $i }}" @checked(in_array($i, $data['tindakan'] ?? []))>
                    <label class="form-check-label" for="tindakan-{{ $i }}">
                        <span class="text-secondary text-uppercase"
                            style="font-size:.75rem">{{ $tindakan['kategori'] }}</span><br>
                        {{ $tindakan['tindakan'] }}
                    </label>
                </div>
            @endforeach
            <label class="form-label mt-2">Tindakan Tambahan (jika berkaitan)</label>
            <textarea name="tindakan_lain" class="form-control" rows="2">{{ $data['tindakan_lain'] ?? '' }}</textarea>
        </div>

        {{-- 9 · Kesimpulan --}}
        <div class="report-card mb-4">
            <h4 class="section-title">9 · Kesimpulan (pilih yang berkaitan dengan dapatan sebenar)</h4>
            @foreach (config('kriptografi.kesimpulan') as $id => $kesimpulan)
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="kesimpulan-{{ $id }}"
                        name="kesimpulan[]" value="{{ $id }}" @checked(in_array($id, $data['kesimpulan'] ?? []))>
                    <label class="form-check-label" for="kesimpulan-{{ $id }}">
                        <strong>{{ $kesimpulan['nama'] }}</strong>
                        @if ($id === 'lapuk')
                            <span class="text-secondary">— nama algoritma diisi automatik daripada pilihan bertanda
                                ▲</span>
                        @endif
                    </label>
                </div>
            @endforeach
            <label class="form-label mt-2">Kesimpulan Tambahan (jika berkaitan)</label>
            <textarea name="kesimpulan_lain" class="form-control" rows="2">{{ $data['kesimpulan_lain'] ?? '' }}</textarea>
        </div>

        <div class="report-card mb-4 d-flex align-items-center gap-3 flex-wrap">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save"></i> Simpan Dapatan
            </button>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="selesai" name="selesai" value="1"
                    @checked($analisis?->selesai)>
                <label class="form-check-label" for="selesai">
                    Tanda analisis selesai (dikira dalam kemajuan dashboard)
                </label>
            </div>
        </div>

    </form>

    <script>
        // Papar / sembunyi medan bilangan & pemerhatian algoritma.
        document.querySelectorAll('.algo-toggle').forEach(cb => {
            cb.addEventListener('change', function() {
                document.querySelectorAll('.algo-medan-' + this.dataset.target)
                    .forEach(el => el.style.display = this.checked ? '' : 'none');
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
                this.closest('[data-senarai]').querySelector('.nota-kosong').style.display = 'none';
            });
        });

        document.addEventListener('click', function(e) {
            if (e.target.closest('.padam-baris')) {
                e.target.closest('.baris-item').remove();
            }
        });
    </script>

@endsection
