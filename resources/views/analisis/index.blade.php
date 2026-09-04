@extends('layouts.app')

@section('title', 'Analisis Inventori Kriptografi')

@section('page-title', 'Analisis Inventori Kriptografi')

@section('content')

    @can('manage-analysis')
        <div class="report-card mb-4">

            <h4 class="section-title">Pilih Entiti Untuk Analisis</h4>
            <p class="text-secondary">
                Input analisis berstruktur — pilihan jawapan, checkbox dan field input.
                Hanya item yang dipilih akan dipertimbangkan dalam kandungan laporan.
            </p>

            <form action="{{ route('analisis.borang') }}" method="GET">

                <div class="row mb-3">
                    <div class="col">
                        <label class="form-label">Pilih Sektor</label>
                        <select id="sector-select" name="sector_code" class="form-select" required>
                            <option value="">-- Sila Pilih --</option>
                            @foreach ($sektor as $sectorCode => $sector)
                                <option value="{{ $sectorCode }}">{{ $sectorCode }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col">
                        <label class="form-label">Nama Agensi / Entiti</label>
                        <select id="agency-select" name="agency_code" class="form-select" required disabled>
                            <option value="">-- Pilih Sektor Dahulu --</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-pencil-square"></i> Buka Borang Analisis
                </button>

            </form>

        </div>

        <script>
            const agensiIkutSektor = @json(collect($sektor)->map(fn($s) => $s['agencies']));

            document.getElementById('sector-select').addEventListener('change', function() {
                const agencySelect = document.getElementById('agency-select');
                const senarai = agensiIkutSektor[this.value] || [];

                agencySelect.innerHTML = '<option value="">-- Sila Pilih --</option>';
                senarai.forEach(a => {
                    const opt = document.createElement('option');
                    opt.value = a.code;
                    opt.textContent = a.code;
                    agencySelect.appendChild(opt);
                });
                agencySelect.disabled = senarai.length === 0;
            });
        </script>
    @endcan

    <div class="report-card">

        <h4 class="section-title">Rekod Analisis</h4>

        <div class="table-responsive-custom">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th scope="col">Sektor</th>
                        <th scope="col">Entiti</th>
                        <th scope="col">Kod Rujukan</th>
                        {{-- Dinamakan tepat seperti medan yang dipaparkannya:
                             `status_borang` peringkat 3.1, yang labelnya
                             ditakrifkan oleh AliranKerja::medan(). --}}
                        <th scope="col">Status Laporan Inventori Kriptografi</th>
                        <th scope="col">Kemas Kini</th>
                        <th scope="col">Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rekod as $item)
                        <tr>
                            <td>{{ $item->sector_code }}</td>
                            <td>{{ $item->agency_code }}</td>
                            <td>{{ $item->kod_rujukan ?? '-' }}</td>
                            {{--
                                DUA maklumat berbeza, sengaja dipaparkan
                                bersama:

                                Lencana  "Status Laporan Inventori Kriptografi"
                                         — medan `status_borang` peringkat 3.1,
                                         nilai yang SAMA yang dipilih pegawai
                                         pada halaman Kemajuan Analisis Entiti.
                                Borang   keadaan borang input ini sendiri.
                                         `analisis_inventori.selesai` bermaksud
                                         "telah dimuktamadkan", iaitu SYARAT
                                         sebelum peringkat 3.1 boleh ditutup —
                                         bukan status laporan.

                                Sebelum ini lajur ini memaparkan medan `selesai`
                                sebagai "Selesai", lalu melaporkan laporan yang
                                masih "Dalam Semakan" sebagai sudah selesai.

                                Cabang paparan mengikut konvensyen yang SAMA
                                dengan entiti/partials/status-borang.blade.php.
                            --}}
                            <td>
                                @php
                                    $statusLaporan = $kemajuanEntiti[$item->agency_code] ?? null;
                                    $nilaiLaporan = $statusLaporan['nilai'] ?? null;
                                    $kelasLaporan = $statusLaporan['kelas'] ?? null;
                                @endphp

                                @if ($nilaiLaporan === null || $nilaiLaporan === '')
                                    <span class="text-secondary">Belum direkod</span>
                                @elseif ($kelasLaporan)
                                    <span class="status-badge {{ $kelasLaporan }}">{{ $nilaiLaporan }}</span>
                                @else
                                    {{-- "Tidak Berkaitan" bukan kedudukan kerja, jadi tiada pil warna. --}}
                                    <span class="text-secondary">{{ $nilaiLaporan }}</span>
                                @endif

                                <small class="d-block text-secondary mt-1">
                                    Borang: {{ $item->selesai ? 'Dimuktamadkan' : 'Draf' }}
                                </small>
                            </td>
                            <td>{{ $item->updated_at?->format('d/m/Y H:i') }}</td>
                            <td class="text-nowrap">
                                <a class="btn btn-sm btn-outline-light"
                                    href="{{ route('entiti.show', $item->agency_code) }}"
                                    title="Maklumat entiti {{ $item->agency_code }}"
                                    aria-label="Maklumat entiti {{ $item->agency_code }}">
                                    <i class="bi bi-building" aria-hidden="true"></i>
                                </a>
                                @can('manage-analysis')
                                    <a class="btn btn-sm btn-outline-light"
                                        href="{{ route('analisis.borang', ['sector_code' => $item->sector_code, 'agency_code' => $item->agency_code]) }}"
                                        title="Sunting dapatan {{ $item->agency_code }}"
                                        aria-label="Sunting dapatan {{ $item->agency_code }}">
                                        <i class="bi bi-pencil" aria-hidden="true"></i>
                                    </a>
                                @endcan
                                <a class="btn btn-sm btn-primary" href="{{ route('laporan.inventori', $item) }}">
                                    <i class="bi bi-file-earmark-text"></i> Laporan
                                </a>
                            </td>
                        </tr>
                    @empty
                        <x-empty-state colspan="6" icon="bi-clipboard-data" title="Tiada rekod analisis">
                            Rekod muncul di sini setelah dapatan analisis dimasukkan bagi entiti yang ditugaskan.
                        </x-empty-state>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $rekod->links() }}</div>

    </div>

@endsection
