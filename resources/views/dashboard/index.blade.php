@extends('layouts.app')

@section('title', 'Papan Pemuka')

@section('page-title', 'Papan Pemuka')

@section('content')

    <div class="dashboard-grid">

        <div class="stat-card">
            <div class="stat-title">
                Jumlah Entiti
            </div>

            <div class="stat-value">

                {{ $jumlahMuatNaik }}

            </div>
        </div>

        <div class="stat-card">
            <div class="stat-title">
                Jumlah Aset
            </div>

            <div class="stat-value">
                0
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-title">
                Analisis Risiko
            </div>

            <div class="stat-value">
                0
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-title">
                Laporan Dijana
            </div>

            <div class="stat-value">
                0
            </div>
        </div>

    </div>

    <div class="dashboard-section">

        <div class="report-card">

            <h4 class="section-title">
                Aktiviti Terkini
            </h4>

            <p class="text-secondary">
                Tiada rekod tersedia.
            </p>

        </div>

    </div>

    <div class="dashboard-section">

        <div class="report-card">

            <h4 class="section-title">
                Ringkasan Risiko
            </h4>

            <div style="height:300px">

                Carta Analisis Risiko Akan Dipaparkan Di Sini

            </div>

        </div>

    </div>

@endsection
