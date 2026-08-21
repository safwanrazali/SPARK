@props([
    'workflow' => null,
    'peringkat' => null,
    'compact' => false,
])

@php
    use App\Models\WorkflowStageStatus;

    $peringkatSemasa = $workflow?->current_stage ?? 0;

    /*
     * Status sebenar setiap peringkat, apabila pemanggil membekalkannya.
     *
     * Tanpa ini stepper hanya tahu "peringkat semasa", jadi ia tidak dapat
     * memaparkan dua peringkat yang berjalan serentak — dan itulah keadaan
     * biasa dalam aliran ini: Jana Laporan kekal Dalam Proses sementara
     * Semakan & Kelulusan turut Dalam Proses, sehingga Ketua Bahagian
     * mengesahkan laporan.
     */
    $statusPeringkat = fn (int $nombor): ?string => $peringkat?->get($nombor)?->status;
@endphp

<div {{ $attributes->merge(['class' => 'workflow-stepper' . ($compact ? ' workflow-stepper--compact' : '')]) }}>

    @foreach (\App\Models\WorkflowStatus::WORKFLOW_STAGES as $nombor => $nama)
        @php
            $status = $statusPeringkat($nombor);

            $keadaan = match (true) {
                $status === WorkflowStageStatus::SELESAI => 'selesai',
                $status === WorkflowStageStatus::DALAM_PROSES => 'semasa',
                $status === WorkflowStageStatus::BELUM_MULA => 'menunggu',
                $nombor < $peringkatSemasa => 'selesai',
                $nombor === $peringkatSemasa => 'semasa',
                default => 'menunggu',
            };

            // Peringkat semasa yang belum bergerak tetap ditandakan supaya
            // pengguna nampak di mana giliran berada.
            if ($keadaan === 'menunggu' && $nombor === $peringkatSemasa) {
                $keadaan = 'semasa';
            }
        @endphp

        <div class="workflow-step workflow-step--{{ $keadaan }}"
            title="{{ sprintf('%02d', $nombor) }} — {{ $nama }}{{ $status ? ' — ' . $status : '' }}">

            <div class="workflow-step__track" aria-hidden="true"></div>

            <div class="workflow-step__node">
                @if ($keadaan === 'selesai')
                    <i class="bi bi-check-lg"></i>
                @else
                    {{ sprintf('%02d', $nombor) }}
                @endif
            </div>

            @unless ($compact)
                <div class="workflow-step__label">{{ $nama }}</div>

                @if ($status !== null)
                    <span
                        class="status-badge {{ $peringkat->get($nombor)->statusBadgeClass() }}">{{ $status }}</span>
                @elseif ($keadaan === 'semasa' && $workflow !== null)
                    <span class="status-badge {{ $workflow->statusBadgeClass() }}">{{ $workflow->status }}</span>
                @endif
            @endunless

        </div>
    @endforeach

</div>
