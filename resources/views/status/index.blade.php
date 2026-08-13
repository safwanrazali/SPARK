@extends('layouts.app')

@section('title', 'Status Tiga Laporan')

@section('page-title', 'Status Tiga Laporan Bagi Setiap Entiti')

@section('content')

    <div class="report-card">

        <h4 class="section-title">Kitaran Status: Belum Bermula → Dalam Proses → Siap</h4>
        <p class="text-secondary">
            @can('manage-status')
                Klik status untuk mengemas kini. Hak kemas kini dikawal mengikut peranan
                (Pegawai Penyelaras Analisis / Pentadbir).
            @else
                Peranan semasa hanya boleh melihat status. Kemas kini dilakukan oleh
                Pegawai Penyelaras Analisis.
            @endcan
        </p>

        <div class="table-responsive-custom">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th>Entiti</th>
                        @foreach (\App\Models\StatusLaporan::JENIS as $nama)
                            <th>{{ $nama }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entiti as $e)
                        <tr>
                            <td>
                                <strong>{{ $e->agency_name }}</strong><br>
                                <span class="text-secondary">{{ $e->sector_name }}</span>
                            </td>
                            @foreach (\App\Models\StatusLaporan::JENIS as $jenis => $nama)
                                @php
                                    $rekod = $status->get($e->agency_code)?->firstWhere('jenis', $jenis);
                                    $nilai = $rekod?->status ?? 'Belum Bermula';
                                    $kelas =
                                        ['Siap' => 'status-rendah', 'Dalam Proses' => 'status-sederhana'][$nilai] ??
                                        'status-tinggi';
                                @endphp
                                <td>
                                    @can('manage-status')
                                        <form action="{{ route('status.kitar') }}" method="POST" class="m-0">
                                            @csrf
                                            <input type="hidden" name="sector_code" value="{{ $e->sector_code }}">
                                            <input type="hidden" name="sector_name" value="{{ $e->sector_name }}">
                                            <input type="hidden" name="agency_code" value="{{ $e->agency_code }}">
                                            <input type="hidden" name="agency_name" value="{{ $e->agency_name }}">
                                            <input type="hidden" name="jenis" value="{{ $jenis }}">
                                            <button type="submit" class="btn btn-sm p-0 border-0 bg-transparent"
                                                title="Klik untuk kemas kini status">
                                                <span class="status-badge {{ $kelas }}">{{ $nilai }}</span>
                                            </button>
                                        </form>
                                    @else
                                        <span class="status-badge {{ $kelas }}">{{ $nilai }}</span>
                                    @endcan
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center">
                                Tiada entiti dipantau lagi. Entiti muncul di sini setelah mempunyai
                                rekod muat naik atau dapatan analisis.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>

@endsection
