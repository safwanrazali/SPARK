@extends('layouts.app')

@section('title', 'Laporan Inventori')

@section('page-title', 'Jana Laporan Analisis Inventori Kriptografi')

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
                        <th>Sektor</th>
                        <th>Entiti</th>
                        <th>Kod Rujukan</th>
                        <th>Status Laporan</th>
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
                            <td><span class="status-badge status-rendah">{{ $item->status_laporan }}</span></td>
                            <td>{{ $item->updated_at?->format('d/m/Y H:i') }}</td>
                            <td>
                                <a href="{{ route('laporan.inventori', $item) }}" class="btn btn-sm btn-primary">
                                    <i class="bi bi-file-earmark-text"></i> Pratonton Laporan
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center">
                                Tiada dapatan analisis direkodkan. Masukkan dapatan melalui
                                Analisis Inventori Kriptografi terlebih dahulu.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $rekod->links() }}</div>

    </div>

@endsection
