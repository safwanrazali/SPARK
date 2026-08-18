<header class="page-header">

    <div>
        <span class="page-eyebrow">Sistem Pemantauan &amp; Pelaporan Analisis Migrasi PQC</span>
        <h1 class="page-title">@yield('page-title', 'Papan Pemuka')</h1>
    </div>

    <div class="header-right d-flex align-items-center gap-3">

        <a href="{{ route('profil.edit') }}" class="header-user" title="Profil Saya">
            <i class="bi bi-person-circle" aria-hidden="true"></i>
            <span>{{ auth()->user()->name }}</span>
            <span class="header-user__role">{{ auth()->user()->roleLabel() }}</span>
        </a>

        <form method="POST" action="{{ route('logout') }}" class="m-0">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-light">
                <i class="bi bi-box-arrow-right" aria-hidden="true"></i> Log Keluar
            </button>
        </form>

    </div>

</header>
