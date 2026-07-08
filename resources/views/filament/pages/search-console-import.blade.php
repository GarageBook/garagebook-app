<x-filament-panels::page>
    @php
        $card = 'rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900';
    @endphp

    <div class="space-y-6">
        <section class="{{ $card }}">
            <form wire:submit="import" class="space-y-5">
                <div>
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Bulk CSV import</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Upload meerdere Google Search Console CSV-exportbestanden. GarageBook herkent pages, queries, landen, apparaten, zoekopmaak en datumdiagrammen automatisch.</p>
                </div>

                <div class="grid gap-4 xl:grid-cols-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                        Importdatum
                        <input type="date" wire:model="date" class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" />
                        @error('date')
                            <span class="mt-1 block text-sm text-danger-600">{{ $message }}</span>
                        @enderror
                    </label>

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                        CSV-bestanden
                        <input type="file" wire:model="csvFiles" multiple accept=".csv,text/csv" class="mt-2 block w-full rounded-lg border border-dashed border-gray-300 px-3 py-8 text-sm dark:border-gray-700 dark:bg-gray-950" />
                        <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Maximaal 25 bestanden, 20 MB per bestand.</span>
                        @error('csvFiles')
                            <span class="mt-1 block text-sm text-danger-600">{{ $message }}</span>
                        @enderror
                        @error('csvFiles.*')
                            <span class="mt-1 block text-sm text-danger-600">{{ $message }}</span>
                        @enderror
                    </label>
                </div>

                <details class="rounded-lg border border-gray-200 p-3 text-sm dark:border-gray-800">
                    <summary class="cursor-pointer font-medium text-gray-700 dark:text-gray-200">Legacy losse Pages/Queries upload</summary>
                    <div class="mt-4 grid gap-4 xl:grid-cols-2">
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
                </details>

                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                    <input type="checkbox" wire:model="replace" class="border-gray-300" />
                    Bestaande data voor deze datum vervangen
                </label>

                <div class="flex justify-end">
                    <x-filament::button type="submit" wire:loading.attr="disabled">
                        Importeren
                    </x-filament::button>
                </div>
            </form>
        </section>

        @if ($result)
            <section class="{{ $card }}">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Importresultaat</h2>
                @if (($result['status'] ?? null) !== 'failed')
                    <p class="mt-1 mb-3 text-sm font-medium text-success-700 dark:text-success-400">Import succesvol voltooid.</p>
                @endif
                <dl class="grid gap-3 text-sm md:grid-cols-2 xl:grid-cols-5">
                    <div><dt class="text-gray-500 dark:text-gray-400">Status</dt><dd class="font-medium">{{ $result['status'] }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Bestanden verwerkt</dt><dd class="font-medium">{{ $result['processed_files'] ?? 0 }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Bestanden overgeslagen</dt><dd class="font-medium">{{ $result['skipped_files'] ?? 0 }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Bewust overgeslagen</dt><dd class="font-medium">{{ $result['intentionally_skipped_files'] ?? 0 }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Pagina's</dt><dd class="font-medium">{{ number_format($result['pages'] ?? 0, 0, ',', '.') }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Queries</dt><dd class="font-medium">{{ number_format($result['queries'] ?? 0, 0, ',', '.') }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Landen</dt><dd class="font-medium">{{ number_format($result['countries'] ?? 0, 0, ',', '.') }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Apparaten</dt><dd class="font-medium">{{ number_format($result['devices'] ?? 0, 0, ',', '.') }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Zoekopmaak</dt><dd class="font-medium">{{ number_format($result['search_appearances'] ?? 0, 0, ',', '.') }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Datumregels</dt><dd class="font-medium">{{ number_format($result['date_rows'] ?? 0, 0, ',', '.') }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Importduur</dt><dd class="font-medium">{{ number_format($result['duration_seconds'] ?? 0, 1, ',', '.') }}s</dd></div>
                </dl>

                @if (($result['notices'] ?? []) !== [])
                    <div class="mt-4 rounded-lg border border-info-200 bg-info-50 p-3 text-sm text-info-900">
                        <strong>Info</strong>
                        <ul class="mt-2 list-disc pl-5">
                            @foreach ($result['notices'] as $notice)
                                <li>{{ $notice }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (($result['warnings'] ?? []) !== [])
                    <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                        <strong>Waarschuwingen</strong>
                        <ul class="mt-2 list-disc pl-5">
                            @foreach ($result['warnings'] as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (($result['errors'] ?? []) !== [])
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
            <h2 class="mb-3 text-base font-semibold text-gray-950 dark:text-white">Laatste import sessions</h2>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1100px] text-left text-sm">
                    <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="py-2 pr-4">Datum</th>
                            <th class="py-2 pr-4">Status</th>
                            <th class="py-2 pr-4">Verwerkt</th>
                            <th class="py-2 pr-4">Overgeslagen</th>
                            <th class="py-2 pr-4">Pages</th>
                            <th class="py-2 pr-4">Queries</th>
                            <th class="py-2 pr-4">Countries</th>
                            <th class="py-2 pr-4">Devices</th>
                            <th class="py-2 pr-4">Search appearance</th>
                            <th class="py-2 pr-4">Date rows</th>
                            <th class="py-2 pr-4">Gebruiker</th>
                            <th class="py-2 pr-4">Duur</th>
                            <th class="py-2 pr-4">Info</th>
                            <th class="py-2 pr-4">Warnings</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($history as $row)
                            <tr>
                                <td class="py-3 pr-4">{{ $row['date'] }}</td>
                                <td class="py-3 pr-4">{{ $row['status'] }}</td>
                                <td class="py-3 pr-4">{{ $row['processed_files'] }}</td>
                                <td class="py-3 pr-4">{{ $row['skipped_files'] }}</td>
                                <td class="py-3 pr-4">{{ $row['pages'] }}</td>
                                <td class="py-3 pr-4">{{ $row['queries'] }}</td>
                                <td class="py-3 pr-4">{{ $row['countries'] }}</td>
                                <td class="py-3 pr-4">{{ $row['devices'] }}</td>
                                <td class="py-3 pr-4">{{ $row['search_appearances'] }}</td>
                                <td class="py-3 pr-4">{{ $row['date_rows'] }}</td>
                                <td class="py-3 pr-4">{{ $row['user'] }}</td>
                                <td class="py-3 pr-4">{{ $row['duration'] }}</td>
                                <td class="py-3 pr-4">
                                    @if ($row['notices'] !== [])
                                        <details>
                                            <summary>{{ count($row['notices']) }}</summary>
                                            <ul class="mt-2 list-disc pl-5">
                                                @foreach ($row['notices'] as $notice)
                                                    <li>{{ $notice }}</li>
                                                @endforeach
                                            </ul>
                                        </details>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="py-3 pr-4">
                                    @if ($row['warnings'] !== [])
                                        <details>
                                            <summary>{{ count($row['warnings']) }}</summary>
                                            <ul class="mt-2 list-disc pl-5">
                                                @foreach ($row['warnings'] as $warning)
                                                    <li>{{ $warning }}</li>
                                                @endforeach
                                            </ul>
                                        </details>
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="14" class="py-4 text-gray-500">Nog geen imports gevonden.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
