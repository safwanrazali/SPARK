@extends('layouts.app')

@section('title', 'Kemaskini Pengguna')

@section('page-title', 'Kemaskini Pengguna')

@section('content')

    <div class="report-card">

        <h4 class="section-title mb-4">Kemaskini Pengguna: {{ $user->name }}</h4>

        <form method="POST" action="{{ route('administration.users.update', $user) }}">
            @csrf
            @method('PUT')

            @include('admin.users._form', ['user' => $user])

            <button type="submit" class="btn btn-primary">
                Kemaskini Pengguna
            </button>
        </form>

    </div>

@endsection
