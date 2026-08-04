<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Masuk - Sistem Penilaian Risiko Aset</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    <style>
        .login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            width: 100%;
            max-width: 400px;
        }
    </style>
</head>

<body>
    <div class="login-wrapper">
        <div class="login-card report-card">
            <h4 class="section-title mb-4">Log Masuk</h4>

            @if ($errors->any())
                <div class="text-danger mb-3">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.attempt') }}">
                @csrf

                <div class="mb-3">
                    <label class="form-label">Nama Pengguna</label>
                    <input type="text" name="username" class="form-control" value="{{ old('username') }}" required
                        autofocus>
                </div>

                <div class="mb-3">
                    <label class="form-label">Kata Laluan</label>
                    <input type="password" name="password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    Log Masuk
                </button>
            </form>
        </div>
    </div>
</body>

</html>
