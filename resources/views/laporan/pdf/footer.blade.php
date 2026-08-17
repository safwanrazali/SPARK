{{-- resources/views/laporan/pdf/footer.blade.php

     GAYA SEBARIS SENGAJA DIKEKALKAN — JANGAN PINDAHKAN KE SCSS.
     Sama seperti header.blade.php: templat ini menjadi footerTemplate Chrome,
     yang dipaparkan dalam dokumen terasing tanpa helaian gaya halaman.
     Kelas SCSS tidak terpakai di sini. Kelas .pageNumber di bawah pula
     ditafsirkan oleh Chrome sendiri, bukan oleh CSS aplikasi. --}}
<div
    style="width:100%; font-size:12px; font-family: Arial, sans-serif; color:#555;
            padding: 4px 15mm 0 15mm; box-sizing: border-box;
            display:flex; justify-content:space-between;">
    <span>{{ $kodRujukan }}</span>
    <span class="pageNumber"></span>
</div>
