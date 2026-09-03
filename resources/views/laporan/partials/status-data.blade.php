    {{-- STATUS PENERIMAAN DAN KEBOLEHGUNAAN DATA — gaya .laporan-seksyen /
         .laporan-jadual dalam resources/scss/laporan-print.scss.

         Partial ini dikongsi oleh pratonton skrin dan badan PDF. --}}
    <section class="laporan-seksyen laporan-seksyen--mula-halaman">
        @include('laporan.partials.tajuk-seksyen', [
            'tahap' => 'h2',
            'kelas' => 'laporan-seksyen__tajuk',
            'teks' => 'Status Penerimaan dan Kebolehgunaan Data',
            'seksyen' => 'data_status',
        ])

        <p class="laporan-seksyen__perenggan">
            Jadual di bawah merumuskan status penerimaan dan kebolehgunaan data inventori kriptografi
            yang dikemukakan melalui Jadual 0–2. Penilaian dilaksanakan berdasarkan aspek
            kelengkapan, kejelasan dan konsistensi data bagi menentukan kesesuaiannya untuk tujuan
            analisis.
        </p>

        <table class="laporan-jadual">
            {{-- Lebar lajur MESTI diisytiharkan di sini. Dengan
                 `table-layout: fixed`, hanya BARIS PERTAMA yang menentukan lebar
                 lajur; meletakkan `width` pada <td> dalam <tbody> tidak memberi
                 kesan dan jadual akan terbahagi sama rata. --}}
            <colgroup>
                <col class="laporan-jadual__lajur-bil">
                <col class="laporan-jadual__lajur-komponen">
                <col class="laporan-jadual__lajur-status">
            </colgroup>
            <thead>
                <tr>
                    <th class="laporan-jadual__kepala">Bil.</th>
                    <th class="laporan-jadual__kepala">Komponen</th>
                    <th class="laporan-jadual__kepala">Status Kebolehgunaan</th>
                </tr>
            </thead>
            <tbody>
                @foreach (['j0' => 'Jadual 0 : Inventori', 'j1' => 'Jadual 1: Software Bill of Materials (SBOM)', 'j2' => 'Jadual 2: Cryptographic Bill of Materials (CBOM)'] as $kunci => $nama)
                    @php
                        $baris = $data['data_status'][$kunci] ?? [];
                        $penerangan = \App\Support\BorangAnalisis::senaraiTeks($baris['nota'] ?? null);
                    @endphp
                    <tr>
                        <td class="laporan-jadual__bil">{{ $loop->iteration }}.</td>
                        <td class="laporan-jadual__komponen">{{ $nama }}</td>
                        <td class="laporan-jadual__status">
                            <span class="laporan-jadual__status-nilai">{{ $baris['kebolehgunaan'] ?? '—' }}</span>
                            @if (count($penerangan) > 1)
                                <ol class="laporan-jadual__penerangan">
                                    @foreach ($penerangan as $titik)
                                        <li>{{ $titik }}</li>
                                    @endforeach
                                </ol>
                            @elseif (count($penerangan) === 1)
                                <p class="laporan-jadual__penerangan-tunggal">{{ $penerangan[0] }}</p>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>


        <p class="laporan-seksyen__catatan-tajuk">Catatan:</p>
        @if (count($failSumber))
            <p class="laporan-seksyen__perenggan">
                Maklumat diperoleh daripada {{ $bilanganFail }} fail berikut:
            </p>
            <ol class="laporan-seksyen__senarai-fail">
                @foreach ($failSumber as $fail)
                    <li>
                        {{ $fail }}
                        (dirujuk sebagai <strong>FAIL {{ $loop->iteration }}</strong> dalam laporan ini)
                        .
                    </li>
                @endforeach
            </ol>
        @else
            <p class="laporan-seksyen__perenggan">
                Tiada fail sumber direkodkan bagi entiti ini.
            </p>
        @endif
    </section>
