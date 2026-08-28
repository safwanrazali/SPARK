@props([
    /*
     * Hirisan carta. Setiap hirisan:
     *
     *   label    nama kategori
     *   nilai    SAIZ hirisan pada gelang
     *   peratus  peratusan yang dilaporkan bagi hirisan itu
     *   warna    warna hirisan, biasanya var(--pie-N)
     *
     * Dua medan pilihan memisahkan "apa yang dilukis" daripada "apa yang
     * dibaca", untuk carta yang saiz hirisannya bukan angka yang hendak
     * ditonjolkan (cth. hirisan sektor bersaiz bilangan entiti, tetapi
     * angka yang dilaporkan ialah berapa banyak yang telah Selesai):
     *
     *   paparBilangan   angka yang dilaporkan, menggantikan `nilai`
     *   paparDaripada   penyebutnya, dilaporkan sebagai "3 / 19"
     *   labelPendek     teks legenda yang lebih ringkas (cth. kod sektor
     *                   sahaja). `label` penuh kekal pada tooltip, jadi nama
     *                   sebenar tidak hilang — cuma tidak lagi memakan lebar
     *                   yang lebih berguna kepada gelang.
     */
    'segmen' => [],
    // Nilai besar di tengah gelang.
    'nilaiTengah' => null,
    // Kapsyen kecil di bawah nilai tengah.
    'labelTengah' => null,
    // Unit yang dilekatkan pada angka setiap hirisan ("entiti").
    'unit' => null,
    // Kekalkan hirisan bernilai sifar dalam legenda. Hirisan sifar tidak
    // pernah melukis apa-apa pada gelang; pilihan ini hanya menentukan sama
    // ada barisnya tetap disenaraikan — perlu apabila legenda bertujuan
    // menyenaraikan SETIAP kategori, termasuk yang masih kosong.
    'paparKosong' => false,
    // Legenda ringkas: warna dan nama sahaja. Angkanya berpindah ke tooltip
    // hirisan, membebaskan ruang supaya gelang boleh dilukis lebih besar.
    // Berguna apabila kategorinya banyak dan senarai angka menenggelamkan
    // carta itu sendiri.
    'legendaRingkas' => false,
])

@php
    $segmen = collect($segmen)
        ->when(! $paparKosong, fn($s) => $s->filter(fn($baris) => ($baris['nilai'] ?? 0) > 0))
        ->values()
        ->all();

    $jumlah = collect($segmen)->sum('nilai');

    // Geometri gelang. Setiap hirisan ialah satu <circle> dengan
    // stroke-dasharray-nya sendiri: satu elemen bagi setiap hirisan, jadi
    // setiap satu boleh dihover dan membawa <title> tersendiri — sesuatu
    // yang tidak mungkin dengan satu conic-gradient tunggal.
    $jejari = 35;
    $lilitan = 2 * M_PI * $jejari;

    $terkumpul = 0;

    $baris = collect($segmen)->map(function (array $s) use ($jumlah, $lilitan, &$terkumpul, $unit) {
        $bilangan = $s['paparBilangan'] ?? $s['nilai'];
        $daripada = $s['paparDaripada'] ?? null;

        $teksPeratus = \App\Support\Peratus::paparan($bilangan, $s['peratus'], $daripada ?? $jumlah);

        $panjang = $jumlah > 0 ? ($s['nilai'] / $jumlah) * $lilitan : 0;
        $offset = $terkumpul;
        $terkumpul += $panjang;

        return $s + [
            'bilangan' => $bilangan,
            'daripada' => $daripada,
            'teksPeratus' => $teksPeratus,
            'panjang' => $panjang,
            'offset' => $offset,
            'tooltip' => trim(sprintf(
                '%s: %s%s %s (%s%%)',
                $s['label'],
                $bilangan,
                $daripada !== null ? ' / ' . $daripada : '',
                $unit ?? '',
                $teksPeratus,
            )),
        ];
    });

    // Hanya kategori yang benar-benar mempunyai nilai disebut dalam huraian —
    // membaca sebelas "sifar peratus" berturut-turut tidak membantu sesiapa.
    $huraian = $baris
        ->filter(fn($s) => $s['bilangan'] > 0)
        ->map(fn($s) => $s['tooltip'])
        ->implode(', ');
@endphp

<div class="pie-chart @if ($legendaRingkas) pie-chart--ringkas @endif">

    <div class="pie-chart__graf">
        <svg class="pie-chart__svg" viewBox="0 0 100 100" role="img" aria-label="{{ $huraian }}">
            {{-- Tanpa putaran ini hirisan pertama bermula pada pukul 3, bukan pukul 12. --}}
            <g transform="rotate(-90 50 50)">
                @foreach ($baris as $s)
                    @continue($s['panjang'] <= 0)
                    <circle class="pie-slice" cx="50" cy="50" r="{{ $jejari }}" stroke="{{ $s['warna'] }}"
                        stroke-dasharray="{{ round($s['panjang'], 4) }} {{ round($lilitan - $s['panjang'], 4) }}"
                        stroke-dashoffset="{{ round(-$s['offset'], 4) }}">
                        <title>{{ $s['tooltip'] }}</title>
                    </circle>
                @endforeach
            </g>
        </svg>

        @if ($nilaiTengah !== null)
            <span class="pie-chart__tengah" aria-hidden="true">
                <span class="pie-chart__nilai">{{ $nilaiTengah }}</span>
                @if ($labelTengah !== null)
                    <span class="pie-chart__kapsyen">{{ $labelTengah }}</span>
                @endif
            </span>
        @endif
    </div>

    <ul class="pie-legend">
        @foreach ($baris as $s)
            <li class="pie-legend__baris @if ($s['bilangan'] === 0) is-kosong @endif"
                @if ($legendaRingkas) title="{{ $s['tooltip'] }}" @endif>
                <span class="pie-legend__dot" style="background: {{ $s['warna'] }}"></span>
                <span class="pie-legend__nama"
                    title="{{ $s['label'] }}">{{ $s['labelPendek'] ?? $s['label'] }}</span>

                @unless ($legendaRingkas)
                    <span class="pie-legend__nilai">
                        {{ $s['bilangan'] }}@if ($s['daripada'] !== null)
                            / {{ $s['daripada'] }}
                        @endif
                        {{ $unit }}
                        <span class="pie-legend__peratus">({{ $s['teksPeratus'] }}%)</span>
                    </span>
                @endunless
            </li>
        @endforeach
    </ul>

</div>
