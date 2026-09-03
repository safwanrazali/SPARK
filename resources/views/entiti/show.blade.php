@extends('layouts.app')

@section('title', $entiti['agency_code'])

@section('page-title', 'Maklumat Entiti')

@section('content')

    @php
        use App\Models\StatusLaporan;
        use App\Models\WorkflowStageStatus;
        use App\Support\AliranKerja;

        /*
        | Halaman ini ialah HELAIAN REKOD entiti, bukan salinan kedua halaman
        | Kemajuan. Kemajuan memiliki stepper, borang tindakan dan sejarah
        | peringkat; di sini kita hanya menjawab satu soalan: apa yang telah
        | direkodkan bagi entiti ini setakat ini.
        |
        | Kedua-dua senarai di bawah diterbitkan daripada takrifan aliran
        | kerja, bukan ditulis tetap — menambah satu Borang atau satu No.
        | Rujukan pada AliranKerja terus muncul di sini.
        */
        $borang = collect(AliranKerja::kekunci())->filter(
            fn (string $kunci) => array_key_exists(AliranKerja::MEDAN_STATUS_BORANG, AliranKerja::medan($kunci)),
        );

        $berujukan = collect(AliranKerja::kekunci())->filter(
            fn (string $kunci) => AliranKerja::labelRujukan($kunci) !== null,
        );

        $tarikh = fn ($nilai) => $nilai instanceof \Illuminate\Support\Carbon
            ? $nilai->format('d/m/Y')
            : $nilai;
    @endphp

    @include('entiti.partials.maklumat-entiti')

    @include('entiti.partials.kedudukan-semasa')

    @include('entiti.partials.status-borang')

    @include('entiti.partials.no-rujukan')

    <div class="row g-4 mb-4">
        @include('entiti.partials.penugasan')

        @include('entiti.partials.dapatan-analisis')
    </div>

    @include('entiti.partials.laporan')

@endsection