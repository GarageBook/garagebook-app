<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GarageBook - Bouw aan het verhaal van jouw motor</title>

    <link rel="stylesheet" href="{{ asset('styles.css') }}">

    <link rel="icon" type="image/png" href="favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="favicon.svg" />
    <link rel="shortcut icon" href="favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body style="margin:0; background:#f5f5f5; font-family:'Zalando Sans', sans-serif; color:#111827;">

    <!-- HEADER -->
    <header style="
        background-color:#181818;
        position:sticky;
        top:0;
        z-index:9999;
        box-shadow:0 2px 12px rgba(0,0,0,0.12);
    ">
        <div style="max-width:1200px; margin:0 auto; padding:24px 40px; display:flex; justify-content:space-between; align-items:center;">
            <div>
                <img
                    src="{{ asset('images/garagebook-logo-white.png') }}"
                    alt="GarageBook motor onderhoud app logo"
                    style="height:42px;"
                >
            </div>

            <div style="display:flex; align-items:center; gap:20px;">
                <a href="/admin/login" style="color:#ffffff; text-decoration:none; font-size:16px;">
                    Login
                </a>

                <a href="/admin/register" style="
                    background:#ffd200;
                    color:#000;
                    padding:14px 24px;
                    border-radius:12px;
                    text-decoration:none;
                    font-weight:700;
                    font-size:16px;
                ">
                    Registreer gratis
                </a>
            </div>
        </div>
    </header>

    <!-- HERO -->
    <section style="padding:90px 0 80px;">
        <div style="
            max-width:1200px;
            margin:0 auto;
            padding:0 40px;
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:80px;
            align-items:center;
        ">
            <div>
                <h1 style="
                    font-size:52px;
                    line-height:60px !important;
                    font-weight:700;
                    margin:0 0 24px;
                    letter-spacing:-1px;
                ">
                    Alle onderhoud van je motor op één plek
                </h1>

                <p style="
                    font-size:26px;
                    color:#4b5563;
                    margin:0 0 36px;
                ">
                    Van onderhoud en reparaties tot upgrades en foto's: bouw het volledige verhaal van je motor digitaal op.
                </p>

                <div style="display:flex; gap:16px;">
                    <a href="/admin/register" style="
                        background:#ffd200;
                        color:#000;
                        padding:16px 28px;
                        border-radius:14px;
                        text-decoration:none;
                        font-weight:700;
                        font-size:18px;
                    ">
                        Registreer gratis!
                    </a>

                    <a href="/admin/login" style="
                        background:#ffffff;
                        color:#111827;
                        border:1px solid #d1d5db;
                        padding:16px 28px;
                        border-radius:14px;
                        text-decoration:none;
                        font-weight:700;
                        font-size:18px;
                    ">
                        Login
                    </a>
                </div>
            </div>

            <div>
                <img
                    src="{{ asset('images/garagebook-sleutelen-motor-garage.png') }}"
                    alt="Motor in garage"
                    style="
                        width:100%;
                        border-radius:18px;
                        display:block;
                        box-shadow:0 10px 30px rgba(0,0,0,0.08);
                    "
                >
            </div>
        </div>
    </section>

    <!-- MIDDLE SECTION -->
    <section style="padding:40px 0 80px;">
        <div style="max-width:1200px; margin:0 auto; padding:0 40px;">
            <div style="
                display:grid;
                grid-template-columns:1fr 1fr;
                gap:50px;
                align-items:center;
            ">
                <div>
                    <img
                        src="{{ asset('images/garagebook-motor-display.png') }}"
                        alt="Kilometer dashboard motor"
                        style="
                            width:100%;
                            border-radius:18px;
                            display:block;
                        "
                    >
                </div>

                <div style="display:flex; flex-direction:column; gap:18px;">

                    <p style=" color:#4b5563; font-size:24px; margin:0 0 70px 30px; width:50%;">
                        Houd onderhoud, reparaties en upgrades overzichtelijk bij en zie in één oogopslag wat er aan je motor is gedaan.
                    </p>

                    <div style="background:#fff; padding:28px; border-radius:16px; box-shadow:0 2px 8px rgba(0,0,0,0.05);">
                        <strong>Slimme kilometercheck</strong><br>
                        Houd kilometerstanden bij en zie wanneer onderhoud nodig is.
                    </div>

                    <div style="background:#fff; padding:28px; border-radius:16px; box-shadow:0 2px 8px rgba(0,0,0,0.05);">
                        <strong>Onderhoudstijdlijn</strong><br>
                        Alle onderhoudsacties netjes chronologisch op één plek.
                    </div>

                    <div style="background:#fff; padding:28px; border-radius:16px; box-shadow:0 2px 8px rgba(0,0,0,0.05);">
                        <strong>Alles gedocumenteerd</strong><br>
                        Van oliebeurt tot custom mods: alles wordt opgeslagen.
                    </div>

                    <div style="background:#fff; padding:28px; border-radius:16px; box-shadow:0 2px 8px rgba(0,0,0,0.05);">
                        <strong>Facturen & foto's</strong><br>
                        Upload foto's van werkplaatsfacturen en bonnetjes.
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- BOTTOM BANNER -->
    <section style="padding:0;">
        <div style="position:relative;">
            <img
                src="{{ asset('images/garagebook-cinematic-story-banner.jpg') }}"
                alt="Motor garage banner"
                style="
                    width:100%;
                    display:block;
                "
            >
        </div>
    </section>

    <!-- FOOTER -->
    <footer style="
        background:#fff;
        padding:40px 0 60px;
    ">
        <div style="max-width:1200px; margin:0 auto; padding:0 40px;">
            <img
                src="{{ asset('images/garagebook-logo.png') }}"
                alt="GarageBook"
                style="height:42px; margin-bottom:16px;"
            >

            <p style="color:#6b7280; margin:0;">
                Alles over je voertuig. Op één plek.
            </p>
        </div>
    </footer>

</body>
</html>