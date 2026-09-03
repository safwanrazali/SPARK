        {{-- 8 · Tindakan susulan --}}
        <div class="report-card mb-4" data-seksyen="tindakan">
            <h4 class="section-title">8 · Cadangan Tindakan Susulan</h4>
            @foreach (config('kriptografi.tindakan_susulan') as $i => $tindakan)
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="tindakan-{{ $i }}" name="tindakan[]"
                        value="{{ $i }}" @checked(in_array($i, $data['tindakan'] ?? []))>
                    <label class="form-check-label" for="tindakan-{{ $i }}">
                        {{ $tindakan['tindakan'] }}
                    </label>
                </div>
            @endforeach
            {{-- Tindakan tambahan ditaip sendiri oleh pegawai dan boleh
                 sebanyak mana yang diperlukan; setiap baris menjadi satu item
                 bernombor dalam senarai cadangan laporan. --}}
            @php $tindakanLain = \App\Support\BorangAnalisis::senaraiTeks($data['tindakan_lain'] ?? null) ?: ['']; @endphp
            <label class="form-label mt-2">Tindakan Tambahan (jika berkaitan)</label>
            <div class="penerangan-senarai" data-penerangan="tindakan_lain">
                @foreach ($tindakanLain as $satu)
                    <div class="input-group mb-2 penerangan-baris">
                        <input type="text" name="tindakan_lain[]" class="form-control"
                            value="{{ $satu }}" placeholder="Nyatakan tindakan susulan tambahan">
                        <button type="button" class="btn btn-outline-light penerangan-buang"
                            aria-label="Buang tindakan ini">
                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                        </button>
                    </div>
                @endforeach
            </div>
            <button type="button" class="btn btn-sm btn-outline-light penerangan-tambah"
                data-sasaran="tindakan_lain">
                <i class="bi bi-plus-lg" aria-hidden="true"></i> Tambah Tindakan
            </button>
        </div>
