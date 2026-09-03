{{-- `use` MESTI diulang dalam setiap partial: @include dikompil menjadi
     fail PHP tersendiri, jadi import templat induk TIDAK diwarisi. --}}
@php
    use App\Support\AliranKerja;
@endphp

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
