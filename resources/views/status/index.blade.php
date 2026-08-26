@extends('layouts.app')

@section('title', 'Status Tiga Laporan')

@section('page-title', 'Status Tiga Laporan')

@section('content')

    <div class="report-card">

        <h4 class="section-title">
            Kitaran Status: Belum Bermula → Dalam Proses → Dalam Semakan → Selesai
        </h4>
        <p class="text-secondary">
            Halaman ini <strong>paparan sahaja</strong>. Setiap status dikira daripada
            <a href="{{ route('workflow.index') }}">Kemajuan Analisis Entiti</a> dan tidak boleh
            diubah di sini: laporan yang sedang disemak Pegawai Penyelaras Analisis atau menunggu
            kelulusan Ketua Bahagian dipaparkan sebagai <strong>Dalam Semakan</strong>, dan hanya
            menjadi <strong>Selesai</strong> setelah Ketua Bahagian mengesahkannya.
        </p>

        <div class="table-responsive-custom">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th scope="col">Entiti</th>
                        @foreach (\App\Models\StatusLaporan::JENIS as $nama)
                            <th scope="col">{{ $nama }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entiti as $e)
                        <tr>
                            <td>
                                <strong>{{ $e->agency_code }}</strong><br>
                                <span class="text-secondary text-nowrap">Sektor {{ $e->sector_code }}</span>
                            </td>
                            @foreach (\App\Models\StatusLaporan::JENIS as $jenis => $nama)
                                @php
                                    $laporan = $status->get($e->agency_code)[$jenis] ?? null;
                                    $nilai = $laporan['status'] ?? \App\Models\StatusLaporan::PAPARAN_BELUM_BERMULA;
                                    $kelas = $laporan['kelas'] ?? \App\Models\StatusLaporan::badgePaparan($nilai);
                                @endphp
                                <td>
                                    <span class="status-badge {{ $kelas }}">{{ $nilai }}</span>
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <x-empty-state colspan="4" icon="bi-list-check" title="Tiada entiti dipantau">
                            Entiti muncul di sini setelah mempunyai rekod muat naik atau dapatan analisis.
                        </x-empty-state>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $entiti->links() }}</div>

    </div>

@endsection
