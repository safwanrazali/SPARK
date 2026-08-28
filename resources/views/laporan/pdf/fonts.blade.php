{{--
    FON APTOS UNTUK PDF SAHAJA.

    Dokumen PDF diserahkan kepada Browsershot::html() sebagai satu rentetan
    HTML tanpa pelayan HTTP dan tanpa URL asas, jadi Chrome TIDAK boleh
    mengambil fail fon melalui laluan relatif mahupun file://. Satu-satunya
    cara yang pasti ialah membenamkan fon sebagai data URI base64 di sini.

    Jangan tukar kepada <link> atau url() biasa: PDF akan jatuh senyap
    kepada fon lalai Chrome dan laporan TIDAK akan dicetak dalam Aptos.

    Partial ini SENGAJA tidak digunakan oleh mana-mana paparan WebView —
    pratonton skrin (resources/views/laporan/inventori.blade.php) kekal
    pada fon aplikasi sedia ada.

    Kepala dan kaki halaman WAJIB memasukkan partial ini sendiri: Chrome
    memaparkan headerTemplate/footerTemplate sebagai dokumen TERASING yang
    tidak mewarisi @font-face daripada halaman utama.

    Parameter pilihan `$aptosPilihan` — senarai pasangan [berat, gaya] —
    mengehadkan berat yang dibenamkan. Kepala/kaki halaman hanya memerlukan
    400 normal, jadi ia mengelak membenamkan lima fon pada setiap templat.
    Tanpa parameter ini, semua berat dibenamkan.

    Sumber fon dan lesen: public/fonts/aptos/README.md
--}}
@php
    // Hanya berat/gaya yang benar-benar dirujuk oleh gaya laporan.
    // Menambah berat di sini tanpa fail yang sepadan akan menyebabkan
    // Chrome mensintesis berat palsu, bukan menggunakan Aptos sebenar.
    $fonAptos = [
        ['fail' => 'Aptos.woff2',             'berat' => 400, 'gaya' => 'normal'],
        ['fail' => 'Aptos-Italic.woff2',      'berat' => 400, 'gaya' => 'italic'],
        ['fail' => 'Aptos-Bold.woff2',        'berat' => 700, 'gaya' => 'normal'],
        ['fail' => 'Aptos-Bold-Italic.woff2', 'berat' => 700, 'gaya' => 'italic'],
        ['fail' => 'Aptos-ExtraBold.woff2',   'berat' => 800, 'gaya' => 'normal'],
    ];

    if (! empty($aptosPilihan)) {
        $fonAptos = array_values(array_filter(
            $fonAptos,
            fn (array $fon) => in_array([$fon['berat'], $fon['gaya']], $aptosPilihan, true),
        ));
    }
@endphp
<style>
    @foreach ($fonAptos as $fon)
    @php $laluan = public_path('fonts/aptos/'.$fon['fail']); @endphp
    @continue(! is_file($laluan))
    @font-face {
        font-family: 'Aptos';
        font-style: {{ $fon['gaya'] }};
        font-weight: {{ $fon['berat'] }};
        /* `block` (bukan `swap`): Chrome mesti MENUNGGU fon sebelum
           melukis, jika tidak muka surat pertama boleh dicetak dalam fon
           sandaran. Fon terbenam, jadi tiada penantian rangkaian. */
        font-display: block;
        src: url(data:font/woff2;base64,{{ base64_encode(file_get_contents($laluan)) }}) format('woff2');
    }
    @endforeach
</style>
