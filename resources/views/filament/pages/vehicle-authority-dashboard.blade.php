<x-filament-panels::page>
    @php
        $card = 'rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900';
        $total = $stats['total'] ?? 0;
        $indexable = $stats['indexable'] ?? 0;
        $hidden = $stats['hidden'] ?? 0;
        $noPublic = $stats['no_public_vehicles'] ?? 0;
    @endphp

    <div class="space-y-8">

        {{-- Overview stats --}}
        <section>
            <h3 class="mb-3 text-lg font-semibold">Overzicht</h3>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                @foreach([
                    'Totaal modellen' => $total,
                    'Indexeerbaar' => $indexable,
                    'Verborgen' => $hidden,
                    'Zonder publieke voertuigen' => $noPublic,
                ] as $label => $value)
                    <div class="{{ $card }}">
                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</div>
                        <div class="mt-1 text-2xl font-semibold">{{ $value }}</div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Filters (URL-based) --}}
        <section>
            <h3 class="mb-3 text-lg font-semibold">Filteren</h3>
            <form method="GET" action="" class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">Merk</label>
                    <input
                        type="text"
                        name="brand"
                        value="{{ $filterBrand }}"
                        placeholder="bijv. Yamaha"
                        class="rounded border border-gray-300 px-3 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">Min. publieke voertuigen</label>
                    <input
                        type="number"
                        name="min_vehicles"
                        value="{{ $filterMinPublicVehicles }}"
                        min="0"
                        class="w-24 rounded border border-gray-300 px-3 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                </div>
                <button type="submit" class="rounded bg-gray-800 px-4 py-1.5 text-sm text-white dark:bg-gray-700">
                    Toepassen
                </button>
                <a href="?" class="rounded border border-gray-300 px-4 py-1.5 text-sm dark:border-gray-700">
                    Reset
                </a>
            </form>
        </section>

        {{-- Filtered models table --}}
        <section>
            <h3 class="mb-3 text-lg font-semibold">
                Indexeerbare modellen
                @if($filterBrand || $filterMinPublicVehicles > 0)
                    <span class="ml-2 text-sm font-normal text-gray-500">(gefilterd)</span>
                @endif
            </h3>
            @if($filteredModels->isEmpty())
                <p class="text-sm text-gray-500">Geen resultaten. Voer eerst <code>php artisan garagebook:vehicle-authority:sync</code> uit.</p>
            @else
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800">
                    <table class="w-full text-sm">
                        <thead class="border-b border-gray-200 bg-gray-50 text-left dark:border-gray-800 dark:bg-gray-900">
                            <tr>
                                <th class="px-4 py-3 font-semibold">Merk</th>
                                <th class="px-4 py-3 font-semibold">Model</th>
                                <th class="px-4 py-3 font-semibold">Categorie</th>
                                <th class="px-4 py-3 text-right font-semibold">Voertuigen</th>
                                <th class="px-4 py-3 text-right font-semibold">Publiek</th>
                                <th class="px-4 py-3 font-semibold">Slug</th>
                                <th class="px-4 py-3 font-semibold">Eerst gezien</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($filteredModels as $model)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/50">
                                    <td class="px-4 py-2.5">{{ $model->brand }}</td>
                                    <td class="px-4 py-2.5 font-medium">{{ $model->model }}</td>
                                    <td class="px-4 py-2.5 text-gray-500">{{ $model->category ?? '–' }}</td>
                                    <td class="px-4 py-2.5 text-right">{{ $model->vehicle_count }}</td>
                                    <td class="px-4 py-2.5 text-right font-semibold text-green-700 dark:text-green-400">{{ $model->public_vehicle_count }}</td>
                                    <td class="px-4 py-2.5">
                                        <a href="{{ url('/onderhoud/' . $model->slug) }}" target="_blank" class="text-blue-600 hover:underline dark:text-blue-400">
                                            /onderhoud/{{ $model->slug }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-2.5 text-gray-500">{{ $model->first_seen_at?->format('d-m-Y') ?? '–' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <div class="grid gap-6 xl:grid-cols-2">

            {{-- Top 20 --}}
            <section>
                <h3 class="mb-3 text-lg font-semibold">Top 20 populairste modellen</h3>
                @if($topModels->isEmpty())
                    <p class="text-sm text-gray-500">Geen data beschikbaar.</p>
                @else
                    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800">
                        <table class="w-full text-sm">
                            <thead class="border-b border-gray-200 bg-gray-50 text-left dark:border-gray-800 dark:bg-gray-900">
                                <tr>
                                    <th class="px-4 py-2.5 font-semibold">#</th>
                                    <th class="px-4 py-2.5 font-semibold">Model</th>
                                    <th class="px-4 py-2.5 text-right font-semibold">Publiek</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach($topModels as $i => $model)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/50">
                                        <td class="px-4 py-2 text-gray-400">{{ $i + 1 }}</td>
                                        <td class="px-4 py-2">
                                            <a href="{{ url('/onderhoud/' . $model->slug) }}" target="_blank" class="hover:underline">
                                                {{ $model->brand }} {{ $model->model }}
                                            </a>
                                        </td>
                                        <td class="px-4 py-2 text-right font-semibold">{{ $model->public_vehicle_count }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            {{-- Newest models --}}
            <section>
                <h3 class="mb-3 text-lg font-semibold">Nieuwste modellen</h3>
                @if($newestModels->isEmpty())
                    <p class="text-sm text-gray-500">Geen data beschikbaar.</p>
                @else
                    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800">
                        <table class="w-full text-sm">
                            <thead class="border-b border-gray-200 bg-gray-50 text-left dark:border-gray-800 dark:bg-gray-900">
                                <tr>
                                    <th class="px-4 py-2.5 font-semibold">Model</th>
                                    <th class="px-4 py-2.5 font-semibold">Eerste keer</th>
                                    <th class="px-4 py-2.5 text-right font-semibold">Publiek</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach($newestModels as $model)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/50">
                                        <td class="px-4 py-2">
                                            <a href="{{ url('/onderhoud/' . $model->slug) }}" target="_blank" class="hover:underline">
                                                {{ $model->brand }} {{ $model->model }}
                                            </a>
                                        </td>
                                        <td class="px-4 py-2 text-gray-500">{{ $model->first_seen_at?->format('d-m-Y') ?? '–' }}</td>
                                        <td class="px-4 py-2 text-right font-semibold">{{ $model->public_vehicle_count }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

        </div>

    </div>
</x-filament-panels::page>
