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

        @can('view-dashboard')
            <li>
                <a href="{{ route('dashboard') }}">
                    <i class="bi bi-grid"></i>
                    <span class="menu-text">Papan Pemuka</span>
                </a>
            </li>
        @endcan

        <li class="sidebar-section-title"><span class="menu-text">Inventori</span></li>

        @can('manage-upload')
            <li>
                <a href="{{ route('muat-naik.index') }}">
                    <i class="bi bi-upload"></i>
                    <span class="menu-text">Muat Naik MasterTable</span>
                </a>
            </li>
        @endcan

        <li>
            <a href="{{ route('muat-naik.history') }}">
                <i class="bi bi-clock-history"></i>
                <span class="menu-text">Sejarah Muat Naik</span>
            </a>
        </li>

        <li>
            <a href="{{ route('analisis.index') }}">
                <i class="bi bi-hdd-stack"></i>
                <span class="menu-text">Analisis Inventori Kriptografi</span>
            </a>
        </li>

        <li class="sidebar-section-title"><span class="menu-text">Pemantauan</span></li>

        @can('manage-assignment')
            <li>
                <a href="{{ route('penugasan.index') }}">
                    <i class="bi bi-person-check"></i>
                    <span class="menu-text">Penugasan Entiti</span>
                </a>
            </li>
        @endcan

        <li>
            <a href="{{ route('workflow.index') }}">
                <i class="bi bi-diagram-3"></i>
                <span class="menu-text">Kemajuan Workflow</span>
            </a>
        </li>

        <li>
            <a href="{{ route('status.index') }}">
                <i class="bi bi-list-check"></i>
                <span class="menu-text">Status Tiga Laporan</span>
            </a>
        </li>

        @can('view-audit-trail')
            <li>
                <a href="{{ route('audit.index') }}">
                    <i class="bi bi-shield-check"></i>
                    <span class="menu-text">Jejak Audit</span>
                </a>
            </li>
        @endcan

        <li class="sidebar-section-title"><span class="menu-text">Penilaian Risiko</span></li>

        <li>
            <a href="#" class="disabled-link" title="Modul Fasa 1 seterusnya (roadmap Okt–Nov)">
                <i class="bi bi-shield-exclamation"></i>
                <span class="menu-text">Penilaian Risiko PQC</span>
            </a>
        </li>

        <li class="sidebar-section-title"><span class="menu-text">Laporan</span></li>

        <li>
            <a href="{{ route('laporan.index') }}">
                <i class="bi bi-file-earmark-bar-graph"></i>
                <span class="menu-text">Laporan Inventori</span>
            </a>
        </li>

        <li>
            <a href="#" class="disabled-link" title="Modul Fasa 1 seterusnya (roadmap Okt–Nov)">
                <i class="bi bi-file-earmark-medical"></i>
                <span class="menu-text">Laporan Risiko</span>
            </a>
        </li>

        <li>
            <a href="#" class="disabled-link" title="Modul Fasa 1 seterusnya (roadmap Nov–Dis)">
                <i class="bi bi-file-earmark-richtext"></i>
                <span class="menu-text">Laporan Kesiapsiagaan</span>
            </a>
        </li>

        @can('access-administration')
            <li class="sidebar-section-title"><span class="menu-text">Pentadbiran</span></li>

            <li>
                <a href="{{ route('administration.users.index') }}">
                    <i class="bi bi-people"></i>
                    <span class="menu-text">Pengguna</span>
                </a>
            </li>
        @endcan

    </ul>

</div>
