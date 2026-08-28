{{-- resources/views/laporan/pdf/footer.blade.php

     GAYA SEBARIS SENGAJA DIKEKALKAN — JANGAN PINDAHKAN KE SCSS.
     Sama seperti header.blade.php: templat ini menjadi footerTemplate Chrome,
     yang dipaparkan dalam dokumen terasing tanpa helaian gaya halaman.
     Kelas SCSS tidak terpakai di sini. Kelas .pageNumber di bawah pula
     ditafsirkan oleh Chrome sendiri, bukan oleh CSS aplikasi.

     Atas sebab yang SAMA, @font-face Aptos mesti dibenamkan semula di sini.
     Hanya berat 400 dibenamkan — kaki halaman tiada teks tebal atau condong.

     Saiz fon diisytiharkan pada bekas ini sahaja supaya KEDUA-DUA kod
     rujukan dan nombor muka surat mewarisinya. --}}
@include('laporan.pdf.fonts', ['aptosPilihan' => [[400, 'normal']]])

{{-- WAJIB: lihat nota yang sama dalam header.blade.php — Chrome memberi
     dokumen footerTemplate saiz fon akar 1px, jadi `0.833rem` akan menjadi
     0.83px tanpa pengisytiharan ini. Dengan akar 16px, 0.833rem = 13.33px. --}}
<style>
    :root {
        font-size: 16px;
    }
</style>
<div
    style="width:100%; font-size:0.833rem; font-family: 'Aptos', Arial, sans-serif; color:#555;
            padding: 4px 15mm 0 15mm; box-sizing: border-box;
            display:flex; justify-content:space-between;">
    <span>{{ $kodRujukan }}</span>
    <span class="pageNumber"></span>
</div>
