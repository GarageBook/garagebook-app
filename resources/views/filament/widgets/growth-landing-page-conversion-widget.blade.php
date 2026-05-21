@php
    $visibleRows = collect($rows)->take(8);
@endphp

<x-filament-widgets::widget>
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
        <div class="flex items-start justify-between gap-4 border-b border-gray-100 p-5 dark:border-white/10">
            <div class="flex items-start gap-3">
                <span class="inline-flex rounded-xl bg-cyan-50 p-2 text-cyan-700 dark:bg-cyan-500/10 dark:text-cyan-200">
                    <x-filament::icon icon="heroicon-o-arrow-trending-up" class="h-5 w-5" />
                </span>
                <div class="space-y-1">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Landingpage conversie</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Registraties gekoppeld aan landing pages en betrouwbare brondata.</p>
                </div>
            </div>
            <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600 dark:bg-white/5 dark:text-gray-300">
                Alleen betrouwbare koppelingen
            </span>
        </div>

        <div class="p-5">
            @if ($visibleRows->isEmpty())
                <div class="rounded-2xl border border-dashed border-gray-200 bg-gray-50 px-6 py-10 text-center dark:border-white/10 dark:bg-white/5">
                    <p class="text-sm font-medium text-gray-900 dark:text-white">Nog geen data beschikbaar</p>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Landingpageconversie wordt zichtbaar zodra landing pages in attribution data zijn opgeslagen.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full table-fixed divide-y divide-gray-100 text-sm dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="w-[34%] px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Landing page</th>
                                <th class="w-[12%] px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Visits</th>
                                <th class="w-[12%] px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Registraties</th>
                                <th class="w-[12%] px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Conversie</th>
                                <th class="w-[14%] px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Top source</th>
                                <th class="w-[16%] px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Laatste registratie</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white dark:divide-white/10 dark:bg-transparent">
                            @foreach ($visibleRows as $row)
                                <tr class="hover:bg-gray-50/70 dark:hover:bg-white/[0.03]">
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                        <code class="block max-w-xs truncate rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-700 dark:bg-white/5 dark:text-gray-200" title="{{ $row['landing_page'] ?: '—' }}">{{ $row['landing_page'] ?: '—' }}</code>
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm tabular-nums whitespace-nowrap text-gray-900 dark:text-white">{{ $row['visits'] === null ? '—' : number_format($row['visits'], 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right text-sm tabular-nums whitespace-nowrap text-gray-900 dark:text-white">{{ number_format($row['registrations'], 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right text-sm tabular-nums whitespace-nowrap text-gray-900 dark:text-white">{{ $row['conversion_rate'] === null ? '—' : number_format($row['conversion_rate'], 1, ',', '.') . '%' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                        <div class="max-w-[10rem] truncate" title="{{ $row['top_source'] ?: '—' }}">{{ $row['top_source'] ?: '—' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">{{ $row['latest_registration'] ?? '—' }}</td>
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
