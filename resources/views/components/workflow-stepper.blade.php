@props([
    'workflow' => null,
    'peringkat' => null,
    'compact' => false,
])

@php
    use App\Models\WorkflowStageStatus;
    use App\Support\AliranKerja;

    $kunciSemasa = $workflow?->current_stage_key;

    /*
     * Status sebenar setiap peringkat, apabila pemanggil membekalkannya.
     *
     * Tanpa ini stepper hanya tahu "peringkat semasa", jadi ia tidak dapat
     * membezakan peringkat yang sedang berjalan daripada yang belum bermula.
     */
    $statusPeringkat = fn (string $kunci): ?string => $peringkat?->get($kunci)?->status;

    /*
     * Lebar setiap kumpulan peringkat utama, dijana sebagai lajur grid.
     *
     * Setiap kumpulan mendapat lebar mengikut BILANGAN sub-peringkatnya, supaya
     * setiap bulatan sama luas merentas keseluruhan baris — kumpulan bersub-tiga
     * tidak boleh sesempit kumpulan berproses tunggal.
     *
     * Grid (dan bukan flex) digunakan kerana tajuk kumpulan mempunyai bilangan
     * baris yang berbeza-beza: "Penyediaan & Pengesahan Data" membalut kepada
     * dua baris sedangkan "Analisis Data" tidak. Dengan grid dua baris, SEMUA
     * tajuk berkongsi baris pertama, jadi baris bulatan bermula pada paras yang
     * sama tanpa mengira panjang tajuk.
     */
    $lebarMinimum = $compact ? 34 : 120;

    $kolum = collect(array_keys(AliranKerja::UTAMA))
        ->map(function (int $utama) use ($lebarMinimum): string {
            $bilangan = max(1, count(AliranKerja::subPeringkat($utama)));

            return sprintf('minmax(%dpx, %dfr)', $bilangan * $lebarMinimum, $bilangan);
        })
        ->implode(' ');
@endphp

<div {{ $attributes->merge(['class' => 'workflow-stepper' . ($compact ? ' workflow-stepper--compact' : '')]) }}
    style="--kolum: {{ $kolum }}">

    {{--
        Lima kumpulan peringkat UTAMA. Sub-peringkat berada DI DALAM
        kumpulannya, bukan disenaraikan rata di sebelahnya — itulah beza
        antara "sistem lima peringkat" dan "sistem lapan langkah".
    --}}
    @foreach (AliranKerja::UTAMA as $utama => $namaUtama)
        @php
            $sub = AliranKerja::subPeringkat($utama);

            // Peringkat utama dianggap selesai apabila setiap sub-peringkat
            // FASA SEMASA di dalamnya selesai. Peringkat fasa akan datang
            // tidak boleh menyekat kiraan ini: modulnya belum wujud.
            $subSemasa = array_values(array_filter($sub, fn (string $k) => AliranKerja::adalahSemasa($k)));

            $selesaiUtama = $subSemasa !== [] && collect($subSemasa)
                ->every(fn (string $k) => $statusPeringkat($k) === WorkflowStageStatus::SELESAI);

            $akanDatangUtama = $subSemasa === [];

            // Nama sub-peringkat hanya bermakna apabila kumpulan itu BENAR-BENAR
            // mempunyai lebih daripada satu proses. Bagi peringkat 2, 4 dan 5,
            // nama sub-peringkat sama dengan tajuk kumpulan — memaparkannya dua
            // kali hanya mengulang perkataan yang sama di bawah bulatan.
            $adaSub = AliranKerja::adaSubPeringkat($utama);
        @endphp

        <div class="workflow-utama {{ $selesaiUtama ? 'workflow-utama--selesai' : '' }} {{ $akanDatangUtama ? 'workflow-utama--akan-datang' : '' }}">

            @unless ($compact)
                <div class="workflow-utama__label">
                    <span class="workflow-utama__nombor">{{ $utama }}</span>
                    {{ $namaUtama }}
                    @if ($akanDatangUtama)
                        <span class="workflow-utama__fasa">Fasa Akan Datang</span>
                    @endif
                </div>
            @endunless

            <div class="workflow-utama__langkah">
                @foreach ($sub as $kunci)
                    @php
                        $status = $statusPeringkat($kunci);
                        $akanDatang = AliranKerja::adalahAkanDatang($kunci);

                        $keadaan = match (true) {
                            // Peringkat fasa akan datang tidak pernah "semasa":
                            // ia tiada giliran kerana ia belum dibina.
                            $akanDatang => 'akan-datang',
                            $status === WorkflowStageStatus::SELESAI => 'selesai',
                            $status === WorkflowStageStatus::DALAM_PROSES => 'semasa',
                            $kunci === $kunciSemasa => 'semasa',
                            default => 'menunggu',
                        };

                        // Nombor peringkat penuh kekal dalam tooltip: ia berguna
                        // untuk rujukan silang dengan borang dan jejak audit,
                        // tetapi tidak perlu memenuhi ruang paparan.
                        $tajuk = AliranKerja::labelPenuh($kunci)
                            . ($status && ! $akanDatang ? ' — ' . $status : '')
                            . ($akanDatang ? ' — belum dibina' : '');
                    @endphp

                    <div class="workflow-step workflow-step--{{ $keadaan }}" title="{{ $tajuk }}">

                        <div class="workflow-step__track" aria-hidden="true"></div>

                        {{--
                            Bulatan membawa KEADAAN peringkat, bukan nombornya.
                            Nombor sub-peringkat telah dibuang: kumpulan di
                            atasnya sudah menomborkan peringkat utama, dan
                            mengulanginya pada setiap bulatan hanya menambah
                            angka yang perlu dibaca tanpa memberi maklumat baharu.
                        --}}
                        <div class="workflow-step__node">
                            @if ($keadaan === 'selesai')
                                <i class="bi bi-check-lg"></i>
                            @elseif ($akanDatang)
                                <i class="bi bi-hourglass"></i>
                            @endif
                        </div>

                        @unless ($compact)
                            {{--
                                Baris nama sentiasa ditempah, walaupun kosong.
                                Peringkat tanpa sub-peringkat tidak memerlukan
                                nama di sini — tajuk kumpulan di atasnya sudah
                                menyebutnya — tetapi tanpa ruang yang ditempah,
                                lencana statusnya naik satu baris dan tidak lagi
                                sebaris dengan lencana kumpulan lain.
                            --}}
                            <div class="workflow-step__label" @unless ($adaSub) aria-hidden="true" @endunless>
                                {{ $adaSub ? AliranKerja::label($kunci) : '' }}
                            </div>

                            @if ($akanDatang)
                                <span class="workflow-step__nota">Belum dibina</span>
                            @elseif ($status !== null)
                                <span class="status-badge {{ $peringkat->get($kunci)->statusBadgeClass() }}">{{ $status }}</span>
                            @endif
                        @endunless

                    </div>
                @endforeach
            </div>

        </div>
    @endforeach

</div>
