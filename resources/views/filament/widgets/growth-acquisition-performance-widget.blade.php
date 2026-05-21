@php
    $visibleRows = collect($rows)->take(8);
@endphp

<x-filament-widgets::widget>
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
        <div class="flex items-start justify-between gap-4 border-b border-gray-100 p-5 dark:border-white/10">
            <div class="flex items-start gap-3">
                <span class="inline-flex rounded-xl bg-sky-50 p-2 text-sky-700 dark:bg-sky-500/10 dark:text-sky-200">
                    <x-filament::icon icon="heroicon-o-megaphone" class="h-5 w-5" />
                </span>
                <div class="space-y-1">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Acquisitie</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Bronnen en campagnes op basis van beschikbare attribution data.</p>
                </div>
            </div>
            <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700 dark:bg-amber-500/10 dark:text-amber-200">
                Gebaseerd op beschikbare attribution data
            </span>
        </div>

        <div class="p-5">
            @if ($visibleRows->isEmpty())
                <div class="rounded-2xl border border-dashed border-gray-200 bg-gray-50 px-6 py-10 text-center dark:border-white/10 dark:bg-white/5">
                    <p class="text-sm font-medium text-gray-900 dark:text-white">Nog geen attributionregistraties beschikbaar.</p>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Acquisitiegegevens verschijnen zodra attributiedata is opgeslagen.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full table-fixed divide-y divide-gray-100 text-sm dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="w-[20%] px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Source</th>
                                <th class="w-[16%] px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Medium</th>
                                <th class="w-[20%] px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Campaign</th>
                                <th class="w-[12%] px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Visits</th>
                                <th class="w-[12%] px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Registraties</th>
                                <th class="w-[10%] px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Conversie</th>
                                <th class="w-[10%] px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Laatste activiteit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white dark:divide-white/10 dark:bg-transparent">
                            @foreach ($visibleRows as $row)
                                <tr class="hover:bg-gray-50/70 dark:hover:bg-white/[0.03]">
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                        <div class="min-w-0">
                                            <div class="truncate font-semibold" title="{{ $row['source'] ?: '—' }}">{{ $row['source'] ?: '—' }}</div>
                                            <div class="mt-1 text-xs text-gray-500">{{ $row['medium'] ?: '—' }}</div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $row['medium'] ?: '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                        <div class="max-w-xs truncate" title="{{ $row['campaign'] ?: '—' }}">{{ $row['campaign'] ?: '—' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm tabular-nums whitespace-nowrap text-gray-900 dark:text-white">{{ $row['visits'] === null ? '—' : number_format($row['visits'], 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right text-sm tabular-nums whitespace-nowrap text-gray-900 dark:text-white">{{ number_format($row['registrations'], 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right text-sm tabular-nums whitespace-nowrap text-gray-900 dark:text-white">{{ $row['conversion_rate'] === null ? '—' : number_format($row['conversion_rate'], 1, ',', '.') . '%' }}</td>
                                    <td class="px-4 py-3 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">{{ $row['latest_activity'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Toont top {{ $visibleRows->count() }}.</p>
            @endif
        </div>
    </div>
</x-filament-widgets::widget>
