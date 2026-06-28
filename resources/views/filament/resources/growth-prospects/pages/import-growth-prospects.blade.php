<x-filament-panels::page>
    <div class="space-y-6">
        <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="space-y-4">
                <div>
                    <h2 class="text-base font-semibold text-gray-950">CSV upload</h2>
                    <p class="mt-1 text-sm text-gray-500">Upload een CSV met een headerregel. De eerste 20 regels worden als preview getoond.</p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <label class="block flex-1 text-sm font-medium text-gray-700">
                        CSV-bestand
                        <input type="file" wire:model="csvFile" accept=".csv,text/csv,text/plain" class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                    </label>

                    <x-filament::button type="button" wire:click="uploadCsv" wire:loading.attr="disabled">
                        Preview laden
                    </x-filament::button>
                </div>

                @error('csvFile')
                    <p class="text-sm text-danger-600">{{ $message }}</p>
                @enderror
            </div>
        </section>

        @if ($headers !== [])
            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-gray-950">Kolommapping</h2>
                        <p class="mt-1 text-sm text-gray-500">Koppel CSV-kolommen aan GrowthProspect velden.</p>
                    </div>

                    <x-filament::button type="button" color="gray" wire:click="refreshPreview">
                        Preview vernieuwen
                    </x-filament::button>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach (\App\Services\Growth\GrowthProspectCsvImportService::MAPPING_FIELDS as $field => $label)
                        <label class="block text-sm font-medium text-gray-700">
                            {{ $label }}
                            <select wire:model="mapping.{{ $field }}" class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                <option value="">Niet importeren</option>
                                @foreach ($headers as $header)
                                    <option value="{{ $header }}">{{ $header }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endforeach
                </div>
            </section>

            <section class="grid gap-4 md:grid-cols-3">
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-5">
                    <p class="text-sm font-medium text-emerald-700">Nieuw</p>
                    <p class="mt-1 text-3xl font-semibold text-emerald-900">{{ $summary['new'] }}</p>
                </div>
                <div class="rounded-xl border border-sky-200 bg-sky-50 p-5">
                    <p class="text-sm font-medium text-sky-700">Update</p>
                    <p class="mt-1 text-3xl font-semibold text-sky-900">{{ $summary['update'] }}</p>
                </div>
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-5">
                    <p class="text-sm font-medium text-amber-700">Overgeslagen</p>
                    <p class="mt-1 text-3xl font-semibold text-amber-900">{{ $summary['skipped'] }}</p>
                </div>
            </section>

            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h2 class="text-base font-semibold text-gray-950">Preview</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Regel</th>
                                <th class="px-4 py-3">Actie</th>
                                <th class="px-4 py-3">Naam</th>
                                <th class="px-4 py-3">Website</th>
                                <th class="px-4 py-3">E-mail</th>
                                <th class="px-4 py-3">Partner slug</th>
                                <th class="px-4 py-3">Reden</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($previewRows as $row)
                                <tr>
                                    <td class="px-4 py-3">{{ $row['line'] }}</td>
                                    <td class="px-4 py-3">{{ $row['action'] }}</td>
                                    <td class="px-4 py-3">{{ $row['data']['name'] ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ $row['data']['website'] ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ $row['data']['email'] ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ $row['data']['partner_slug'] ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ $row['reason'] ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">Geen previewregels beschikbaar.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="flex justify-end">
                <x-filament::button type="button" wire:click="import" wire:loading.attr="disabled">
                    Import bevestigen
                </x-filament::button>
            </div>
        @endif

        @if ($importResult)
            <section class="rounded-xl border border-emerald-200 bg-emerald-50 p-5 text-sm text-emerald-900">
                Import afgerond. Nieuw: {{ $importResult['created'] }}. Bijgewerkt: {{ $importResult['updated'] }}. Overgeslagen: {{ $importResult['skipped'] }}.
            </section>
        @endif
    </div>
</x-filament-panels::page>
