{{-- `use` MESTI diulang dalam setiap partial: @include dikompil menjadi
     fail PHP tersendiri, jadi import templat induk TIDAK diwarisi. --}}
@php
    use App\Support\AliranKerja;
@endphp

    <div class="report-card">
        <h4 class="section-title">Belum Memasuki Aliran Kerja</h4>
        <p class="text-secondary mb-0">
            Entiti ini belum memasuki aliran kerja kerana peringkat
            <strong>1.1 Penerimaan Data</strong> belum dimulakan.

            @if ($bolehKendali(AliranKerja::PERTAMA))
                Rekodkan Tarikh Terima dan Status Borang Penerimaan Data di
                atas — entiti akan memasuki aliran kerja dan peringkat
                <strong>1.2 Pendaftaran Data</strong> terbuka.
            @else
                Ketua Bahagian atau Pegawai Penyelaras Analisis perlu
                merekodkan peringkat tersebut terlebih dahulu.
            @endif
        </p>
    </div>
