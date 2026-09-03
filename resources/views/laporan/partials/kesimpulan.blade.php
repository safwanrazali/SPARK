    <section class="laporan-seksyen laporan-seksyen--mula-halaman">
        @include('laporan.partials.tajuk-seksyen', [
            'tahap' => 'h2',
            'kelas' => 'laporan-seksyen__tajuk',
            'teks' => 'Kesimpulan',
            'seksyen' => 'kesimpulan',
        ])

        @if (count($kesimpulan))
            @foreach ($kesimpulan as $blok)
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
        @else
            <p class="laporan-seksyen__perenggan">
                Tiada kesimpulan direkodkan berdasarkan dapatan analisis.
            </p>
        @endif
    </section>
