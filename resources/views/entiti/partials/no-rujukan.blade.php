{{-- `use` MESTI diulang dalam setiap partial: @include dikompil menjadi
     fail PHP tersendiri, jadi import templat induk TIDAK diwarisi. --}}
@php
    use App\Support\AliranKerja;
@endphp

    {{--
        ── No. Rujukan ──────────────────────────────────────────────────
        Diasingkan daripada jadual di atas kerana pemiliknya berbeza: setiap
        No. Rujukan dimasukkan oleh Pegawai Kawalan Dokumen, bukan oleh
        pegawai yang melaksanakan peringkatnya. Lajur "Direkod Oleh" di sini
        merujuk PKD, bukan pemilik peringkat.
    --}}
    <div class="report-card mb-4">

        <h4 class="section-title">No. Rujukan</h4>
        <p class="text-secondary">
            Setiap nombor rujukan direkodkan oleh Pegawai Kawalan Dokumen.
        </p>

        <div class="table-responsive-custom">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th scope="col">Dokumen</th>
                        <th scope="col">Peringkat</th>
                        <th scope="col">No. Rujukan</th>
                        <th scope="col">Direkod Oleh</th>
                        <th scope="col">Tarikh Direkod</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($berujukan as $kunci)
                        @php
                            $rekod = $peringkat->get($kunci);
                            $nombor = $rekod?->no_rujukan;
                        @endphp
                        <tr>
                            <td>{{ AliranKerja::labelRujukan($kunci) }}</td>
                            <td class="text-nowrap">
                                <span class="workflow-stage-tag">{{ $kunci }}</span>
                                {{ AliranKerja::label($kunci) }}
                            </td>
                            <td>
                                @if ($nombor === null || $nombor === '')
                                    <span class="text-secondary">Belum direkod</span>
                                @else
                                    <strong>{{ $nombor }}</strong>
                                @endif
                            </td>
                            <td>{{ $rekod?->noRujukanOleh?->name ?? '-' }}</td>
                            <td class="text-nowrap">{{ $rekod?->no_rujukan_pada?->format('d/m/Y H:i') ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    </div>
