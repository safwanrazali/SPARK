@extends('layouts.app')

@section('title', 'Workflow — ' . $entiti['agency_name'])

@section('page-title', 'Workflow Entiti')

@section('content')

    <div class="report-card mb-4">

        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h4 class="section-title mb-1">{{ $entiti['agency_name'] }}</h4>
                <p class="text-secondary mb-0">
                    {{ $entiti['sector_name'] }} · Kod Entiti: {{ $entiti['agency_code'] }}
                </p>
            </div>
            <div class="entity-actions">
                <a href="{{ route('entiti.show', $entiti['agency_code']) }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-building"></i> Maklumat Entiti
                </a>
                <a href="{{ route('workflow.index') }}" class="btn btn-sm btn-outline-light">
                    <i class="bi bi-arrow-left"></i> Senarai Entiti
                </a>
            </div>
        </div>

    </div>

    <div class="report-card mb-4">

        <h4 class="section-title">Kemajuan Workflow</h4>

        <x-workflow-stepper :workflow="$workflow" class="mb-4" />

        @if ($workflow)
            <div class="row g-3 workflow-meta">
                <div class="col-md-3">
                    <div class="stat-title">Peringkat Semasa</div>
                    <div class="workflow-meta__value">
                        {{ sprintf('%02d', $workflow->current_stage) }} — {{ $workflow->stage_name }}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-title">Status</div>
                    <div class="workflow-meta__value">
                        <span class="status-badge {{ $workflow->statusBadgeClass() }}">{{ $workflow->status }}</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-title">Tarikh Status</div>
                    <div class="workflow-meta__value">{{ $workflow->status_since?->format('d/m/Y H:i') ?? '-' }}</div>
                </div>
                <div class="col-md-3">
                    <div class="stat-title">Dikemas Kini Oleh</div>
                    <div class="workflow-meta__value">{{ $workflow->updatedBy?->name ?? '-' }}</div>
                </div>
            </div>

            @if ($workflow->notes)
                <p class="text-secondary mt-3 mb-0">
                    <strong>Catatan terakhir:</strong> {{ $workflow->notes }}
                </p>
            @endif
        @else
            <p class="text-secondary mb-0">
                Entiti ini belum didaftarkan dalam workflow. Pendaftaran akan bermula
                pada peringkat 01 — {{ \App\Models\WorkflowStatus::getStageName(1) }}.
            </p>
        @endif

    </div>

    @can('manage-workflow')
        <div class="report-card mb-4">

            <h4 class="section-title">Kemas Kini Workflow</h4>

            @if (! $workflow)
                <p class="text-secondary">
                    Daftarkan entiti ini untuk mula memantau kemajuannya.
                </p>
                <form action="{{ route('workflow.mula', $entiti['agency_code']) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-play-circle"></i> Daftar Dalam Workflow
                    </button>
                </form>
            @else
                <div class="row g-4">

                    {{-- Maju satu peringkat sahaja: 01 → 02 → … → 07 --}}
                    <div class="col-lg-4">
                        <h6 class="workflow-action__title">Peringkat Seterusnya</h6>

                        @if ($workflow->isComplete())
                            <p class="text-secondary mb-0">
                                Entiti telah berada pada peringkat terakhir
                                (07 — {{ \App\Models\WorkflowStatus::getStageName(7) }}).
                            </p>
                        @else
                            @php $seterusnya = $workflow->getNextStage(); @endphp
                            <p class="text-secondary">
                                {{ sprintf('%02d', $seterusnya) }} —
                                {{ \App\Models\WorkflowStatus::getStageName($seterusnya) }}
                            </p>
                            <form action="{{ route('workflow.peringkat', $entiti['agency_code']) }}" method="POST">
                                @csrf
                                <input type="hidden" name="to_stage" value="{{ $seterusnya }}">
                                <label class="form-label" for="status_maju">Status Peringkat Baharu</label>
                                <select id="status_maju" name="status" class="form-select mb-3">
                                    @foreach (\App\Models\WorkflowStatus::STATUSES as $status)
                                        <option value="{{ $status }}"
                                            @selected($status === \App\Models\WorkflowStatus::DEFAULT_STATUS)>
                                            {{ $status }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-arrow-right-circle"></i> Majukan Peringkat
                                </button>
                            </form>
                        @endif
                    </div>

                    {{-- Kembali ke peringkat sebelumnya — sebab adalah wajib. --}}
                    <div class="col-lg-4">
                        <h6 class="workflow-action__title">Kembali Ke Peringkat Sebelumnya</h6>

                        @if ($workflow->current_stage <= \App\Models\WorkflowStatus::FIRST_STAGE)
                            <p class="text-secondary mb-0">
                                Tiada peringkat sebelum peringkat 01.
                            </p>
                        @else
                            <form action="{{ route('workflow.peringkat', $entiti['agency_code']) }}" method="POST">
                                @csrf
                                <label class="form-label" for="to_stage_undur">Peringkat</label>
                                <select id="to_stage_undur" name="to_stage" class="form-select mb-3" required>
                                    @for ($i = \App\Models\WorkflowStatus::FIRST_STAGE; $i < $workflow->current_stage; $i++)
                                        <option value="{{ $i }}">
                                            {{ sprintf('%02d', $i) }} — {{ \App\Models\WorkflowStatus::getStageName($i) }}
                                        </option>
                                    @endfor
                                </select>
                                <label class="form-label" for="reason">Sebab (wajib)</label>
                                <textarea id="reason" name="reason" class="form-control mb-3" rows="2" required
                                    placeholder="Nyatakan sebab entiti dikembalikan">{{ old('reason') }}</textarea>
                                <button type="submit" class="btn btn-outline-light">
                                    <i class="bi bi-arrow-counterclockwise"></i> Kembalikan Peringkat
                                </button>
                            </form>
                        @endif
                    </div>

                    {{-- Status dalam peringkat semasa (tanpa menukar peringkat). --}}
                    <div class="col-lg-4">
                        <h6 class="workflow-action__title">Status Peringkat Semasa</h6>
                        <p class="text-secondary">
                            Kitaran: {{ implode(' → ', \App\Models\WorkflowStatus::STATUSES) }}
                        </p>
                        <form action="{{ route('workflow.status', $entiti['agency_code']) }}" method="POST">
                            @csrf
                            <label class="form-label" for="status_semasa">Status</label>
                            <select id="status_semasa" name="status" class="form-select mb-3" required>
                                @foreach (\App\Models\WorkflowStatus::STATUSES as $status)
                                    <option value="{{ $status }}" @selected($status === $workflow->status)>
                                        {{ $status }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check2-circle"></i> Kemas Kini Status
                            </button>
                        </form>
                    </div>

                </div>
            @endif

        </div>
    @endcan

    <div class="report-card">

        <h4 class="section-title">Sejarah Peringkat</h4>
        <p class="text-secondary">
            Setiap perubahan direkodkan bersama pegawai dan masa untuk tujuan jejak audit.
        </p>

        <div class="table-responsive-custom">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th scope="col">Tarikh &amp; Masa</th>
                        <th scope="col">Tindakan</th>
                        <th scope="col">Dari</th>
                        <th scope="col">Kepada</th>
                        <th scope="col">Oleh</th>
                        <th scope="col">Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sejarah as $log)
                        <tr>
                            <td>{{ $log->changed_at?->format('d/m/Y H:i') }}</td>
                            <td>{{ $log->getActionLabel() }}</td>
                            <td>
                                @if ($log->action === \App\Services\WorkflowTransitionService::ACTION_STAGE_CHANGED)
                                    {{ sprintf('%02d', $log->old_value) }} —
                                    {{ $log->metadata['from_stage_name'] ?? '' }}
                                @else
                                    {{ $log->old_value ?? '-' }}
                                @endif
                            </td>
                            <td>
                                @if ($log->action === \App\Services\WorkflowTransitionService::ACTION_STATUS_UPDATED)
                                    {{ $log->new_value }}
                                @else
                                    {{ sprintf('%02d', $log->new_value) }} —
                                    {{ $log->metadata['to_stage_name'] ?? '' }}
                                @endif
                            </td>
                            <td>{{ $log->changedBy?->name ?? '-' }}</td>
                            <td>{{ $log->metadata['reason'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <x-empty-state colspan="6" icon="bi-clock-history" title="Tiada perubahan peringkat">
                            Sejarah muncul apabila entiti didaftarkan atau peringkatnya dikemas kini.
                        </x-empty-state>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>

@endsection
