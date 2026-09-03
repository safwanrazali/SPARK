{{-- `use` MESTI diulang dalam setiap partial: @include dikompil menjadi
     fail PHP tersendiri, jadi import templat induk TIDAK diwarisi. --}}
@php
    use App\Support\AliranKerja;
    use App\Models\WorkflowStageStatus;
@endphp

    {{--
        ── Status Borang ────────────────────────────────────────────────
        Inti halaman. Satu baris bagi SETIAP borang dalam aliran kerja,
        dengan status borangnya sendiri (perbendaharaan tujuh nilai) di
        sebelah status peringkat yang memilikinya. Kedua-duanya diperlukan:
        satu peringkat boleh Selesai sementara borangnya "Telah Diserah",
        dan hanya paparan ini menunjukkan kedua-duanya serentak.
    --}}
    <div class="report-card mb-4">

        <h4 class="section-title">Status Borang</h4>
        <p class="text-secondary">
            Kedudukan setiap borang yang direkodkan sepanjang aliran kerja entiti ini.
        </p>

        <div class="table-responsive-custom">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th scope="col">Borang</th>
                        <th scope="col">Peringkat</th>
                        <th scope="col">Status Borang</th>
                        <th scope="col">Status Peringkat</th>
                        <th scope="col">Tarikh Direkod</th>
                        <th scope="col">Kemas Kini</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($borang as $kunci)
                        @php
                            $rekod = $peringkat->get($kunci);
                            $akanDatang = AliranKerja::adalahAkanDatang($kunci);
                            $medan = AliranKerja::medan($kunci);
                            $nilaiBorang = $rekod?->status_borang;
                            $kelasBorang = AliranKerja::badgeStatusBorang($nilaiBorang);
                        @endphp
                        <tr>
                            <td>{{ $medan[AliranKerja::MEDAN_STATUS_BORANG] }}</td>
                            <td class="text-nowrap">
                                <span class="workflow-stage-tag">{{ $kunci }}</span>
                                {{ AliranKerja::label($kunci) }}
                            </td>
                            <td>
                                @if ($nilaiBorang === null || $nilaiBorang === '')
                                    <span class="text-secondary">Belum direkod</span>
                                @elseif ($kelasBorang)
                                    <span class="status-badge {{ $kelasBorang }}">{{ $nilaiBorang }}</span>
                                @else
                                    {{-- "Tidak Berkaitan" bukan kedudukan kerja, jadi tiada pil warna. --}}
                                    <span class="text-secondary">{{ $nilaiBorang }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($akanDatang)
                                    <span class="text-secondary fst-italic">Belum dibina</span>
                                @else
                                    <span class="status-badge {{ $rekod?->statusBadgeClass() ?? 'status-tinggi' }}">
                                        {{ $rekod?->status ?? WorkflowStageStatus::BELUM_MULA }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $adaTarikh = false;
                                @endphp
                                @foreach ($medan as $lajur => $label)
                                    @continue($lajur === AliranKerja::MEDAN_STATUS_BORANG)
                                    @php
                                        $nilai = $rekod?->{$lajur};
                                    @endphp
                                    @if ($nilai !== null && $nilai !== '')
                                        @php
                                            $adaTarikh = true;
                                        @endphp
                                        <div>
                                            <small class="text-secondary">{{ $label }}:</small>
                                            {{ $tarikh($nilai) }}
                                        </div>
                                    @endif
                                @endforeach
                                @unless ($adaTarikh)
                                    <span class="text-secondary">-</span>
                                @endunless
                            </td>
                            <td class="text-nowrap">
                                {{ $rekod?->updated_at?->format('d/m/Y H:i') ?? '-' }}
                                @if ($rekod?->updatedBy)
                                    <div><small class="text-secondary">{{ $rekod->updatedBy->name }}</small></div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    </div>
