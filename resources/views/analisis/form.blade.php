@extends('layouts.app')

@section('title', 'Borang Analisis — ' . $agensi['code'])

@section('page-title', 'Input Analisis Berstruktur')

@section('content')

    @include('analisis.partials.kepala')

    @include('analisis.partials.draf-bar')

    <form action="{{ route('analisis.simpan') }}" method="POST" id="borang-analisis">
        @csrf
        <input type="hidden" name="sector_code" value="{{ $sectorCode }}">
        <input type="hidden" name="agency_code" value="{{ $agensi['code'] }}">
        <input type="hidden" name="seksyen" id="seksyen-semasa" value="">

        @include('analisis.partials.seksyen-maklumat')

        @include('analisis.partials.seksyen-status-data')

        @include('analisis.partials.seksyen-profil')

        @include('analisis.partials.seksyen-algoritma')

        @include('analisis.partials.seksyen-protokol-pustaka-vendor')

        @include('analisis.partials.seksyen-tindakan')

        @include('analisis.partials.seksyen-kesimpulan')

        @include('analisis.partials.butang-simpan')

    </form>

    {{-- Skrip kekal SEBARIS (bukan modul Vite) supaya masa pelaksanaan,
         susunan dan capaiannya kepada DOM halaman ini tidak berubah. --}}
    @include('analisis.partials.skrip')

@endsection
