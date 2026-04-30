<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'GarageBook')</title>
    <meta name="description" content="@yield('meta_description', 'GarageBook helpt motorrijders om onderhoud, historie, upgrades en belangrijke momenten van hun motor overzichtelijk vast te leggen.')">
    <meta name="robots" content="@yield('meta_robots', 'index,follow')">
    <link rel="canonical" href="@yield('canonical_url', url()->current())">
    <meta property="og:locale" content="nl_NL">
    <meta property="og:site_name" content="GarageBook">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:title" content="@yield('og_title', trim($__env->yieldContent('title', 'GarageBook')))">
    <meta property="og:description" content="@yield('og_description', trim($__env->yieldContent('meta_description', 'GarageBook helpt motorrijders om onderhoud, historie, upgrades en belangrijke momenten van hun motor overzichtelijk vast te leggen.')))">
    <meta property="og:url" content="@yield('canonical_url', url()->current())">
    <meta name="twitter:card" content="summary_large_image">
    @hasSection('structured_data')
        @yield('structured_data')
    @else
        <script type="application/ld+json">
            {!! json_encode([
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'Organization',
                        'name' => 'GarageBook',
                        'url' => url('/'),
                        'logo' => asset('images/garagebook-logo.png'),
                        'sameAs' => [
                            'https://www.instagram.com/garagebook.global',
                            'https://linkedin.com/company/thegaragebook/',
                            'https://www.facebook.com/profile.php?id=61584164445375',
                        ],
                    ],
                    [
                        '@type' => 'WebSite',
                        'name' => 'GarageBook',
                        'url' => url('/'),
                        'inLanguage' => 'nl-NL',
                    ],
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
        </script>
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="gb-public-body">

    <!-- HEADER -->
    <header class="gb-public-header">
        <div class="gb-public-header__inner">
            <a href="{{ url('/') }}">
                <img
                    src="{{ asset('images/garagebook-logo-white.png') }}"
                    alt="GarageBook"
                    class="gb-public-header__logo"
                >
            </a>

            <div class="gb-public-header__actions">
                @auth
                    <a href="/admin" class="gb-public-header__login">
                        Dashboard
                    </a>
                @else
                    <a href="/admin/login" class="gb-public-header__login">
                        Login
                    </a>

                    <a href="/admin/register" class="gb-public-header__cta">
                        Registreer gratis
                    </a>
                @endauth
            </div>
        </div>
    </header>

    <!-- CONTENT -->
    <main>
        @yield('content')
    </main>

    <!-- FOOTER -->
    @include('partials.footer')

</body>
</html>
