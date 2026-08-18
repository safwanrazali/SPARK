@extends('layouts.app')

@section('title', 'Profil Saya')

@section('page-title', 'Profil Saya')

@section('content')

    <div class="report-card">

        <h4 class="section-title mb-4">Maklumat Akaun</h4>

        <form method="POST" action="{{ route('profil.update') }}">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label class="form-label" for="name">Nama Penuh</label>
                <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $user->name) }}"
                    required autofocus>
                @error('name')
                    <div class="text-danger mt-2">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="username">Nama Pengguna</label>
                <input type="text" name="username" id="username" class="form-control"
                    value="{{ old('username', $user->username) }}" required>
                @error('username')
                    <div class="text-danger mt-2">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="email">Emel</label>
                <input type="email" name="email" id="email" class="form-control"
                    value="{{ old('email', $user->email) }}" required>
                @error('email')
                    <div class="text-danger mt-2">{{ $message }}</div>
                @enderror
            </div>

            {{-- Peranan ditetapkan oleh Pentadbir Sistem, jadi ia dipapar
                 sebagai maklumat sahaja dan tiada dalam borang. --}}
            <div class="mb-4">
                <span class="form-label d-block">Peranan</span>
                <p class="form-static">
                    @forelse ($user->assignedRoleLabels() as $label)
                        <span class="role-pill">{{ $label }}</span>
                    @empty
                        Tiada peranan
                    @endforelse
                </p>
                <small class="form-hint">
                    Peranan hanya boleh ditukar oleh Pentadbir Sistem.
                </small>
            </div>

            <hr class="my-4">

            <h5 class="section-title mb-3">Tukar Kata Laluan</h5>

            <p class="form-hint mb-3">
                Biarkan kedua-dua medan di bawah kosong jika anda tidak mahu menukar kata laluan.
            </p>

            <div class="mb-3">
                <label class="form-label" for="password">Kata Laluan</label>
                <input type="password" name="password" id="password" class="form-control" autocomplete="new-password">
                <small class="form-hint">
                    Sekurang-kurangnya 12 aksara, mengandungi huruf besar, huruf kecil, nombor dan simbol.
                </small>
                @error('password')
                    <div class="text-danger mt-2">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-4">
                <label class="form-label" for="password_confirmation">Sahkan Kata Laluan</label>
                <input type="password" name="password_confirmation" id="password_confirmation" class="form-control"
                    autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-primary">
                Kemaskini Profil
            </button>

        </form>

    </div>

@endsection
