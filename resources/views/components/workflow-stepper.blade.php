@props([
    'workflow' => null,
    'compact' => false,
])

@php
    $peringkatSemasa = $workflow?->current_stage ?? 0;
@endphp

<div {{ $attributes->merge(['class' => 'workflow-stepper' . ($compact ? ' workflow-stepper--compact' : '')]) }}>

    @foreach (\App\Models\WorkflowStatus::WORKFLOW_STAGES as $nombor => $nama)
        @php
            $keadaan = match (true) {
                $nombor < $peringkatSemasa => 'selesai',
                $nombor === $peringkatSemasa => 'semasa',
                default => 'menunggu',
            };
        @endphp

        <div class="workflow-step workflow-step--{{ $keadaan }}"
            title="{{ sprintf('%02d', $nombor) }} — {{ $nama }}">

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

                @if ($keadaan === 'semasa' && $workflow !== null)
                    <span class="status-badge {{ $workflow->statusBadgeClass() }}">{{ $workflow->status }}</span>
                @endif
            @endunless

        </div>
    @endforeach

</div>
