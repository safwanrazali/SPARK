<div class="mb-3">
    <label class="form-label">Nama Penuh</label>
    <input type="text" name="name" class="form-control" value="{{ old('name', $user?->name) }}" required autofocus>
    @error('name')
        <div class="text-danger mt-2">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label">Nama Pengguna</label>
    <input type="text" name="username" class="form-control" value="{{ old('username', $user?->username) }}" required>
    @error('username')
        <div class="text-danger mt-2">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label">Emel</label>
    <input type="email" name="email" class="form-control" value="{{ old('email', $user?->email) }}" required>
    @error('email')
        <div class="text-danger mt-2">{{ $message }}</div>
    @enderror
</div>

@php
    // Nilai lama diutamakan supaya pilihan pengguna kekal selepas ralat.
    $perananDipilih = old('roles', $user?->assignedRoles() ?? []);
@endphp

<div class="mb-3">
    <span class="form-label d-block">Peranan</span>
    <small class="form-hint mb-2">
        Seorang pengguna boleh memegang lebih daripada satu peranan. Kebenaran
        daripada semua peranan yang dipilih akan digabungkan.
    </small>

    <div class="role-choices">
        @foreach (\App\Models\User::roleDefinitions() as $value => $takrif)
            {{-- Hanya singkatan dipapar; nama penuh kekal dicapai melalui
                 tooltip dan dibaca oleh pembaca skrin melalui aria-label. --}}
            <label class="role-choice" title="{{ $takrif['label'] }}">
                <input type="checkbox" name="roles[]" value="{{ $value }}"
                    aria-label="{{ $takrif['label'] }}"
                    @checked(in_array($value, $perananDipilih, true))>
                <span class="role-choice__code">{{ $takrif['singkatan'] }}</span>
            </label>
        @endforeach
    </div>

    @error('roles')
        <div class="text-danger mt-2">{{ $message }}</div>
    @enderror
    @error('roles.*')
        <div class="text-danger mt-2">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label">
        Kata Laluan {{ $user ? '(kosongkan jika tidak mahu tukar)' : '' }}
    </label>
    <input type="password" name="password" class="form-control" {{ $user ? '' : 'required' }}>
    <small class="text-secondary">
        Sekurang-kurangnya 12 aksara, mengandungi huruf besar, huruf kecil, nombor dan simbol.
    </small>
    @error('password')
        <div class="text-danger mt-2">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label">Sahkan Kata Laluan</label>
    <input type="password" name="password_confirmation" class="form-control" {{ $user ? '' : 'required' }}>
</div>
