    {{--
        Baris metrik 2/2 — bilangan laporan mengikut jenis (StatusLaporan::JENIS).

        Hanya laporan yang telah diserahkan kepada NACSA dikira; laporan yang
        masih dalam kitaran semakan belum menjadi laporan yang "ada" dalam
        sistem. Nota di bawah setiap nilai menyatakannya supaya angka itu tidak
        disalah anggap sebagai jumlah laporan yang sedang disediakan.
    --}}
    <div class="metric-row metric-row--laporan">

        <div class="metric-card">
            <div class="metric-card__label">
                <span class="metric-card__dot is-primary"></span>
                Jumlah Laporan Analisis Inventori Kriptografi
            </div>
            <div class="metric-card__value">{{ $jumlahLaporan['inventori'] }}</div>
            <div class="metric-card__bar is-primary"></div>
        </div>

        <div class="metric-card">
            <div class="metric-card__label">
                <span class="metric-card__dot is-cyan"></span>
                Jumlah Laporan Penilaian Risiko Migrasi PQC
            </div>
            <div class="metric-card__value">{{ $jumlahLaporan['risiko'] }}</div>
            <div class="metric-card__bar is-cyan"></div>
        </div>

        <div class="metric-card">
            <div class="metric-card__label">
                <span class="metric-card__dot is-success"></span>
                Jumlah Laporan Kesiapsiagaan
            </div>
            <div class="metric-card__value">{{ $jumlahLaporan['kesiapsiagaan'] }}</div>
            <div class="metric-card__nota">Diserahkan kepada NACSA</div>
            <div class="metric-card__bar is-success"></div>
        </div>

    </div>
