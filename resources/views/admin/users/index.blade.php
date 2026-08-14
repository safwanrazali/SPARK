@extends('layouts.app')

@section('title', 'Pengurusan Pengguna')

@section('page-title', 'Pengurusan Pengguna')

@section('content')

    <div class="report-card">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="section-title mb-0">Senarai Pengguna</h4>
            <a href="{{ route('administration.users.create') }}" class="btn btn-primary">
                <i class="bi bi-person-plus"></i> Tambah Pengguna
            </a>
        </div>

        @if ($errors->any())
            <div class="text-danger mb-3">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="table-responsive-custom">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th scope="col">Nama</th>
                        <th scope="col">Nama Pengguna</th>
                        <th scope="col">Emel</th>
                        <th scope="col">Peranan</th>
                        <th scope="col">Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->username }}</td>
                            <td>{{ $user->email }}</td>
                            <td>
                                <span class="status-badge status-rendah">
                                    {{ $user->roleLabel() }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('administration.users.edit', $user) }}"
                                    class="btn btn-sm btn-outline-light">
                                    <i class="bi bi-pencil"></i>
                                </a>

                                @if ($user->id !== auth()->id())
                                    <form action="{{ route('administration.users.destroy', $user) }}" method="POST"
                                        class="d-inline"
                                        onsubmit="return confirm('Anda pasti mahu memadam pengguna ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-empty-state colspan="5" icon="bi-people" title="Tiada pengguna">
                            Tambah pengguna baharu untuk memberikan akses kepada sistem.
                        </x-empty-state>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $users->links() }}
        </div>

    </div>

@endsection
