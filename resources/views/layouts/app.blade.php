<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Sistem Pemantauan & Pelaporan Analisis Migrasi PQC')</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>

<body>

    {{-- Fasa 11 — sasaran fokus pertama untuk pengguna papan kekunci. --}}
    <a class="skip-link" href="#kandungan">Langkau ke kandungan utama</a>

    <div class="route-progress" id="route-progress" aria-hidden="true"></div>

    @include('layouts.sidebar')

    <button type="button" class="sidebar-backdrop" id="sidebarBackdrop" tabindex="-1"
        aria-label="Tutup menu navigasi"></button>

    <div class="main-content">

        @include('layouts.header')

        <main class="content-wrapper" id="kandungan" tabindex="-1">

            @if (session('success'))
                <x-alert type="success">{{ session('success') }}</x-alert>
            @endif

            @if (session('error'))
                <x-alert type="danger">{{ session('error') }}</x-alert>
            @endif

            @if ($errors->any())
                <x-alert type="danger" title="Sila betulkan perkara berikut:">
                    @if ($errors->count() === 1)
                        {{ $errors->first() }}
                    @else
                        <ul class="app-alert__list">
                            @foreach ($errors->all() as $ralat)
                                <li>{{ $ralat }}</li>
                            @endforeach
                        </ul>
                    @endif
                </x-alert>
            @endif

            @yield('content')

        </main>

    </div>

</body>

</html>
