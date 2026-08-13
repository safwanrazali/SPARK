@extends('layouts.app')

@section('title', 'Papan Pemuka')

@section('page-title', 'Dashboard Pemantauan — Analisis & Pelaporan Migrasi PQC')

@section('content')

    <div class="dashboard-grid">

        <div class="stat-card">
            <div class="stat-title">Jumlah Sektor</div>
            <div class="stat-value">{{ $jumlahSektor }}</div>
        </div>

        <div class="stat-card">
            <div class="stat-title">Jumlah Entiti Dipantau</div>
            <div class="stat-value">{{ $jumlahEntiti }}</div>
        </div>

        <div class="stat-card">
            <div class="stat-title">Analisis Selesai</div>
            <div class="stat-value">{{ $analisisSelesai }}</div>
        </div>

        <div class="stat-card">
            <div class="stat-title">Laporan Siap</div>
            <div class="stat-value">{{ $laporanSiap }}</div>
        </div>

    </div>

    <div class="dashboard-section">
        <div class="report-card">
            <h4 class="section-title">Kemajuan Analisis Mengikut Sektor</h4>

            @forelse ($mengikutSektor as $sektor)
                @php $peratus = $sektor['jumlah'] ? round($sektor['selesai'] / $sektor['jumlah'] * 100) : 0; @endphp
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span>{{ $sektor['nama'] }}</span>
                        <span class="text-secondary">{{ $sektor['selesai'] }}/{{ $sektor['jumlah'] }} entiti</span>
                    </div>
                    <div style="height:8px;background:#e9ecf1;border-radius:99px;overflow:hidden">
                        <div style="height:8px;width:{{ $peratus }}%;background:#1f4e8c;border-radius:99px"></div>
                    </div>
                </div>
            @empty
                <p class="text-secondary">
                    Belum ada entiti terlibat dalam proses analisis. Entiti dikira dipantau
                    setelah mempunyai rekod muat naik, dapatan analisis atau status laporan.
                </p>
            @endforelse
        </div>
    </div>

    <div class="dashboard-section">
        <div class="report-card">
            <h4 class="section-title">Status Tiga Laporan ({{ $jumlahRekodLaporan }} rekod laporan)</h4>

            @foreach ([['Siap', $siap, '#1b7f4d'], ['Dalam Proses', $dalamProses, '#a16207'], ['Belum Bermula', $belum, '#5b6472']] as [$nama, $nilai, $warna])
                <div class="d-flex align-items-center gap-3 mb-2">
                    <span style="width:130px">{{ $nama }}</span>
                    <div style="flex:1;height:10px;background:#e9ecf1;border-radius:99px;overflow:hidden">
                        <div
                            style="height:10px;width:{{ $jumlahRekodLaporan ? ($nilai / $jumlahRekodLaporan) * 100 : 0 }}%;background:{{ $warna }};border-radius:99px">
                        </div>
                    </div>
                    <span style="width:36px;text-align:right">{{ $nilai }}</span>
                </div>
            @endforeach

            <hr>

            <div class="d-flex align-items-baseline gap-3">
                <span style="font-size:2.4rem;font-weight:700;color:#1f4e8c">{{ $kemajuan }}%</span>
                <span class="text-secondary">kemajuan keseluruhan analisis &amp; pelaporan</span>
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
