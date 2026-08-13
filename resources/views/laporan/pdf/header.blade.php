{{-- resources/views/laporan/pdf/header.blade.php --}}
<div
    style="width:100%; font-size:9px; font-family: Arial, sans-serif;
            padding: 0 15mm; box-sizing: border-box;
            display:flex; align-items:center; justify-content:space-between;
            border-bottom: 2px solid #333; padding-bottom: 6px;">
    <img src="data:image/png;base64,{{ $nacsaLogoBase64 }}" style="height:22px;">
    <span style="letter-spacing: .35em; color:#b3403a; font-weight:700;">RAHSIA</span>
    <img src="data:image/png;base64,{{ $ptpkmLogoBase64 }}" style="height:22px;">
</div>
