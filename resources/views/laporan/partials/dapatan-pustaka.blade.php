        @include('laporan.partials.tajuk-seksyen', [
            'tahap' => 'h3',
            'kelas' => 'laporan-seksyen__subtajuk laporan-seksyen__subtajuk--mula-halaman',
            'teks' => '4. Pustaka dan Modul Kriptografi',
            'seksyen' => 'pustaka',
        ])

        {{-- Lihat nota pada subseksyen 2: ayat pembuka mengikut kehadiran jadual. --}}
        @php $adaJadualPustaka = count($data['pustaka'] ?? []) > 0; @endphp

        <p class="laporan-seksyen__perenggan">
            {{ $adaJadualPustaka ? 'Jadual di bawah' : 'Bahagian ini' }} merumuskan maklumat pustaka
            dan modul kriptografi yang dikenal pasti berdasarkan data dalam Jadual 0–2, termasuk
            maklumat versi serta padanan dengan sistem atau aset yang berkaitan.
        </p>

        @if ($adaJadualPustaka)
            <table class="laporan-jadual-pustaka">
                {{-- Lebar lajur MESTI di sini: dengan `table-layout: fixed`, hanya baris
                     pertama menentukan lebar lajur. --}}
                <colgroup>
                    <col class="laporan-jadual-pustaka__lajur-bil">
                    <col class="laporan-jadual-pustaka__lajur-nama">
                    <col class="laporan-jadual-pustaka__lajur-versi">
                    <col class="laporan-jadual-pustaka__lajur-bilangan">
                </colgroup>
                <thead>
                    <tr>
                        <th>Bil.</th>
                        <th>Pustaka/Modul Kriptografi</th>
                        <th>Versi</th>
                        <th>Bilangan Sistem/Aset Terlibat</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data['pustaka'] as $baris)
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
                Tiada pustaka atau modul kriptografi dikenal pasti berdasarkan data yang dikemukakan.
            </p>
        @endif

        @if (count($ulasanPustaka))
            <p class="laporan-seksyen__ulasan-tajuk">Ulasan:</p>
            @foreach ($ulasanPustaka as $blok)
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
