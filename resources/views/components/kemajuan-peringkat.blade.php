@props([
    'nombor',
    'nama',
    'rekod' => null,
    'semasa' => false,
    'dikunci' => false,
    'keterangan' => null,
])

@php
    use App\Models\WorkflowStageStatus;

    $status = $rekod?->status ?? WorkflowStageStatus::BELUM_MULA;

    // Empat keadaan visual: selesai, sedang dikerjakan, dikunci (peringkat
    // sebelumnya belum selesai) dan akan datang.
    //
    // Peringkat yang telah bermula sentiasa dipaparkan sebagai aktif walaupun
    // pendahulunya belum Selesai — "Semakan & Kelulusan" berjalan serentak
    // dengan "Jana Laporan", jadi memudarkannya akan mengelirukan.
    $keadaan = match (true) {
        $status === WorkflowStageStatus::SELESAI => 'selesai',
        $status === WorkflowStageStatus::DALAM_PROSES, $semasa => 'semasa',
        $dikunci => 'dikunci',
        default => 'menunggu',
    };
@endphp

<li class="kemajuan-stage kemajuan-stage--{{ $keadaan }}">

    <div class="kemajuan-stage__node" aria-hidden="true">
        @if ($keadaan === 'selesai')
            <i class="bi bi-check-lg"></i>
        @elseif ($keadaan === 'dikunci')
            <i class="bi bi-lock-fill"></i>
        @else
            {{ sprintf('%02d', $nombor) }}
        @endif
    </div>

    <div class="kemajuan-stage__body">

        <div class="kemajuan-stage__head">
            <h5 class="kemajuan-stage__title">
                <span class="kemajuan-stage__number">{{ sprintf('%02d', $nombor) }}</span>
                {{ $nama }}
            </h5>
            <span class="status-badge {{ $rekod?->statusBadgeClass() ?? 'status-tinggi' }}">
                {{ $status }}
            </span>
        </div>

        @if ($keterangan)
            <p class="kemajuan-stage__note">{{ $keterangan }}</p>
        @endif

        @if ($rekod?->completed_at)
            <p class="kemajuan-stage__meta">
                Selesai {{ $rekod->completed_at->format('d/m/Y H:i') }}
                @if ($rekod->updatedBy) — {{ $rekod->updatedBy->name }} @endif
            </p>
        @elseif ($rekod?->started_at)
            <p class="kemajuan-stage__meta">
                Bermula {{ $rekod->started_at->format('d/m/Y H:i') }}
            </p>
        @endif

        {{-- Tindakan hanya dipaparkan apabila pengguna benar-benar boleh
             melakukannya; slot ini dibiarkan kosong jika tiada. --}}
        @if (trim($slot) !== '')
            <div class="kemajuan-stage__actions">{{ $slot }}</div>
        @endif

    </div>

</li>
