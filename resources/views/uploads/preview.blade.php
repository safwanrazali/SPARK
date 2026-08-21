@extends('layouts.app')

@section('title', 'Pratonton Data')

@section('page-title', 'Pratonton Data Excel')

@section('content')

    <div class="report-card">

        <h4 class="section-title">
            Pratonton Rekod
        </h4>
        <p class="text-secondary">
            Memaparkan {{ \App\Support\Halaman::SETIAP_MUKA }} baris pertama fail
            sebagai semakan pantas. Keseluruhan fail akan disimpan apabila disahkan.
        </p>

        <div class="mb-4">
            <p><strong>Kod Sektor:</strong> {{ $sectorCode }}</p>
            <p><strong>Kod Agensi:</strong> {{ $agency['code'] }}</p>
        </div>

        <div class="table-responsive-custom">
            <table class="table-modern">
                @foreach ($preview as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </table>
        </div>

        <form method="POST" action="{{ route('muat-naik.store') }}" class="mt-4">
            @csrf
            <input type="hidden" name="lokasi" value="{{ $lokasi }}">
            <input type="hidden" name="nama_fail" value="{{ $namaFail }}">
            <input type="hidden" name="sector_code" value="{{ $sectorCode }}">
            <input type="hidden" name="sector_name" value="{{ $sector['name'] }}">
            <input type="hidden" name="agency_code" value="{{ $agency['code'] }}">
            <input type="hidden" name="agency_name" value="{{ $agency['name'] }}">

            <button type="submit" class="btn btn-primary">
                Sah dan Simpan
            </button>
        </form>

    </div>

@endsection
