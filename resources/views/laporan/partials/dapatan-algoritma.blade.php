        @include('laporan.partials.tajuk-seksyen', [
            'tahap' => 'h3',
            'kelas' => 'laporan-seksyen__subtajuk laporan-seksyen__subtajuk--mula-halaman',
            'teks' => '2. Algoritma Kriptografi',
            'seksyen' => 'algoritma',
        ])

        {{-- Jadual hanya muncul apabila ada algoritma dikenal pasti, jadi ayat
         pembuka mesti mengikutinya: "Jadual di bawah" apabila jadual dipaparkan,
         "Bahagian ini" apabila tidak. Pembolehubah yang SAMA mengawal kedua-duanya
         supaya ayat dan jadual tidak boleh terpesong. --}}
        @php $adaJadualAlgoritma = count($algoritma) > 0; @endphp

        <p class="laporan-seksyen__perenggan">
            {{ $adaJadualAlgoritma ? 'Jadual di bawah' : 'Bahagian ini' }} merumuskan algoritma dan
            mekanisme kriptografi yang dikenal pasti berdasarkan data dalam Jadual 0–2, mengikut
            primitif atau kategori kriptografi serta bilangan sistem dan aset yang terlibat.
        </p>

        @if ($adaJadualAlgoritma)
            <table class="laporan-jadual-algo">
                {{-- Lebar lajur MESTI di sini: dengan `table-layout: fixed`, hanya baris
                 pertama menentukan lebar lajur. --}}
                <colgroup>
                    <col class="laporan-jadual-algo__lajur-bil">
                    <col class="laporan-jadual-algo__lajur-kategori">
                    <col class="laporan-jadual-algo__lajur-algoritma">
                    <col class="laporan-jadual-algo__lajur-bilangan">
                </colgroup>
                <thead>
                    <tr>
                        <th>Bil.</th>
                        <th>Primitif/Kategori Kriptografi</th>
                        <th>Algoritma/Mekanisme Dikenal Pasti</th>
                        <th>Bilangan Sistem/Aset Terlibat</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($algoritma as $kategori)
                        @foreach ($kategori['item'] as $item)
                            <tr>
                                {{-- Bil. dan kategori ditulis SEKALI sahaja bagi setiap
                                 kumpulan; rowspan merentangi semua algoritmanya
                                 supaya kategori tidak berulang pada setiap baris. --}}
                                @if ($loop->first)
                                    <td class="laporan-jadual-algo__bil" rowspan="{{ count($kategori['item']) }}">
                                        {{ $loop->parent->iteration }}.
                                    </td>
                                    <td class="laporan-jadual-algo__kategori" rowspan="{{ count($kategori['item']) }}">
                                        {{ $kategori['kategori'] }}
                                    </td>
                                @endif
                                <td class="laporan-jadual-algo__algoritma">
                                    <span class="laporan-jadual-algo__label">{{ $item['label'] }}.</span>
                                    {{ $item['nama'] }}
                                </td>
                                <td class="laporan-jadual-algo__bilangan">
                                    {{ $item['bilangan'] !== '' ? $item['bilangan'] : '—' }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="laporan-seksyen__perenggan">
                Tiada algoritma atau mekanisme kriptografi dikenal pasti berdasarkan data yang dikemukakan.
            </p>
        @endif

        @if (count($ulasanAlgoritma))
            <p class="laporan-seksyen__ulasan-tajuk">Ulasan:</p>
            @foreach ($ulasanAlgoritma as $blok)
                @if ($blok['jenis'] === 'senarai')
                    @if ($blok['bernombor'])
                        <ol class="laporan-seksyen__senarai-ulasan">
                            @foreach ($blok['isi'] as $titik)
                                <li>{{ $titik }}</li>
                            @endforeach
                        </ol>
                    @else
                        <ul class="laporan-seksyen__senarai-ulasan">
                            @foreach ($blok['isi'] as $titik)
                                <li>{{ $titik }}</li>
                            @endforeach
                        </ul>
                    @endif
                @else
                    <p class="laporan-seksyen__perenggan">{{ $blok['isi'] }}</p>
                @endif
            @endforeach
        @endif
