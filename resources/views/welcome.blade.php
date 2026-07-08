<!DOCTYPE html>
<html lang="nl">
<head>
    @include('partials.google-tag')
    @include('partials.analytics-tracking')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digitaal onderhoudsboekje voor auto en motor | GarageBook</title>
    <meta name="description" content="Houd de onderhoudshistorie van je auto of motor digitaal bij met GarageBook. Bewaar beurten, facturen, foto's en kilometerstanden op één centrale plek. Gratis starten.">
    <meta name="robots" content="index,follow">
    <link rel="canonical" href="{{ url('/') }}">
    <meta property="og:locale" content="nl_NL">
    <meta property="og:site_name" content="GarageBook">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Digitaal onderhoudsboekje voor auto en motor | GarageBook">
    <meta property="og:description" content="Houd de onderhoudshistorie van je auto of motor digitaal bij. Bewaar beurten, facturen, foto's en kilometerstanden op één plek met GarageBook.">
    <meta property="og:url" content="{{ url('/') }}">
    <meta name="twitter:card" content="summary_large_image">
    <script type="application/ld+json">
        {!! json_encode([
            '@' . 'context' => 'https://schema.org',
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
                [
                    '@type' => 'SoftwareApplication',
                    'name' => 'GarageBook',
                    'applicationCategory' => 'BusinessApplication',
                    'operatingSystem' => 'Web',
                    'url' => url('/'),
                    'description' => 'Digitaal onderhoudsboek voor motorrijders om onderhoud, reparaties, kilometerstanden, foto’s en facturen overzichtelijk vast te leggen.',
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
            <a href="{{ url('/') }}" aria-label="GarageBook home">
                <img
                    src="{{ asset('images/garagebook-logo-white.png') }}"
                    alt="GarageBook motor onderhoud app logo"
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

                    <a href="/start" class="gb-public-header__cta">
                        Registreer gratis
                    </a>
                @endauth
            </div>
        </div>
    </header>

    <main>
        <!-- HERO -->
        <section class="gb-home-section">
            <div class="gb-section-shell gb-section-grid gb-home-grid">
                <div>
                    <h1 class="gb-home-title">
                        Het digitale onderhoudsboek voor auto en motor
                    </h1>

                    <p class="gb-home-lead">
                        Bouw een complete onderhoudshistorie op van je auto, motor of klassieker. Onderhoud, reparaties, kilometerstanden, foto's en facturen overzichtelijk in één digitale tijdlijn.
                    </p>

                    <div class="gb-home-actions">
                        @auth
                            <a href="/admin" class="gb-button gb-button--primary">
                                Naar dashboard
                            </a>
                        @else
                            <a href="/start" class="gb-button gb-button--primary">
                                Registreer gratis!
                            </a>

                            <a href="/admin/login" class="gb-button gb-button--secondary">
                                Login
                            </a>
                        @endauth
                    </div>
                </div>

                <div>
                    <img
                        src="{{ asset('images/garagebook-sleutelen-motor-garage.webp') }}"
                        alt="Motor in garage met onderhoudswerkzaamheden"
                        class="gb-media-rounded gb-media-elevated"
                        width="1536"
                        height="1024"
                        fetchpriority="high"
                        decoding="async"
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
                            src="{{ asset('images/ta-focando-ljJVG5ItaBI-unsplash.jpg') }}"
                            alt="Digitaal overzicht van onderhoud en kilometerstanden op een motor display"
                            class="gb-media-rounded"
                            width="1024"
                            height="1536"
                            loading="lazy"
                            decoding="async"
                        >
                    </div>

                    <div class="gb-feature-grid">


                        <p class="gb-home-feature-intro">
                            Bouw een complete onderhoudshistorie op die je zelf terugvindt, makkelijker deelt en later ook helpt bij verkoop of taxatie.
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

                <section class="pressMention" aria-label="Gezien op">
                    <div class="pressMentionCard">
                        <span class="pressMentionLabel">GarageBook is op de volgende sites verschenen:</span>
                        <div class="pressMentionLogoGrid">
                            <a class="pressMentionLogoLink pressMentionLogoLink--motornieuws" href="https://www.motornieuws.nl/het-onderhoudsboekje-wordt-misschien-wel-volledig-digitaal-dankzij-garagebook/" target="_blank" rel="noopener noreferrer" aria-label="Lees het artikel over GarageBook op Motornieuws">
                                <span class="pressMentionLogoWrap">
                                    <img src="/images/motornieuws_logo.png" alt="Motornieuws" width="482" height="220" loading="lazy">
                                </span>
                            </a>

                            <a class="pressMentionLogoLink pressMentionLogoLink--motorfreaks" href="https://motorfreaks.nl/artikel/garagebook-digitaal-onderhoudsboekje-voor-zelfsleuteraars/" target="_blank" rel="noopener noreferrer" aria-label="Lees het artikel over GarageBook op Motorfreaks">
                                <span class="pressMentionLogoWrap">
                                    <img src="/images/motorfreaks-logo.webp" alt="Motorfreaks" width="507" height="231" loading="lazy">
                                </span>
                            </a>

                            <a class="pressMentionLogoLink pressMentionLogoLink--motor" href="https://www.motor.nl/nieuws/het-onderhoudsboekje-wordt-misschien-wel-volledig-digitaal-dankzij-garagebook/" target="_blank" rel="noopener noreferrer" aria-label="Lees het artikel over GarageBook op Motor.nl">
                                <span class="pressMentionLogoWrap">
                                    <img src="/images/logo_motor_nl.webp" alt="Motor.nl" width="1200" height="244" loading="lazy">
                                </span>
                            </a>

                            <a class="pressMentionLogoLink pressMentionLogoLink--nieuwsmotor" href="https://nieuwsmotor.nl/nieuws/persberichten/garagebook-onderhoudshistorie-motorfietsen-digitaal-vastleggen/" target="_blank" rel="noopener noreferrer" aria-label="Lees het artikel over GarageBook op Nieuwsmotor.nl">
                                <span class="pressMentionLogoWrap">
                                    <img src="/images/nieuwsmotor-logo.svg" alt="Nieuwsmotor.nl" width="245" height="61" loading="lazy">
                                </span>
                            </a>
                        </div>
                    </div>
                </section>

            </div>
        </section>

        <!-- INTERNAL LINKS -->
        <section class="gb-home-section--compact">
            <div class="gb-section-shell">
                <nav class="gb-topic-links" aria-label="Gerelateerde onderwerpen">
                    <h2 class="gb-topic-links__title">Meer over onderhoudshistorie en digitale onderhoudsboekjes</h2>
                    <div class="gb-topic-links__grid">
                        <a href="/onderhoudshistorie-auto" class="gb-topic-link">
                            <strong>Onderhoudshistorie auto</strong>
                            <span>Alles over het bijhouden en opvragen van de onderhoudshistorie van een auto.</span>
                        </a>
                        <a href="/digitaal-onderhoudsboekje-auto" class="gb-topic-link">
                            <strong>Digitaal onderhoudsboekje auto</strong>
                            <span>Vervang het papieren boekje door een digitale versie voor je auto.</span>
                        </a>
                        <a href="/onderhoudsboekje-motor" class="gb-topic-link">
                            <strong>Onderhoudsboekje motor</strong>
                            <span>Houd het onderhoudsboekje van je motor digitaal bij.</span>
                        </a>
                        <a href="/motor-onderhoud-app" class="gb-topic-link">
                            <strong>Motor onderhoud app</strong>
                            <span>De app voor motorrijders die hun rijder serieus bijhouden.</span>
                        </a>
                        <a href="/onderhoudsboekje-kwijt" class="gb-topic-link">
                            <strong>Onderhoudsboekje kwijt?</strong>
                            <span>Stappen om je onderhoudshistorie te reconstrueren.</span>
                        </a>
                        <a href="/digitaal-onderhoudsboekje" class="gb-topic-link">
                            <strong>Digitaal onderhoudsboekje</strong>
                            <span>Centrale voertuiggeschiedenis voor auto, motor en klassieker.</span>
                        </a>
                    </div>
                </nav>
            </div>
        </section>

        <!-- BOTTOM BANNER -->
        <section>
            <div>
                <img
                    src="{{ asset('images/garagebook-cinematic-story-banner.webp') }}"
                    alt="Cinematische banner van een motor in werkplaatssetting"
                    class="gb-banner-image"
                    width="7262"
                    height="2875"
                    loading="lazy"
                    decoding="async"
                >
            </div>
        </section>
    </main>

    @include('partials.footer')

</body>
</html>
