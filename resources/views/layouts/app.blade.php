<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'Sistem Penilaian Risiko Aset')</title>

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>

<body>

    @include('layouts.sidebar')

    <div class="main-content">

        @include('layouts.header')

        <div class="content-wrapper">
            @if (session('success'))
                <div class="alert alert-success">

                    {{ session('success') }}

                </div>
            @endif
            @yield('content')
        </div>

    </div>

</body>

</html>
