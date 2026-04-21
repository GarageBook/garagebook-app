<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GarageBook - Bouw aan het verhaal van jouw motor</title>
    <meta name="description" content="GarageBook helpt motorrijders om onderhoud, reparaties, upgrades en kilometerstanden overzichtelijk vast te leggen op één plek.">
    <meta name="robots" content="index,follow">
    <link rel="canonical" href="{{ url('/') }}">
    <meta property="og:locale" content="nl_NL">
    <meta property="og:site_name" content="GarageBook">
    <meta property="og:type" content="website">
    <meta property="og:title" content="GarageBook - Bouw aan het verhaal van jouw motor">
    <meta property="og:description" content="GarageBook helpt motorrijders om onderhoud, reparaties, upgrades en kilometerstanden overzichtelijk vast te leggen op één plek.">
    <meta property="og:url" content="{{ url('/') }}">
    <meta name="twitter:card" content="summary_large_image">
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

    <link rel="icon" type="image/png" href="favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="favicon.svg" />
    <link rel="shortcut icon" href="favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
</head>
<body class="gb-public-body">

    <!-- HEADER -->
    <header class="gb-public-header">
        <div class="gb-public-header__inner">
            <a href="/" aria-label="GarageBook home">
                <img
                    src="{{ asset('images/garagebook-logo-white.png') }}"
                    alt="GarageBook motor onderhoud app logo"
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

    <main>
        <!-- HERO -->
        <section class="gb-home-section">
            <div class="gb-section-shell gb-section-grid gb-home-grid">
                <div>
                    <h1 class="gb-home-title">
                        Alle onderhoud van je motor op één plek
                    </h1>

                    <p class="gb-home-lead">
                        Van onderhoud en reparaties tot upgrades en foto's: bouw het volledige verhaal van je motor digitaal op.
                    </p>

                    <div class="gb-home-actions">
                        <a href="/admin/register" class="gb-button gb-button--primary">
                            Registreer gratis!
                        </a>

                        <a href="/admin/login" class="gb-button gb-button--secondary">
                            Login
                        </a>
                    </div>
                </div>

                <div>
                    <img
                        src="{{ asset('images/garagebook-sleutelen-motor-garage.png') }}"
                        alt="Motor in garage"
                        class="gb-media-rounded gb-media-elevated"
                    >
                </div>
            </div>
        </section>

        <!-- MIDDLE SECTION -->
        <section class="gb-home-section--compact">
            <div class="gb-section-shell">
                <div class="gb-section-grid gb-home-grid--features">
                    <div>
                        <img
                            src="{{ asset('images/garagebook-motor-display.png') }}"
                            alt="Kilometer dashboard motor"
                            class="gb-media-rounded"
                        >
                    </div>

                    <div class="gb-feature-grid">

                        <p class="gb-home-feature-intro">
                            Houd onderhoud, reparaties en upgrades overzichtelijk bij en zie in één oogopslag wat er aan je motor is gedaan.
                        </p>

                        <div class="gb-feature-card">
                            <strong>Slimme kilometercheck</strong><br>
                            Houd kilometerstanden bij en zie wanneer onderhoud nodig is.
                        </div>

                        <div class="gb-feature-card">
                            <strong>Onderhoudstijdlijn</strong><br>
                            Alle onderhoudsacties netjes chronologisch op één plek.
                        </div>

                        <div class="gb-feature-card">
                            <strong>Alles gedocumenteerd</strong><br>
                            Van oliebeurt tot custom mods: alles wordt opgeslagen.
                        </div>

                        <div class="gb-feature-card">
                            <strong>Facturen & foto's</strong><br>
                            Upload foto's van werkplaatsfacturen en bonnetjes.
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- BOTTOM BANNER -->
        <section>
            <div>
                <img
                    src="{{ asset('images/garagebook-cinematic-story-banner.jpg') }}"
                    alt="Motor garage banner"
                    class="gb-banner-image"
                >
            </div>
        </section>
    </main>

    @include('partials.footer')

</body>
</html>
