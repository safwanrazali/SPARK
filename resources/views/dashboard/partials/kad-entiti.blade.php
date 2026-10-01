    {{--
        Baris metrik 1/2 — corong entiti.

            SEMUA ENTITI
                |
                v
            ENTITI DITERIMA          (Buku Kerja MPQ diterima)
                |
                +--> ENTITI DALAM PROSES
                +--> ENTITI SELESAI

        Dua penyebut berbeza, dan setiap kad menyatakan miliknya dalam nota
        di bawah nilai:

        - Entiti Diterima      : keseluruhan entiti dalam senarai induk —
                                 ini soalan LIPUTAN.
        - Dalam Proses/Selesai : entiti yang telah DITERIMA — ini soalan
                                 KEMAJUAN, dan entiti yang Buku Kerja MPQ-nya
                                 belum diterima belum boleh bergerak.

        Kad "Entiti Selesai" di sini bermaksud peringkat 5 Selesai. Ia BUKAN
        ukuran yang sama dengan carta "Kemajuan Keseluruhan" di bawah, yang
        melaporkan Kemajuan Analisis fasa semasa (1.1–3.1); kedua-duanya
        sengaja dikekalkan kerana ia menjawab soalan yang berlainan.

        Penyebut sifar memberi 0%, bukan NaN.
    --}}
    <div class="metric-row metric-row--kira">

        <div class="metric-card">
            <div class="metric-card__label">
                <span class="metric-card__dot is-primary"></span>
                Jumlah Sektor
            </div>
            <div class="metric-card__value">{{ $jumlahSektor }}</div>
            <div class="metric-card__bar is-primary"></div>
        </div>

        <div class="metric-card">
            <div class="metric-card__label">
                <span class="metric-card__dot is-cyan"></span>
                Jumlah Entiti
            </div>
            <div class="metric-card__value">{{ $jumlahEntiti }}</div>
            <div class="metric-card__bar is-cyan"></div>
        </div>

        {{-- Medan `syarat_lanjut` peringkat 1.1 — Tarikh Terima + Status Borang
             Penerimaan Data — direkod oleh KB atau PPA. Ini pintu masuk kepada
             semua yang lain. --}}
        <div class="metric-card">
            <div class="metric-card__label">
                <span class="metric-card__dot is-cyan"></span>
                Entiti Diterima
            </div>
            <div class="metric-card__value">
                {{ \App\Support\Peratus::kad($peratusEntitiDiterima) }}<span class="metric-card__unit">%</span>
            </div>
            <div class="metric-card__nota">
                {{ $entitiDiterima }} daripada {{ $jumlahEntiti }} entiti
            </div>
            <div class="metric-card__bar metric-card__bar--kemajuan">
                <span class="metric-card__bar-isi is-cyan" style="width: {{ $peratusEntitiDiterima }}%"></span>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-card__label">
                <span class="metric-card__dot is-warning"></span>
                Entiti Dalam Proses
            </div>
            <div class="metric-card__value">
                {{ \App\Support\Peratus::kad($peratusEntitiDalamProses) }}<span class="metric-card__unit">%</span>
            </div>
            <div class="metric-card__nota">
                {{ $entitiDalamProses }} daripada {{ $entitiDiterima }} entiti yang diterima
            </div>
            <div class="metric-card__bar metric-card__bar--kemajuan">
                <span class="metric-card__bar-isi is-warning" style="width: {{ $peratusEntitiDalamProses }}%"></span>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-card__label">
                <span class="metric-card__dot is-success"></span>
                Entiti Selesai
            </div>
            <div class="metric-card__value">
                {{ \App\Support\Peratus::kad($peratusEntitiSelesai) }}<span class="metric-card__unit">%</span>
            </div>
            <div class="metric-card__nota">
                {{ $entitiSelesai }} daripada {{ $entitiDiterima }} entiti yang diterima
            </div>
            <div class="metric-card__bar metric-card__bar--kemajuan">
                <span class="metric-card__bar-isi is-success" style="width: {{ $peratusEntitiSelesai }}%"></span>
            </div>
        </div>

    </div>
