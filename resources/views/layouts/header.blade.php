<div class="page-header">

    <div>
        <h2 class="page-title">
            Sistem Pemantauan & Pelaporan Analisis Migrasi PQC

        </h2>
        <p class="page-subtitle">
            @yield('page-title')
        </p>
    </div>

    <div class="header-right d-flex align-items-center gap-3">
        <span>
            {{ auth()->user()->name }}
        </span>

        <form method="POST" action="{{ route('logout') }}" class="m-0">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-light">
                <i class="bi bi-box-arrow-right"></i> Log Keluar
            </button>
        </form>
    </div>

</div>
