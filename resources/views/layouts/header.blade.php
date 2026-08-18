@php
    $pengguna = auth()->user();
@endphp

<header class="page-header">

    <div>
        <span class="page-eyebrow">Sistem Pemantauan &amp; Pelaporan Analisis Migrasi PQC</span>
        <h1 class="page-title">@yield('page-title', 'Papan Pemuka')</h1>
    </div>

    {{-- Satu ikon akaun sahaja pada header; identiti dan tindakan akaun
         berada dalam menu juntai supaya header kekal ringkas. --}}
    <div class="header-right dropdown">

        <button type="button" class="header-account" id="menuAkaun" data-bs-toggle="dropdown" aria-expanded="false"
            aria-label="Menu akaun {{ $pengguna->name }}">
            <i class="bi bi-person-circle" aria-hidden="true"></i>
        </button>

        <div class="dropdown-menu dropdown-menu-end account-menu" aria-labelledby="menuAkaun">

            <div class="account-menu__identity">
                <p class="account-menu__name">{{ $pengguna->name }}</p>
                <p class="account-menu__username">{{ '@' . $pengguna->username }}</p>
                @foreach ($pengguna->assignedRoleLabels() as $label)
                    <span class="account-menu__role">{{ $label }}</span>
                @endforeach
            </div>

            <hr class="dropdown-divider">

            <a class="dropdown-item" href="{{ route('profil.edit') }}">
                <i class="bi bi-person-gear" aria-hidden="true"></i>
                Profil Saya
            </a>

            <hr class="dropdown-divider">

            <form method="POST" action="{{ route('logout') }}" class="m-0">
                @csrf
                <button type="submit" class="dropdown-item account-menu__logout">
                    <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                    Log Keluar
                </button>
            </form>

        </div>

    </div>

</header>
