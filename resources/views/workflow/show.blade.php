@extends('layouts.app')

@section('title', 'Kemajuan Analisis Entiti — ' . $entiti['agency_code'])

@section('page-title', 'Kemajuan Analisis Entiti')

@section('content')

    @php
        use App\Models\LaporanSemakan;
        use App\Models\WorkflowStageStatus;
        use App\Models\WorkflowStatus;
        use App\Services\KemajuanAnalisisService;

        $pengguna = auth()->user();

        $didaftar = $peringkat->isNotEmpty();

        $bolehPA = $pengguna->can('advance-analysis-stage');
        $bolehSemak = $pengguna->can('review-report');
        $bolehLulus = $pengguna->can('approve-report');
        $bolehSerah = $pengguna->can('submit-to-nacsa');

        $status = fn (int $stage): string => $peringkat->get($stage)?->status ?? WorkflowStageStatus::BELUM_MULA;
        $selesai = fn (int $stage): bool => $status($stage) === WorkflowStageStatus::SELESAI;

        // Satu peringkat "terbuka" apabila pendahulunya telah Selesai.
        $terbuka = fn (int $stage): bool => $stage === WorkflowStatus::FIRST_STAGE || $selesai($stage - 1);

        $analisisLengkap = (bool) ($analisis?->selesai);
        $statusLaporan = $laporan?->status;

        $badgeKeseluruhan = match ($keseluruhan) {
            KemajuanAnalisisService::KESELURUHAN_SIAP => 'status-rendah',
            KemajuanAnalisisService::KESELURUHAN_DALAM_PROSES => 'status-sederhana',
            default => 'status-tinggi',
        };

        $jumlahPeringkat = count(WorkflowStatus::WORKFLOW_STAGES);
    @endphp

    <div class="report-card mb-4">

        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h4 class="section-title mb-1">{{ $entiti['agency_code'] }}</h4>
                <p class="text-secondary mb-0">Sektor {{ $entiti['sector_code'] }}</p>
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

    {{-- Stepper mendatar: kedudukan sekilas pandang, dikongsi dengan modul
         pemantauan sedia ada. Senarai menegak di bawah ialah ruang kerja. --}}
    <div class="report-card mb-4">
        <h4 class="section-title">Peringkat Workflow</h4>
        <x-workflow-stepper :workflow="$workflow" />
    </div>

    @if (! $didaftar)

        <div class="report-card">
            <h4 class="section-title">Belum Memasuki Aliran Kerja</h4>
            <p class="text-secondary mb-0">
                Entiti ini belum didaftarkan dalam workflow kerana
                <strong>Penerimaan &amp; Pendaftaran Data</strong> belum Selesai.
                Pegawai Penyelaras Rekod perlu menandakannya melalui skrin
                Penetapan Entiti sebelum kemajuan analisis boleh bermula.
            </p>
        </div>

    @else

        <div class="report-card mb-4">

            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                <h4 class="section-title mb-0">Ringkasan Kemajuan</h4>
                <span class="status-badge {{ $badgeKeseluruhan }}">{{ $keseluruhan }}</span>
            </div>

            <div class="row g-3 workflow-meta">
                <div class="col-md-4">
                    <div class="stat-title">Peringkat Semasa</div>
                    <div class="workflow-meta__value">
                        {{ sprintf('%02d', $peringkatSemasa) }} —
                        {{ WorkflowStatus::getStageName($peringkatSemasa) }}
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-title">Peringkat Selesai</div>
                    <div class="workflow-meta__value">{{ $bilanganSelesai }} / {{ $jumlahPeringkat }}</div>
                </div>
                <div class="col-md-4">
                    <div class="stat-title">Status Laporan</div>
                    <div class="workflow-meta__value">
                        @if ($laporan)
                            <span class="status-badge {{ $laporan->statusBadgeClass() }}">{{ $laporan->status }}</span>
                        @else
                            <span class="text-secondary">Belum Dijana</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="workflow-progress mt-3" role="img"
                aria-label="Kemajuan {{ round(($bilanganSelesai / $jumlahPeringkat) * 100) }} peratus">
                <span style="--progress: {{ round(($bilanganSelesai / $jumlahPeringkat) * 100) }}%"></span>
            </div>

            @if ($laporan?->status === LaporanSemakan::DIKEMBALIKAN && $laporan->catatan)
                <x-alert type="warning" title="Laporan dikembalikan" class="mt-3">
                    {{ $laporan->catatan }}
                </x-alert>
            @endif

        </div>

        <div class="report-card mb-4">

            <h4 class="section-title">Peringkat Kemajuan</h4>
            <p class="text-secondary">
                Setiap peringkat hanya boleh ditandakan Selesai setelah peringkat sebelumnya Selesai.
                Tindakan yang tidak dibenarkan bagi peranan anda tidak dipaparkan.
            </p>

            <ol class="kemajuan-list">

                {{-- 01 — Penerimaan & Pendaftaran Data (PPR, skrin Penetapan Entiti) --}}
                <x-kemajuan-peringkat :nombor="WorkflowStatus::STAGE_PENDAFTARAN"
                    :nama="WorkflowStatus::getStageName(WorkflowStatus::STAGE_PENDAFTARAN)"
                    :rekod="$peringkat->get(WorkflowStatus::STAGE_PENDAFTARAN)"
                    :semasa="$peringkatSemasa === WorkflowStatus::STAGE_PENDAFTARAN"
                    keterangan="Ditandakan oleh Pegawai Penyelaras Rekod melalui Penetapan Entiti." />

                {{-- 02 — Semakan Awal Data (PA) --}}
                <x-kemajuan-peringkat :nombor="WorkflowStatus::STAGE_SEMAKAN_AWAL"
                    :nama="WorkflowStatus::getStageName(WorkflowStatus::STAGE_SEMAKAN_AWAL)"
                    :rekod="$peringkat->get(WorkflowStatus::STAGE_SEMAKAN_AWAL)"
                    :semasa="$peringkatSemasa === WorkflowStatus::STAGE_SEMAKAN_AWAL"
                    :dikunci="! $terbuka(WorkflowStatus::STAGE_SEMAKAN_AWAL)">
                    @if ($bolehPA && $terbuka(WorkflowStatus::STAGE_SEMAKAN_AWAL) && ! $selesai(WorkflowStatus::STAGE_SEMAKAN_AWAL))
                        <form action="{{ route('kemajuan.selesai', [$entiti['agency_code'], WorkflowStatus::STAGE_SEMAKAN_AWAL]) }}"
                            method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="bi bi-check2-circle"></i> Selesai
                            </button>
                        </form>
                    @endif
                </x-kemajuan-peringkat>

                {{-- 03 — Penyediaan & Pengesahan Data (PA) --}}
                <x-kemajuan-peringkat :nombor="WorkflowStatus::STAGE_PENYEDIAAN"
                    :nama="WorkflowStatus::getStageName(WorkflowStatus::STAGE_PENYEDIAAN)"
                    :rekod="$peringkat->get(WorkflowStatus::STAGE_PENYEDIAAN)"
                    :semasa="$peringkatSemasa === WorkflowStatus::STAGE_PENYEDIAAN"
                    :dikunci="! $terbuka(WorkflowStatus::STAGE_PENYEDIAAN)">
                    @if ($bolehPA && $terbuka(WorkflowStatus::STAGE_PENYEDIAAN) && ! $selesai(WorkflowStatus::STAGE_PENYEDIAAN))
                        <form action="{{ route('kemajuan.selesai', [$entiti['agency_code'], WorkflowStatus::STAGE_PENYEDIAAN]) }}"
                            method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="bi bi-check2-circle"></i> Selesai
                            </button>
                        </form>
                    @endif
                </x-kemajuan-peringkat>

                {{-- 04 — Analisis Data (PA): borang input + Simpan Dapatan + Selesai --}}
                <x-kemajuan-peringkat :nombor="WorkflowStatus::STAGE_ANALISIS"
                    :nama="WorkflowStatus::getStageName(WorkflowStatus::STAGE_ANALISIS)"
                    :rekod="$peringkat->get(WorkflowStatus::STAGE_ANALISIS)"
                    :semasa="$peringkatSemasa === WorkflowStatus::STAGE_ANALISIS"
                    :dikunci="! $terbuka(WorkflowStatus::STAGE_ANALISIS)"
                    :keterangan="'Dapatan inventori: ' . ($analisisLengkap ? 'Lengkap' : 'Belum Lengkap')">
                    @if ($bolehPA && $terbuka(WorkflowStatus::STAGE_ANALISIS))
                        <a class="btn btn-sm btn-outline-light"
                            href="{{ route('analisis.borang', ['sector_code' => $entiti['sector_code'], 'agency_code' => $entiti['agency_code']]) }}">
                            <i class="bi bi-pencil-square"></i> Input Analisis Inventori Kriptografi
                        </a>

                        @if (! $selesai(WorkflowStatus::STAGE_ANALISIS))
                            <form action="{{ route('kemajuan.selesai', [$entiti['agency_code'], WorkflowStatus::STAGE_ANALISIS]) }}"
                                method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-primary" @disabled(! $analisisLengkap)
                                    title="{{ $analisisLengkap ? 'Tandakan Analisis Data Selesai' : 'Simpan Dapatan perlu Lengkap dahulu' }}">
                                    <i class="bi bi-check2-circle"></i> Selesai
                                </button>
                            </form>
                        @endif
                    @endif
                </x-kemajuan-peringkat>

                {{-- 05 — Jana Laporan (PA): Jana → Betulkan / Hantar --}}
                <x-kemajuan-peringkat :nombor="WorkflowStatus::STAGE_JANA_LAPORAN"
                    :nama="WorkflowStatus::getStageName(WorkflowStatus::STAGE_JANA_LAPORAN)"
                    :rekod="$peringkat->get(WorkflowStatus::STAGE_JANA_LAPORAN)"
                    :semasa="$peringkatSemasa === WorkflowStatus::STAGE_JANA_LAPORAN"
                    :dikunci="! $terbuka(WorkflowStatus::STAGE_JANA_LAPORAN)"
                    keterangan="Peringkat ini hanya menjadi Selesai setelah laporan disahkan Ketua Bahagian.">
                    @if ($bolehPA && $terbuka(WorkflowStatus::STAGE_JANA_LAPORAN))
                        @if ($laporan === null)
                            <form action="{{ route('kemajuan.jana-laporan', $entiti['agency_code']) }}" method="POST"
                                class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="bi bi-file-earmark-bar-graph"></i> Jana Laporan
                                </button>
                            </form>
                        @elseif ($laporan->bolehDisuntingPA())
                            @if ($analisis)
                                <a class="btn btn-sm btn-outline-light"
                                    href="{{ route('laporan.inventori', $analisis) }}">
                                    <i class="bi bi-eye"></i> Pratonton
                                </a>
                            @endif
                            <a class="btn btn-sm btn-outline-light"
                                href="{{ route('analisis.borang', ['sector_code' => $entiti['sector_code'], 'agency_code' => $entiti['agency_code']]) }}">
                                <i class="bi bi-pencil"></i> Betulkan
                            </a>
                            <form action="{{ route('kemajuan.hantar', $entiti['agency_code']) }}" method="POST"
                                class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="bi bi-send"></i> Hantar
                                </button>
                            </form>
                        @endif
                    @endif
                </x-kemajuan-peringkat>

                {{-- 06 — Semakan & Kelulusan (PPA kemudian KB) --}}
                <x-kemajuan-peringkat :nombor="WorkflowStatus::STAGE_SEMAKAN_KELULUSAN"
                    :nama="WorkflowStatus::getStageName(WorkflowStatus::STAGE_SEMAKAN_KELULUSAN)"
                    :rekod="$peringkat->get(WorkflowStatus::STAGE_SEMAKAN_KELULUSAN)"
                    :semasa="$peringkatSemasa === WorkflowStatus::STAGE_SEMAKAN_KELULUSAN"
                    :dikunci="! $terbuka(WorkflowStatus::STAGE_SEMAKAN_KELULUSAN)">

                    @if ($laporan && $analisis && ($bolehSemak || $bolehLulus))
                        <a class="btn btn-sm btn-outline-light" href="{{ route('laporan.inventori', $analisis) }}">
                            <i class="bi bi-eye"></i> Pratonton
                        </a>
                    @endif

                    {{-- PPA: hantar kepada KB --}}
                    @if ($bolehSemak && $statusLaporan === LaporanSemakan::MENUNGGU_PPA)
                        <form action="{{ route('kemajuan.semak', $entiti['agency_code']) }}" method="POST"
                            class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="bi bi-send"></i> Hantar
                            </button>
                        </form>
                    @endif

                    {{-- KB: sahkan --}}
                    @if ($bolehLulus && $statusLaporan === LaporanSemakan::MENUNGGU_KB)
                        <form action="{{ route('kemajuan.sahkan', $entiti['agency_code']) }}" method="POST"
                            class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="bi bi-patch-check"></i> Sahkan
                            </button>
                        </form>
                    @endif

                    {{-- Kembalikan — Catatan wajib, jadi butang kekal
                         dilumpuhkan sehingga medan diisi (lihat app.js). --}}
                    @php
                        $bolehKembalikan =
                            ($bolehSemak && $statusLaporan === LaporanSemakan::MENUNGGU_PPA) ||
                            ($bolehLulus && $statusLaporan === LaporanSemakan::MENUNGGU_KB);
                    @endphp

                    @if ($bolehKembalikan)
                        <form action="{{ route('kemajuan.kembalikan', $entiti['agency_code']) }}" method="POST"
                            class="kemajuan-kembalikan" data-catatan-wajib>
                            @csrf
                            <label class="form-label" for="catatan">Catatan (wajib untuk mengembalikan)</label>
                            <textarea id="catatan" name="catatan" class="form-control mb-2" rows="2" maxlength="2000"
                                required data-catatan
                                placeholder="Nyatakan sebab laporan dikembalikan kepada Pegawai Analisis">{{ old('catatan') }}</textarea>
                            <button type="submit" class="btn btn-sm btn-outline-light" data-catatan-butang disabled>
                                <i class="bi bi-arrow-counterclockwise"></i> Kembalikan
                            </button>
                        </form>
                    @endif

                </x-kemajuan-peringkat>

                {{-- 07 — Penyerahan & Penutupan --}}
                <x-kemajuan-peringkat :nombor="WorkflowStatus::STAGE_PENYERAHAN"
                    :nama="WorkflowStatus::getStageName(WorkflowStatus::STAGE_PENYERAHAN)"
                    :rekod="$peringkat->get(WorkflowStatus::STAGE_PENYERAHAN)"
                    :semasa="$peringkatSemasa === WorkflowStatus::STAGE_PENYERAHAN"
                    :dikunci="! $terbuka(WorkflowStatus::STAGE_PENYERAHAN)"
                    keterangan="Penyerahan laporan yang telah disahkan kepada NACSA.">

                    @if ($laporan?->isSah() && $analisis)
                        <a class="btn btn-sm btn-outline-light" href="{{ route('laporan.unduh', $analisis) }}">
                            <i class="bi bi-download"></i> Muat Turun
                        </a>
                    @endif

                    @if ($bolehSerah && $terbuka(WorkflowStatus::STAGE_PENYERAHAN) && ! $selesai(WorkflowStatus::STAGE_PENYERAHAN))
                        <form action="{{ route('kemajuan.serah', $entiti['agency_code']) }}" method="POST"
                            class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-primary" @disabled(! ($laporan?->isSah()))
                                title="{{ $laporan?->isSah() ? 'Serahkan kepada NACSA' : 'Laporan perlu berstatus Sah dahulu' }}">
                                <i class="bi bi-send-check"></i> Hantar
                            </button>
                        </form>
                    @endif

                </x-kemajuan-peringkat>

            </ol>

        </div>

    @endif


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

        <div class="mt-3">{{ $sejarah->links() }}</div>

    </div>

@endsection
