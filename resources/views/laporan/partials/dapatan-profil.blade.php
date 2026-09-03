        @include('laporan.partials.tajuk-seksyen', [
            'tahap' => 'h3',
            'kelas' => 'laporan-seksyen__subtajuk',
            'teks' => '1. Profil Sistem dan Aset',
            'seksyen' => 'profil',
        ])

        <p class="laporan-seksyen__perenggan">
            Jadual di bawah merumuskan profil sistem dan aset yang dikenal pasti berdasarkan data
            dalam Jadual 0, mengikut kategori utama yang digunakan bagi tujuan analisis inventori
            kriptografi.
        </p>

        <table class="laporan-jadual-ringkas">
            {{-- Lebar lajur MESTI di sini: dengan `table-layout: fixed`, hanya baris
                 pertama menentukan lebar lajur. --}}
            <colgroup>
                <col class="laporan-jadual-ringkas__lajur-bil">
                <col class="laporan-jadual-ringkas__lajur-perkara">
                <col class="laporan-jadual-ringkas__lajur-jumlah">
            </colgroup>
            <thead>
                <tr>
                    <th>Bil.</th>
                    <th>Perkara</th>
                    <th>Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($profil as $baris)
                    <tr>
                        <td class="laporan-jadual-ringkas__bil">{{ $loop->iteration }}.</td>
                        <td>{{ $baris['perkara'] }}</td>
                        <td class="laporan-jadual-ringkas__jumlah">{{ $baris['jumlah'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if (count($ulasanProfil))
            <p class="laporan-seksyen__ulasan-tajuk">Ulasan:</p>
            {{-- Perenggan dan senarai dihasilkan daripada teks yang ditaip pegawai;
                 lihat App\Support\TeksBerformat untuk konvensyennya. --}}
            @foreach ($ulasanProfil as $blok)
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
