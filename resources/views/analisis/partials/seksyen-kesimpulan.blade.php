        {{-- 9 · Kesimpulan --}}
        <div class="report-card mb-4" data-seksyen="kesimpulan">
            <h4 class="section-title">9 · Kesimpulan</h4>
            {{-- Kesimpulan ditaip sepenuhnya oleh pegawai: ia berbeza bagi
                 setiap entiti, jadi tiada bank ayat atau kotak semak. Perenggan
                 dan senarai bernombor dihasilkan oleh TeksBerformat. --}}
            <textarea name="kesimpulan" id="kesimpulan" class="form-control" rows="10"
                placeholder="Nyatakan kesimpulan analisis bagi entiti ini.">{{ $data['kesimpulan'] ?? '' }}</textarea>
            <div class="form-text">
                Tinggalkan satu baris kosong untuk memulakan perenggan baharu.
                Mulakan baris dengan <code>1.</code> <code>2.</code> (atau <code>-</code>)
                untuk menghasilkan senarai bernombor.
            </div>
        </div>
