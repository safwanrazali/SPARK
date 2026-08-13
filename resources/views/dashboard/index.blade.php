@extends('layouts.app')

@section('title', 'Papan Pemuka')

@section('page-title', 'Dashboard Pemantauan — Analisis & Pelaporan Migrasi PQC')

@section('content')

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
                <span class="metric-card__dot is-success"></span>
                Analisis Selesai
            </div>
            <div class="metric-card__value">{{ $analisisSelesai }}</div>
            <div class="metric-card__bar is-success"></div>
        </div>

        <div class="metric-card">
            <div class="metric-card__label">
                <span class="metric-card__dot is-warning"></span>
                Laporan Siap
            </div>
            <div class="metric-card__value">{{ $laporanSiap }}</div>
            <div class="metric-card__bar is-warning"></div>
        </div>

    </div>

    {{-- Baris carta: kemajuan sektor / status 3 laporan / kemajuan keseluruhan --}}
    <div class="dashboard-section chart-row">

        <div class="chart-card">
            <div class="chart-card__title">Kemajuan Analisis Mengikut Sektor</div>

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
                    Belum ada entiti terlibat dalam proses analisis. Entiti dikira dipantau
                    setelah mempunyai rekod muat naik, dapatan analisis atau status laporan.
                </p>
            @endif
        </div>

        <div class="chart-card">
            <div class="chart-card__title">Status 3 Laporan</div>

            <div class="status-pills">
                @foreach ([['label' => 'Siap', 'nilai' => $siap, 'kelas' => 'siap'], ['label' => 'Dalam Proses', 'nilai' => $dalamProses, 'kelas' => 'proses'], ['label' => 'Belum', 'nilai' => $belum, 'kelas' => 'belum']] as $baris)
                    @php $lebar = $jumlahRekodLaporan ? round($baris['nilai'] / $jumlahRekodLaporan * 100) : 0; @endphp
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
                <div class="donut-caption">Peratus analisis &amp; pelaporan</div>
            </div>
        </div>

    </div>

    <div class="dashboard-section">
        <div class="report-card">
            <h4 class="section-title">Aktiviti Terkini</h4>

            @forelse ($aktivitiTerkini as $item)
                <div class="d-flex justify-content-between border-bottom py-2">
                    <span>
                        Analisis inventori — <strong>{{ $item->agency_name }}</strong>
                        ({{ $item->sector_name }})
                    </span>
                    <span class="text-secondary">{{ $item->updated_at?->format('d/m/Y H:i') }}</span>
                </div>
            @empty
                <p class="text-secondary">Tiada rekod tersedia.</p>
            @endforelse
        </div>
    </div>

@endsection
