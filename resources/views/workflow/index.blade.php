@extends('layouts.app')

@section('title', 'Kemajuan Analisis Entiti')

@section('page-title', 'Kemajuan Analisis')

@section('content')

    <div class="report-card mb-4">

        <h4 class="section-title">7 Peringkat Kemajuan Analisis</h4>
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
            {{ $jumlahDidaftar }} entiti telah didaftarkan dalam Kemajuan Analisis.
            @if (!$sectorCode)
                Pilih sektor di atas untuk melihat keseluruhan entiti dalam sektor tersebut.
            @endif
        </p>

        @php
            /*
             * Pegawai Penyelaras Rekod memerhati Kemajuan Analisis Entiti
             * sahaja — tiada satu pun tindakan peringkat miliknya, jadi lajur
             * Tindakan digugurkan sepenuhnya daripada paparannya. Ini keputusan
             * paparan; kebenaran sebenar tetap dikuatkuasakan oleh gate dan
             * middleware `entity.access` pada setiap route.
             */
            $adaTindakan = !auth()->user()->isPegawaiPenyelarasRekod();
        @endphp

        <div class="table-responsive-custom">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th scope="col">Entiti</th>
                        <th scope="col">Pegawai Analisis (PA)</th>
                        <th scope="col">Peringkat Semasa</th>
                        <th scope="col">Status Keseluruhan</th>
                        <th scope="col">Status Laporan</th>
                        <th scope="col">Kemajuan</th>
                        @if ($adaTindakan)
                            <th scope="col">Tindakan</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @php
                        $jumlahPeringkat = count(\App\Models\WorkflowStatus::WORKFLOW_STAGES);

                        $kemajuanServis = app(\App\Services\KemajuanAnalisisService::class);

                        $laporanBerkenaan = fn(?\Illuminate\Support\Collection $peringkat): bool
                            => $kemajuanServis->statusLaporanBerkenaan($peringkat);

                        $badgeKeseluruhan = fn(string $nilai): string => match ($nilai) {
                            \App\Services\KemajuanAnalisisService::KESELURUHAN_SIAP => 'status-rendah',
                            \App\Services\KemajuanAnalisisService::KESELURUHAN_DALAM_PROSES => 'status-sederhana',
                            default => 'status-tinggi',
                        };
                    @endphp

                    @forelse ($entiti as $e)
                        @php
                            // "Berdaftar" bermaksud peringkat 01 Selesai —
                            // bukan sekadar mempunyai baris peringkat, yang
                            // kekal walaupun selepas Ketua Bahagian menetapkan
                            // semula entiti.
                            $didaftar = $e['peringkat']?->get(\App\Models\WorkflowStatus::STAGE_PENDAFTARAN)?->isSelesai() ?? false;
                            $peratus = $didaftar ? round(($e['bilanganSelesai'] / $jumlahPeringkat) * 100) : 0;
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $e['agency_code'] }}</strong><br>
                                <span class="text-secondary text-nowrap">Sektor {{ $e['sector_code'] }}</span>
                            </td>
                            <td>
                                @if ($e['penugasan'])
                                    <span class="status-badge status-rendah">{{ $e['penugasan']->assignedTo?->name }}</span>
                                @else
                                    <span class="status-badge status-tinggi">Belum Ditugaskan</span>
                                @endif
                            </td>
                            <td>
                                @if ($didaftar)
                                    <span class="workflow-stage-tag">{{ sprintf('%02d', $e['peringkatSemasa']) }}</span>
                                    {{ \App\Models\WorkflowStatus::getStageName($e['peringkatSemasa']) }}
                                @else
                                    <span class="text-secondary">Belum Didaftarkan</span>
                                @endif
                            </td>
                            <td>
                                <span class="status-badge {{ $badgeKeseluruhan($e['keseluruhan']) }}">
                                    {{ $e['keseluruhan'] }}
                                </span>
                            </td>
                            <td>
                                {{-- Lajur tidak boleh hilang bagi satu baris
                                     sahaja, jadi entiti yang belum sampai ke
                                     peringkat 05 memaparkan sengkang dan bukan
                                     status laporan yang belum berkenaan. --}}
                                @if ($laporanBerkenaan($e['peringkat']))
                                    <span
                                        class="status-badge {{ \App\Models\LaporanSemakan::badgePaparan($e['laporan']) }}">
                                        {{ \App\Models\LaporanSemakan::paparanUntuk($e['laporan']) }}
                                    </span>
                                @else
                                    <span class="text-secondary">&mdash;</span>
                                @endif
                            </td>
                            <td class="workflow-progress-cell">
                                <div class="workflow-progress" role="img"
                                    aria-label="Kemajuan {{ $peratus }} peratus">
                                    <span style="--progress: {{ $peratus }}%"></span>
                                </div>
                                <small class="text-secondary">{{ $e['bilanganSelesai'] }}/{{ $jumlahPeringkat }}</small>
                            </td>
                            @if ($adaTindakan)
                                <td class="text-nowrap">
                                    <a class="btn btn-sm btn-primary" href="{{ route('entiti.show', $e['agency_code']) }}">
                                        <i class="bi bi-building"></i> Entiti
                                    </a>
                                    <a class="btn btn-sm btn-outline-light"
                                        href="{{ route('workflow.show', $e['agency_code']) }}">
                                        <i class="bi bi-diagram-3"></i> Kemajuan
                                    </a>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <x-empty-state :colspan="$adaTindakan ? 7 : 6" icon="bi-diagram-3" title="Tiada entiti dipantau">
                            Pilih sektor di atas untuk memaparkan entiti dan mendaftarkannya ke dalam workflow.
                        </x-empty-state>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $entiti->links() }}</div>

    </div>

@endsection
