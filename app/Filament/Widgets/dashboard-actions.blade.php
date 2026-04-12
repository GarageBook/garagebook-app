<x-filament-widgets::widget>
    <x-filament::section>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <a
                href="/admin/vehicles"
                class="rounded-2xl border p-6 hover:shadow-lg transition"
            >
                <h2 class="text-lg font-bold">Mijn voertuigen</h2>
                <p class="text-sm text-gray-500">
                    Bekijk en beheer al je voertuigen
                </p>
            </a>

            <a
                href="/admin/maintenance-logs/create"
                class="rounded-2xl border p-6 hover:shadow-lg transition"
            >
                <h2 class="text-lg font-bold">Onderhoud toevoegen</h2>
                <p class="text-sm text-gray-500">
                    Voeg direct een nieuwe onderhoudsregel toe
                </p>
            </a>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>