        {{--
            Tiada kotak semak "tanda analisis selesai": menekan "Simpan
            Dapatan" itu sendirilah pengisytiharan siap bagi borang ini.

            Penyerahan laporan kepada PPA TIDAK berlaku di sini: ia milik
            peringkat 4 dan 5, yang belum dibina. Peringkat 3.1 ditutup
            melalui tindakan "Selesai" pada halaman Kemajuan Analisis Entiti.
        --}}
        <div class="report-card mb-4 d-flex align-items-center gap-3 flex-wrap">
            <button type="submit" formaction="{{ route('analisis.draf') }}" class="btn btn-outline-light">
                <i class="bi bi-journal-arrow-down"></i> Simpan Draf
            </button>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check2-circle"></i> Simpan Dapatan
            </button>
            <span class="text-secondary draft-hint">
                <i class="bi bi-info-circle"></i>
                Simpan Draf menyimpan kerja separa siap tanpa pengesahan penuh.
                Simpan Dapatan memuktamadkan borang; peringkat 3.1 ditutup
                melalui tindakan "Selesai" pada halaman Kemajuan Analisis Entiti.
            </span>
        </div>
