        @include('laporan.partials.tajuk-seksyen', [
            'tahap' => 'h3',
            'kelas' => 'laporan-seksyen__subtajuk laporan-seksyen__subtajuk--mula-halaman',
            'teks' => '3. Protokol Kriptografi',
            'seksyen' => 'protokol',
        ])

        {{-- Lihat nota pada subseksyen 2: ayat pembuka mengikut kehadiran jadual. --}}
        @php $adaJadualProtokol = count($data['protokol'] ?? []) > 0; @endphp

        <p class="laporan-seksyen__perenggan">
            {{ $adaJadualProtokol ? 'Jadual di bawah' : 'Bahagian ini' }} merumuskan protokol
            kriptografi yang dikenal pasti berdasarkan maklumat yang direkodkan dalam Jadual 0–2.
            Pada masa ini, Buku Kerja Migrasi PQC tidak menyediakan medan khusus untuk merekodkan
            protokol kriptografi. Oleh itu, maklumat protokol dikenal pasti berdasarkan rekod yang
            dikemukakan oleh entiti, termasuk maklumat yang direkodkan pada medan komponen atau
            algoritma.
        </p>

        @if ($adaJadualProtokol)
            <table class="laporan-jadual-protokol">
                {{-- Lebar lajur MESTI di sini: dengan `table-layout: fixed`, hanya baris
                 pertama menentukan lebar lajur. --}}
                <colgroup>
                    <col class="laporan-jadual-protokol__lajur-bil">
                    <col class="laporan-jadual-protokol__lajur-nama">
                    <col class="laporan-jadual-protokol__lajur-versi">
                    <col class="laporan-jadual-protokol__lajur-bilangan">
                </colgroup>
                <thead>
                    <tr>
                        <th>Bil.</th>
                        <th>Protokol Kriptografi</th>
                        <th>Versi</th>
                        <th>Bilangan Sistem/Aset Terlibat</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data['protokol'] as $baris)
                        <tr>
                            <td class="laporan-jadual-algo__bil">{{ $loop->iteration }}.</td>
                            <td>{{ $baris['nama'] ?? '' ?: '—' }}</td>
                            <td class="laporan-jadual-protokol__versi">{{ $baris['versi'] ?? '' ?: '—' }}</td>
                            <td class="laporan-jadual-algo__bilangan">{{ $baris['bilangan'] ?? '' ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="laporan-seksyen__perenggan">
                Tiada protokol kriptografi dikenal pasti berdasarkan data yang dikemukakan.
            </p>
        @endif

        @if (count($ulasanProtokol))
            <p class="laporan-seksyen__ulasan-tajuk">Ulasan:</p>
            @foreach ($ulasanProtokol as $blok)
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
