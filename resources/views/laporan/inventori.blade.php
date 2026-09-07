@extends('layouts.app')

@section('title', 'Laporan Analisis Inventori Kriptografi — ' . $analisis->agency_name)

@section('page-title', 'Laporan Inventori Kriptografi')

@section('content')

    <div class="report-card mb-4 d-print-none">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="text-secondary">
                Templat + business rules + input berstruktur → laporan.
                Betulkan input melalui borang analisis sebelum laporan dimuktamadkan.
            </span>
            <div class="d-flex gap-2">
                @can('manage-analysis')
                    <a class="btn btn-outline-light"
                        href="{{ route('analisis.borang', ['sector_code' => $analisis->sector_code, 'agency_code' => $analisis->agency_code]) }}">
                        <i class="bi bi-pencil"></i> Betulkan Input
                    </a>
                @endcan
                <a class="btn btn-primary" href="{{ route('laporan.unduh', $analisis) }}">
                    <i class="bi bi-file-earmark-pdf"></i> Muat Turun PDF
                </a>
            </div>
        </div>
    </div>

    {{-- Gaya pratonton laporan: resources/scss/laporan-pratonton.scss --}}
    <div class="laporan-rasmi">
        <div class="laporan-rasmi__jata">
            <img class="laporan-rasmi__jata-nacsa" src="{{ asset('image/logo_nacsa.png') }}">
            <div class="klasifikasi mb-3">RAHSIA</div>
            <img class="laporan-rasmi__jata-ptpkm" src="{{ asset('image/logo_ptpkm.png') }}">
        </div>


        {{-- Badan laporan dikongsi BAIT DEMI BAIT dengan muat turun PDF
             (resources/views/laporan/pdf/body.blade.php): kedua-duanya
             memasukkan partial yang SAMA di bawah, jadi kandungan laporan
             tidak boleh lagi terpesong antara skrin dan PDF.

             $widgetCatatan menghidupkan widget catatan KB/PPA pada setiap
             tajuk seksyen. Ia BENAR di sini kerana ini paparan skrin; badan
             PDF menetapkannya palsu. --}}
        @php $widgetCatatan = true; @endphp

        @include('laporan.partials.pengenalan')
        @include('laporan.partials.tujuan')
        @include('laporan.partials.status-data')
        @include('laporan.partials.dapatan')
        @include('laporan.partials.tindakan')
        @include('laporan.partials.kesimpulan')
        @include('laporan.partials.pengesahan')

        <div class="laporan-rasmi__footer">
            <span>{{ $analisis->kod_rujukan ?? '[KOD RUJUKAN FAIL]' }}</span>
            <span>1</span>
        </div>
    </div>

@endsection
