@extends('layouts.app')

@section('title', 'Kemajuan Workflow Entiti')

@section('page-title', 'Kemajuan Workflow')

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
                        <option value="{{ $kod }}" @selected($sectorCode === $kod)>{{ $kod }}</option>
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
                        <th scope="col">Entiti</th>
                        <th scope="col">Peringkat Semasa</th>
                        <th scope="col">Status</th>
                        <th scope="col">Tarikh Status</th>
                        <th scope="col">Dikemas Kini Oleh</th>
                        <th scope="col">Kemajuan</th>
                        <th scope="col">Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entiti as $e)
                        @php $w = $e['workflow']; @endphp
                        <tr>
                            <td>
                                <strong>{{ $e['agency_code'] }}</strong><br>
                                <span class="text-secondary text-nowrap">Sektor {{ $e['sector_code'] }}</span>
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
                                    <span style="--progress: {{ $w?->progressPercentage() ?? 0 }}%"></span>
                                </div>
                                <small class="text-secondary">{{ $w?->current_stage ?? 0 }}/7</small>
                            </td>
                            <td class="text-nowrap">
                                <a class="btn btn-sm btn-primary"
                                    href="{{ route('entiti.show', $e['agency_code']) }}">
                                    <i class="bi bi-building"></i> Entiti
                                </a>
                                <a class="btn btn-sm btn-outline-light"
                                    href="{{ route('workflow.show', $e['agency_code']) }}">
                                    <i class="bi bi-diagram-3"></i> Workflow
                                </a>
                            </td>
                        </tr>
                    @empty
                        <x-empty-state colspan="7" icon="bi-diagram-3" title="Tiada entiti dipantau">
                            Pilih sektor di atas untuk memaparkan entiti dan mendaftarkannya ke dalam workflow.
                        </x-empty-state>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $entiti->links() }}</div>

    </div>

@endsection
