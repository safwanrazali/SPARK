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
                    <label class="form-label">Kod Rujukan Laporan</label>
                    <input type="text" name="kod_rujukan" class="form-control" placeholder="cth. PTPKM/INV/2026/001"
                        value="{{ old('kod_rujukan', $borang['kod_rujukan'] ?? null) }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Status Laporan</label>
                    <select name="status_laporan" class="form-select">
                        @foreach (['Muktamad', 'Muktamad dengan Catatan', 'Memerlukan Tindakan Susulan'] as $status)
                            <option value="{{ $status }}" @selected(old('status_laporan', $borang['status_laporan'] ?? null) === $status)>
                                {{ $status }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- 2 · Status data diterima --}}
        <div class="report-card mb-4" data-seksyen="data_status">
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
        <div class="report-card mb-4" data-seksyen="profil">
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
        <div class="report-card mb-4" data-seksyen="algoritma">
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
                            <div @class(['col-md-2', 'algo-medan-'.$k, 'is-hidden' => $sedia === null])>
                                <input type="number" min="0" name="algoritma[{{ $k }}][bilangan]"
                                    class="form-control form-control-sm" placeholder="Bil. aset"
                                    value="{{ $sedia['bilangan'] ?? '' }}">
                            </div>
                            <div @class(['col-md-6', 'algo-medan-'.$k, 'is-hidden' => $sedia === null])>
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

                <p @class(['text-secondary', 'mb-0', 'nota-kosong', 'is-hidden' => count($data[$medan] ?? []) > 0])>
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
                        <span
                            class="text-secondary text-uppercase form-check-kategori">{{ $tindakan['kategori'] }}</span><br>
                        {{ $tindakan['tindakan'] }}
                    </label>
                </div>
            @endforeach
            <label class="form-label mt-2">Tindakan Tambahan (jika berkaitan)</label>
            <textarea name="tindakan_lain" class="form-control" rows="2">{{ $data['tindakan_lain'] ?? '' }}</textarea>
        </div>

        {{-- 9 · Kesimpulan --}}
        <div class="report-card mb-4" data-seksyen="kesimpulan">
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

        {{--
            Tiada lagi kotak semak "tanda analisis selesai": penyiapan bukan
            lagi sesuatu yang Pegawai Analisis isytiharkan sendiri. "Hantar"
            memuktamadkan borang DAN menyerahkannya kepada PPA, dan peringkat
            Jana Laporan hanya menjadi Selesai setelah Ketua Bahagian
            mengesahkan laporan.
        --}}
        <div class="report-card mb-4 d-flex align-items-center gap-3 flex-wrap">
            <button type="submit" formaction="{{ route('analisis.draf') }}" class="btn btn-outline-light">
                <i class="bi bi-journal-arrow-down"></i> Simpan Draf
            </button>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-send"></i> Hantar
            </button>
            <span class="text-secondary draft-hint">
                <i class="bi bi-info-circle"></i>
                Simpan Draf menyimpan kerja separa siap tanpa pengesahan penuh.
                Hantar memuktamadkan borang dan menyerahkan laporan kepada
                Pegawai Penyelaras Analisis untuk semakan.
            </span>
        </div>

    </form>

    <script>
        // Papar / sembunyi medan bilangan & pemerhatian algoritma.
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

            let kotor = false;      // ada perubahan belum disimpan
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
