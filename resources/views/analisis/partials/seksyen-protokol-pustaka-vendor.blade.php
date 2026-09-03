        {{-- 5–7 · Protokol / Pustaka / Vendor --}}
        @foreach ([
            'protokol' => ['5 · Protokol Kriptografi', ['nama' => 'Nama protokol', 'versi' => 'Versi', 'bilangan' => 'Bil. sistem/aset']],
            'pustaka' => ['6 · Pustaka dan Modul Kriptografi', ['nama' => 'Nama pustaka/modul', 'versi' => 'Versi', 'bilangan' => 'Bil. sistem/aset']],
            'vendor' => ['7 · Maklumat Vendor', ['nama' => 'Nama vendor', 'produk' => 'Produk/Komponen', 'bilangan' => 'Bil. sistem/aset']],
        ] as $medan => [$tajuk, $kolum])
            <div class="report-card mb-4" data-senarai="{{ $medan }}" data-seksyen="{{ $medan }}">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="section-title mb-0">{{ $tajuk }}</h4>
                    <button type="button" class="btn btn-sm btn-outline-light tambah-baris"
                        data-medan="{{ $medan }}">
                        <i class="bi bi-plus-lg"></i> Tambah Baris
                    </button>
                </div>

                <div class="senarai-baris" id="senarai-{{ $medan }}">
                    @foreach ($data[$medan] ?? [] as $i => $baris)
                        <div class="row align-items-center mb-2 baris-item">
                            @foreach ($kolum as $k => $label)
                                <div class="col">
                                    <input type="text"
                                        name="{{ $medan }}[{{ $i }}][{{ $k }}]"
                                        class="form-control form-control-sm" placeholder="{{ $label }}"
                                        value="{{ $baris[$k] ?? '' }}">
                                </div>
                            @endforeach
                            <div class="col-auto">
                                <button type="button" class="btn btn-sm btn-danger padam-baris">✕</button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <template id="templat-{{ $medan }}">
                    <div class="row align-items-center mb-2 baris-item">
                        @foreach ($kolum as $k => $label)
                            <div class="col">
                                <input type="text" data-nama="{{ $k }}"
                                    class="form-control form-control-sm" placeholder="{{ $label }}">
                            </div>
                        @endforeach
                        <div class="col-auto">
                            <button type="button" class="btn btn-sm btn-danger padam-baris">✕</button>
                        </div>
                    </div>
                </template>

                @if (in_array($medan, ['protokol', 'pustaka', 'vendor'], true))
                    {{-- Ulasan ditaip sendiri oleh pegawai; perenggan dan
                         senarai bernombor dihasilkan oleh TeksBerformat. --}}
                    @php $medanUlasan = 'ulasan_'.$medan; @endphp
                    <div class="mt-3">
                        <label class="form-label" for="{{ $medanUlasan }}">Ulasan</label>
                        <textarea name="{{ $medanUlasan }}" id="{{ $medanUlasan }}" class="form-control" rows="6"
                            placeholder="cth. Maklumat versi tidak direkodkan secara konsisten bagi sebahagian rekod.">{{ $data[$medanUlasan] ?? '' }}</textarea>
                        <div class="form-text">
                            Tinggalkan satu baris kosong untuk memulakan perenggan baharu.
                            Mulakan baris dengan <code>1.</code> <code>2.</code> (atau <code>-</code>)
                            untuk menghasilkan senarai bernombor.
                        </div>
                    </div>
                @endif

                <p @class([
                    'text-secondary',
                    'mb-0',
                    'nota-kosong',
                    'is-hidden' => count($data[$medan] ?? []) > 0,
                ])>
                    Tiada rekod. Baris yang tidak digunakan tidak akan dipaparkan dalam laporan muktamad.
                </p>
            </div>
        @endforeach
