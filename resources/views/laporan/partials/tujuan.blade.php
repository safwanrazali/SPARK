    {{-- TUJUAN — gaya dalam resources/scss/laporan-print.scss (.laporan-seksyen).

         Partial ini dikongsi oleh pratonton skrin dan badan PDF. --}}
    <section class="laporan-seksyen">
        <h2 class="laporan-seksyen__tajuk">Tujuan</h2>

        <p class="laporan-seksyen__perenggan">
            Laporan ini disediakan bagi membentangkan dapatan analisis inventori kriptografi
            <strong>{{ $analisis->agency_name ?: '—' }}</strong> berdasarkan data dan maklumat
            yang dikemukakan selaras dengan Arahan Ketua Eksekutif NACSA No. 9.
        </p>

        <p class="laporan-seksyen__perenggan">
            Analisis ini dilaksanakan terhadap data yang dikemukakan melalui Jadual 0: Inventori,
            Jadual 1: <em>Software Bill of Materials</em> (SBOM) dan Jadual 2:
            <em>Cryptographic Bill of Materials</em> (CBOM), yang selepas ini dirujuk secara
            kolektif sebagai Jadual 0–2. Skop analisis merangkumi maklumat aset, komponen
            perisian, algoritma dan protokol kriptografi, pustaka atau modul kriptografi serta
            maklumat vendor yang berkaitan.
        </p>

        <p class="laporan-seksyen__perenggan">
            Laporan ini digunakan sebagai dokumen rujukan rasmi dalam pelaksanaan Klinik Migrasi
            Kriptografi Pasca-Kuantum (PQC) bagi membincangkan dapatan, mendapatkan pengesahan
            daripada entiti serta mengenal pasti tindakan susulan yang diperlukan bagi menyokong
            penilaian risiko dan perancangan migrasi PQC.
        </p>
    </section>
