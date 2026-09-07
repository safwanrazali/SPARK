{{--
    Tajuk seksyen laporan.

    Templat laporan dikongsi oleh DUA saluran, dan hanya tajuknya berbeza
    antara keduanya:

      pratonton skrin  tajuk + widget catatan KB/PPA, disusun sebaris
      PDF              tajuk sahaja

    `$widgetCatatan` ditetapkan SEKALI oleh templat induk
    (laporan/inventori.blade.php dan laporan/pdf/body.blade.php) dan diwarisi
    oleh setiap @include di bawahnya. Kelas `mb-0` hanya dikenakan pada varian
    skrin kerana di situ sahaja tajuk berkongsi baris dengan widget.

    Catatan TIDAK PERNAH muncul dalam PDF: LaporanController@unduh memanggil
    siapkanData(includeComments: false) DAN menetapkan $widgetCatatan palsu.

    @param  string  $tahap    'h2' bagi tajuk seksyen, 'h3' bagi subtajuk
    @param  string  $kelas    kelas tajuk, tanpa `mb-0`
    @param  string  $teks     teks tajuk
    @param  string  $seksyen  kunci seksyen catatan
--}}
@if ($widgetCatatan)
    <div class="d-flex align-items-center justify-content-between gap-2">
        <{{ $tahap }} class="{{ $kelas }} mb-0">{{ $teks }}</{{ $tahap }}>
        <x-section-comment-widget :section="$seksyen" :catatan="$catatan" :analisis="$analisis" />
    </div>
@else
    <{{ $tahap }} class="{{ $kelas }}">{{ $teks }}</{{ $tahap }}>
@endif
