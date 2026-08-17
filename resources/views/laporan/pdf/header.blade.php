{{-- resources/views/laporan/pdf/header.blade.php --}}
<div
    style="width:100%; font-size:9px; font-family: Arial, sans-serif;
            padding: 0 15mm; box-sizing: border-box;
            display:flex; align-items:center; justify-content:space-between; margin: 0 50px 20px;">
    {{-- Tinggi kedua-dua logo SENGAJA berbeza kerana ruang kosong dalam fail
         PNG berbeza: logo_nacsa.png (kanvas segi empat sama 225x225) hanya
         diisi 32% pada paksi menegak, manakala logo_ptpkm.png (1600x601)
         diisi 71%. Jika kedua-duanya diberi height yang sama, logo NACSA
         kelihatan ~2.3x lebih kecil daripada PTPKM.

         Nilai di bawah dikira supaya SAIZ TAMPAK kedua-duanya hampir sama
         (~117x38px berbanding ~118x36px), jadi kekalkan nisbah 120:50.

         AMARAN: kotak-margin header ini ~37mm (120px logo + 20px margin
         bawah). Margin atas halaman dalam LaporanController::unduh() mesti
         kekal lebih besar daripadanya (kini 47mm), jika tidak header akan
         bertindih dengan kandungan pada muka surat kedua dan seterusnya. --}}
    <img src="data:image/png;base64,{{ $nacsaLogoBase64 }}" style="height:120px;">
    <span style="letter-spacing: .35em; color:#000; font-weight:400; font-size: 14px;">RAHSIA</span>
    <img src="data:image/png;base64,{{ $ptpkmLogoBase64 }}" style="height:50px;">
</div>
