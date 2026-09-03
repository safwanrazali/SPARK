    {{-- Pengenalan laporan — gaya dalam resources/scss/laporan-print.scss
         (ukuran A4) + laporan-pratonton.scss (saiz taip skrin).

         Partial ini dikongsi oleh pratonton skrin dan badan PDF. --}}
    <div class="laporan-id">
        <div class="laporan-id__banner">
            <h1 class="laporan-id__tajuk">Laporan Analisis Inventori Kriptografi</h1>
            <div class="laporan-id__baris">Sektor : {{ $analisis->sector_name ?: '—' }}</div>
            <div class="laporan-id__baris">Entiti : {{ $analisis->agency_name ?: '—' }}</div>
        </div>

        <table class="laporan-id__jadual">
            <tbody>
                <tr>
                    <th scope="row" class="laporan-id__label">Klasifikasi</th>
                    <td class="laporan-id__nilai">{{ $klasifikasi }}</td>
                </tr>
                <tr>
                    <th scope="row" class="laporan-id__label">Tarikh Laporan</th>
                    <td class="laporan-id__nilai">{{ $analisis->tarikh_laporan?->format('d/m/Y') ?? '—' }}</td>
                </tr>
                <tr>
                    <th scope="row" class="laporan-id__label">Kod Rujukan Laporan</th>
                    <td class="laporan-id__nilai">{{ $analisis->kod_rujukan ?: '—' }}</td>
                </tr>
                <tr>
                    <th scope="row" class="laporan-id__label">Status Laporan</th>
                    <td class="laporan-id__nilai">{{ $analisis->status_laporan ?: '—' }}</td>
                </tr>
            </tbody>
        </table>

        @if ($widgetKomentar)
            <div class="d-flex justify-content-end mt-2">
                <x-section-comment-widget :section="'maklumat'" :komentar="$komentar" :analisis="$analisis" />
            </div>
        @endif
    </div>
