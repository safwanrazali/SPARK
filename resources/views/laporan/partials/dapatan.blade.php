    {{-- RINGKASAN DAPATAN ANALISIS INVENTORI KRIPTOGRAFI + lima subseksyen.
         Gaya dalam resources/scss/laporan-print.scss (.laporan-seksyen /
         .laporan-jadual-ringkas).

         Partial ini dikongsi oleh pratonton skrin dan badan PDF, jadi tiada
         lagi pasangan yang perlu diselaraskan secara manual. --}}
    <section class="laporan-seksyen laporan-seksyen--mula-halaman">
        <h2 class="laporan-seksyen__tajuk">Ringkasan Dapatan Analisis Inventori Kriptografi</h2>

        <p class="laporan-seksyen__perenggan">
            Bahagian ini merumuskan dapatan utama hasil analisis inventori kriptografi berdasarkan
            data dalam Jadual 0–2, merangkumi profil sistem dan aset, penggunaan algoritma dan
            protokol kriptografi, pustaka atau modul kriptografi serta maklumat vendor yang
            berkaitan.
        </p>
        @include('laporan.partials.dapatan-profil')
        @include('laporan.partials.dapatan-algoritma')
        @include('laporan.partials.dapatan-protokol')
        @include('laporan.partials.dapatan-pustaka')
        @include('laporan.partials.dapatan-vendor')
    </section>
