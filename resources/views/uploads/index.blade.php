@extends('layouts.app')

@section('title', 'Muat Naik Data')

@section('page-title', 'Muat Naik Data')

@section('content')

    <div class="report-card">

        <h4 class="section-title">
            Import Data Excel
        </h4>

        <p class="text-secondary">
            Muat naik fail Excel yang mengandungi helaian
            MasterTable atau MasterTable_Risk.
        </p>

        <form action="{{ route('muat-naik.preview') }}" method="POST" enctype="multipart/form-data">

            @csrf
            <div class="mb-3">
                <label class="form-label">
                    Pilih Sektor
                </label>
                <select name="sektor" class="form-select" required>

                    <option value="">
                        -- Sila Pilih --
                    </option>

                    @foreach (config('sektor') as $sektor)
                        <option value="{{ $sektor }}">

                            {{ $sektor }}

                        </option>
                    @endforeach

                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">
                    Nama Agensi / Entiti
                </label>
                <select name="sektor" class="form-select" required>

                    <option value="">
                        -- Sila Pilih --
                    </option>

                    @foreach (config('sektor') as $sektor)
                        <option value="{{ $sektor }}">

                            {{ $sektor }}

                        </option>
                    @endforeach

                </select>
            </div>

            <div class="mb-3">

                <label class="form-label">
                    Pilih Fail Excel
                </label>

                <input type="file" name="fail_excel" class="form-control" required>
                @error('fail_excel')
                    <div class="text-danger mt-2">

                        {{ $message }}

                    </div>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary">

                Muat Naik

            </button>

        </form>

    </div>
    <div class="report-card mt-4">

        <h5>Keperluan Fail</h5>

        <ul>

            <li>Format .xlsx atau .xls</li>

            <li>
                Mesti mengandungi helaian
                MasterTable atau MasterTable_Risk
            </li>

            <li>
                Struktur kolum hendaklah mengikut
                templat rasmi organisasi
            </li>

        </ul>

    </div>
@endsection
