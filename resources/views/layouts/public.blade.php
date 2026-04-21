<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'GarageBook')
</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="gb-public-body">

    <!-- HEADER -->
    <header class="gb-public-header">
        <div class="gb-public-header__inner">
            <a href="/">
                <img
                    src="{{ asset('images/garagebook-logo-white.png') }}"
                    alt="GarageBook"
                    class="gb-public-header__logo"
                >
            </a>

            <div class="gb-public-header__actions">
                <a href="/admin/login" class="gb-public-header__login">
                    Login
                </a>

                <a href="/admin/register" class="gb-public-header__cta">
                    Registreer gratis
                </a>
            </div>
        </div>
    </header>

    <!-- CONTENT -->
    <div>
        @yield('content')
    </div>

    <!-- FOOTER -->
    @include('partials.footer')

</body>
</html>
