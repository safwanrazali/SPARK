@extends('layouts.app')

@section('title', $entiti['agency_code'])

@section('page-title', 'Maklumat Entiti')

@section('content')

    {{-- ── Maklumat Entiti ─────────────────────────────────────────────── --}}
    <div class="report-card mb-4">

        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">

            <div>
                <h4 class="section-title mb-1">{{ $entiti['agency_code'] }}</h4>
                <p class="text-secondary mb-0">
                    Sektor {{ $entiti['sector_code'] }}
                </p>
            </div>

            {{-- Tindakan yang tersedia — hanya yang dibenarkan bagi peranan semasa. --}}
            <div class="entity-actions">
                <a href="{{ route('workflow.show', $entiti['agency_code']) }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-diagram-3"></i> Workflow
                </a>

                @can('manage-assignment')
                    <a href="{{ route('penugasan.show', $entiti['agency_code']) }}" class="btn btn-sm btn-outline-light">
                        <i class="bi bi-person-check"></i> Penugasan
                    </a>
                @endcan

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
            </div>

        </div>

    </div>

    {{-- ── Ringkasan kedudukan semasa ──────────────────────────────────── --}}
    <div class="report-card mb-4">

        <h4 class="section-title">Kedudukan Semasa</h4>

        <div class="row g-3 workflow-meta">
            <div class="col-md-3">
                <div class="stat-title">Peringkat Semasa</div>
                <div class="workflow-meta__value">
                    @if ($workflow)
                        {{ sprintf('%02d', $workflow->current_stage) }} — {{ $workflow->stage_name }}
                    @else
                        <span class="text-secondary">Belum Didaftarkan</span>
                    @endif
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-title">Status</div>
                <div class="workflow-meta__value">
                    @if ($workflow)
                        <span class="status-badge {{ $workflow->statusBadgeClass() }}">{{ $workflow->status }}</span>
                    @else
                        <span class="status-badge status-tinggi">Belum Bermula</span>
                    @endif
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-title">Tarikh Status</div>
                <div class="workflow-meta__value">{{ $workflow?->status_since?->format('d/m/Y H:i') ?? '-' }}</div>
            </div>
            <div class="col-md-3">
                <div class="stat-title">Pegawai Analisis</div>
                <div class="workflow-meta__value">
                    {{ $penugasan?->assignedTo?->name ?? '-' }}
                </div>
            </div>
        </div>

    </div>

    {{-- ── Kemajuan Analisis ───────────────────────────────────────────── --}}
    <div class="report-card mb-4">

        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
            <h4 class="section-title mb-0">Kemajuan Analisis</h4>
            @if ($workflow)
                <span class="text-secondary">
                    Peringkat {{ $workflow->current_stage }} daripada {{ \App\Models\WorkflowStatus::LAST_STAGE }}
                    ({{ $workflow->progressPercentage() }}%)
                </span>
            @endif
        </div>

        <x-workflow-stepper :workflow="$workflow" :peringkat="$peringkat" class="mb-2" />

        @if ($workflow)
            <p class="text-secondary mb-0">
                Dikemas kini oleh {{ $workflow->updatedBy?->name ?? '-' }}
                @if ($workflow->notes)
                    · Catatan terakhir: {{ $workflow->notes }}
                @endif
            </p>
        @else
            <p class="text-secondary mb-0">
                Entiti ini belum didaftarkan dalam workflow 7 peringkat.
            </p>
        @endif

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
    <div class="report-card mb-4">

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
                    @foreach (\App\Models\StatusLaporan::JENIS as $jenis => $nama)
                        @php
                            $rekod = $statusLaporan->get($jenis);
                            $nilai = $rekod?->status ?? 'Belum Bermula';
                            $kelas =
                                ['Siap' => 'status-rendah', 'Dalam Proses' => 'status-sederhana'][$nilai] ??
                                'status-tinggi';
                        @endphp
                        <tr>
                            <td>{{ $nama }}</td>
                            <td><span class="status-badge {{ $kelas }}">{{ $nilai }}</span></td>
                            <td>{{ $rekod?->updated_at?->format('d/m/Y H:i') ?? '-' }}</td>
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

    {{-- ── Sejarah ─────────────────────────────────────────────────────── --}}
    <div class="report-card">

        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h4 class="section-title">Sejarah</h4>
                <p class="text-secondary">
                    Perubahan penting bagi entiti ini, terbaharu di atas.
                </p>
            </div>
            @can('view-audit-trail')
                <a href="{{ route('audit.index', ['agency_code' => $entiti['agency_code']]) }}"
                    class="btn btn-sm btn-outline-light">
                    <i class="bi bi-shield-check"></i> Jejak Audit Penuh
                </a>
            @endcan
        </div>

        <div class="table-responsive-custom">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th scope="col">Tarikh &amp; Masa</th>
                        <th scope="col">Tindakan</th>
                        <th scope="col">Dari</th>
                        <th scope="col">Kepada</th>
                        <th scope="col">Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sejarah as $log)
                        <tr>
                            <td>{{ $log->changed_at?->format('d/m/Y H:i') }}</td>
                            <td>{{ $log->getActionLabel() }}</td>
                            <td>{{ $log->old_value ?? '-' }}</td>
                            <td>{{ $log->new_value ?? '-' }}</td>
                            <td>{{ $log->changedBy?->name ?? '-' }}</td>
                        </tr>
                    @empty
                        <x-empty-state colspan="5" icon="bi-clock-history" title="Tiada sejarah">
                            Perubahan peringkat workflow dan penugasan akan dipaparkan di sini.
                        </x-empty-state>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $sejarah->links() }}</div>

    </div>

@endsection
