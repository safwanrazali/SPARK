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

        // Baris peringkat kekal selepas "Set Semula" Ketua Bahagian, jadi
        // kehadirannya tidak membuktikan entiti berdaftar. Peringkat 01
        // Selesai ialah ujian sebenar.
        $didaftar = $peringkat->get(WorkflowStatus::STAGE_PENDAFTARAN)?->isSelesai() ?? false;

        $bolehPA = $pengguna->can('advance-analysis-stage');
        $bolehSemak = $pengguna->can('review-report');
        $bolehLulus = $pengguna->can('approve-report');
        $bolehSerah = $pengguna->can('submit-to-nacsa');

        $status = fn(int $stage): string => $peringkat->get($stage)?->status ?? WorkflowStageStatus::BELUM_MULA;
        $selesai = fn(int $stage): bool => $status($stage) === WorkflowStageStatus::SELESAI;

        // Satu peringkat "terbuka" apabila pendahulunya telah Selesai.
        $terbuka = fn(int $stage): bool => $stage === WorkflowStatus::FIRST_STAGE || $selesai($stage - 1);

        // "Lengkap" bermakna borang disimpan melalui "Simpan Dapatan",
        // bukan sekadar draf — itulah syarat laporan boleh dihantar.
        $analisisLengkap = (bool) $analisis?->selesai;
        $statusLaporan = $laporan?->status;
        $dikembalikan = $statusLaporan === LaporanSemakan::DIKEMBALIKAN;

        // Laporan berada di tangan penyemak: PA tidak boleh menyunting
        // atau menghantar apa-apa sehingga ia dikembalikan atau disahkan.
        $dalamSemakan = (bool) $laporan?->sedangDisemak();

        $badgeKeseluruhan = match ($keseluruhan) {
            KemajuanAnalisisService::KESELURUHAN_SIAP => 'status-rendah',
            KemajuanAnalisisService::KESELURUHAN_DALAM_PROSES => 'status-sederhana',
            default => 'status-tinggi',
        };

        $jumlahPeringkat = count(WorkflowStatus::WORKFLOW_STAGES);

        /*
        | Tindakan yang benar-benar tersedia kepada pengguna ini, dikira
        | sekali supaya bar tindakan tahu sama ada ia perlu wujud langsung.
        |
        | PENTING: ini TIDAK boleh diringkaskan kepada "peringkat semasa"
        | sahaja. Peringkat 05 (Jana Laporan) hanya menjadi Selesai setelah
        | Ketua Bahagian mengesahkan laporan, jadi 06 (Semakan & Kelulusan)
        | berjalan serentak dengannya — tindakan penyemak berada pada 06
        | sedangkan peringkat semasa masih 05.
        */
        $giliranPPA = $bolehSemak && $statusLaporan === LaporanSemakan::MENUNGGU_PPA;
        $giliranKB = $bolehLulus && $statusLaporan === LaporanSemakan::MENUNGGU_KB;
        $bolehKembalikan = $giliranPPA || $giliranKB;

        $tindakan = [
            WorkflowStatus::STAGE_SEMAKAN_AWAL => $bolehPA
                && $terbuka(WorkflowStatus::STAGE_SEMAKAN_AWAL)
                && ! $selesai(WorkflowStatus::STAGE_SEMAKAN_AWAL),

            WorkflowStatus::STAGE_PENYEDIAAN => $bolehPA
                && $terbuka(WorkflowStatus::STAGE_PENYEDIAAN)
                && ! $selesai(WorkflowStatus::STAGE_PENYEDIAAN),

            // Peringkat 04 kini pengesahan PA semata-mata — borang analisis
            // bukan lagi syaratnya, dan tiada butang kekal selepas Selesai.
            WorkflowStatus::STAGE_ANALISIS => $bolehPA
                && $terbuka(WorkflowStatus::STAGE_ANALISIS)
                && ! $selesai(WorkflowStatus::STAGE_ANALISIS),

            // Peringkat 05 milik PA sehingga laporan dihantar; sebaik ia
            // berada di tangan penyemak, PA tiada tindakan langsung.
            WorkflowStatus::STAGE_JANA_LAPORAN => $bolehPA
                && $terbuka(WorkflowStatus::STAGE_JANA_LAPORAN)
                && ! $selesai(WorkflowStatus::STAGE_JANA_LAPORAN)
                && ! $dalamSemakan,

            WorkflowStatus::STAGE_SEMAKAN_KELULUSAN => $giliranPPA || $giliranKB,

            WorkflowStatus::STAGE_PENYERAHAN => ($laporan?->isSah() && $analisis)
                || ($bolehSerah
                    && $terbuka(WorkflowStatus::STAGE_PENYERAHAN)
                    && ! $selesai(WorkflowStatus::STAGE_PENYERAHAN)),
        ];

        $adaTindakan = $didaftar && in_array(true, $tindakan, true);

        // Status Laporan bermula pada peringkat 05; lihat komen pada kad
        // Ringkasan Kemajuan di bawah.
        $statusLaporanBerkenaan = $terbuka(WorkflowStatus::STAGE_JANA_LAPORAN);

        $borangUrl = route('analisis.borang', [
            'sector_code' => $entiti['sector_code'],
            'agency_code' => $entiti['agency_code'],
        ]);

        $tajukPeringkat = fn (int $stage): string => sprintf('%02d', $stage)
            .' '.WorkflowStatus::getStageName($stage);
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

    {{--
        Satu tempat sahaja untuk kedudukan DAN tindakan. Stepper mendatar
        memaparkan turutan tujuh peringkat, dan bar di bawahnya membawa
        tindakan yang benar-benar terbuka kepada peranan pengguna — jadi
        tiada senarai menegak yang mengulang maklumat yang sama. Siapa
        menyelesaikan apa dan bila kekal direkodkan dalam "Sejarah
        Peringkat" di hujung halaman.
    --}}
    <div class="report-card mb-4">

        <h4 class="section-title">Peringkat Kemajuan</h4>

        <x-workflow-stepper :workflow="$workflow" :peringkat="$peringkat" />

        @if ($adaTindakan)
            <div class="peringkat-tindakan">

                <p class="peringkat-tindakan__tajuk">
                    Tindakan yang tidak dibenarkan bagi peranan anda tidak dipaparkan.
                </p>

                {{-- 02 — Semakan Awal Data (PA) --}}
                @if ($tindakan[WorkflowStatus::STAGE_SEMAKAN_AWAL])
                    <div class="peringkat-tindakan__kumpulan">
                        <span class="peringkat-tindakan__label">
                            {{ $tajukPeringkat(WorkflowStatus::STAGE_SEMAKAN_AWAL) }}
                        </span>
                        <div class="peringkat-tindakan__butang">
                            <form
                                action="{{ route('kemajuan.selesai', [$entiti['agency_code'], WorkflowStatus::STAGE_SEMAKAN_AWAL]) }}"
                                method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="bi bi-check2-circle"></i> Selesai
                                </button>
                            </form>
                        </div>
                    </div>
                @endif

                {{-- 03 — Penyediaan & Pengesahan Data (PA) --}}
                @if ($tindakan[WorkflowStatus::STAGE_PENYEDIAAN])
                    <div class="peringkat-tindakan__kumpulan">
                        <span class="peringkat-tindakan__label">
                            {{ $tajukPeringkat(WorkflowStatus::STAGE_PENYEDIAAN) }}
                        </span>
                        <div class="peringkat-tindakan__butang">
                            <form
                                action="{{ route('kemajuan.selesai', [$entiti['agency_code'], WorkflowStatus::STAGE_PENYEDIAAN]) }}"
                                method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="bi bi-check2-circle"></i> Selesai
                                </button>
                            </form>
                        </div>
                    </div>
                @endif

                {{-- 04 — Analisis Data (PA): pengesahan sahaja --}}
                @if ($tindakan[WorkflowStatus::STAGE_ANALISIS])
                    <div class="peringkat-tindakan__kumpulan">
                        <span class="peringkat-tindakan__label">
                            {{ $tajukPeringkat(WorkflowStatus::STAGE_ANALISIS) }}
                            <small class="peringkat-tindakan__nota">
                                Pengesahan bahawa analisis telah dilaksanakan. Borang
                                input dilengkapkan pada peringkat berikutnya.
                            </small>
                        </span>
                        <div class="peringkat-tindakan__butang">
                            <form
                                action="{{ route('kemajuan.selesai', [$entiti['agency_code'], WorkflowStatus::STAGE_ANALISIS]) }}"
                                method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="bi bi-check2-circle"></i> Selesai
                                </button>
                            </form>
                        </div>
                    </div>
                @endif

                {{-- 05 — Jana Laporan (PA): lengkapkan borang, kemudian hantar kepada PPA --}}
                @if ($tindakan[WorkflowStatus::STAGE_JANA_LAPORAN])
                    <div class="peringkat-tindakan__kumpulan">
                        <span class="peringkat-tindakan__label">
                            {{ $tajukPeringkat(WorkflowStatus::STAGE_JANA_LAPORAN) }}
                            <small class="peringkat-tindakan__nota">
                                Borang Input Analisis Inventori Kriptografi:
                                {{ $analisisLengkap ? 'Lengkap' : 'Belum Lengkap' }}.
                                Peringkat ini Selesai hanya setelah laporan disahkan Ketua Bahagian.
                            </small>
                        </span>
                        <div class="peringkat-tindakan__butang">
                            <a class="btn btn-sm btn-outline-light" href="{{ $borangUrl }}">
                                @if (!$analisisLengkap)
                                    <i class="bi bi-pencil-square"></i> Lengkapkan Borang
                                @elseif ($dikembalikan)
                                    <i class="bi bi-pencil"></i> Betulkan
                                @else
                                    <i class="bi bi-pencil"></i> Kemas Kini Borang
                                @endif
                            </a>

                            @if ($analisis)
                                <a class="btn btn-sm btn-outline-light"
                                    href="{{ route('laporan.inventori', $analisis) }}">
                                    <i class="bi bi-eye"></i> Pratonton
                                </a>
                            @endif

                            @if ($analisisLengkap)
                                <form action="{{ route('kemajuan.hantar', $entiti['agency_code']) }}" method="POST"
                                    class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        <i class="bi bi-send"></i>
                                        {{ $dikembalikan ? 'Hantar Semula' : 'Hantar kepada PPA' }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- 06 — Semakan & Kelulusan (PPA kemudian KB) --}}
                @if ($tindakan[WorkflowStatus::STAGE_SEMAKAN_KELULUSAN])
                    <div class="peringkat-tindakan__kumpulan">
                        <span class="peringkat-tindakan__label">
                            {{ $tajukPeringkat(WorkflowStatus::STAGE_SEMAKAN_KELULUSAN) }}
                            <small class="peringkat-tindakan__nota">
                                {{ $giliranKB
                                    ? 'Laporan menunggu pengesahan Ketua Bahagian.'
                                    : 'Laporan menunggu semakan Pegawai Penyelaras Analisis.' }}
                            </small>
                        </span>
                        <div class="peringkat-tindakan__butang">
                            @if ($analisis)
                                <a class="btn btn-sm btn-outline-light"
                                    href="{{ route('laporan.inventori', $analisis) }}">
                                    <i class="bi bi-eye"></i> Pratonton
                                </a>
                            @endif

                            {{-- PPA: hantar kepada KB --}}
                            @if ($giliranPPA)
                                <form action="{{ route('kemajuan.semak', $entiti['agency_code']) }}" method="POST"
                                    class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        <i class="bi bi-send"></i> Hantar kepada KB
                                    </button>
                                </form>
                            @endif

                        </div>

                        {{--
                            Satu medan Catatan berkongsi kedua-dua tindakan
                            penyemak, kerana ia catatan yang SAMA — apa yang
                            berbeza hanyalah sama ada ia wajib:

                            - "Kembalikan" mewajibkannya; butangnya kekal
                              dilumpuhkan sehingga medan diisi (lihat app.js),
                              dan pelayan menolak penghantaran kosong.
                            - "Sahkan" menerimanya sebagai pilihan.

                            Kedua-dua butang berada dalam satu borang dan
                            dibezakan melalui `formaction`, jadi Ketua Bahagian
                            menaip sekali sahaja tanpa perlu memilih kotak yang
                            betul dahulu.

                            Catatan ini direkodkan pada jejak entiti (Sejarah
                            Peringkat di bawah) dan TIDAK muncul dalam laporan.
                        --}}
                        @if ($bolehKembalikan)
                            <form action="{{ route('kemajuan.kembalikan', $entiti['agency_code']) }}" method="POST"
                                class="kemajuan-kembalikan" data-catatan-wajib>
                                @csrf

                                <label class="form-label" for="catatan">
                                    {{ $giliranKB
                                        ? 'Catatan (wajib untuk mengembalikan, pilihan untuk mengesahkan)'
                                        : 'Catatan (wajib untuk mengembalikan)' }}
                                </label>

                                <textarea id="catatan" name="catatan" class="form-control mb-2" rows="2" maxlength="2000"
                                    data-catatan
                                    placeholder="{{ $giliranKB
                                        ? 'Nyatakan sebab laporan dikembalikan, atau catatan pengesahan (jika ada)'
                                        : 'Nyatakan sebab laporan dikembalikan kepada Pegawai Analisis' }}">{{ old('catatan') }}</textarea>

                                <button type="submit" class="btn btn-sm btn-outline-light" data-catatan-butang disabled>
                                    <i class="bi bi-arrow-counterclockwise"></i> Kembalikan
                                </button>

                                @if ($giliranKB)
                                    <button type="submit" class="btn btn-sm btn-primary"
                                        formaction="{{ route('kemajuan.sahkan', $entiti['agency_code']) }}">
                                        <i class="bi bi-patch-check"></i> Sahkan
                                    </button>
                                @endif
                            </form>
                        @endif
                    </div>
                @endif

                {{-- 07 — Penyerahan & Penutupan --}}
                @if ($tindakan[WorkflowStatus::STAGE_PENYERAHAN])
                    <div class="peringkat-tindakan__kumpulan">
                        <span class="peringkat-tindakan__label">
                            {{ $tajukPeringkat(WorkflowStatus::STAGE_PENYERAHAN) }}
                            <small class="peringkat-tindakan__nota">
                                Penyerahan laporan yang telah disahkan kepada NACSA.
                            </small>
                        </span>
                        <div class="peringkat-tindakan__butang">
                            @if ($laporan?->isSah() && $analisis)
                                <a class="btn btn-sm btn-outline-light"
                                    href="{{ route('laporan.unduh', $analisis) }}">
                                    <i class="bi bi-download"></i> Muat Turun
                                </a>
                            @endif

                            @if ($bolehSerah && $terbuka(WorkflowStatus::STAGE_PENYERAHAN) && !$selesai(WorkflowStatus::STAGE_PENYERAHAN))
                                <form action="{{ route('kemajuan.serah', $entiti['agency_code']) }}" method="POST"
                                    class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-primary" @disabled(!$laporan?->isSah())
                                        title="{{ $laporan?->isSah() ? 'Serahkan kepada NACSA' : 'Laporan perlu berstatus Sah dahulu' }}">
                                        <i class="bi bi-send-check"></i> Hantar
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endif

            </div>
        @endif

    </div>

    @if (!$didaftar)

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
                {{--
                    Status Laporan muncul hanya bermula peringkat 05 (Jana
                    Laporan) — sebelum itu tiada laporan untuk diberi status,
                    dan "Belum Lengkap" akan terbaca sebagai kerja tertunggak.

                    Perbendaharaan paparan ('Belum Lengkap' / 'Dalam Semakan'
                    / 'Disahkan') dipetakan daripada keadaan sebenar laporan —
                    lihat LaporanSemakan::PAPARAN. Keadaan terperinci (di
                    tangan PPA atau KB) kekal dalam Sejarah Peringkat.
                --}}
                @if ($statusLaporanBerkenaan)
                    <div class="col-md-4">
                        <div class="stat-title">Status Laporan</div>
                        <div class="workflow-meta__value">
                            <span class="status-badge {{ LaporanSemakan::badgePaparan($laporan) }}">
                                {{ LaporanSemakan::paparanUntuk($laporan) }}
                            </span>
                        </div>
                    </div>
                @endif
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

    @endif


    <div class="report-card">

        <h4 class="section-title">Sejarah Peringkat</h4>
        <p class="text-secondary">
            Setiap perubahan peringkat — termasuk kitaran semakan laporan — direkodkan
            bersama pegawai dan masa untuk tujuan jejak audit.
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
                        @php
                            /*
                             * Tiga bentuk rekod berkongsi jadual ini:
                             *
                             * - Workflow lama: old_value/new_value ialah NOMBOR
                             *   peringkat, namanya dalam metadata.
                             * - Aliran semasa & kitaran laporan: kedua-duanya
                             *   ialah STATUS ('Belum Mula' → 'Selesai'), dan
                             *   peringkat yang terlibat berada dalam metadata.
                             *
                             * Menganggap semuanya nombor peringkat akan
                             * memaparkan "00 —" bagi rekod status.
                             */
                            $nomborPeringkat = in_array($log->action, [
                                \App\Services\WorkflowTransitionService::ACTION_INITIALIZED,
                                \App\Services\WorkflowTransitionService::ACTION_STAGE_CHANGED,
                            ], true);

                            $peringkatLog = $log->metadata['stage'] ?? null;

                            $catatanLog = $log->metadata['catatan']
                                ?? $log->metadata['notes']
                                ?? $log->metadata['reason']
                                ?? null;
                        @endphp
                        <tr>
                            <td class="text-nowrap">{{ $log->changed_at?->format('d/m/Y H:i') }}</td>
                            <td>
                                {{ $log->getActionLabel() }}
                                @if ($peringkatLog !== null)
                                    <br>
                                    <small class="text-secondary">
                                        {{ sprintf('%02d', $peringkatLog) }} —
                                        {{ $log->metadata['stage_name'] ?? WorkflowStatus::getStageName($peringkatLog) }}
                                    </small>
                                @endif
                            </td>
                            <td>
                                @if ($nomborPeringkat && $log->old_value !== null)
                                    {{ sprintf('%02d', $log->old_value) }} —
                                    {{ $log->metadata['from_stage_name'] ?? '' }}
                                @else
                                    {{ $log->old_value ?? '-' }}
                                @endif
                            </td>
                            <td>
                                @if ($nomborPeringkat && $log->new_value !== null)
                                    {{ sprintf('%02d', $log->new_value) }} —
                                    {{ $log->metadata['to_stage_name'] ?? '' }}
                                @else
                                    {{ $log->new_value ?? '-' }}
                                @endif
                            </td>
                            <td>{{ $log->changedBy?->name ?? '-' }}</td>
                            <td>{{ $catatanLog ?? '-' }}</td>
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
