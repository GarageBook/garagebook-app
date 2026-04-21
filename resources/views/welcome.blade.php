<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GarageBook - Bouw aan het verhaal van jouw motor</title>

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
            <div>
                <img
                    src="{{ asset('images/garagebook-logo-white.png') }}"
                    alt="GarageBook motor onderhoud app logo"
                    class="gb-public-header__logo"
                >
            </div>

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

    @include('partials.footer')

</body>
</html>
