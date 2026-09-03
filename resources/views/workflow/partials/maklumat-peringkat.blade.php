{{-- `use` MESTI diulang dalam setiap partial: @include dikompil menjadi
     fail PHP tersendiri, jadi import templat induk TIDAK diwarisi. --}}
@php
    use App\Support\AliranKerja;
    use App\Models\WorkflowStageStatus;
@endphp

    {{--
        Data yang telah direkodkan pada setiap peringkat. Dipaparkan
        kepada SEMUA peranan yang boleh melihat entiti ini — merekod
        ialah hak terhad, membaca tidak.
    --}}
    <div class="report-card mb-4">

        <h4 class="section-title">Maklumat Peringkat</h4>

        <div class="table-responsive-custom">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th scope="col">Peringkat</th>
                        <th scope="col">Status</th>
                        <th scope="col">Maklumat Direkod</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (AliranKerja::kekunci() as $kunci)
                        @php
                            $rekod = $peringkat->get($kunci);
                            $akanDatang = AliranKerja::adalahAkanDatang($kunci);
                            $tangkapan = array_filter(
                                $rekod?->dataTangkapan() ?? [],
                                fn($nilai) => $nilai !== null && $nilai !== '',
                            );
                        @endphp
                        <tr>
                            <td>
                                <span class="workflow-stage-tag">{{ $kunci }}</span>
                                {{ AliranKerja::label($kunci) }}
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
                                @if ($akanDatang)
                                    <span class="text-secondary">—</span>
                                @elseif ($tangkapan === [])
                                    <span class="text-secondary">Belum direkod</span>
                                @else
                                    @foreach ($tangkapan as $label => $nilai)
                                        <div>
                                            <small class="text-secondary">{{ $label }}:</small>
                                            {{ $nilai instanceof \Illuminate\Support\Carbon ? $nilai->format('d/m/Y') : $nilai }}
                                        </div>
                                    @endforeach
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    </div>
