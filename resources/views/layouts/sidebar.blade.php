@php
    // Fasa 11 — penunjuk halaman semasa untuk navigasi sisi.
    $pautan = function (string $pattern) {
        return request()->routeIs($pattern) ? 'active' : '';
    };

    // Navlink induk ialah label kumpulan statik — ia menyerlah apabila salah
    // satu SubNavlink di bawahnya ialah halaman semasa.
    $pemantauanAktif = request()->routeIs('penugasan.*', 'workflow.*');
    $laporanAktif = request()->routeIs('analisis.*', 'status.*');
    $pentadbiranAktif = request()->routeIs('administration.*');
@endphp

<nav class="sidebar" id="sidebar" aria-label="Navigasi utama">

    <div class="sidebar-logo">
        {{-- Tanda jenama sahaja pada rel; jata penuh apabila menu mengembang. --}}
        <a href="{{ route('dashboard') }}" class="sidebar-brand" aria-label="Halaman utama SPARK">
            <img src="{{ asset('image/main_icon.png') }}" alt="" aria-hidden="true" class="logo-mark">
            <img src="{{ asset('image/main_logo.png') }}" alt="" aria-hidden="true" class="logo">
        </a>
        {{-- Mengunci menu supaya ia kekal terbuka; atribut ditetapkan semula
             oleh app.js mengikut saiz skrin. --}}
        <button type="button" class="sidebar-toggle" id="toggleSidebar"
            aria-label="Kunci menu navigasi supaya kekal terbuka" aria-controls="sidebar" aria-pressed="false">
            <i class="bi bi-unlock" aria-hidden="true"></i>
        </button>
    </div>

    <ul class="sidebar-menu">

        {{-- 1. Papan Pemuka --}}
        @can('view-dashboard')
            <li>
                <a href="{{ route('dashboard') }}" class="{{ $pautan('dashboard') }}" title="Papan Pemuka"
                    @if (request()->routeIs('dashboard')) aria-current="page" @endif>
                    <i class="bi bi-grid" aria-hidden="true"></i>
                    <span class="menu-text">Papan Pemuka</span>
                </a>
            </li>
        @endcan

        {{-- 2. Pemantauan Entiti --}}
        <li>
            <a class="sidebar-parent {{ $pemantauanAktif ? 'active' : '' }}" id="navPemantauanEntiti"
                title="Pemantauan Entiti">
                <i class="bi bi-binoculars" aria-hidden="true"></i>
                <span class="menu-text">Pemantauan Entiti</span>
            </a>

            <ul class="sidebar-submenu" aria-labelledby="navPemantauanEntiti">
                {{-- 2.1 Penetapan Entiti --}}
                @can('manage-assignment')
                    <li>
                        <a href="{{ route('penugasan.index') }}" class="{{ $pautan('penugasan.*') }}"
                            title="Penetapan Entiti" @if (request()->routeIs('penugasan.*')) aria-current="page" @endif>
                            <i class="bi bi-person-check" aria-hidden="true"></i>
                            <span class="menu-text">Penetapan Entiti</span>
                        </a>
                    </li>
                @endcan

                {{-- 2.2 Kemajuan Analisis Entiti --}}
                <li>
                    <a href="{{ route('workflow.index') }}" class="{{ $pautan('workflow.*') }}"
                        title="Kemajuan Analisis Entiti"
                        @if (request()->routeIs('workflow.*')) aria-current="page" @endif>
                        <i class="bi bi-diagram-3" aria-hidden="true"></i>
                        <span class="menu-text">Kemajuan Analisis Entiti</span>
                    </a>
                </li>
            </ul>
        </li>

        {{-- 3. Laporan --}}
        <li>
            <a class="sidebar-parent {{ $laporanAktif ? 'active' : '' }}" id="navLaporan" title="Laporan">
                {{-- Ikon carta dikekalkan daripada menu Laporan terdahulu; kelas
                     bi-file-earmark-text digunakan sebagai penanda pautan laporan
                     pada halaman entiti, jadi ia sengaja dielakkan di sini. --}}
                <i class="bi bi-file-earmark-bar-graph" aria-hidden="true"></i>
                <span class="menu-text">Laporan</span>
            </a>

            <ul class="sidebar-submenu" aria-labelledby="navLaporan">
                {{-- 3.1 Analisis Inventori Kriptografi --}}
                <li>
                    <a href="{{ route('analisis.index') }}" class="{{ $pautan('analisis.*') }}"
                        title="Analisis Inventori Kriptografi"
                        @if (request()->routeIs('analisis.*')) aria-current="page" @endif>
                        <i class="bi bi-hdd-stack" aria-hidden="true"></i>
                        <span class="menu-text">Analisis Inventori Kriptografi</span>
                    </a>
                </li>

                {{--
                    3.2 Penilaian Risiko PQC dan 3.3 Laporan Kesiapsiagaan sengaja
                    disembunyikan buat masa ini. Halaman, laluan dan komponennya
                    dikekalkan; hanya masukan menu ini yang dilindungi sehingga
                    modul berkenaan dibuka pada fasa berikutnya.

                    <li>
                        <a href="#" class="disabled-link" title="Modul Fasa 1 seterusnya (roadmap Okt–Nov)"
                            aria-disabled="true">
                            <i class="bi bi-shield-exclamation" aria-hidden="true"></i>
                            <span class="menu-text">Penilaian Risiko PQC</span>
                        </a>
                    </li>

                    <li>
                        <a href="#" class="disabled-link" title="Modul Fasa 1 seterusnya (roadmap Nov–Dis)"
                            aria-disabled="true">
                            <i class="bi bi-file-earmark-richtext" aria-hidden="true"></i>
                            <span class="menu-text">Laporan Kesiapsiagaan</span>
                        </a>
                    </li>
                --}}

                {{-- 3.4 Status 3 Laporan --}}
                <li>
                    <a href="{{ route('status.index') }}" class="{{ $pautan('status.*') }}" title="Status 3 Laporan"
                        @if (request()->routeIs('status.*')) aria-current="page" @endif>
                        <i class="bi bi-list-check" aria-hidden="true"></i>
                        <span class="menu-text">Status 3 Laporan</span>
                    </a>
                </li>
            </ul>
        </li>

        {{-- 4. Log Audit --}}
        @can('view-audit-trail')
            <li>
                <a href="{{ route('audit.index') }}" class="{{ $pautan('audit.*') }}" title="Log Audit"
                    @if (request()->routeIs('audit.*')) aria-current="page" @endif>
                    <i class="bi bi-shield-check" aria-hidden="true"></i>
                    <span class="menu-text">Log Audit</span>
                </a>
            </li>
        @endcan

        {{-- 5. Pentadbiran --}}
        @can('access-administration')
            <li>
                <a class="sidebar-parent {{ $pentadbiranAktif ? 'active' : '' }}" id="navPentadbiran"
                    title="Pentadbiran">
                    <i class="bi bi-gear" aria-hidden="true"></i>
                    <span class="menu-text">Pentadbiran</span>
                </a>

                <ul class="sidebar-submenu" aria-labelledby="navPentadbiran">
                    {{-- 5.1 Pengguna --}}
                    <li>
                        <a href="{{ route('administration.users.index') }}" class="{{ $pautan('administration.*') }}"
                            title="Pengguna" @if (request()->routeIs('administration.*')) aria-current="page" @endif>
                            <i class="bi bi-people" aria-hidden="true"></i>
                            <span class="menu-text">Pengguna</span>
                        </a>
                    </li>
                </ul>
            </li>
        @endcan

    </ul>

</nav>
