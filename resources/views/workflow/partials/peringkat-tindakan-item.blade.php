{{-- `use` MESTI diulang dalam setiap partial: @include dikompil menjadi
     fail PHP tersendiri, jadi import templat induk TIDAK diwarisi. --}}
@php
    use App\Support\AliranKerja;
    use App\Services\KemajuanAnalisisService;
    use App\Models\WorkflowStageStatus;
@endphp

{{-- Satu blok tindakan bagi SATU peringkat. Pemboleh ubah $kunci, $rekod,
     $medan, $labelRujukan dan $milikSaya diwarisi daripada gelung dalam
     workflow/partials/peringkat-kemajuan.blade.php. --}}
    <div class="peringkat-tindakan__kumpulan">

        <span class="peringkat-tindakan__label">
            {{ AliranKerja::labelPenuh($kunci) }}

            @if (! $milikSaya && $bolehTugaskan($kunci))
                <small class="peringkat-tindakan__nota">
                    Peringkat ini telah Selesai; penugasan Pegawai
                    Analisis kekal boleh dikemas kini di sini.
                </small>
            @elseif (! $milikSaya)
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
                                @php
                                    // "Selesai" menuntut Borang Input Analisis
                                    // Inventori Kriptografi dimuktamadkan dahulu.
                                    $selesaiTerkunci = AliranKerja::statusSelesaiPerluBorangAnalisis($kunci)
                                        && ! $analisisLengkap;
                                @endphp

                                <select class="form-select @error($lajur) is-invalid @enderror"
                                    id="{{ $kunci }}-{{ $lajur }}" name="{{ $lajur }}">
                                    <option value="">— Pilih —</option>
                                    @foreach (AliranKerja::statusBorang($kunci) as $pilihan)
                                        @php
                                            $terkunci = $selesaiTerkunci
                                                && $pilihan === WorkflowStageStatus::SELESAI;
                                        @endphp
                                        <option value="{{ $pilihan }}" @selected($nilai === $pilihan)
                                            @disabled($terkunci)>
                                            {{ $pilihan }}{{ $terkunci ? ' — borang belum lengkap' : '' }}
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
                    {{--
                        Peringkat berderivasi tiada butang
                        "Selesai": statusnya ialah jawapan
                        kepada kelengkapan datanya, bukan
                        sesuatu yang ditekan. Menyimpan medan
                        terakhir yang tinggal itulah yang
                        menyiapkannya.
                    --}}
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-save"></i> Simpan
                    </button>

                    @unless (AliranKerja::statusDiterbitkan($kunci))
                        {{-- Simpan + tandakan Selesai dalam satu hantaran. --}}
                        <button type="submit" class="btn btn-sm btn-primary"
                            formaction="{{ route('kemajuan.selesai', [$entiti['agency_code'], $kunci]) }}">
                            <i class="bi bi-check2-circle"></i> Selesai
                        </button>
                    @endunless
                </div>

                @if (AliranKerja::statusDiterbitkan($kunci))
                    @php
                        $belumLengkap = app(KemajuanAnalisisService::class)
                            ->medanBelumLengkap($rekod, $kunci);
                        $syaratLanjut = AliranKerja::syaratLanjut($kunci);
                        $tertunggakLanjut = array_intersect($belumLengkap, $syaratLanjut);
                    @endphp

                    <small class="peringkat-tindakan__nota d-block mt-2">
                        @if ($belumLengkap === [])
                            Peringkat ini Selesai — kesemua medannya telah direkod.
                        @else
                            Peringkat ini menjadi <strong>Selesai</strong> apabila
                            {{ implode(', ', array_map(fn($l) => AliranKerja::labelMedan($kunci, $l), $belumLengkap)) }}
                            direkod.

                            @if ($tertunggakLanjut === [])
                                Peringkat seterusnya sudah pun terbuka.
                            @else
                                Peringkat seterusnya terbuka sebaik
                                {{ implode(' dan ', array_map(fn($l) => AliranKerja::labelMedan($kunci, $l), $tertunggakLanjut)) }}
                                direkod.
                            @endif
                        @endif

                        @if (AliranKerja::perluPenugasanUntukLanjut($kunci) && $penugasan === null)
                            Peringkat seterusnya juga memerlukan seorang
                            <strong>Pegawai Analisis</strong> ditugaskan.
                        @endif
                    </small>
                @endif
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

                    @unless ($analisisLengkap)
                        {{ AliranKerja::labelMedan($kunci, AliranKerja::MEDAN_STATUS_BORANG) }}
                        hanya boleh ditetapkan
                        <strong>{{ WorkflowStageStatus::SELESAI }}</strong>
                        setelah borang ini dilengkapkan.
                    @endunless
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

        {{--
            Penugasan Pegawai Analisis — kerja peringkat 1.2.
            Borang berasingan kerana borang HTML tidak boleh
            bersarang, dan kerana ia bukan medan peringkat: ia
            menulis ke `entiti_assignment`, bukan ke baris
            peringkat.
        --}}
        @if ($bolehTugaskan($kunci))
            <form action="{{ route('kemajuan.tugaskan', $entiti['agency_code']) }}"
                method="POST" class="peringkat-borang peringkat-borang--rujukan mt-2">
                @csrf

                <label class="form-label" for="assigned_to_user_id">
                    Pegawai Analisis
                    <small class="peringkat-tindakan__nota">
                        Pegawai yang akan menjalankan peringkat 1.3 dan seterusnya.
                    </small>
                </label>

                <div class="d-flex gap-2 flex-wrap align-items-start">
                    <select id="assigned_to_user_id" name="assigned_to_user_id"
                        class="form-select @error('assigned_to_user_id') is-invalid @enderror"
                        style="max-width: 320px">
                        <option value="">— Pilih Pegawai Analisis —</option>
                        @foreach ($analysts as $pegawai)
                            <option value="{{ $pegawai->id }}"
                                @selected(old('assigned_to_user_id', $penugasan?->assigned_to_user_id) == $pegawai->id)>
                                {{ $pegawai->name }}
                            </option>
                        @endforeach
                    </select>

                    <button type="submit" class="btn btn-sm btn-outline-light">
                        <i class="bi bi-person-check"></i> Tugaskan
                    </button>
                </div>

                @if ($penugasan)
                    <small class="peringkat-tindakan__nota d-block mt-1">
                        Ditugaskan kepada <strong>{{ $penugasan->assignedTo?->name }}</strong>
                        pada {{ $penugasan->assigned_at?->format('d/m/Y') }}.
                    </small>
                @else
                    <small class="peringkat-tindakan__nota d-block mt-1">
                        Belum ditugaskan kepada mana-mana Pegawai Analisis.
                    </small>
                @endif
            </form>
        @endif

    </div>
