{{-- `use` MESTI diulang dalam setiap partial: @include dikompil menjadi
     fail PHP tersendiri, jadi import templat induk TIDAK diwarisi. --}}
@php
    use App\Support\AliranKerja;
@endphp

    {{--
        ── Kedudukan Semasa ─────────────────────────────────────────────
        Empat angka, tiada stepper: stepper milik halaman Kemajuan, dan
        memaparkannya di kedua-dua tempat itulah yang menjadikan halaman ini
        terasa berulang. Di sini kita hanya menamakan peringkat yang sedang
        aktif dan siapa memegangnya.
    --}}
    <div class="report-card mb-4">

        <h4 class="section-title">Kedudukan Semasa</h4>

        <div class="row g-3 workflow-meta">
            <div class="col-md-3">
                <div class="stat-title">Peringkat Aktif</div>
                <div class="workflow-meta__value">
                    @if ($dalamAliran)
                        <span class="workflow-stage-tag">{{ $peringkatSemasa }}</span>
                        {{ AliranKerja::label($peringkatSemasa) }}
                    @else
                        <span class="text-secondary">Belum Didaftarkan</span>
                    @endif
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-title">Status Keseluruhan</div>
                <div class="workflow-meta__value">
                    <span class="status-badge {{ $badgeKeseluruhan }}">{{ $keseluruhan }}</span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-title">Peringkat Selesai</div>
                <div class="workflow-meta__value">
                    {{ $siapFasa }} / {{ $jumlahFasa }}
                    <small class="text-secondary">
                        ({{ $jumlahFasa ? round(($siapFasa / $jumlahFasa) * 100) : 0 }}%)
                    </small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-title">Pegawai Analisis</div>
                <div class="workflow-meta__value">
                    {{ $penugasan?->assignedTo?->name ?? '-' }}
                </div>
            </div>
        </div>

    </div>
