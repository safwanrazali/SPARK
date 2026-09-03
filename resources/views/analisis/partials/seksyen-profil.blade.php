        {{-- 3 · Profil sistem dan aset --}}
        <div class="report-card mb-4" data-seksyen="profil">
            <h4 class="section-title">3 · Profil Sistem dan Aset (Jadual 0)</h4>
            @foreach (config('kriptografi.kategori_profil') as $kategori)
                @php
                    $k = md5($kategori);
                    $sedia = $data['profil'][$kategori] ?? [];
                @endphp
                <div class="row align-items-center mb-2">
                    <div class="col-md-4"><strong>{{ $kategori }}</strong></div>
                    <div class="col-md-3">
                        <input type="number" min="0" name="profil[{{ $k }}][jumlah]"
                            class="form-control" placeholder="Jumlah" value="{{ $sedia['jumlah'] ?? '' }}">
                    </div>
                </div>
            @endforeach

            {{-- Ulasan ditaip sendiri oleh pegawai. Perenggan dan senarai
                 bernombor dihasilkan daripada konvensyen menaip biasa oleh
                 App\Support\TeksBerformat — tiada markup khas perlu dipelajari. --}}
            <div class="mt-3">
                <label class="form-label" for="ulasan_profil">Ulasan</label>
                <textarea name="ulasan_profil" id="ulasan_profil" class="form-control" rows="6"
                    placeholder="cth. Sebanyak 48 rekod sistem dan aset direkodkan dalam Jadual 0.">{{ $data['ulasan_profil'] ?? '' }}</textarea>
                <div class="form-text">
                    Tinggalkan satu baris kosong untuk memulakan perenggan baharu.
                    Mulakan baris dengan <code>1.</code> <code>2.</code> (atau <code>-</code>)
                    untuk menghasilkan senarai bernombor.
                </div>
            </div>
        </div>
