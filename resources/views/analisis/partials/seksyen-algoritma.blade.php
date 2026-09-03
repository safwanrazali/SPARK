        {{-- 4 · Algoritma kriptografi --}}
        <div class="report-card mb-4" data-seksyen="algoritma">
            <h4 class="section-title">4 · Algoritma Kriptografi Dikenal Pasti Digunakan</h4>
            <p class="text-secondary">
                Hanya algoritma yang ditanda dipaparkan dalam kandungan laporan.
            </p>

            {{-- Katalog bertingkat: primitif => sub-kumpulan => algoritma => metadata.
                 Sub-kumpulan '' bermakna kategori itu tiada sub-kumpulan pada
                 laman AKSA MySEAL, jadi tajuk kecilnya dilangkau. Kunci yang
                 disimpan kekal "Kategori|Algoritma" — sub-kumpulan, pengelasan
                 MySEAL dan parameter TIDAK masuk ke dalam kunci. --}}
            @foreach (config('kriptografi.kategori_algoritma') as $kategori => $subKumpulan)
                <div class="border rounded p-3 mb-3">
                    <strong class="d-block mb-2">{{ $kategori }}</strong>
                    @foreach ($subKumpulan as $subTajuk => $senarai)
                        @if ($subTajuk !== '')
                            <div class="text-secondary small mt-3 mb-1">{{ $subTajuk }}</div>
                        @endif
                        @foreach ($senarai as $algo => $meta)
                            @php
                                $id = $kategori . '|' . $algo;
                                $k = md5($id);
                                $sedia = $data['algoritma'][$id] ?? null;
                                $myseal = config('kriptografi.myseal_kategori.' . $meta['myseal']);
                            @endphp
                            <div class="row align-items-center mb-2">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input algo-toggle" type="checkbox"
                                            id="algo-{{ $k }}" name="algoritma[{{ $k }}][dipilih]"
                                            value="1" data-target="{{ $k }}"
                                            @checked($sedia !== null)>
                                        <input type="hidden" name="algoritma[{{ $k }}][id]"
                                            value="{{ $id }}">
                                        <label class="form-check-label" for="algo-{{ $k }}">
                                            {{ $algo }}
                                            {{-- Approved ialah lalai dan tidak dilencanakan:
                                                 melencanakan 104 baris Approved hanya menambah
                                                 hingar. Neutral dan Monitored ditandakan supaya
                                                 pegawai nampak status MySEAL algoritma itu. --}}
                                            @if ($meta['myseal'] !== 'Approved')
                                                <span class="badge {{ $myseal['kelas'] }} align-middle"
                                                    title="Pengelasan AKSA MySEAL 2.1">{{ $myseal['label'] }}</span>
                                            @endif
                                            @if (in_array($algo, config('kriptografi.tidak_disyorkan')))
                                                <span class="text-danger" title="Tidak lagi disyorkan">▲</span>
                                            @endif
                                            @if (in_array($algo, config('kriptografi.risiko_kuantum')))
                                                <strong title="Berisiko kuantum">Q</strong>
                                            @endif
                                        </label>
                                        {{-- Panjang kunci / panjang cerna / varian / set
                                             parameter seperti laman rasmi. RUJUKAN sahaja:
                                             borang merekod satu kotak semak dan satu bilangan
                                             bagi setiap algoritma, jadi varian tidak dipilih
                                             berasingan. --}}
                                        @if (!empty($meta['parameter']))
                                            <div class="form-text ms-4 mt-0">{{ $meta['parameter'] }}</div>
                                        @endif
                                    </div>
                                </div>
                                <div @class([
                                    'col-md-3',
                                    'algo-medan-' . $k,
                                    'is-hidden' => $sedia === null,
                                ])>
                                    <input type="number" min="0" name="algoritma[{{ $k }}][bilangan]"
                                        class="form-control form-control-sm" placeholder="Bil. sistem/aset"
                                        value="{{ $sedia['bilangan'] ?? '' }}">
                                </div>
                            </div>
                        @endforeach
                    @endforeach
                </div>
            @endforeach

            {{-- Katalog di atas meliputi KETIGA-TIGA kategori AKSA MySEAL 2.1
                 (Approved, Neutral, Monitored). Algoritma yang tiada pada
                 mana-mana daripada tiga laman rasmi itu — cth. MD5, RC4,
                 Blowfish — direkodkan di sini, dan tetap dikesan oleh
                 AnalisisInventori::algoritmaLapuk()/algoritmaKuantum(). --}}
            @php $algoLain = \App\Support\BorangAnalisis::algoritmaLain($data['algoritma_lain'] ?? null) ?: [['nama' => '', 'bilangan' => '']]; @endphp
            <label class="form-label">Lain-lain (nyatakan, jika berkaitan)</label>
            {{-- `data-berindeks` memberitahu skrip supaya menomborkan semula
                 nama medan selepas baris ditambah atau dibuang. Setiap baris
                 mempunyai DUA medan, jadi `algoritma_lain[]` tidak boleh
                 digunakan — PHP akan mencipta elemen berasingan bagi nama dan
                 bilangan, lalu memutuskan pasangannya. --}}
            <div class="penerangan-senarai" data-penerangan="algoritma_lain" data-berindeks>
                @foreach ($algoLain as $i => $satu)
                    <div class="input-group mb-2 penerangan-baris">
                        <input type="text" data-nama="nama" name="algoritma_lain[{{ $i }}][nama]"
                            class="form-control" value="{{ $satu['nama'] }}" placeholder="cth. 3DES">
                        <input type="number" min="0" data-nama="bilangan"
                            name="algoritma_lain[{{ $i }}][bilangan]" class="form-control algo-lain-bilangan"
                            value="{{ $satu['bilangan'] }}" placeholder="Bil. sistem/aset">
                        <button type="button" class="btn btn-outline-light penerangan-buang"
                            aria-label="Buang algoritma ini">
                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                        </button>
                    </div>
                @endforeach
            </div>
            <button type="button" class="btn btn-sm btn-outline-light penerangan-tambah" data-sasaran="algoritma_lain">
                <i class="bi bi-plus-lg" aria-hidden="true"></i> Tambah Algoritma
            </button>

            {{-- Ulasan ditaip sendiri oleh pegawai; perenggan dan senarai
                 bernombor dihasilkan oleh App\Support\TeksBerformat. --}}
            <div class="mt-3">
                <label class="form-label" for="ulasan_algoritma">Ulasan</label>
                <textarea name="ulasan_algoritma" id="ulasan_algoritma" class="form-control" rows="6"
                    placeholder="cth. Analisis mengenal pasti penggunaan algoritma yang tidak lagi disyorkan.">{{ $data['ulasan_algoritma'] ?? '' }}</textarea>
                <div class="form-text">
                    Tinggalkan satu baris kosong untuk memulakan perenggan baharu.
                    Mulakan baris dengan <code>1.</code> <code>2.</code> (atau <code>-</code>)
                    untuk menghasilkan senarai bernombor.
                </div>
            </div>
        </div>
