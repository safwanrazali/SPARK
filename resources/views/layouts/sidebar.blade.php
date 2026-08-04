<div class="sidebar" id="sidebar">

    <div class="sidebar-logo">

        <span class="sidebar-title">
            <img src="{{ asset('image/main_logo.png') }}" alt="Logo" class="logo" style="width: 100px; height: auto;">
        </span>

        <button class="btn btn-sm btn-outline-light" id="toggleSidebar">

            <i class="bi bi-list"></i>

        </button>

    </div>

    <ul class="sidebar-menu">

        <li>
            <a href="/">
                <i class="bi bi-grid"></i>
                <span class="menu-text">
                    Papan Pemuka
                </span>
            </a>
        </li>

        <li>
            <a href="{{ route('muat-naik.index') }}">
                <i class="bi bi-upload"></i>
                <span class="menu-text">
                    Muat Naik Data
                </span>
            </a>
        </li>

        <li>
            <a href="{{ route('muat-naik.history') }}">
                <i class="bi bi-clock-history"></i>
                <span class="menu-text">
                    Sejarah Muat Naik
                </span>
            </a>
        </li>

        <li>
            <a href="#">
                <i class="bi bi-bar-chart"></i>
                <span class="menu-text">
                    Analisis Risiko
                </span>
            </a>
        </li>

        <li>
            <a href="#">
                <i class="bi bi-file-earmark-pdf"></i>
                <span class="menu-text">
                    Laporan
                </span>
            </a>
        </li>

        <li>
            <a href="#">
                <i class="bi bi-gear"></i>
                <span class="menu-text">
                    Tetapan
                </span>
            </a>
        </li>

    </ul>

</div>
