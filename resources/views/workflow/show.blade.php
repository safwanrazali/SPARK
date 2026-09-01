@extends('layouts.app')

@section('title', 'Kemajuan Analisis Entiti — ' . $entiti['agency_code'])

@section('page-title', 'Kemajuan Analisis Entiti')

@section('content')

    @php
        use App\Models\WorkflowStageStatus;
        use App\Services\KemajuanAnalisisService;
        use App\Support\AliranKerja;

        $pengguna = auth()->user();

        // Baris peringkat kekal selepas "Set Semula" Ketua Bahagian, jadi
        // kehadirannya tidak membuktikan entiti berada dalam aliran kerja.
        // Peringkat 1.1 Selesai ialah ujian sebenar.
        $didaftar = $peringkat->get(AliranKerja::PENERIMAAN_DATA)?->isSelesai() ?? false;

        $status = fn(string $kunci): string => $peringkat->get($kunci)?->status ?? WorkflowStageStatus::BELUM_MULA;
        $selesai = fn(string $kunci): bool => $status($kunci) === WorkflowStageStatus::SELESAI;

        // Satu peringkat "terbuka" apabila pendahulunya dalam turutan aliran
        // telah Selesai. Peringkat pertama sentiasa terbuka.
        $terbuka = function (string $kunci) use ($selesai): bool {
            $sebelum = AliranKerja::sebelum($kunci);

            return $sebelum === null || $selesai($sebelum);
        };

        // Bolehkah pengguna ini melaksanakan peringkat berkenaan SEKARANG?
        // Tiga syarat: peranan, giliran, dan peringkat belum ditutup.
        $bolehKendali = function (string $kunci) use ($pengguna, $terbuka, $selesai): bool {
            $gate = AliranKerja::gate($kunci);

            return $gate !== null
                && $pengguna->can($gate)
                && $terbuka($kunci)
                && ! $selesai($kunci);
        };

        /*
        | Bolehkah pengguna ini memasukkan No. Rujukan peringkat berkenaan?
        |
        | Setiap No. Rujukan milik PPR, tanpa mengira siapa memiliki
        | peringkatnya — jadi gate diambil daripada takrifan aliran kerja dan
        | bukan daripada gate peringkat.
        |
        | Gilirannya TIDAK terikat kepada status peringkat: nombor rujukan
        | boleh direkodkan sepanjang peringkat itu berjalan.
        */
        $bolehRujukan = function (string $kunci) use ($pengguna): bool {
            $gate = \App\Support\AliranKerja::gateRujukan($kunci);

            return $gate !== null && $pengguna->can($gate);
        };

        $jumlahPeringkat = app(KemajuanAnalisisService::class)->jumlahPeringkatSemasa();

        $badgeKeseluruhan = match ($keseluruhan) {
            KemajuanAnalisisService::KESELURUHAN_SIAP => 'status-rendah',
            KemajuanAnalisisService::KESELURUHAN_DALAM_PROSES => 'status-sederhana',
            default => 'status-tinggi',
        };

        // Peringkat fasa semasa yang mempunyai tindakan terbuka kepada
        // pengguna ini — sama ada tindakan peringkat atau No. Rujukan.
        $peringkatBertindak = collect(AliranKerja::semasa())->filter(
            fn(string $kunci) => $bolehKendali($kunci) || $bolehRujukan($kunci),
        );

        $adaTindakan = $didaftar && $peringkatBertindak->isNotEmpty();

        $analisisLengkap = (bool) $analisis?->selesai;

        $borangUrl = route('analisis.borang', [
            'sector_code' => $entiti['sector_code'],
            'agency_code' => $entiti['agency_code'],
        ]);
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
        memaparkan lima peringkat utama beserta sub-peringkatnya, dan bar di
        bawahnya membawa tindakan yang benar-benar terbuka kepada peranan
        pengguna. Siapa menyelesaikan apa dan bila kekal direkodkan dalam
        "Sejarah Peringkat" di hujung halaman.
    --}}
    <div class="report-card mb-4">

        <h4 class="section-title">Peringkat Kemajuan</h4>

        <x-workflow-stepper :workflow="$workflow" :peringkat="$peringkat" />

        @if ($adaTindakan)
            <div class="peringkat-tindakan">

                <p class="peringkat-tindakan__tajuk">
                    Tindakan yang tidak dibenarkan bagi peranan anda tidak dipaparkan.
                </p>

                {{--
                    Satu blok bagi setiap peringkat, dijana daripada takrifan
                    aliran kerja. Medan yang ditangkap, labelnya dan siapa
                    memasukkan No. Rujukan semuanya datang daripada
                    App\Support\AliranKerja — jadi menambah medan pada satu
                    peringkat ialah satu perubahan pada takrifan, bukan pada
                    paparan ini.
                --}}
                @foreach ($peringkatBertindak as $kunci)
                    @php
                        $rekod = $peringkat->get($kunci);
                        $medan = AliranKerja::medan($kunci);
                        $labelRujukan = AliranKerja::labelRujukan($kunci);
                        $milikSaya = $bolehKendali($kunci);
                    @endphp

                    <div class="peringkat-tindakan__kumpulan">

                        <span class="peringkat-tindakan__label">
                            {{ AliranKerja::labelPenuh($kunci) }}

                            @if (! $milikSaya)
                                <small class="peringkat-tindakan__nota">
                                    Peringkat ini bukan tanggungjawab anda; hanya
                                    {{ $labelRujukan }} boleh dikemas kini di sini.
                                </small>
                            @elseif (! $terbuka($kunci))
                                <small class="peringkat-tindakan__nota">
                                    Peringkat sebelumnya perlu Selesai terlebih dahulu.
                                </small>
                            @endif
                        </span>

                        {{-- Borang data peringkat + butang Selesai — pemilik peringkat --}}
                        @if ($milikSaya && $medan !== [])
                            <form action="{{ route('kemajuan.simpan', [$entiti['agency_code'], $kunci]) }}"
                                method="POST" class="peringkat-borang">
                                @csrf

                                <div class="row g-2">
                                    @foreach ($medan as $lajur => $label)
                                        @php
                                            $bertarikh = in_array($lajur, AliranKerja::MEDAN_TARIKH, true);
                                            $nilai = old($lajur, $bertarikh ? $rekod?->{$lajur}?->format('Y-m-d') : $rekod?->{$lajur});
                                        @endphp
                                        <div class="col-md-3">
                                            <label class="form-label"
                                                for="{{ $kunci }}-{{ $lajur }}">{{ $label }}</label>

                                            {{--
                                                Status Borang ialah senarai
                                                tertutup, jadi ia dipilih dan
                                                bukan ditaip: nilai di luar
                                                perbendaharaan tidak sepatutnya
                                                boleh masuk langsung.
                                            --}}
                                            @if ($lajur === AliranKerja::MEDAN_STATUS_BORANG)
                                                <select class="form-select @error($lajur) is-invalid @enderror"
                                                    id="{{ $kunci }}-{{ $lajur }}" name="{{ $lajur }}">
                                                    <option value="">— Pilih —</option>
                                                    @foreach (AliranKerja::statusBorang($kunci) as $pilihan)
                                                        <option value="{{ $pilihan }}" @selected($nilai === $pilihan)>
                                                            {{ $pilihan }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <input type="{{ $bertarikh ? 'date' : 'text' }}"
                                                    class="form-control @error($lajur) is-invalid @enderror"
                                                    id="{{ $kunci }}-{{ $lajur }}" name="{{ $lajur }}"
                                                    value="{{ $nilai }}" maxlength="255">
                                            @endif
                                        </div>
                                    @endforeach
                                </div>

                                <div class="peringkat-tindakan__butang mt-2">
                                    <button type="submit" class="btn btn-sm btn-outline-light">
                                        <i class="bi bi-save"></i> Simpan
                                    </button>

                                    {{-- Simpan + tandakan Selesai dalam satu hantaran. --}}
                                    <button type="submit" class="btn btn-sm btn-primary"
                                        formaction="{{ route('kemajuan.selesai', [$entiti['agency_code'], $kunci]) }}">
                                        <i class="bi bi-check2-circle"></i> Selesai
                                    </button>
                                </div>
                            </form>
                        @elseif ($milikSaya)
                            <div class="peringkat-tindakan__butang">
                                <form action="{{ route('kemajuan.selesai', [$entiti['agency_code'], $kunci]) }}"
                                    method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        <i class="bi bi-check2-circle"></i> Selesai
                                    </button>
                                </form>
                            </div>
                        @endif

                        {{--
                            Peringkat 3.1 ialah tempat Borang Input Analisis
                            Inventori Kriptografi dilengkapkan. Pautannya
                            berada di sini kerana borang itu ialah kerja
                            peringkat ini, bukan langkah berasingan.
                        --}}
                        @if ($kunci === AliranKerja::ANALISIS_INVENTORI && $milikSaya)
                            <div class="peringkat-tindakan__butang mt-2">
                                <a class="btn btn-sm btn-outline-light" href="{{ $borangUrl }}">
                                    <i class="bi bi-pencil-square"></i>
                                    {{ $analisisLengkap ? 'Kemas Kini Borang' : 'Lengkapkan Borang' }}
                                </a>

                                @if ($analisis)
                                    <a class="btn btn-sm btn-outline-light"
                                        href="{{ route('laporan.inventori', $analisis) }}">
                                        <i class="bi bi-eye"></i> Pratonton
                                    </a>
                                @endif

                                <small class="peringkat-tindakan__nota d-block mt-1">
                                    Borang Input Analisis Inventori Kriptografi:
                                    {{ $analisisLengkap ? 'Lengkap' : 'Belum Lengkap' }}.
                                </small>
                            </div>
                        @endif

                        {{--
                            No. Rujukan — borang BERASINGAN kerana pemiliknya
                            BUKAN pemilik peringkat: setiap No. Rujukan
                            dimasukkan oleh Pegawai Penyelaras Rekod, walaupun
                            peringkatnya milik KB, PPA atau PA.
                        --}}
                        @if ($bolehRujukan($kunci))
                            <form action="{{ route('kemajuan.rujukan', [$entiti['agency_code'], $kunci]) }}"
                                method="POST" class="peringkat-borang peringkat-borang--rujukan mt-2">
                                @csrf

                                <label class="form-label" for="{{ $kunci }}-no-rujukan">
                                    {{ $labelRujukan }}
                                    <small class="peringkat-tindakan__nota">
                                        Dimasukkan oleh Pegawai Penyelaras Rekod.
                                    </small>
                                </label>

                                <div class="d-flex gap-2 flex-wrap align-items-start">
                                    <input type="text" id="{{ $kunci }}-no-rujukan" name="no_rujukan"
                                        class="form-control @error('no_rujukan') is-invalid @enderror"
                                        value="{{ old('no_rujukan', $rekod?->no_rujukan) }}" maxlength="255"
                                        style="max-width: 320px">

                                    <button type="submit" class="btn btn-sm btn-outline-light">
                                        <i class="bi bi-hash"></i> Simpan Rujukan
                                    </button>
                                </div>
                            </form>
                        @endif

                    </div>
                @endforeach

            </div>
        @endif

    </div>

    @if (!$didaftar)

        <div class="report-card">
            <h4 class="section-title">Belum Memasuki Aliran Kerja</h4>
            <p class="text-secondary mb-0">
                Entiti ini belum memasuki aliran kerja kerana peringkat
                <strong>1.1 Penerimaan Data</strong> belum Selesai.
                Ketua Bahagian atau Pegawai Penyelaras Analisis perlu
                menandakannya melalui skrin Penetapan Entiti sebelum kemajuan
                analisis boleh bermula.
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
                        {{ AliranKerja::labelPenuh($peringkatSemasa) }}
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-title">Peringkat Selesai</div>
                    <div class="workflow-meta__value">{{ $bilanganSelesai }} / {{ $jumlahPeringkat }}</div>
                </div>
                {{--
                    "Peringkat Selesai" dikira terhadap peringkat FASA SEMASA
                    (1.1 hingga 3.1) dan bukan kelapan-lapan peringkat:
                    peringkat 3.2, 4 dan 5 belum dibina, jadi mengukur
                    terhadapnya akan memaparkan kemajuan penuh sebagai
                    kekurangan yang tiada siapa boleh tutup.
                --}}
                <div class="col-md-4">
                    <div class="stat-title">Fasa Semasa Berakhir Pada</div>
                    <div class="workflow-meta__value">
                        {{ AliranKerja::labelPenuh(AliranKerja::TERAKHIR_SEMASA) }}
                    </div>
                </div>
            </div>

            <div class="workflow-progress mt-3" role="img"
                aria-label="Kemajuan {{ round(($bilanganSelesai / $jumlahPeringkat) * 100) }} peratus">
                <span style="--progress: {{ round(($bilanganSelesai / $jumlahPeringkat) * 100) }}%"></span>
            </div>

        </div>

        {{--
            Data yang telah direkodkan pada setiap peringkat. Dipaparkan
            kepada SEMUA peranan yang boleh melihat entiti ini — merekod
            ialah hak terhad, membaca tidak.
        --}}
        <div class="report-card mb-4">

            <h4 class="section-title">Maklumat Peringkat</h4>

            <div class="table-responsive-custom">
                <table class="table-modern">
                    <thead>
                        <tr>
                            <th scope="col">Peringkat</th>
                            <th scope="col">Status</th>
                            <th scope="col">Maklumat Direkod</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (AliranKerja::kekunci() as $kunci)
                            @php
                                $rekod = $peringkat->get($kunci);
                                $akanDatang = AliranKerja::adalahAkanDatang($kunci);
                                $tangkapan = array_filter(
                                    $rekod?->dataTangkapan() ?? [],
                                    fn($nilai) => $nilai !== null && $nilai !== '',
                                );
                            @endphp
                            <tr>
                                <td>
                                    <span class="workflow-stage-tag">{{ $kunci }}</span>
                                    {{ AliranKerja::label($kunci) }}
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
                                    @if ($akanDatang)
                                        <span class="text-secondary">—</span>
                                    @elseif ($tangkapan === [])
                                        <span class="text-secondary">Belum direkod</span>
                                    @else
                                        @foreach ($tangkapan as $label => $nilai)
                                            <div>
                                                <small class="text-secondary">{{ $label }}:</small>
                                                {{ $nilai instanceof \Illuminate\Support\Carbon ? $nilai->format('d/m/Y') : $nilai }}
                                            </div>
                                        @endforeach
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        </div>

    @endif


    <div class="report-card">

        <h4 class="section-title">Sejarah Peringkat</h4>
        <p class="text-secondary">
            Setiap perubahan peringkat dan setiap maklumat yang direkodkan padanya
            disimpan bersama pegawai dan masa untuk tujuan jejak audit.
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
                             * - Aliran semasa: kedua-duanya ialah STATUS
                             *   ('Belum Mula' → 'Selesai'), dan kunci peringkat
                             *   yang terlibat berada dalam metadata.
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
                                        {{ $log->metadata['stage_name'] ?? AliranKerja::labelPenuh($peringkatLog) }}
                                    </small>
                                @endif
                            </td>
                            <td>
                                @if ($nomborPeringkat && $log->old_value !== null)
                                    {{ $log->metadata['from_stage_name'] ?? $log->old_value }}
                                @else
                                    {{ $log->old_value ?? '-' }}
                                @endif
                            </td>
                            <td>
                                @if ($nomborPeringkat && $log->new_value !== null)
                                    {{ $log->metadata['to_stage_name'] ?? $log->new_value }}
                                @else
                                    {{ $log->new_value ?? '-' }}
                                @endif
                            </td>
                            <td>{{ $log->changedBy?->name ?? '-' }}</td>
                            <td>{{ $catatanLog ?? '-' }}</td>
                        </tr>
                    @empty
                        <x-empty-state colspan="6" icon="bi-clock-history" title="Tiada perubahan peringkat">
                            Sejarah muncul apabila entiti memasuki aliran kerja atau peringkatnya dikemas kini.
                        </x-empty-state>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $sejarah->links() }}</div>

    </div>

@endsection
