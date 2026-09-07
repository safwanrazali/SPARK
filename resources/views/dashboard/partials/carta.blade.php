    {{--
        Baris carta: Kemajuan Analisis mengikut sektor / kemajuan keseluruhan.

        UKURAN DI SINI BERBEZA daripada kad "Entiti Selesai" di atas, dan
        perbezaannya disengajakan:

          Kad  "Entiti Selesai"  peringkat 5 Selesai, atas Entiti Diterima.
                                 Modul peringkat 5 belum dibina, jadi kad itu
                                 kekal 0% sepanjang fasa ini.
          Carta "Siap"           KESEMUA peringkat FASA SEMASA Selesai
                                 (1.1 sehingga 3.1), atas KESELURUHAN entiti.

        Kedua-duanya dikekalkan kerana ia menjawab soalan yang berlainan:
        satu melaporkan penyerahan laporan, satu lagi melaporkan kemajuan
        kerja analisis yang benar-benar boleh dibuat hari ini.

        Perbendaharaan "Siap / Dalam Proses / Belum Mula" diambil terus
        daripada KemajuanAnalisisService — perkataan "Selesai" SENGAJA tidak
        digunakan pada carta ini supaya ia tidak dibaca sebagai kad di atas.
    --}}
    @php
        // Dibina daripada AliranKerja, bukan ditulis "3.1" secara tetap:
        // apabila peringkat 4 dan 5 memasuki fasa semasa, sarikata ini
        // mengikut tanpa suntingan.
        $peringkatAkhirSemasa = \App\Support\AliranKerja::labelPenuh(
            \App\Support\AliranKerja::TERAKHIR_SEMASA,
        );
    @endphp
    <div class="dashboard-section chart-row">

        <div class="chart-card">
            <div class="chart-card__title">Entiti Siap Kemajuan Analisis Mengikut Sektor</div>
            <div class="chart-card__subtajuk">
                Siap = kesemua peringkat fasa semasa Selesai, berakhir pada {{ $peringkatAkhirSemasa }}.
                Diukur terhadap keseluruhan entiti setiap sektor.
            </div>

            @if ($selesai > 0)
                @php
                    // Gelang membahagikan KESELURUHAN entiti kepada sektornya:
                    // saiz setiap hirisan ialah bilangan entiti sektor itu, jadi
                    // kesebelas-sebelas hirisan berjumlah 100% entiti. Legendanya
                    // pula melaporkan berapa banyak antaranya telah Selesai.
                    // Palet --pie-1 … --pie-11 ada dalam dashboard.scss.
                    $segmenSektor = collect($selesaiMengikutSektor)
                        ->values()
                        ->map(
                            fn($sektor, $i) => [
                                'label' => $sektor['kod'] . ' — ' . $sektor['nama'],
                                'labelPendek' => $sektor['kod'],
                                'nilai' => $sektor['jumlah'],
                                'paparBilangan' => $sektor['selesai'],
                                'paparDaripada' => $sektor['jumlah'],
                                'peratus' => $sektor['peratus'],
                                'warna' => 'var(--pie-' . ($i % 11 + 1) . ')',
                            ],
                        )
                        ->all();
                @endphp

                <x-pie-chart unit="entiti siap" :segmen="$segmenSektor" :papar-kosong="true" :legenda-ringkas="true"
                    :nilai-tengah="\App\Support\Peratus::paparan($selesai, $peratusSelesaiKeseluruhan, $jumlahEntiti) . '%'"
                    label-tengah="Entiti Siap" />
            @else
                <x-empty-state icon="bi-pie-chart" title="Tiada entiti siap">
                    Carta ini muncul setelah sekurang-kurangnya satu entiti menamatkan kesemua
                    peringkat fasa semasa Kemajuan Analisis dalam skop penapis semasa.
                </x-empty-state>
            @endif
        </div>

        <div class="chart-card">
            <div class="chart-card__title">Kemajuan Keseluruhan</div>
            <div class="chart-card__subtajuk">
                Kemajuan Analisis bagi kesemua {{ $jumlahEntiti }} entiti, berakhir pada
                {{ $peringkatAkhirSemasa }}. Bukan ukuran yang sama dengan kad "Entiti Selesai".
            </div>

            @if ($jumlahEntiti > 0)
                @php
                    // Warna semantik: Siap hijau, Dalam Proses jingga, Belum Mula kelabu —
                    // sama seperti badge status di modul Kemajuan Analisis.
                    $segmenKemajuan = collect($kemajuanTaburan)
                        ->map(fn($baris) => $baris + ['warna' => 'var(--pie-' . $baris['kunci'] . ')'])
                        ->all();

                @endphp

                <x-pie-chart unit="entiti" :segmen="$segmenKemajuan"
                    :nilai-tengah="\App\Support\Peratus::paparan($selesai, $peratusSelesaiKeseluruhan, $jumlahEntiti) . '%'"
                    label-tengah="Siap" />
            @else
                <x-empty-state icon="bi-pie-chart" title="Tiada entiti dipantau">
                    Entiti dikira dipantau setelah mempunyai rekod workflow, penugasan,
                    analisis atau status laporan.
                </x-empty-state>
            @endif
        </div>

    </div>
