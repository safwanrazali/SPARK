@extends('layouts.app')

@section('title', $entiti['agency_code'])

@section('page-title', 'Maklumat Entiti')

@section('content')

    @php
        use App\Models\StatusLaporan;
        use App\Models\WorkflowStageStatus;
        use App\Support\AliranKerja;

        /*
        | Halaman ini ialah HELAIAN REKOD entiti, bukan salinan kedua halaman
        | Kemajuan. Kemajuan memiliki stepper, borang tindakan dan sejarah
        | peringkat; di sini kita hanya menjawab satu soalan: apa yang telah
        | direkodkan bagi entiti ini setakat ini.
        |
        | Kedua-dua senarai di bawah diterbitkan daripada takrifan aliran
        | kerja, bukan ditulis tetap — menambah satu Borang atau satu No.
        | Rujukan pada AliranKerja terus muncul di sini.
        */
        $borang = collect(AliranKerja::kekunci())->filter(
            fn (string $kunci) => array_key_exists(AliranKerja::MEDAN_STATUS_BORANG, AliranKerja::medan($kunci)),
        );

        $berujukan = collect(AliranKerja::kekunci())->filter(
            fn (string $kunci) => AliranKerja::labelRujukan($kunci) !== null,
        );

        $tarikh = fn ($nilai) => $nilai instanceof \Illuminate\Support\Carbon
            ? $nilai->format('d/m/Y')
            : $nilai;
    @endphp

    {{-- ── Entiti ──────────────────────────────────────────────────────── --}}
    <div class="report-card mb-4">

        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">

            <div>
                <h4 class="section-title mb-1">{{ $entiti['agency_code'] }}</h4>
                <p class="text-secondary mb-0">
                    {{ $entiti['agency_name'] }} · Sektor {{ $entiti['sector_code'] }}
                    @if (! empty($entiti['sector_name']))
                        {{ $entiti['sector_name'] }}
                    @endif
                </p>
            </div>

            {{-- Tindakan yang tersedia — hanya yang dibenarkan bagi peranan semasa. --}}
            <div class="entity-actions">
                <a href="{{ route('workflow.show', $entiti['agency_code']) }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-diagram-3"></i> Kemajuan
                </a>

                @can('manage-analysis')
                    <a href="{{ route('analisis.borang', [
                        'sector_code' => $entiti['sector_code'],
                        'agency_code' => $entiti['agency_code'],
                    ]) }}"
                        class="btn btn-sm btn-outline-light">
                        <i class="bi bi-pencil-square"></i> Borang Analisis
                    </a>
                @endcan

                @if ($analisis)
                    <a href="{{ route('laporan.inventori', $analisis) }}" class="btn btn-sm btn-outline-light">
                        <i class="bi bi-file-earmark-text"></i> Laporan
                    </a>
                @endif

                @can('view-audit-trail')
                    <a href="{{ route('audit.index', ['agency_code' => $entiti['agency_code']]) }}"
                        class="btn btn-sm btn-outline-light">
                        <i class="bi bi-shield-check"></i> Jejak Audit
                    </a>
                @endcan
            </div>

        </div>

    </div>

    {{--
        ── Kedudukan Semasa ─────────────────────────────────────────────
        Empat angka, tiada stepper: stepper milik halaman Kemajuan, dan
        memaparkannya di kedua-dua tempat itulah yang menjadikan halaman ini
        terasa berulang. Di sini kita hanya menamakan peringkat yang sedang
        aktif dan siapa memegangnya.
    --}}
    <div class="report-card mb-4">

        <h4 class="section-title">Kedudukan Semasa</h4>

        <div class="row g-3 workflow-meta">
            <div class="col-md-3">
                <div class="stat-title">Peringkat Aktif</div>
                <div class="workflow-meta__value">
                    @if ($dalamAliran)
                        <span class="workflow-stage-tag">{{ $peringkatSemasa }}</span>
                        {{ AliranKerja::label($peringkatSemasa) }}
                    @else
                        <span class="text-secondary">Belum Didaftarkan</span>
                    @endif
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-title">Status Keseluruhan</div>
                <div class="workflow-meta__value">
                    <span class="status-badge {{ $badgeKeseluruhan }}">{{ $keseluruhan }}</span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-title">Peringkat Selesai</div>
                <div class="workflow-meta__value">
                    {{ $siapFasa }} / {{ $jumlahFasa }}
                    <small class="text-secondary">
                        ({{ $jumlahFasa ? round(($siapFasa / $jumlahFasa) * 100) : 0 }}%)
                    </small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-title">Pegawai Analisis</div>
                <div class="workflow-meta__value">
                    {{ $penugasan?->assignedTo?->name ?? '-' }}
                </div>
            </div>
        </div>

    </div>

    {{--
        ── Status Borang ────────────────────────────────────────────────
        Inti halaman. Satu baris bagi SETIAP borang dalam aliran kerja,
        dengan status borangnya sendiri (perbendaharaan tujuh nilai) di
        sebelah status peringkat yang memilikinya. Kedua-duanya diperlukan:
        satu peringkat boleh Selesai sementara borangnya "Telah Diserah",
        dan hanya paparan ini menunjukkan kedua-duanya serentak.
    --}}
    <div class="report-card mb-4">

        <h4 class="section-title">Status Borang</h4>
        <p class="text-secondary">
            Kedudukan setiap borang yang direkodkan sepanjang aliran kerja entiti ini.
        </p>

        <div class="table-responsive-custom">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th scope="col">Borang</th>
                        <th scope="col">Peringkat</th>
                        <th scope="col">Status Borang</th>
                        <th scope="col">Status Peringkat</th>
                        <th scope="col">Tarikh Direkod</th>
                        <th scope="col">Kemas Kini</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($borang as $kunci)
                        @php
                            $rekod = $peringkat->get($kunci);
                            $akanDatang = AliranKerja::adalahAkanDatang($kunci);
                            $medan = AliranKerja::medan($kunci);
                            $nilaiBorang = $rekod?->status_borang;
                            $kelasBorang = AliranKerja::badgeStatusBorang($nilaiBorang);
                        @endphp
                        <tr>
                            <td>{{ $medan[AliranKerja::MEDAN_STATUS_BORANG] }}</td>
                            <td class="text-nowrap">
                                <span class="workflow-stage-tag">{{ $kunci }}</span>
                                {{ AliranKerja::label($kunci) }}
                            </td>
                            <td>
                                @if ($nilaiBorang === null || $nilaiBorang === '')
                                    <span class="text-secondary">Belum direkod</span>
                                @elseif ($kelasBorang)
                                    <span class="status-badge {{ $kelasBorang }}">{{ $nilaiBorang }}</span>
                                @else
                                    {{-- "Tidak Berkaitan" bukan kedudukan kerja, jadi tiada pil warna. --}}
                                    <span class="text-secondary">{{ $nilaiBorang }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($akanDatang)
                                    <span class="text-secondary fst-italic">Belum dibina</span>
                                @else
                                    <span class="status-badge {{ $rekod?->statusBadgeClass() ?? 'status-tinggi' }}">
                                        {{ $rekod?->status ?? WorkflowStageStatus::BELUM_MULA }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $adaTarikh = false;
                                @endphp
                                @foreach ($medan as $lajur => $label)
                                    @continue($lajur === AliranKerja::MEDAN_STATUS_BORANG)
                                    @php
                                        $nilai = $rekod?->{$lajur};
                                    @endphp
                                    @if ($nilai !== null && $nilai !== '')
                                        @php
                                            $adaTarikh = true;
                                        @endphp
                                        <div>
                                            <small class="text-secondary">{{ $label }}:</small>
                                            {{ $tarikh($nilai) }}
                                        </div>
                                    @endif
                                @endforeach
                                @unless ($adaTarikh)
                                    <span class="text-secondary">-</span>
                                @endunless
                            </td>
                            <td class="text-nowrap">
                                {{ $rekod?->updated_at?->format('d/m/Y H:i') ?? '-' }}
                                @if ($rekod?->updatedBy)
                                    <div><small class="text-secondary">{{ $rekod->updatedBy->name }}</small></div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    </div>

    {{--
        ── No. Rujukan ──────────────────────────────────────────────────
        Diasingkan daripada jadual di atas kerana pemiliknya berbeza: setiap
        No. Rujukan dimasukkan oleh Pegawai Penyelaras Rekod, bukan oleh
        pegawai yang melaksanakan peringkatnya. Lajur "Direkod Oleh" di sini
        merujuk PPR, bukan pemilik peringkat.
    --}}
    <div class="report-card mb-4">

        <h4 class="section-title">No. Rujukan</h4>
        <p class="text-secondary">
            Setiap nombor rujukan direkodkan oleh Pegawai Penyelaras Rekod.
        </p>

        <div class="table-responsive-custom">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th scope="col">Dokumen</th>
                        <th scope="col">Peringkat</th>
                        <th scope="col">No. Rujukan</th>
                        <th scope="col">Direkod Oleh</th>
                        <th scope="col">Tarikh Direkod</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($berujukan as $kunci)
                        @php
                            $rekod = $peringkat->get($kunci);
                            $nombor = $rekod?->no_rujukan;
                        @endphp
                        <tr>
                            <td>{{ AliranKerja::labelRujukan($kunci) }}</td>
                            <td class="text-nowrap">
                                <span class="workflow-stage-tag">{{ $kunci }}</span>
                                {{ AliranKerja::label($kunci) }}
                            </td>
                            <td>
                                @if ($nombor === null || $nombor === '')
                                    <span class="text-secondary">Belum direkod</span>
                                @else
                                    <strong>{{ $nombor }}</strong>
                                @endif
                            </td>
                            <td>{{ $rekod?->noRujukanOleh?->name ?? '-' }}</td>
                            <td class="text-nowrap">{{ $rekod?->no_rujukan_pada?->format('d/m/Y H:i') ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    </div>

    <div class="row g-4 mb-4">

        {{-- ── Penugasan ───────────────────────────────────────────────── --}}
        <div class="col-lg-6">
            <div class="report-card h-100">

                <h4 class="section-title">Penugasan</h4>

                @if ($penugasan)
                    <dl class="entity-facts mb-0">
                        <dt>Pegawai Analisis</dt>
                        <dd>{{ $penugasan->assignedTo?->name ?? '-' }}</dd>

                        <dt>Ditugaskan Oleh</dt>
                        <dd>{{ $penugasan->assignedBy?->name ?? '-' }}</dd>

                        <dt>Tarikh Penugasan</dt>
                        <dd>{{ $penugasan->assigned_at?->format('d/m/Y H:i') ?? '-' }}</dd>

                        <dt>Status Penugasan</dt>
                        <dd>
                            <span class="status-badge {{ $penugasan->statusBadgeClass() }}">
                                {{ $penugasan->statusLabel() }}
                            </span>
                        </dd>
                    </dl>
                @else
                    <p class="text-secondary mb-0">
                        Entiti ini belum ditugaskan kepada mana-mana Pegawai Analisis.
                    </p>
                @endif

            </div>
        </div>

        {{-- ── Dapatan Analisis ────────────────────────────────────────── --}}
        <div class="col-lg-6">
            <div class="report-card h-100">

                <h4 class="section-title">Dapatan Analisis</h4>

                @if ($analisis)
                    <dl class="entity-facts mb-0">
                        <dt>Status Analisis</dt>
                        <dd>
                            <span class="status-badge {{ $analisis->selesai ? 'status-rendah' : 'status-sederhana' }}">
                                {{ $analisis->selesai ? 'Selesai' : 'Dalam Proses' }}
                            </span>
                        </dd>

                        <dt>Kod Rujukan</dt>
                        <dd>{{ $analisis->kod_rujukan ?? '-' }}</dd>

                        <dt>Status Laporan</dt>
                        <dd>{{ $analisis->status_laporan ?? '-' }}</dd>

                        <dt>Kemas Kini Terakhir</dt>
                        <dd>{{ $analisis->updated_at?->format('d/m/Y H:i') ?? '-' }}</dd>
                    </dl>
                @else
                    <p class="text-secondary mb-0">
                        Tiada dapatan analisis direkodkan untuk entiti ini.
                    </p>
                @endif

            </div>
        </div>

    </div>

    {{-- ── Laporan ─────────────────────────────────────────────────────── --}}
    <div class="report-card">

        <h4 class="section-title">Laporan</h4>

        <div class="table-responsive-custom">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th scope="col">Jenis Laporan</th>
                        <th scope="col">Status</th>
                        <th scope="col">Kemas Kini</th>
                        <th scope="col">Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (StatusLaporan::JENIS as $jenis => $nama)
                        @php
                            $rekod = $statusLaporan[$jenis] ?? null;
                            $nilai = $rekod['status'] ?? StatusLaporan::PAPARAN_BELUM_BERMULA;
                            $kelas = $rekod['kelas'] ?? StatusLaporan::badgePaparan($nilai);
                        @endphp
                        <tr>
                            <td>{{ $nama }}</td>
                            <td>
                                @if ($kelas)
                                    <span class="status-badge {{ $kelas }}">{{ $nilai }}</span>
                                @else
                                    <span class="text-secondary">{{ $nilai }}</span>
                                @endif
                            </td>
                            <td>{{ ($rekod['kemas_kini'] ?? null)?->format('d/m/Y H:i') ?? '-' }}</td>
                            <td>
                                @if ($jenis === 'inventori' && $analisis)
                                    <a class="btn btn-sm btn-outline-light"
                                        href="{{ route('laporan.inventori', $analisis) }}">
                                        <i class="bi bi-eye"></i> Papar
                                    </a>
                                @else
                                    <span class="text-secondary">-</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    </div>

@endsection