@extends('layouts.app')

@section('title', 'Penugasan Entiti')

@section('page-title', 'Penugasan Entiti')

@section('content')

    <div class="report-card mb-4">

        <h4 class="section-title">Pilih Sektor</h4>
        <p class="text-secondary">
            Pilih sektor untuk memaparkan semua entiti di bawahnya, kemudian tugaskan
            entiti kepada Pegawai Analisis. Setiap entiti hanya boleh mempunyai satu
            penugasan aktif pada satu masa.
        </p>

        <form action="{{ route('penugasan.index') }}" method="GET" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label" for="sector_code">Sektor</label>
                <select id="sector_code" name="sector_code" class="form-select">
                    <option value="">-- Entiti yang telah ditugaskan sahaja --</option>
                    @foreach (config('sektor') as $kod => $sektor)
                        <option value="{{ $kod }}" @selected($sectorCode === $kod)>{{ $kod }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-funnel"></i> Papar Entiti
                </button>
                @if ($sectorCode)
                    <a href="{{ route('penugasan.index') }}" class="btn btn-outline-light">Set Semula</a>
                @endif
            </div>
        </form>

    </div>

    @if ($analysts->isEmpty())
        <x-alert type="warning" title="Tiada Pegawai Analisis berdaftar">
            Tambah pengguna dengan peranan Pegawai Analisis melalui modul Pentadbiran
            sebelum membuat penugasan.
        </x-alert>
    @endif

    <div class="report-card">

        <h4 class="section-title">Senarai Entiti</h4>
        <p class="text-secondary">{{ $jumlahAktif }} penugasan aktif dalam sistem.</p>

        <div class="table-responsive-custom">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th scope="col">Entiti</th>
                        <th scope="col">Pegawai Analisis</th>
                        <th scope="col">Ditugaskan Oleh</th>
                        <th scope="col">Tarikh Penugasan</th>
                        <th scope="col">Tugaskan / Tukar Ganti</th>
                        <th scope="col">Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entiti as $e)
                        @php $p = $e['penugasan']; @endphp
                        <tr>
                            <td>
                                <strong>{{ $e['agency_code'] }}</strong><br>
                                <span class="text-secondary">Sektor {{ $e['sector_code'] }}</span>
                            </td>
                            <td>
                                @if ($p)
                                    <span class="status-badge status-rendah">{{ $p->assignedTo?->name }}</span>
                                @else
                                    <span class="status-badge status-tinggi">Belum Ditugaskan</span>
                                @endif
                            </td>
                            <td>{{ $p?->assignedBy?->name ?? '-' }}</td>
                            <td>{{ $p?->assigned_at?->format('d/m/Y H:i') ?? '-' }}</td>
                            <td>
                                <form action="{{ route('penugasan.simpan', $e['agency_code']) }}" method="POST"
                                    class="assignment-form">
                                    @csrf
                                    <select name="assigned_to_user_id" class="form-select form-select-sm" required
                                        @disabled($analysts->isEmpty())>
                                        <option value="">-- Pilih Pegawai --</option>
                                        @foreach ($analysts as $analyst)
                                            <option value="{{ $analyst->id }}"
                                                @disabled($p && $p->assigned_to_user_id === $analyst->id)>
                                                {{ $analyst->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-primary"
                                        @disabled($analysts->isEmpty())>
                                        <i class="bi bi-person-check"></i>
                                        {{ $p ? 'Tukar' : 'Tugaskan' }}
                                    </button>
                                </form>
                            </td>
                            <td class="text-nowrap">
                                <a class="btn btn-sm btn-outline-light"
                                    href="{{ route('entiti.show', $e['agency_code']) }}"
                                    title="Maklumat entiti {{ $e['agency_code'] }}"
                                    aria-label="Maklumat entiti {{ $e['agency_code'] }}">
                                    <i class="bi bi-building" aria-hidden="true"></i>
                                </a>
                                <a class="btn btn-sm btn-outline-light"
                                    href="{{ route('penugasan.show', $e['agency_code']) }}">
                                    <i class="bi bi-clock-history"></i> Sejarah
                                </a>
                                @if ($p)
                                    <form action="{{ route('penugasan.tarik', $e['agency_code']) }}" method="POST"
                                        class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-light"
                                            title="Tarik balik penugasan {{ $e['agency_code'] }}"
                                            aria-label="Tarik balik penugasan {{ $e['agency_code'] }}">
                                            <i class="bi bi-person-dash" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-empty-state colspan="6" icon="bi-person-check" title="Tiada entiti ditugaskan">
                            Pilih sektor di atas untuk memaparkan entiti dan menugaskannya kepada Pegawai Analisis.
                        </x-empty-state>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $entiti->links() }}</div>

    </div>

@endsection
