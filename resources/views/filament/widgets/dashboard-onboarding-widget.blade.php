<x-filament::widget>
    <x-filament::card>
        <div class="flex flex-col gap-6">
            <div>
                <h2 class="text-xl font-bold tracking-tight">Klaar om te starten?</h2>
                <p class="text-sm text-gray-500">Voltooi de volgende stappen om het maximale uit GarageBook te halen.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                @foreach($steps as $step)
                    <div class="flex flex-col justify-between p-5 border border-gray-100 rounded-2xl bg-gray-50/50">
                        <div class="mb-4">
                            <div class="flex items-center justify-center w-10 h-10 mb-4 bg-white rounded-lg shadow-sm">
                                <x-filament::icon
                                    :icon="$step['icon']"
                                    class="w-6 h-6 text-gray-700"
                                />
                            </div>
                            <h3 class="font-bold text-gray-900">{{ $step['title'] }}</h3>
                            <p class="mt-1 text-sm text-gray-600">{{ $step['description'] }}</p>
                        </div>

                        <x-filament::button
                            :href="$step['url']"
                            tag="a"
                            :color="($step['primary'] ?? false) ? 'warning' : 'gray'"
                            size="sm"
                            class="w-full shadow-sm"
                            style="{{ ($step['primary'] ?? false) ? 'background-color: #ffd200; color: #000;' : '' }}"
                        >
                            {{ $step['cta'] }}
                        </x-filament::button>
                    </div>
                @endforeach
            </div>
        </div>
    </x-filament::card>
</x-filament::widget>
