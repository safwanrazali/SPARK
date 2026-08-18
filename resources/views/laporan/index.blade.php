@extends('layouts.app')

@section('title', 'Laporan Inventori')

@section('page-title', 'Penjanaan Laporan')

@section('content')

    <div class="report-card">

        <h4 class="section-title">Laporan Mengikut Entiti</h4>
        <p class="text-secondary">
            Sistem menyusun input berstruktur mengikut struktur dan kandungan templat
            laporan rasmi. Semak pratonton dan betulkan input melalui borang analisis
            sebelum laporan dimuktamadkan.
        </p>

        <div class="table-responsive-custom">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th scope="col">Sektor</th>
                        <th scope="col">Entiti</th>
                        <th scope="col">Kod Rujukan</th>
                        <th scope="col">Status Laporan</th>
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
                            <td><span class="status-badge status-rendah">{{ $item->status_laporan }}</span></td>
                            <td>{{ $item->updated_at?->format('d/m/Y H:i') }}</td>
                            <td>
                                <a href="{{ route('laporan.inventori', $item) }}" class="btn btn-sm btn-primary">
                                    <i class="bi bi-file-earmark-text"></i> Pratonton Laporan
                                </a>
                            </td>
                        </tr>
                    @empty
                        <x-empty-state colspan="6" icon="bi-file-earmark-bar-graph" title="Tiada laporan tersedia">
                            Laporan boleh dijana selepas dapatan analisis dimasukkan bagi entiti berkenaan.
                        </x-empty-state>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $rekod->links() }}</div>

    </div>

@endsection
