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

        {{-- Dipapar sekali sahaja: kata laluan sementara tidak disimpan dalam
             bentuk yang boleh dibaca semula. --}}
        @if (session('kata_laluan_sementara'))
            @php($semula = session('kata_laluan_sementara'))
            <x-alert type="warning" title="Kata laluan sementara telah dijana" class="mb-4">
                <p class="mb-2">
                    Sampaikan kelayakan ini kepada pemilik akaun. Mereka akan diminta
                    menukarnya sebaik sahaja log masuk.
                </p>

                <dl class="kelayakan-sementara">
                    <dt>Nama pengguna</dt>
                    <dd>{{ $semula['username'] }}</dd>
                    <dt>Kata laluan</dt>
                    <dd><code>{{ $semula['kata_laluan'] }}</code></dd>
                </dl>

                <p class="mb-0">
                    <strong>Salin sekarang</strong> — ia tidak akan dipaparkan semula, dan
                    sesi aktif pengguna tersebut telah ditamatkan.
                </p>
            </x-alert>
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
                                @forelse ($user->assignedRoleLabels() as $label)
                                    <span class="status-badge status-rendah">{{ $label }}</span>
                                @empty
                                    <span class="text-secondary">Tiada peranan</span>
                                @endforelse
                            </td>
                            <td>
                                <a href="{{ route('administration.users.edit', $user) }}"
                                    class="btn btn-sm btn-outline-light" title="Kemaskini pengguna">
                                    <i class="bi bi-pencil" aria-hidden="true"></i>
                                    <span class="visually-hidden">Kemaskini {{ $user->name }}</span>
                                </a>

                                @if ($user->id !== auth()->id())
                                    {{-- Kata laluan sementara dijana oleh sistem; pentadbir tidak
                                         memilihnya sendiri. --}}
                                    <form
                                        action="{{ route('administration.users.tetap-semula-kata-laluan', $user) }}"
                                        method="POST" class="d-inline"
                                        onsubmit="return confirm('Tetapkan semula kata laluan {{ $user->name }}? Sesi aktif mereka akan ditamatkan.');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-light"
                                            title="Tetapkan semula kata laluan">
                                            <i class="bi bi-key" aria-hidden="true"></i>
                                            <span class="visually-hidden">Tetapkan semula kata laluan {{ $user->name }}</span>
                                        </button>
                                    </form>
                                @endif

                                @if ($user->id !== auth()->id())
                                    <form action="{{ route('administration.users.destroy', $user) }}" method="POST"
                                        class="d-inline"
                                        onsubmit="return confirm('Anda pasti mahu memadam pengguna ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger"
                                            title="Padam pengguna">
                                            <i class="bi bi-trash" aria-hidden="true"></i>
                                            <span class="visually-hidden">Padam {{ $user->name }}</span>
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
