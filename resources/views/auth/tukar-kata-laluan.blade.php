<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tukar Kata Laluan - Sistem Pemantauan &amp; Pelaporan Analisis Migrasi PQC</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>

{{-- Sengaja tiada bar sisi atau menu: pengguna dikunci di skrin ini sehingga
     kata laluan sementara diganti. --}}

<body>
    <div class="login-wrapper">
        <div class="login-card report-card">

            <h4 class="section-title mb-2">Tukar Kata Laluan</h4>

            <p class="form-hint mb-4">
                Kata laluan anda dikeluarkan oleh Pentadbir Sistem. Sila
                gantikannya dengan kata laluan pilihan sendiri sebelum meneruskan.
            </p>

            @error('password')
                <div class="text-danger mb-3">{{ $message }}</div>
            @enderror

            <form method="POST" action="{{ route('kata-laluan.simpan') }}">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label class="form-label" for="password">Kata Laluan Baharu</label>
                    <input type="password" name="password" id="password" class="form-control"
                        autocomplete="new-password" required autofocus>
                    <small class="form-hint">
                        Sekurang-kurangnya 12 aksara, mengandungi huruf besar, huruf kecil, nombor dan simbol.
                    </small>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="password_confirmation">Sahkan Kata Laluan Baharu</label>
                    <input type="password" name="password_confirmation" id="password_confirmation"
                        class="form-control" autocomplete="new-password" required>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    Simpan &amp; Teruskan
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}" class="mt-3 text-center">
                @csrf
                <button type="submit" class="btn btn-sm btn-link">Log Keluar</button>
            </form>

        </div>
    </div>
</body>

</html>
