@php
    // Fasa 11 — penunjuk halaman semasa untuk navigasi sisi.
    $pautan = function (string $pattern) {
        return request()->routeIs($pattern) ? 'active' : '';
    };
@endphp

<nav class="sidebar" id="sidebar" aria-label="Navigasi utama">

    <div class="sidebar-logo">
        <a href="{{ route('dashboard') }}" class="sidebar-brand">
            <img src="{{ asset('image/main_logo.png') }}" alt="Halaman utama SPARK" class="logo">
        </a>
        <button class="sidebar-toggle" id="toggleSidebar" aria-label="Buka atau tutup menu navigasi"
            aria-controls="sidebar" aria-expanded="true">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
    </div>

    <ul class="sidebar-menu">

        @can('view-dashboard')
            <li>
                <a href="{{ route('dashboard') }}" class="{{ $pautan('dashboard') }}" title="Papan Pemuka"
                    @if (request()->routeIs('dashboard')) aria-current="page" @endif>
                    <i class="bi bi-grid" aria-hidden="true"></i>
                    <span class="menu-text">Papan Pemuka</span>
                </a>
            </li>
        @endcan

        <li class="sidebar-section-title"><span class="menu-text">Inventori</span></li>

        @can('manage-upload')
            <li>
                <a href="{{ route('muat-naik.index') }}" class="{{ $pautan('muat-naik.index') }}"
                    title="Muat Naik MasterTable" @if (request()->routeIs('muat-naik.index')) aria-current="page" @endif>
                    <i class="bi bi-upload" aria-hidden="true"></i>
                    <span class="menu-text">Muat Naik MasterTable</span>
                </a>
            </li>
        @endcan

        <li>
            <a href="{{ route('muat-naik.history') }}" class="{{ $pautan('muat-naik.history') }}"
                title="Sejarah Muat Naik" @if (request()->routeIs('muat-naik.history')) aria-current="page" @endif>
                <i class="bi bi-clock-history" aria-hidden="true"></i>
                <span class="menu-text">Sejarah Muat Naik</span>
            </a>
        </li>

        <li>
            <a href="{{ route('analisis.index') }}" class="{{ $pautan('analisis.*') }}"
                title="Analisis Inventori Kriptografi" @if (request()->routeIs('analisis.*')) aria-current="page" @endif>
                <i class="bi bi-hdd-stack" aria-hidden="true"></i>
                <span class="menu-text">Analisis Inventori Kriptografi</span>
            </a>
        </li>

        <li class="sidebar-section-title"><span class="menu-text">Pemantauan</span></li>

        @can('manage-assignment')
            <li>
                <a href="{{ route('penugasan.index') }}" class="{{ $pautan('penugasan.*') }}" title="Penugasan Entiti"
                    @if (request()->routeIs('penugasan.*')) aria-current="page" @endif>
                    <i class="bi bi-person-check" aria-hidden="true"></i>
                    <span class="menu-text">Penugasan Entiti</span>
                </a>
            </li>
        @endcan

        <li>
            <a href="{{ route('workflow.index') }}" class="{{ $pautan('workflow.*') }}" title="Kemajuan Workflow"
                @if (request()->routeIs('workflow.*')) aria-current="page" @endif>
                <i class="bi bi-diagram-3" aria-hidden="true"></i>
                <span class="menu-text">Kemajuan Workflow</span>
            </a>
        </li>

        <li>
            <a href="{{ route('status.index') }}" class="{{ $pautan('status.*') }}" title="Status Tiga Laporan"
                @if (request()->routeIs('status.*')) aria-current="page" @endif>
                <i class="bi bi-list-check" aria-hidden="true"></i>
                <span class="menu-text">Status Tiga Laporan</span>
            </a>
        </li>

        @can('view-audit-trail')
            <li>
                <a href="{{ route('audit.index') }}" class="{{ $pautan('audit.*') }}" title="Jejak Audit"
                    @if (request()->routeIs('audit.*')) aria-current="page" @endif>
                    <i class="bi bi-shield-check" aria-hidden="true"></i>
                    <span class="menu-text">Jejak Audit</span>
                </a>
            </li>
        @endcan

        <li class="sidebar-section-title"><span class="menu-text">Penilaian Risiko</span></li>

        <li>
            <a href="#" class="disabled-link" title="Modul Fasa 1 seterusnya (roadmap Okt–Nov)"
                aria-disabled="true">
                <i class="bi bi-shield-exclamation" aria-hidden="true"></i>
                <span class="menu-text">Penilaian Risiko PQC</span>
            </a>
        </li>

        <li class="sidebar-section-title"><span class="menu-text">Laporan</span></li>

        <li>
            <a href="{{ route('laporan.index') }}" class="{{ $pautan('laporan.*') }}" title="Laporan Inventori"
                @if (request()->routeIs('laporan.*')) aria-current="page" @endif>
                <i class="bi bi-file-earmark-bar-graph" aria-hidden="true"></i>
                <span class="menu-text">Laporan Inventori</span>
            </a>
        </li>

        <li>
            <a href="#" class="disabled-link" title="Modul Fasa 1 seterusnya (roadmap Okt–Nov)"
                aria-disabled="true">
                <i class="bi bi-file-earmark-medical" aria-hidden="true"></i>
                <span class="menu-text">Laporan Risiko</span>
            </a>
        </li>

        <li>
            <a href="#" class="disabled-link" title="Modul Fasa 1 seterusnya (roadmap Nov–Dis)"
                aria-disabled="true">
                <i class="bi bi-file-earmark-richtext" aria-hidden="true"></i>
                <span class="menu-text">Laporan Kesiapsiagaan</span>
            </a>
        </li>

        @can('access-administration')
            <li class="sidebar-section-title"><span class="menu-text">Pentadbiran</span></li>

            <li>
                <a href="{{ route('administration.users.index') }}" class="{{ $pautan('administration.*') }}"
                    title="Pengguna" @if (request()->routeIs('administration.*')) aria-current="page" @endif>
                    <i class="bi bi-people" aria-hidden="true"></i>
                    <span class="menu-text">Pengguna</span>
                </a>
            </li>
        @endcan

    </ul>

</nav>
