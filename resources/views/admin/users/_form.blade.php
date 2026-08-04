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

<div class="mb-3">
    <label class="form-label">Peranan</label>
    <select name="role" class="form-select" required>
        <option value="">-- Sila Pilih --</option>
        @foreach (['administrator' => 'Administrator', 'coordinator' => 'Coordinator', 'analyst' => 'Analyst'] as $value => $label)
            <option value="{{ $value }}" @selected(old('role', $user?->role) === $value)>
                {{ $label }}
            </option>
        @endforeach
    </select>
    @error('role')
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
