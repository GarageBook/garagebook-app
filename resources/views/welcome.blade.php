<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="{{ asset('styles.css') }}">

    <link rel="icon" type="image/png" href="favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="favicon.svg" />
    <link rel="shortcut icon" href="favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png" />

    <title>GarageBook - Bouw aan het verhaal van jouw motor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white text-gray-900">

    <header style="background-color:#181818;">
        <div class="max-w-7xl mx-auto px-6 py-5 flex justify-between items-center">
            <div class="text-2xl font-bold tracking-tight">
                <img src="https://garagebook.nl/assets/GarageBookLogo.png" alt="GarageBook motor onderhoud app logo">
            </div>

            <div class="space-x-4">
                <a href="/admin/login" class="text-sm font-medium hover:underline">
                    Login
                </a>

                <a href="/admin/register" class="bg-[#ffd200] hover:bg-[#ffd200] text-black px-4 py-2 rounded-lg text-sm font-medium">
                    Registreer gratis
                </a>
            </div>
        </div>
    </header>

    <section class="max-w-7xl mx-auto px-6 py-20 grid md:grid-cols-2 gap-12 items-center">
        <div>
            <h1 class="text-5xl font-bold leading-tight">
                Bouw aan het verhaal van jouw motor
            </h1>

            <p class="mt-6 text-xl text-gray-600 leading-relaxed">
                Houd onderhoud, upgrades, kilometerstanden en reparaties overzichtelijk bij.
                Alles op één plek voor jouw motorverhaal.
            </p>

            <div class="mt-8 flex gap-4">
                <a href="/admin/register" class="bg-[#ffd200] hover:bg-[#ffd200] text-black px-6 py-3 rounded-xl font-semibold">
                    Start gratis
                </a>

                <a href="/admin/login" class="border border-gray-300 px-6 py-3 rounded-xl font-semibold">
                    Login
                </a>
            </div>
        </div>

        <div class="rounded-3xl shadow-2xl p-8 border border-gray-200 bg-gray-50">
            <div class="space-y-4">
                <div class="p-4 rounded-xl bg-white shadow">
                    Slimme kilometercheck
                </div>
                <div class="p-4 rounded-xl bg-white shadow">
                    Onderhoudstijdlijn
                </div>
                <div class="p-4 rounded-xl bg-white shadow">
                    Facturen & foto's
                </div>
                <div class="p-4 rounded-xl bg-white shadow">
                    Custom mods overzicht
                </div>
            </div>
        </div>
    </section>

    <section class="bg-gray-50 py-20">
        <div class="max-w-7xl mx-auto px-6">
            <h2 class="text-3xl font-bold text-center mb-12">
                Alles voor jouw garage
            </h2>

            <div class="grid md:grid-cols-3 gap-8">
                <div class="bg-white rounded-2xl shadow p-6">
                    <h3 class="font-bold text-lg">Onderhoud</h3>
                    <p class="mt-2 text-gray-600">
                        Leg alle onderhoudsmomenten en reparaties vast.
                    </p>
                </div>

                <div class="bg-white rounded-2xl shadow p-6">
                    <h3 class="font-bold text-lg">Historie</h3>
                    <p class="mt-2 text-gray-600">
                        Altijd inzicht in het complete verhaal van je motor.
                    </p>
                </div>

                <div class="bg-white rounded-2xl shadow p-6">
                    <h3 class="font-bold text-lg">Waarde</h3>
                    <p class="mt-2 text-gray-600">
                        Verhoog de verkoopwaarde met aantoonbare historie.
                    </p>
                </div>
            </div>
        </div>
    </section>

</body>
</html>