    <section class="laporan-seksyen laporan-seksyen--mula-halaman">
        <h2 class="laporan-seksyen__tajuk">Pengesahan Laporan</h2>

        <table class="laporan-pengesahan">
            {{-- Lebar lajur MESTI di sini: dengan `table-layout: fixed`, hanya baris
                 pertama menentukan lebar lajur. --}}
            <colgroup>
                <col class="laporan-pengesahan__lajur-peranan">
                <col class="laporan-pengesahan__lajur-nama">
                <col class="laporan-pengesahan__lajur-tandatangan">
                <col class="laporan-pengesahan__lajur-tarikh">
            </colgroup>
            <thead>
                <tr>
                    <th>Peranan</th>
                    <th>Nama</th>
                    <th>Tandatangan</th>
                    <th>Tarikh</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($pengesahan as $baris)
                    {{-- Baris tanpa nama bercetak ditandatangani sepenuhnya dengan
                         tangan. Teksnya dijajarkan ke ATAS supaya ruang kosong di
                         bawah label kekal untuk menulis nama dan tandatangan pada
                         salinan bercetak. --}}
                    <tr @class(['laporan-pengesahan__baris-manual' => $baris['nama'] === ''])>
                        <td class="laporan-pengesahan__peranan">{{ $baris['peranan'] }}</td>
                        <td class="laporan-pengesahan__nama">{{ $baris['nama'] }}</td>
                        {{-- Sel tandatangan dan tarikh sengaja KOSONG: laporan
                             dicetak untuk ditandatangani. Tiada kawalan interaktif
                             diletakkan di sini kerana ia tidak berfungsi dalam PDF. --}}
                        <td class="laporan-pengesahan__tandatangan"></td>
                        <td class="laporan-pengesahan__tarikh">{{ $baris['tarikh'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p class="laporan-seksyen__penafian-tajuk">Penafian dan Had Penggunaan Laporan:</p>

        <p class="laporan-seksyen__perenggan">
            Laporan ini disediakan berdasarkan data dan maklumat yang dikemukakan oleh entiti
            melalui NACSA serta analisis yang dilaksanakan oleh Bahagian Migrasi PQC, PTPKM.
            Ketepatan dapatan bergantung pada kelengkapan, ketepatan, konsistensi dan
            kebolehgunaan data yang diterima.
        </p>

        <p class="laporan-seksyen__perenggan">
            Laporan ini merupakan <strong>penilaian inventori kriptografi bagi tujuan pelaksanaan
                Klinik Migrasi PQC</strong> berdasarkan rekod yang dikemukakan. Laporan ini tidak
            menggantikan audit teknikal, semakan kod sumber, pengesahan konfigurasi sistem,
            <em>vulnerability scanning</em>, <em>penetration testing</em> atau pensijilan
            keselamatan kriptografi.
        </p>

        <p class="laporan-seksyen__perenggan">
            Dapatan atau isu yang memerlukan pengesahan hendaklah disemak bersama pemilik sistem,
            pegawai teknikal atau vendor yang berkaitan sebelum sebarang perubahan konfigurasi,
            penggantian komponen atau pelaksanaan tindakan migrasi dibuat.
        </p>
    </section>
