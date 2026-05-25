<x-filament::widget>
    @if ($panel)
        <x-filament::card>
            <div class="flex flex-col gap-4 {{ $panel['tone'] === 'subtle' ? 'md:flex-row md:items-center md:justify-between' : '' }}">
                <div class="flex gap-4">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $panel['tone'] === 'subtle' ? 'bg-gray-100' : 'bg-white shadow-sm ring-1 ring-gray-100' }}">
                        <x-filament::icon
                            :icon="$panel['icon']"
                            class="h-6 w-6 text-gray-700"
                        />
                    </div>

                    <div>
                        @if ($state === 'no_vehicles')
                            <p class="text-sm font-medium text-warning-600">Stap 1</p>
                        @endif

                        <h2 class="text-xl font-bold tracking-tight text-gray-900">{{ $panel['title'] }}</h2>
                        <p class="mt-1 text-sm text-gray-600">{{ $panel['description'] }}</p>
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row {{ $panel['tone'] === 'subtle' ? 'md:justify-end' : '' }}">
                    <x-filament::button
                        :href="$panel['primaryCta']['url']"
                        tag="a"
                        :color="$panel['tone'] === 'subtle' ? 'gray' : 'warning'"
                        class="shadow-sm"
                        style="{{ $panel['tone'] === 'subtle' ? '' : 'background-color: #ffd200; color: #000;' }}"
                    >
                        {{ $panel['primaryCta']['label'] }}
                    </x-filament::button>

                    @if (! empty($panel['secondaryCta']))
                        <x-filament::button
                            :href="$panel['secondaryCta']['url']"
                            tag="a"
                            color="gray"
                            class="shadow-sm"
                        >
                            {{ $panel['secondaryCta']['label'] }}
                        </x-filament::button>
                    @endif
                </div>
            </div>
        </x-filament::card>
    @endif
</x-filament::widget>
