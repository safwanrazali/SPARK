<div class="sidebar" id="sidebar">

    <div class="sidebar-logo">
        <a href="/" class="sidebar-brand">
            <img src="{{ asset('image/main_logo.png') }}" alt="SPARK" class="logo">
        </a>
        <button class="sidebar-toggle" id="toggleSidebar" aria-label="Toggle sidebar">
            <i class="bi bi-list"></i>
        </button>
    </div>

    <ul class="sidebar-menu">

        <li>
            <a href="/">
                <i class="bi bi-grid"></i>
                <span class="menu-text">Papan Pemuka</span>
            </a>
        </li>

        <li class="sidebar-section-title"><span class="menu-text">Inventori</span></li>

        <li>
            <a href="{{ route('muat-naik.index') }}">
                <i class="bi bi-upload"></i>
                <span class="menu-text">Muat Naik MasterTable</span>
            </a>
        </li>

        <li>
            <a href="{{ route('muat-naik.history') }}">
                <i class="bi bi-clock-history"></i>
                <span class="menu-text">Sejarah Muat Naik</span>
            </a>
        </li>

        <li>
            <a href="#" class="disabled-link" title="Akan datang">
                <i class="bi bi-hdd-stack"></i>
                <span class="menu-text">Inventori Kriptografi</span>
            </a>
        </li>

        <li class="sidebar-section-title"><span class="menu-text">Penilaian Risiko</span></li>

        <li>
            <a href="#" class="disabled-link" title="Akan datang">
                <i class="bi bi-shield-exclamation"></i>
                <span class="menu-text">Penilaian Risiko</span>
            </a>
        </li>

        <li>
            <a href="#" class="disabled-link" title="Akan datang">
                <i class="bi bi-journal-text"></i>
                <span class="menu-text">Sejarah Penilaian</span>
            </a>
        </li>

        <li class="sidebar-section-title"><span class="menu-text">Laporan</span></li>

        <li>
            <a href="#" class="disabled-link" title="Akan datang">
                <i class="bi bi-file-earmark-bar-graph"></i>
                <span class="menu-text">Laporan Inventori</span>
            </a>
        </li>

        <li>
            <a href="#" class="disabled-link" title="Akan datang">
                <i class="bi bi-file-earmark-medical"></i>
                <span class="menu-text">Laporan Risiko</span>
            </a>
        </li>

        <li>
            <a href="#" class="disabled-link" title="Akan datang">
                <i class="bi bi-file-earmark-richtext"></i>
                <span class="menu-text">Laporan Menyeluruh</span>
            </a>
        </li>

        @can('access-administration')
            <li class="sidebar-section-title"><span class="menu-text">Pentadbiran</span></li>

            <li>
                <a href="#" class="disabled-link" title="Akan datang">
                    <i class="bi bi-people"></i>
                    <span class="menu-text">Pengguna</span>
                </a>
            </li>

            <li>
                <a href="#" class="disabled-link" title="Akan datang">
                    <i class="bi bi-key"></i>
                    <span class="menu-text">Peranan</span>
                </a>
            </li>

            <li>
                <a href="#" class="disabled-link" title="Akan datang">
                    <i class="bi bi-gear"></i>
                    <span class="menu-text">Tetapan</span>
                </a>
            </li>
        @endcan

        <li>
            <a href="{{ route('administration.users.index') }}">
                <i class="bi bi-people"></i>
                <span class="menu-text">Pengguna</span>
            </a>
        </li>

    </ul>

</div>
