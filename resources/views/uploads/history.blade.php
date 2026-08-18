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
                        <th scope="col">Nama Fail</th>
                        <th scope="col">Sektor</th>
                        <th scope="col">Agensi</th>
                        <th scope="col">Status</th>
                        <th scope="col">Jumlah Rekod</th>
                        <th scope="col">Tarikh</th>
                        <th scope="col">Tindakan</th>
                    </tr>

                </thead>

                <tbody>

                    @forelse($rekod as $item)
                        <tr>

                            <td>{{ $item->nama_fail }}</td>
                            <td>{{ $item->sector_code }}</td>
                            <td>{{ $item->agency_code }}</td>

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

                            <td>
                                <form action="{{ route('muat-naik.destroy', $item) }}" method="POST"
                                    onsubmit="return confirm('Anda pasti mahu memadam rekod ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        Padam
                                    </button>
                                </form>
                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td colspan="7" class="text-center">

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
