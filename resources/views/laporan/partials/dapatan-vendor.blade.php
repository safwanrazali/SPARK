        @include('laporan.partials.tajuk-seksyen', [
            'tahap' => 'h3',
            'kelas' => 'laporan-seksyen__subtajuk laporan-seksyen__subtajuk--mula-halaman',
            'teks' => '5. Maklumat Vendor',
            'seksyen' => 'vendor',
        ])

        {{-- Lihat nota pada subseksyen 2: ayat pembuka mengikut kehadiran jadual. --}}
        @php $adaJadualVendor = count($vendor) > 0; @endphp

        <p class="laporan-seksyen__perenggan">
            {{ $adaJadualVendor ? 'Jadual di bawah' : 'Bahagian ini' }} merumuskan maklumat vendor
            serta produk atau komponen yang dikenal pasti berdasarkan data dalam Jadual 0–2, termasuk
            padanan dengan sistem atau aset yang berkaitan.
        </p>

        @if ($adaJadualVendor)
            <table class="laporan-jadual-vendor">
                {{-- Lebar lajur MESTI di sini: dengan `table-layout: fixed`, hanya baris
                 pertama menentukan lebar lajur. --}}
                <colgroup>
                    <col class="laporan-jadual-vendor__lajur-bil">
                    <col class="laporan-jadual-vendor__lajur-nama">
                    <col class="laporan-jadual-vendor__lajur-produk">
                    <col class="laporan-jadual-vendor__lajur-bilangan">
                </colgroup>
                <thead>
                    <tr>
                        <th>Bil.</th>
                        <th>Nama Vendor</th>
                        <th>Produk/Komponen</th>
                        <th>Bilangan Sistem/Aset Terlibat</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($vendor as $kumpulan)
                        @foreach ($kumpulan['item'] as $item)
                            <tr>
                                {{-- Bil. dan nama vendor ditulis SEKALI sahaja bagi setiap
                                 kumpulan; rowspan merentangi semua produknya. --}}
                                @if ($loop->first)
                                    <td class="laporan-jadual-algo__bil" rowspan="{{ count($kumpulan['item']) }}">
                                        {{ $loop->parent->iteration }}.
                                    </td>
                                    <td class="laporan-jadual-vendor__nama" rowspan="{{ count($kumpulan['item']) }}">
                                        {{ $kumpulan['nama'] }}
                                    </td>
                                @endif
                                <td class="laporan-jadual-vendor__produk">
                                    @if ($item['label'] !== '')
                                        <span class="laporan-jadual-algo__label">{{ $item['label'] }}.</span>
                                    @endif
                                    {{ $item['produk'] !== '' ? $item['produk'] : '—' }}
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
                Tiada maklumat vendor dikenal pasti berdasarkan data yang dikemukakan.
            </p>
        @endif

        @if (count($ulasanVendor))
            <p class="laporan-seksyen__ulasan-tajuk">Ulasan:</p>
            @foreach ($ulasanVendor as $blok)
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
