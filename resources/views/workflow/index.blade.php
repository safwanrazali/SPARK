@extends('layouts.app')

@section('title', 'Kemajuan Analisis Entiti')

@section('page-title', 'Kemajuan Analisis')

@section('content')

    <div class="report-card mb-4">

        <h4 class="section-title">5 Peringkat Kemajuan Analisis</h4>
        <p class="text-secondary">
            Setiap entiti dipantau melalui lima peringkat utama, daripada
            Penerimaan &amp; Semakan Awal Data sehingga Semakan, Kelulusan &amp;
            Penyerahan Laporan. Peringkat 1 dan 3 mengandungi sub-peringkat.
            Fasa semasa berakhir pada
            <strong>{{ \App\Support\AliranKerja::labelPenuh(\App\Support\AliranKerja::TERAKHIR_SEMASA) }}</strong>;
            peringkat selepasnya belum dibina.
        </p>

        <x-workflow-stepper class="mb-4" />

        {{--
            Satu menu, tiga jenis paparan. "Entiti Diterima" ialah pilihan
            LALAI: kerja aliran kerja bermula pada penerimaan Buku Kerja MPQ,
            jadi senarai itulah yang paling berguna sebagai paparan pertama.

            Sektor kekal sebagai pilihan di bawahnya untuk melihat KESELURUHAN
            entiti satu sektor, termasuk yang belum disentuh langsung.
        --}}
        <form action="{{ route('workflow.index') }}" method="GET" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label" for="skop">Papar</label>
                <select id="skop" name="skop" class="form-select">
                    <option value="{{ \App\Http\Controllers\WorkflowController::SKOP_DITERIMA }}"
                        @selected($skop === \App\Http\Controllers\WorkflowController::SKOP_DITERIMA)>
                        Entiti Diterima — Buku Kerja MPQ diterima, terbaru dahulu
                    </option>

                    @if ($bolehLihatDitugaskan)
                        <option value="{{ \App\Http\Controllers\WorkflowController::SKOP_DITUGASKAN }}"
                            @selected($skop === \App\Http\Controllers\WorkflowController::SKOP_DITUGASKAN)>
                            Entiti Ditugaskan Kepada Saya
                        </option>
                    @endif

                    <optgroup label="Mengikut sektor">
                        @foreach ($sektor as $kod => $s)
                            <option value="{{ $kod }}" @selected($sectorCode === $kod)>
                                {{ $kod }} — {{ $s['name'] }}
                            </option>
                        @endforeach
                    </optgroup>
                </select>
            </div>
            <div class="col-md-6">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-funnel"></i> Papar Entiti
                </button>
                @if ($skop !== \App\Http\Controllers\WorkflowController::SKOP_DITERIMA)
                    <a href="{{ route('workflow.index') }}" class="btn btn-outline-light">Set Semula</a>
                @endif
            </div>
        </form>

    </div>

    <div class="report-card">

        <h4 class="section-title">Kedudukan Semasa Entiti</h4>
        <p class="text-secondary">
            {{ $jumlahDidaftar }} entiti telah memasuki aliran kerja Kemajuan Analisis
            merentas kesemua sektor.
        </p>

        {{--
            Nota di bawah tajuk menyatakan APA yang sedang dipaparkan, kerana
            ketiga-tiga skop menghasilkan senarai yang kelihatan serupa tetapi
            bermaksud perkara yang berlainan.
        --}}
        @php
            $skopDiterima = \App\Http\Controllers\WorkflowController::SKOP_DITERIMA;
            $skopDitugaskan = \App\Http\Controllers\WorkflowController::SKOP_DITUGASKAN;
        @endphp

        <p class="text-secondary">
            @if ($sectorCode)
                Memaparkan kesemua entiti sektor {{ $sectorCode }}, termasuk yang belum
                memasuki aliran kerja.
            @elseif ($skop === $skopDitugaskan)
                Memaparkan entiti yang ditugaskan kepada anda, penugasan terbaru dahulu.
            @else
                Memaparkan entiti yang Buku Kerja MPQ-nya telah diterima
                (Tarikh Terima dan Status Borang Penerimaan Data direkod),
                yang terbaru dikemas kini dahulu.
            @endif
        </p>

        {{--
            Lajur Tindakan dipaparkan kepada SETIAP peranan yang boleh membuka
            skrin ini.

            Pautan "Entiti" dan "Kemajuan" ialah navigasi, bukan tindakan:
            keduanya membawa ke halaman yang peranan itu memang dibenarkan
            melihat. Menyembunyikannya daripada mana-mana peranan hanya
            memaksanya menaip URL untuk sampai ke tempat yang sama — dan
            kebenaran sebenar tetap dikuatkuasakan oleh gate serta middleware
            `entity.access` pada setiap route.
        --}}

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
                        <th scope="col">Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $kemajuanServis = app(\App\Services\KemajuanAnalisisService::class);

                        // Penyebut kemajuan ialah peringkat FASA SEMASA
                        // (1.1 hingga 3.1). Peringkat 3.2, 4 dan 5 belum
                        // dibina, jadi ia tidak boleh dikira sebagai kerja
                        // yang tertunggak.
                        $jumlahPeringkat = $kemajuanServis->jumlahPeringkatSemasa();

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
                            // "Berdaftar" bermaksud peringkat 1.1 telah BERMULA,
                            // bukan Selesai. Peringkat 1.1 hanya Selesai setelah
                            // No. Rujukan direkod oleh PKD; menuntut Selesai di
                            // sini melaporkan entiti yang pegawainya sudah bekerja
                            // hingga peringkat 3.1 sebagai "Belum Didaftarkan".
                            //
                            // Ujian yang SAMA digunakan oleh halaman Kemajuan
                            // (workflow/show) dan halaman Entiti, supaya ketiga-tiga
                            // skrin tidak memberi jawapan berbeza bagi entiti yang
                            // sama. Entiti yang ditetapkan semula oleh KB tetap
                            // dikira belum berdaftar: setSemula() mengembalikan
                            // peringkat 1.1 kepada Belum Mula.
                            $didaftar = $kemajuanServis->telahMemasukiAliran($e['peringkat']);
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
                                    <span class="workflow-stage-tag">{{ $e['peringkatSemasa'] }}</span>
                                    {{ \App\Support\AliranKerja::label($e['peringkatSemasa']) }}
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
                                     sahaja, jadi entiti yang belum menyelesaikan
                                     peringkat 3.1 memaparkan sengkang dan bukan
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
                            <td class="text-nowrap">
                                <a class="btn btn-sm btn-primary" href="{{ route('entiti.show', $e['agency_code']) }}">
                                    <i class="bi bi-building"></i> Entiti
                                </a>
                                <a class="btn btn-sm btn-outline-light"
                                    href="{{ route('workflow.show', $e['agency_code']) }}">
                                    <i class="bi bi-diagram-3"></i> Kemajuan
                                </a>
                            </td>
                        </tr>
                    @empty
                        @if ($sectorCode)
                            <x-empty-state colspan="7" icon="bi-diagram-3" title="Tiada entiti dalam sektor ini">
                                Sektor {{ $sectorCode }} tiada entiti yang boleh anda lihat.
                            </x-empty-state>
                        @elseif ($skop === $skopDitugaskan)
                            <x-empty-state colspan="7" icon="bi-diagram-3" title="Tiada entiti ditugaskan kepada anda">
                                Entiti akan muncul di sini setelah Pegawai Penyelaras Analisis
                                menugaskannya kepada anda pada peringkat Pendaftaran Data.
                            </x-empty-state>
                        @else
                            <x-empty-state colspan="7" icon="bi-diagram-3" title="Tiada entiti diterima lagi">
                                Entiti muncul di sini setelah Tarikh Terima dan Status Borang
                                Penerimaan Data direkodkan. Pilih satu sektor di atas untuk
                                melihat kesemua entiti termasuk yang belum diterima.
                            </x-empty-state>
                        @endif
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $entiti->links() }}</div>

    </div>

@endsection
