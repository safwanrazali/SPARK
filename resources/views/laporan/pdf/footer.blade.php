{{-- resources/views/laporan/pdf/footer.blade.php --}}
<div
    style="width:100%; font-size:8px; font-family: Arial, sans-serif; color:#555;
            padding: 4px 15mm 0 15mm; box-sizing: border-box;
            display:flex; justify-content:space-between;
            border-top: 1px solid #ccc;">
    <span>{{ $kodRujukan }}</span>
    <span>Muka Surat <span class="pageNumber"></span> / <span class="totalPages"></span></span>
</div>
