
    <section class="laporan-seksyen laporan-seksyen--mula-halaman">
        @include('laporan.partials.tajuk-seksyen', [
            'tahap' => 'h2',
            'kelas' => 'laporan-seksyen__tajuk',
            'teks' => 'Cadangan Tindakan Susulan',
            'seksyen' => 'tindakan',
        ])

        @if (count($tindakan))
            <p class="laporan-seksyen__perenggan">
                Berdasarkan dapatan analisis, entiti disarankan untuk melaksanakan tindakan berikut:
            </p>

            {{-- SATU <ol> sahaja: memecahkannya kepada beberapa senarai akan
                 menyebabkan nombor bermula semula dari 1 apabila laporan terbelah
                 antara muka surat PDF. --}}
            <ol class="laporan-seksyen__senarai-tindakan">
                @foreach ($tindakan as $satu)
                    <li>{{ $satu }}</li>
                @endforeach
            </ol>
        @else
            <p class="laporan-seksyen__perenggan">
                Tiada cadangan tindakan susulan direkodkan berdasarkan dapatan analisis.
            </p>
        @endif
    </section>
