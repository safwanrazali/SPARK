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
                        <option value="{{ $kod }}" @selected($penapis['sector_code'] === $kod)>{{ $sektor['name'] }}</option>
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
                        {{ $penapis['sector_name'] ?? 'Semua sektor' }}
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

    {{-- Baris metrik ringkas --}}
    <div class="metric-row">

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

        <div class="metric-card">
            <div class="metric-card__label">
                <span class="metric-card__dot is-warning"></span>
                Dalam Proses
            </div>
            <div class="metric-card__value">{{ $dalamProses }}</div>
            <div class="metric-card__bar is-warning"></div>
        </div>

        <div class="metric-card">
            <div class="metric-card__label">
                <span class="metric-card__dot is-success"></span>
                Entiti Selesai
            </div>
            <div class="metric-card__value">{{ $selesai }}</div>
            <div class="metric-card__bar is-success"></div>
        </div>

        <div class="metric-card">
            <div class="metric-card__label">
                <span class="metric-card__dot is-cyan"></span>
                Jumlah Laporan
            </div>
            <div class="metric-card__value">{{ $jumlahLaporan }}</div>
            <div class="metric-card__bar is-cyan"></div>
        </div>

        <div class="metric-card">
            <div class="metric-card__label">
                <span class="metric-card__dot is-success"></span>
                Laporan Siap
            </div>
            <div class="metric-card__value">{{ $laporanSiap }}</div>
            <div class="metric-card__bar is-success"></div>
        </div>

    </div>

    {{-- Taburan entiti merentas 7 peringkat workflow (Fasa 7). --}}
    <div class="dashboard-section">
        <div class="report-card">
            <h4 class="section-title">Taburan Workflow 7 Peringkat</h4>
            <p class="text-secondary">
                Kedudukan semasa setiap entiti yang telah didaftarkan dalam workflow.
                @if ($belumDidaftar > 0)
                    {{ $belumDidaftar }} entiti belum didaftarkan dalam workflow.
                @endif
            </p>

            <div class="workflow-taburan">
                @foreach ($taburanWorkflow as $peringkat)
                    <div class="workflow-taburan__baris">
                        <span class="workflow-stage-tag">{{ sprintf('%02d', $peringkat['peringkat']) }}</span>
                        <span class="workflow-taburan__nama">{{ $peringkat['nama'] }}</span>
                        <span class="status-pill-track">
                            <span class="status-pill-fill status-pill-fill--proses"
                                style="width: {{ max(2, $peringkat['peratus']) }}%"></span>
                        </span>
                        <span class="workflow-taburan__nilai">
                            {{ $peringkat['bilangan'] }}
                            <small class="text-secondary">({{ $peringkat['peratus'] }}%)</small>
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Baris carta: kemajuan sektor / status 3 laporan / kemajuan keseluruhan --}}
    <div class="dashboard-section chart-row">

        <div class="chart-card">
            <div class="chart-card__title">Entiti Selesai Mengikut Sektor</div>

            @if (count($mengikutSektor))
                @php
                    $maxPeratus = max(
                        1,
                        ...array_map(
                            fn($s) => $s['jumlah'] ? round(($s['selesai'] / $s['jumlah']) * 100) : 0,
                            $mengikutSektor,
                        ),
                    );
                @endphp
                <div class="bar-chart">
                    @foreach ($mengikutSektor as $sektor)
                        @php $peratus = $sektor['jumlah'] ? round($sektor['selesai'] / $sektor['jumlah'] * 100) : 0; @endphp
                        <div class="bar-chart__col"
                            title="{{ $sektor['nama'] }}: {{ $sektor['selesai'] }}/{{ $sektor['jumlah'] }} entiti ({{ $peratus }}%)">
                            <div class="bar-chart__bar" style="height: {{ max(6, round(($peratus / $maxPeratus) * 100)) }}%">
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="bar-chart__labels">
                    @foreach ($mengikutSektor as $i => $sektor)
                        <div class="bar-chart__label" title="{{ $sektor['nama'] }}">{{ $i + 1 }}</div>
                    @endforeach
                </div>
            @else
                <p class="text-secondary">
                    Belum ada entiti dipantau dalam skop penapis semasa. Entiti dikira dipantau
                    setelah mempunyai rekod workflow, penugasan, analisis atau status laporan.
                </p>
            @endif
        </div>

        <div class="chart-card">
            <div class="chart-card__title">Status 3 Laporan</div>

            <div class="status-pills">
                @foreach ([['label' => 'Siap', 'nilai' => $laporanSiap, 'kelas' => 'siap'], ['label' => 'Dalam Proses', 'nilai' => $laporanDalamProses, 'kelas' => 'proses'], ['label' => 'Belum', 'nilai' => $laporanBelum, 'kelas' => 'belum']] as $baris)
                    @php $lebar = $jumlahLaporan ? round($baris['nilai'] / $jumlahLaporan * 100) : 0; @endphp
                    <div class="status-pill-row">
                        <span class="status-pill status-pill--{{ $baris['kelas'] }}">{{ $baris['label'] }}</span>
                        <span class="status-pill-track">
                            <span class="status-pill-fill status-pill-fill--{{ $baris['kelas'] }}"
                                style="width: {{ max(6, $lebar) }}%"></span>
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="chart-card">
            <div class="chart-card__title">Kemajuan Keseluruhan</div>

            <div class="donut-wrap">
                <div class="donut" style="--pct: {{ $kemajuan }}">
                    <span class="donut__label">{{ $kemajuan }}%</span>
                </div>
                <div class="donut-caption">Peringkat workflow dicapai berbanding 7 peringkat</div>
            </div>
        </div>

    </div>

    <div class="dashboard-section">
        <div class="report-card">
            <h4 class="section-title">Aktiviti Terkini</h4>

            @forelse ($aktivitiTerkini as $log)
                <div class="d-flex justify-content-between border-bottom py-2">
                    <span>
                        {{ $log->getActionLabel() }} —
                        <strong>{{ $log->agency_name ?? $log->agency_code }}</strong>
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
