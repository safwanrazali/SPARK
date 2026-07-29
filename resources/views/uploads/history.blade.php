@extends('layouts.app')

@section('title', 'Sejarah Muat Naik')

@section('page-title', 'Sejarah Muat Naik')

@section('content')

<div class="report-card">

    <h4 class="section-title">
        Rekod Muat Naik Fail
    </h4>
<div class="table-responsive-custom">
    <table class="table-modern">

        <thead>

        <tr>
            <th>Nama Fail</th>
            <th>Status</th>
            <th>Jumlah Rekod</th>
            <th>Tarikh</th>
        </tr>

        </thead>

        <tbody>

        @forelse($rekod as $item)

            <tr>

                <td>{{ $item->nama_fail }}</td>

                <td>

                    <span class="status-badge status-rendah">
                        {{ $item->status }}
                    </span>

                </td>

                <td>
                    {{ $item->jumlah_rekod ?? '-' }}
                </td>

                <td>
                    {{ $item->created_at?->format('d/m/Y H:i') }}
                </td>

            </tr>

        @empty

            <tr>

                <td colspan="4" class="text-center">

                    Tiada rekod ditemui

                </td>

            </tr>

        @endforelse

        </tbody>

    </table>
</div>
    <div class="mt-3">

        {{ $rekod->links() }}

    </div>

</div>

@endsection