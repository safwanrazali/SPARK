{{-- `use` MESTI diulang dalam setiap partial: @include dikompil menjadi
     fail PHP tersendiri, jadi import templat induk TIDAK diwarisi. --}}
@php
    use App\Models\StatusLaporan;
@endphp

    {{-- ── Laporan ─────────────────────────────────────────────────────── --}}
    <div class="report-card">

        <h4 class="section-title">Laporan</h4>

        <div class="table-responsive-custom">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th scope="col">Jenis Laporan</th>
                        <th scope="col">Status</th>
                        <th scope="col">Kemas Kini</th>
                        <th scope="col">Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (StatusLaporan::JENIS as $jenis => $nama)
                        @php
                            $rekod = $statusLaporan[$jenis] ?? null;
                            $nilai = $rekod['status'] ?? StatusLaporan::PAPARAN_BELUM_BERMULA;
                            $kelas = $rekod['kelas'] ?? StatusLaporan::badgePaparan($nilai);
                        @endphp
                        <tr>
                            <td>{{ $nama }}</td>
                            <td>
                                @if ($kelas)
                                    <span class="status-badge {{ $kelas }}">{{ $nilai }}</span>
                                @else
                                    <span class="text-secondary">{{ $nilai }}</span>
                                @endif
                            </td>
                            <td>{{ ($rekod['kemas_kini'] ?? null)?->format('d/m/Y H:i') ?? '-' }}</td>
                            <td>
                                @if ($jenis === 'inventori' && $analisis)
                                    <a class="btn btn-sm btn-outline-light"
                                        href="{{ route('laporan.inventori', $analisis) }}">
                                        <i class="bi bi-eye"></i> Papar
                                    </a>
                                @else
                                    <span class="text-secondary">-</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    </div>
