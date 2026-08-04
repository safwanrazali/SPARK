@extends('layouts.app')

@section('title', 'Tambah Pengguna')

@section('page-title', 'Tambah Pengguna Baharu')

@section('content')

    <div class="report-card">

        <h4 class="section-title mb-4">Tambah Pengguna</h4>

        <form method="POST" action="{{ route('administration.users.store') }}">
            @csrf

            @include('admin.users._form', ['user' => null])

            <button type="submit" class="btn btn-primary">
                Simpan Pengguna
            </button>
        </form>

    </div>

@endsection
