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
                                <option value="{{ $sectorCode }}">{{ $sector['name'] }}</option>
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

                @if ($errors->any())
                    <div class="text-danger mb-3">{{ $errors->first() }}</div>
                @endif

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
                    opt.textContent = a.code + ' — ' + a.name;
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
                        <th>Sektor</th>
                        <th>Entiti</th>
                        <th>Kod Rujukan</th>
                        <th>Status Analisis</th>
                        <th>Kemas Kini</th>
                        <th>Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rekod as $item)
                        <tr>
                            <td>{{ $item->sector_name }}</td>
                            <td>{{ $item->agency_name }}</td>
                            <td>{{ $item->kod_rujukan ?? '-' }}</td>
                            <td>
                                <span class="status-badge {{ $item->selesai ? 'status-rendah' : 'status-sederhana' }}">
                                    {{ $item->selesai ? 'Selesai' : 'Dalam Proses' }}
                                </span>
                            </td>
                            <td>{{ $item->updated_at?->format('d/m/Y H:i') }}</td>
                            <td class="text-nowrap">
                                <a class="btn btn-sm btn-outline-light"
                                    href="{{ route('entiti.show', $item->agency_code) }}" title="Maklumat entiti">
                                    <i class="bi bi-building"></i>
                                </a>
                                @can('manage-analysis')
                                    <a class="btn btn-sm btn-outline-light"
                                        href="{{ route('analisis.borang', ['sector_code' => $item->sector_code, 'agency_code' => $item->agency_code]) }}">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                @endcan
                                <a class="btn btn-sm btn-primary" href="{{ route('laporan.inventori', $item) }}">
                                    <i class="bi bi-file-earmark-text"></i> Laporan
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center">Tiada rekod analisis ditemui</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $rekod->links() }}</div>

    </div>

@endsection
