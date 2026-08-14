@extends('layouts.app')

@section('title', 'Penugasan — ' . $entiti['agency_name'])

@section('page-title', 'Penugasan Entiti')

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
                <a href="{{ route('penugasan.index', ['sector_code' => $entiti['sector_code']]) }}"
                    class="btn btn-sm btn-outline-light">
                    <i class="bi bi-arrow-left"></i> Senarai Entiti
                </a>
            </div>
        </div>

    </div>

    <div class="report-card mb-4">

        <h4 class="section-title">Penugasan Semasa</h4>

        @if ($aktif)
            <div class="row g-3 workflow-meta">
                <div class="col-md-3">
                    <div class="stat-title">Pegawai Analisis</div>
                    <div class="workflow-meta__value">{{ $aktif->assignedTo?->name ?? '-' }}</div>
                </div>
                <div class="col-md-3">
                    <div class="stat-title">Ditugaskan Oleh</div>
                    <div class="workflow-meta__value">{{ $aktif->assignedBy?->name ?? '-' }}</div>
                </div>
                <div class="col-md-3">
                    <div class="stat-title">Tarikh Penugasan</div>
                    <div class="workflow-meta__value">{{ $aktif->assigned_at?->format('d/m/Y H:i') ?? '-' }}</div>
                </div>
                <div class="col-md-3">
                    <div class="stat-title">Status</div>
                    <div class="workflow-meta__value">
                        <span class="status-badge {{ $aktif->statusBadgeClass() }}">{{ $aktif->statusLabel() }}</span>
                    </div>
                </div>
            </div>

            @if ($aktif->notes)
                <p class="text-secondary mt-3 mb-0"><strong>Catatan:</strong> {{ $aktif->notes }}</p>
            @endif
        @else
            <p class="text-secondary mb-0">Entiti ini belum ditugaskan kepada mana-mana Pegawai Analisis.</p>
        @endif

    </div>

    <div class="report-card mb-4">

        <h4 class="section-title">{{ $aktif ? 'Tukar Ganti Penugasan' : 'Tugaskan Pegawai Analisis' }}</h4>

        @if ($analysts->isEmpty())
            <p class="text-secondary mb-0">
                Tiada Pegawai Analisis berdaftar. Tambah pengguna dengan peranan Pegawai Analisis
                melalui modul Pentadbiran terlebih dahulu.
            </p>
        @else
            <div class="row g-4">

                <div class="col-lg-6">
                    <form action="{{ route('penugasan.simpan', $entiti['agency_code']) }}" method="POST">
                        @csrf
                        <label class="form-label" for="assigned_to_user_id">Pegawai Analisis</label>
                        <select id="assigned_to_user_id" name="assigned_to_user_id" class="form-select mb-3" required>
                            <option value="">-- Pilih Pegawai --</option>
                            @foreach ($analysts as $analyst)
                                <option value="{{ $analyst->id }}"
                                    @disabled($aktif && $aktif->assigned_to_user_id === $analyst->id)>
                                    {{ $analyst->name }}
                                    @if ($aktif && $aktif->assigned_to_user_id === $analyst->id)
                                        (penugasan semasa)
                                    @endif
                                </option>
                            @endforeach
                        </select>

                        <label class="form-label" for="notes">Catatan (pilihan)</label>
                        <textarea id="notes" name="notes" class="form-control mb-3" rows="2"
                            placeholder="Contoh: sebab penukaran pegawai">{{ old('notes') }}</textarea>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-person-check"></i>
                            {{ $aktif ? 'Tukar Ganti Pegawai' : 'Tugaskan Pegawai' }}
                        </button>
                    </form>
                </div>

                @if ($aktif)
                    <div class="col-lg-6">
                        <form action="{{ route('penugasan.tarik', $entiti['agency_code']) }}" method="POST">
                            @csrf
                            <label class="form-label" for="reason">Tarik Balik Penugasan</label>
                            <textarea id="reason" name="reason" class="form-control mb-3" rows="2"
                                placeholder="Sebab penarikan (pilihan)">{{ old('reason') }}</textarea>
                            <button type="submit" class="btn btn-outline-light">
                                <i class="bi bi-person-dash"></i> Tarik Balik
                            </button>
                        </form>
                    </div>
                @endif

            </div>
        @endif

    </div>

    <div class="report-card">

        <h4 class="section-title">Sejarah Penugasan</h4>
        <p class="text-secondary">
            Rekod penugasan lama dikekalkan apabila entiti ditukar ganti atau ditarik balik.
        </p>

        <div class="table-responsive-custom">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th scope="col">Tarikh</th>
                        <th scope="col">Pegawai Analisis</th>
                        <th scope="col">Ditugaskan Oleh</th>
                        <th scope="col">Status</th>
                        <th scope="col">Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sejarah as $rekod)
                        <tr>
                            <td>{{ $rekod->assigned_at?->format('d/m/Y H:i') }}</td>
                            <td>{{ $rekod->assignedTo?->name ?? '-' }}</td>
                            <td>{{ $rekod->assignedBy?->name ?? '-' }}</td>
                            <td>
                                <span class="status-badge {{ $rekod->statusBadgeClass() }}">
                                    {{ $rekod->statusLabel() }}
                                </span>
                            </td>
                            <td>{{ $rekod->notes ?? '-' }}</td>
                        </tr>
                    @empty
                        <x-empty-state colspan="5" icon="bi-clock-history" title="Tiada sejarah penugasan">
                            Rekod muncul apabila entiti ditugaskan, ditukar ganti atau ditarik balik.
                        </x-empty-state>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>

@endsection
