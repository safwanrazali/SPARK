{{-- resources/views/laporan/pdf/header.blade.php

     GAYA SEBARIS SENGAJA DIKEKALKAN — JANGAN PINDAHKAN KE SCSS.

     Templat ini diserahkan kepada Browsershot::headerHtml() dalam
     LaporanController::unduh(), yang menyalurkannya ke headerTemplate Chrome.
     Chrome memaparkan kepala/kaki cetakan di dalam kotak margin @page sebagai
     dokumen TERASING: helaian gaya halaman utama tidak diwarisi ke dalamnya.
     Kelas daripada resources/scss/ tidak akan terpakai di sini, jadi
     memindahkan gaya ini ke SCSS akan meruntuhkan susun atur kepala pada
     SETIAP muka surat PDF tanpa sebarang ralat binaan.

     Atas sebab yang SAMA, @font-face Aptos mesti dibenamkan semula di sini:
     dokumen terasing ini tidak mewarisi fon daripada laporan.pdf.body. Hanya
     berat 400 dibenamkan — RAHSIA ialah satu-satunya teks dalam kepala. --}}
@include('laporan.pdf.fonts', ['aptosPilihan' => [[400, 'normal']]])

{{-- WAJIB: Chrome memberi dokumen headerTemplate/footerTemplate saiz fon akar
     1px, BUKAN 16px seperti dokumen biasa. Tanpa pengisytiharan di bawah,
     `font-size: 1rem` pada RAHSIA dipaparkan sebagai 1px — teks halus yang
     hampir tidak kelihatan (disahkan dengan mengukur objek teks dalam PDF
     yang dijana). Menetapkan akar kepada 16px menjadikan 1rem di sini sama
     nilai dengan 1rem dalam badan laporan. --}}
<style>
    :root {
        font-size: 16px;
    }
</style>
<div
    style="width:100%; font-size:9px; font-family: Arial, sans-serif;
            padding: 0 15mm; box-sizing: border-box;
            display:flex; align-items:center; justify-content:space-between; margin: 0 50px 20px;">
    {{-- Kedua-dua logo diberi KOTAK yang sama tepat: 4.45cm x 1.95cm.

         Sumbernya ialah varian image/pdf/logo_*_pdf.png — logo yang SAMA
         dengan fail asal, cuma jidar lutsinarnya dibuang (lihat nota dalam
         LaporanController::unduh()). Memberi fail ASAL kepada kotak ini akan
         menjadikan logo kelihatan jauh lebih kecil daripada kotaknya, kerana
         jidar lutsinar itu dikira sebahagian daripada imej: dakwat NACSA
         mengisi hanya 32% tinggi kanvas asalnya.

         `object-fit: contain` WAJIB kekal. Kotak bernisbah 2.28:1 manakala
         kedua-dua logo terpangkas bernisbah ~3.1:1 dan ~3.3:1 — tanpa
         `contain`, imej akan diregangkan untuk memenuhi kotak. Dengan
         `contain` ia memenuhi LEBAR kotak (4.45cm) pada ketinggian
         ~1.44cm/~1.35cm, tanpa herotan dan tanpa sebarang pemotongan.

         AMARAN: kotak-margin header ini kini ~24.8mm (1.95cm logo + 20px
         margin bawah). Margin atas halaman dalam LaporanController::unduh()
         mesti kekal lebih besar daripadanya (kini 47mm), jika tidak header
         akan bertindih dengan kandungan pada muka surat kedua dan
         seterusnya. --}}
    <img src="data:image/png;base64,{{ $nacsaLogoBase64 }}"
        style="width:4.45cm; height:1.95cm; object-fit:contain;">
    <span
        style="letter-spacing: .35em; color:#000; font-weight:400;
                font-family: 'Aptos', Arial, sans-serif; font-size: 1rem;">RAHSIA</span>
    <img src="data:image/png;base64,{{ $ptpkmLogoBase64 }}"
        style="width:4.45cm; height:1.95cm; object-fit:contain;">
</div>
