<x-filament-panels::page>
    @php
        $card = 'rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900';
    @endphp

    <div class="space-y-6">
        <section class="{{ $card }}">
            <form wire:submit="import" class="space-y-5">
                <div>
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">CSV import</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Upload de Pages- en Queries-export uit Google Search Console.</p>
                </div>

                <div class="grid gap-4 xl:grid-cols-3">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                        Importdatum
                        <input type="date" wire:model="date" class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" />
                        @error('date')
                            <span class="mt-1 block text-sm text-danger-600">{{ $message }}</span>
                        @enderror
                    </label>

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                        Pages CSV
                        <input type="file" wire:model="pagesCsv" accept=".csv,text/csv" class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" />
                        @error('pagesCsv')
                            <span class="mt-1 block text-sm text-danger-600">{{ $message }}</span>
                        @enderror
                    </label>

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                        Queries CSV
                        <input type="file" wire:model="queriesCsv" accept=".csv,text/csv" class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" />
                        @error('queriesCsv')
                            <span class="mt-1 block text-sm text-danger-600">{{ $message }}</span>
                        @enderror
                    </label>
                </div>

                <fieldset class="space-y-2">
                    <legend class="text-sm font-medium text-gray-700 dark:text-gray-200">Overschrijven?</legend>
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                        <input type="radio" wire:model="overwrite" value="no" class="border-gray-300" />
                        Nee
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                        <input type="radio" wire:model="overwrite" value="yes" class="border-gray-300" />
                        Ja, bestaande data vervangen
                    </label>
                    @error('overwrite')
                        <span class="block text-sm text-danger-600">{{ $message }}</span>
                    @enderror
                </fieldset>

                <div class="flex justify-end">
                    <x-filament::button type="submit" wire:loading.attr="disabled">
                        Importeren
                    </x-filament::button>
                </div>
            </form>
        </section>

        @if ($result)
            <section class="{{ $card }}">
                <h2 class="mb-3 text-base font-semibold text-gray-950 dark:text-white">Importresultaat</h2>
                <dl class="grid gap-3 text-sm md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Status</dt>
                        <dd class="font-medium">{{ $result['status'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Pagina's</dt>
                        <dd class="font-medium">{{ number_format($result['pages'], 0, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Queries</dt>
                        <dd class="font-medium">{{ number_format($result['queries'], 0, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Importduur</dt>
                        <dd class="font-medium">{{ number_format($result['duration_seconds'], 1, ',', '.') }}s</dd>
                    </div>
                </dl>

                @if ($result['warnings'] !== [])
                    <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                        <strong>Waarschuwingen</strong>
                        <ul class="mt-2 list-disc pl-5">
                            @foreach ($result['warnings'] as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($result['errors'] !== [])
                    <div class="mt-4 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-900">
                        <strong>Fouten</strong>
                        <ul class="mt-2 list-disc pl-5">
                            @foreach ($result['errors'] as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>
        @endif

        <section class="{{ $card }}">
            <h2 class="mb-3 text-base font-semibold text-gray-950 dark:text-white">Laatste imports</h2>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="py-2 pr-4">Datum</th>
                            <th class="py-2 pr-4">Pagina's</th>
                            <th class="py-2 pr-4">Queries</th>
                            <th class="py-2 pr-4">Gebruiker</th>
                            <th class="py-2 pr-4">Importtijd</th>
                            <th class="py-2 pr-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($history as $row)
                            <tr>
                                <td class="py-3 pr-4">{{ $row['date'] }}</td>
                                <td class="py-3 pr-4">{{ number_format($row['pages'], 0, ',', '.') }}</td>
                                <td class="py-3 pr-4">{{ number_format($row['queries'], 0, ',', '.') }}</td>
                                <td class="py-3 pr-4">{{ $row['user'] }}</td>
                                <td class="py-3 pr-4">{{ $row['duration'] }}</td>
                                <td class="py-3 pr-4">{{ $row['status'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-4 text-gray-500">Nog geen imports gevonden.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
