@extends('layouts.app')

@section('title', 'Kemajuan Workflow Entiti')

@section('page-title', 'Kemajuan Workflow Entiti')

@section('content')

    <div class="report-card mb-4">

        <h4 class="section-title">7 Peringkat Pemantauan</h4>
        <p class="text-secondary">
            Setiap entiti dipantau melalui tujuh peringkat berturutan, daripada
            Penerimaan &amp; Pendaftaran Data sehingga Penyerahan &amp; Penutupan.
        </p>

        <x-workflow-stepper class="mb-4" />

        <form action="{{ route('workflow.index') }}" method="GET" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label" for="sector_code">Pilih Sektor</label>
                <select id="sector_code" name="sector_code" class="form-select">
                    <option value="">-- Entiti dipantau sahaja --</option>
                    @foreach ($sektor as $kod => $s)
                        <option value="{{ $kod }}" @selected($sectorCode === $kod)>{{ $s['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-funnel"></i> Papar Entiti
                </button>
                @if ($sectorCode)
                    <a href="{{ route('workflow.index') }}" class="btn btn-outline-light">Set Semula</a>
                @endif
            </div>
        </form>

    </div>

    <div class="report-card">

        <h4 class="section-title">Kedudukan Semasa Entiti</h4>
        <p class="text-secondary">
            {{ $jumlahDidaftar }} entiti telah didaftarkan dalam workflow.
            @if (! $sectorCode)
                Pilih sektor di atas untuk melihat keseluruhan entiti dalam sektor tersebut.
            @endif
        </p>

        <div class="table-responsive-custom">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th>Entiti</th>
                        <th>Peringkat Semasa</th>
                        <th>Status</th>
                        <th>Tarikh Status</th>
                        <th>Dikemas Kini Oleh</th>
                        <th>Kemajuan</th>
                        <th>Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entiti as $e)
                        @php $w = $e['workflow']; @endphp
                        <tr>
                            <td>
                                <strong>{{ $e['agency_name'] }}</strong><br>
                                <span class="text-secondary">{{ $e['sector_name'] }} · {{ $e['agency_code'] }}</span>
                            </td>
                            <td>
                                @if ($w)
                                    <span class="workflow-stage-tag">{{ sprintf('%02d', $w->current_stage) }}</span>
                                    {{ $w->stage_name }}
                                @else
                                    <span class="text-secondary">Belum Didaftarkan</span>
                                @endif
                            </td>
                            <td>
                                @if ($w)
                                    <span class="status-badge {{ $w->statusBadgeClass() }}">{{ $w->status }}</span>
                                @else
                                    <span class="status-badge status-tinggi">Belum Bermula</span>
                                @endif
                            </td>
                            <td>{{ $w?->status_since?->format('d/m/Y H:i') ?? '-' }}</td>
                            <td>{{ $w?->updatedBy?->name ?? '-' }}</td>
                            <td class="workflow-progress-cell">
                                <div class="workflow-progress" role="img"
                                    aria-label="Kemajuan {{ $w?->progressPercentage() ?? 0 }} peratus">
                                    <span style="width: {{ $w?->progressPercentage() ?? 0 }}%"></span>
                                </div>
                                <small class="text-secondary">{{ $w?->current_stage ?? 0 }}/7</small>
                            </td>
                            <td>
                                <a class="btn btn-sm btn-primary"
                                    href="{{ route('workflow.show', $e['agency_code']) }}">
                                    <i class="bi bi-diagram-3"></i> Workflow
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center">
                                Tiada entiti dipantau lagi. Pilih sektor di atas untuk mendaftarkan
                                entiti ke dalam workflow.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $entiti->links() }}</div>

    </div>

@endsection
