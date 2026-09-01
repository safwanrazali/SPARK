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
@endphp

<div {{ $attributes->merge(['class' => 'workflow-stepper' . ($compact ? ' workflow-stepper--compact' : '')]) }}>

    {{--
        Lima kumpulan peringkat UTAMA. Sub-peringkat berada DI DALAM
        kumpulannya, bukan disenaraikan rata di sebelahnya — itulah beza
        antara "sistem lima peringkat" dan "sistem lapan langkah".
    --}}
    @foreach (AliranKerja::UTAMA as $utama => $namaUtama)
        @php
            $sub = AliranKerja::subPeringkat($utama);

            // Kumpulan diberi lebar mengikut bilangan sub-peringkatnya,
            // supaya setiap bulatan sama luas merentas keseluruhan baris.
            $bilanganSub = max(1, count($sub));

            // Peringkat utama dianggap selesai apabila setiap sub-peringkat
            // FASA SEMASA di dalamnya selesai. Peringkat fasa akan datang
            // tidak boleh menyekat kiraan ini: modulnya belum wujud.
            $subSemasa = array_values(array_filter($sub, fn (string $k) => AliranKerja::adalahSemasa($k)));

            $selesaiUtama = $subSemasa !== [] && collect($subSemasa)
                ->every(fn (string $k) => $statusPeringkat($k) === WorkflowStageStatus::SELESAI);

            $akanDatangUtama = $subSemasa === [];
        @endphp

        <div class="workflow-utama {{ $selesaiUtama ? 'workflow-utama--selesai' : '' }} {{ $akanDatangUtama ? 'workflow-utama--akan-datang' : '' }}"
            style="--sub: {{ $bilanganSub }}">

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

                        // Sub-peringkat dipaparkan dengan nombor penuhnya
                        // ('1.1'); peringkat tanpa sub-peringkat memaparkan
                        // nombor utamanya sahaja ('2').
                        $tajuk = AliranKerja::labelPenuh($kunci)
                            . ($status && ! $akanDatang ? ' — ' . $status : '')
                            . ($akanDatang ? ' — belum dibina' : '');
                    @endphp

                    <div class="workflow-step workflow-step--{{ $keadaan }}" title="{{ $tajuk }}">

                        <div class="workflow-step__track" aria-hidden="true"></div>

                        <div class="workflow-step__node">
                            @if ($keadaan === 'selesai')
                                <i class="bi bi-check-lg"></i>
                            @elseif ($akanDatang)
                                <i class="bi bi-hourglass"></i>
                            @else
                                {{ $kunci }}
                            @endif
                        </div>

                        @unless ($compact)
                            <div class="workflow-step__label">
                                <span class="workflow-step__nombor">{{ $kunci }}</span>
                                {{ AliranKerja::label($kunci) }}
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
