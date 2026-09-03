@extends('layouts.app')

@section('title', 'Papan Pemuka')

@section('page-title', 'Papan Pemuka Pemantauan')

@section('content')

    {{-- Penapis: sektor + julat tarikh status workflow (Fasa 7). --}}
    <div class="report-card mb-4">
        <form action="{{ route('dashboard') }}" method="GET" class="row g-2 align-items-end">

            <div class="col-md-4">
                <label class="form-label" for="sector_code">Sektor</label>
                <select id="sector_code" name="sector_code" class="form-select">
                    <option value="">Semua sektor</option>
                    @foreach (config('sektor') as $kod => $sektor)
                        <option value="{{ $kod }}" @selected($penapis['sector_code'] === $kod)>{{ $kod }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label" for="dari">Tarikh Status Dari</label>
                <input type="date" id="dari" name="dari" class="form-control" value="{{ $penapis['dari'] }}">
            </div>

            <div class="col-md-3">
                <label class="form-label" for="hingga">Hingga</label>
                <input type="date" id="hingga" name="hingga" class="form-control" value="{{ $penapis['hingga'] }}">
            </div>

            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-funnel"></i> Papar
                </button>
            </div>

            @if ($penapis['aktif'])
                <div class="col-12">
                    <span class="text-secondary">
                        Penapis aktif:
                        {{ $penapis['sector_code'] ?? 'Semua sektor' }}
                        @if ($penapis['dari'] || $penapis['hingga'])
                            · Tarikh status workflow
                            {{ $penapis['dari'] ?? 'awal' }} – {{ $penapis['hingga'] ?? 'kini' }}
                        @endif
                    </span>
                    <a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-light ms-2">Set Semula</a>
                </div>
            @endif

        </form>
    </div>

    {{--
        Baris metrik 1/2 — corong entiti.

            SEMUA ENTITI
                |
                v
            ENTITI DITERIMA          (Buku Kerja MPQ diterima)
                |
                +--> ENTITI DALAM PROSES
                +--> ENTITI SELESAI

        Dua penyebut berbeza, dan setiap kad menyatakan miliknya dalam nota
        di bawah nilai:

        - Entiti Diterima      : keseluruhan entiti dalam senarai induk —
                                 ini soalan LIPUTAN.
        - Dalam Proses/Selesai : entiti yang telah DITERIMA — ini soalan
                                 KEMAJUAN, dan entiti yang Buku Kerja MPQ-nya
                                 belum diterima belum boleh bergerak.

        Kad "Entiti Selesai" di sini bermaksud peringkat 5 Selesai. Ia BUKAN
        ukuran yang sama dengan carta "Kemajuan Keseluruhan" di bawah, yang
        melaporkan Kemajuan Analisis fasa semasa (1.1–3.1); kedua-duanya
        sengaja dikekalkan kerana ia menjawab soalan yang berlainan.

        Penyebut sifar memberi 0%, bukan NaN.
    --}}
    <div class="metric-row metric-row--kira">

        <div class="metric-card">
            <div class="metric-card__label">
                <span class="metric-card__dot is-primary"></span>
                Jumlah Sektor
            </div>
            <div class="metric-card__value">{{ $jumlahSektor }}</div>
            <div class="metric-card__bar is-primary"></div>
        </div>

        <div class="metric-card">
            <div class="metric-card__label">
                <span class="metric-card__dot is-cyan"></span>
                Jumlah Entiti
            </div>
            <div class="metric-card__value">{{ $jumlahEntiti }}</div>
            <div class="metric-card__bar is-cyan"></div>
        </div>

        {{-- Medan `syarat_lanjut` peringkat 1.1 — Tarikh Terima + Status Borang
             Penerimaan Data — direkod oleh KB atau PPA. Ini pintu masuk kepada
             semua yang lain. --}}
        <div class="metric-card">
            <div class="metric-card__label">
                <span class="metric-card__dot is-cyan"></span>
                Entiti Diterima
            </div>
            <div class="metric-card__value">
                {{ \App\Support\Peratus::kad($peratusEntitiDiterima) }}<span
                    class="metric-card__unit">%</span>
            </div>
            <div class="metric-card__nota">
                {{ $entitiDiterima }} daripada {{ $jumlahEntiti }} entiti · Buku Kerja MPQ Diterima
            </div>
            <div class="metric-card__bar metric-card__bar--kemajuan">
                <span class="metric-card__bar-isi is-cyan" style="width: {{ $peratusEntitiDiterima }}%"></span>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-card__label">
                <span class="metric-card__dot is-warning"></span>
                Entiti Dalam Proses
            </div>
            <div class="metric-card__value">
                {{ \App\Support\Peratus::kad($peratusEntitiDalamProses) }}<span
                    class="metric-card__unit">%</span>
            </div>
            <div class="metric-card__nota">
                {{ $entitiDalamProses }} daripada {{ $entitiDiterima }} entiti diterima
            </div>
            <div class="metric-card__bar metric-card__bar--kemajuan">
                <span class="metric-card__bar-isi is-warning" style="width: {{ $peratusEntitiDalamProses }}%"></span>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-card__label">
                <span class="metric-card__dot is-success"></span>
                Entiti Selesai
            </div>
            <div class="metric-card__value">
                {{ \App\Support\Peratus::kad($peratusEntitiSelesai) }}<span
                    class="metric-card__unit">%</span>
            </div>
            <div class="metric-card__nota">
                {{ $entitiSelesai }} daripada {{ $entitiDiterima }} entiti diterima
            </div>
            <div class="metric-card__bar metric-card__bar--kemajuan">
                <span class="metric-card__bar-isi is-success" style="width: {{ $peratusEntitiSelesai }}%"></span>
            </div>
        </div>

    </div>

    {{--
        Baris metrik 2/2 — bilangan laporan mengikut jenis (StatusLaporan::JENIS).

        Hanya laporan yang telah diserahkan kepada NACSA dikira; laporan yang
        masih dalam kitaran semakan belum menjadi laporan yang "ada" dalam
        sistem. Nota di bawah setiap nilai menyatakannya supaya angka itu tidak
        disalah anggap sebagai jumlah laporan yang sedang disediakan.
    --}}
    <div class="metric-row metric-row--laporan">

        <div class="metric-card">
            <div class="metric-card__label">
                <span class="metric-card__dot is-primary"></span>
                Jumlah Laporan Analisis Inventori Kriptografi
            </div>
            <div class="metric-card__value">{{ $jumlahLaporan['inventori'] }}</div>
            <div class="metric-card__nota">Diserahkan kepada NACSA</div>
            <div class="metric-card__bar is-primary"></div>
        </div>

        <div class="metric-card">
            <div class="metric-card__label">
                <span class="metric-card__dot is-cyan"></span>
                Jumlah Laporan Penilaian Risiko Migrasi PQC
            </div>
            <div class="metric-card__value">{{ $jumlahLaporan['risiko'] }}</div>
            <div class="metric-card__nota">Diserahkan kepada NACSA</div>
            <div class="metric-card__bar is-cyan"></div>
        </div>

        <div class="metric-card">
            <div class="metric-card__label">
                <span class="metric-card__dot is-success"></span>
                Jumlah Laporan Kesiapsiagaan
            </div>
            <div class="metric-card__value">{{ $jumlahLaporan['kesiapsiagaan'] }}</div>
            <div class="metric-card__nota">Diserahkan kepada NACSA</div>
            <div class="metric-card__bar is-success"></div>
        </div>

    </div>

    {{--
        Baris carta: Kemajuan Analisis mengikut sektor / kemajuan keseluruhan.

        UKURAN DI SINI BERBEZA daripada kad "Entiti Selesai" di atas, dan
        perbezaannya disengajakan:

          Kad  "Entiti Selesai"  peringkat 5 Selesai, atas Entiti Diterima.
                                 Modul peringkat 5 belum dibina, jadi kad itu
                                 kekal 0% sepanjang fasa ini.
          Carta "Siap"           KESEMUA peringkat FASA SEMASA Selesai
                                 (1.1 sehingga 3.1), atas KESELURUHAN entiti.

        Kedua-duanya dikekalkan kerana ia menjawab soalan yang berlainan:
        satu melaporkan penyerahan laporan, satu lagi melaporkan kemajuan
        kerja analisis yang benar-benar boleh dibuat hari ini.

        Perbendaharaan "Siap / Dalam Proses / Belum Mula" diambil terus
        daripada KemajuanAnalisisService — perkataan "Selesai" SENGAJA tidak
        digunakan pada carta ini supaya ia tidak dibaca sebagai kad di atas.
    --}}
    @php
        // Dibina daripada AliranKerja, bukan ditulis "3.1" secara tetap:
        // apabila peringkat 4 dan 5 memasuki fasa semasa, sarikata ini
        // mengikut tanpa suntingan.
        $peringkatAkhirSemasa = \App\Support\AliranKerja::labelPenuh(
            \App\Support\AliranKerja::TERAKHIR_SEMASA,
        );
    @endphp
    <div class="dashboard-section chart-row">

        <div class="chart-card">
            <div class="chart-card__title">Entiti Siap Kemajuan Analisis Mengikut Sektor</div>
            <div class="chart-card__subtajuk">
                Siap = kesemua peringkat fasa semasa Selesai, berakhir pada {{ $peringkatAkhirSemasa }}.
                Diukur terhadap keseluruhan entiti setiap sektor.
            </div>

            @if ($selesai > 0)
                @php
                    // Gelang membahagikan KESELURUHAN entiti kepada sektornya:
                    // saiz setiap hirisan ialah bilangan entiti sektor itu, jadi
                    // kesebelas-sebelas hirisan berjumlah 100% entiti. Legendanya
                    // pula melaporkan berapa banyak antaranya telah Selesai.
                    // Palet --pie-1 … --pie-11 ada dalam dashboard.scss.
                    $segmenSektor = collect($selesaiMengikutSektor)
                        ->values()
                        ->map(
                            fn($sektor, $i) => [
                                'label' => $sektor['kod'] . ' — ' . $sektor['nama'],
                                'labelPendek' => $sektor['kod'],
                                'nilai' => $sektor['jumlah'],
                                'paparBilangan' => $sektor['selesai'],
                                'paparDaripada' => $sektor['jumlah'],
                                'peratus' => $sektor['peratus'],
                                'warna' => 'var(--pie-' . ($i % 11 + 1) . ')',
                            ],
                        )
                        ->all();
                @endphp

                <x-pie-chart unit="entiti siap" :segmen="$segmenSektor" :papar-kosong="true" :legenda-ringkas="true"
                    :nilai-tengah="\App\Support\Peratus::paparan($selesai, $peratusSelesaiKeseluruhan, $jumlahEntiti) . '%'"
                    label-tengah="Entiti Siap" />
            @else
                <x-empty-state icon="bi-pie-chart" title="Tiada entiti siap">
                    Carta ini muncul setelah sekurang-kurangnya satu entiti menamatkan kesemua
                    peringkat fasa semasa Kemajuan Analisis dalam skop penapis semasa.
                </x-empty-state>
            @endif
        </div>

        <div class="chart-card">
            <div class="chart-card__title">Kemajuan Keseluruhan</div>
            <div class="chart-card__subtajuk">
                Kemajuan Analisis bagi kesemua {{ $jumlahEntiti }} entiti, berakhir pada
                {{ $peringkatAkhirSemasa }}. Bukan ukuran yang sama dengan kad "Entiti Selesai".
            </div>

            @if ($jumlahEntiti > 0)
                @php
                    // Warna semantik: Siap hijau, Dalam Proses jingga, Belum Mula kelabu —
                    // sama seperti badge status di modul Kemajuan Analisis.
                    $segmenKemajuan = collect($kemajuanTaburan)
                        ->map(fn($baris) => $baris + ['warna' => 'var(--pie-' . $baris['kunci'] . ')'])
                        ->all();

                @endphp

                <x-pie-chart unit="entiti" :segmen="$segmenKemajuan"
                    :nilai-tengah="\App\Support\Peratus::paparan($selesai, $peratusSelesaiKeseluruhan, $jumlahEntiti) . '%'"
                    label-tengah="Siap" />
            @else
                <x-empty-state icon="bi-pie-chart" title="Tiada entiti dipantau">
                    Entiti dikira dipantau setelah mempunyai rekod workflow, penugasan,
                    analisis, status laporan atau muat naik.
                </x-empty-state>
            @endif
        </div>

    </div>

    <div class="dashboard-section">
        <div class="report-card">
            <h4 class="section-title">Aktiviti Terkini</h4>

            @forelse ($aktivitiTerkini as $log)
                <div class="d-flex justify-content-between border-bottom py-2">
                    <span>
                        {{ $log->getActionLabel() }} —
                        <strong>{{ $log->agency_code }}</strong>
                        @if ($log->changedBy)
                            <span class="text-secondary">oleh {{ $log->changedBy->name }}</span>
                        @endif
                    </span>
                    <span class="text-secondary">{{ $log->changed_at?->format('d/m/Y H:i') }}</span>
                </div>
            @empty
                <x-empty-state icon="bi-activity" title="Tiada aktiviti">
                    Aktiviti muncul di sini apabila peringkat workflow, penugasan atau status laporan berubah.
                </x-empty-state>
            @endforelse
        </div>
    </div>

@endsection
