@extends('layouts.app')

@section('title', 'Papan Pemuka')

@section('page-title', 'Papan Pemuka Pemantauan')

@section('content')

    @include('dashboard.partials.penapis')

    @include('dashboard.partials.kad-entiti')

    @include('dashboard.partials.kad-laporan')

    @include('dashboard.partials.carta')

    @include('dashboard.partials.aktiviti')

@endsection
