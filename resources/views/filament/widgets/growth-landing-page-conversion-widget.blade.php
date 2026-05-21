<x-filament-widgets::widget>
    <x-filament::section
        heading="Landingpage conversie"
        :description="$disclaimer"
        icon="heroicon-o-arrow-trending-up"
        compact
    >
        <div class="space-y-4">
            <div class="flex justify-end">
                <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600 dark:bg-white/5 dark:text-slate-300">
                    Alleen betrouwbare koppelingen
                </span>
            </div>

            @if (count($rows) === 0)
                <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/70 px-6 py-10 text-center dark:border-white/10 dark:bg-white/5">
                    <p class="text-sm font-medium text-slate-700 dark:text-slate-200">Nog geen data beschikbaar</p>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Landingpageconversie wordt zichtbaar zodra landing pages in attribution data zijn opgeslagen.</p>
                </div>
            @else
                <div class="overflow-x-auto rounded-2xl ring-1 ring-slate-200 dark:ring-white/10">
                    <table class="min-w-full table-auto divide-y divide-slate-200 text-sm dark:divide-white/10">
                        <thead class="bg-slate-50/90 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-white/5 dark:text-slate-400">
                            <tr>
                                <th class="px-4 py-3">Landing page</th>
                                <th class="px-4 py-3 text-right">Visits / users</th>
                                <th class="px-4 py-3 text-right">Registraties</th>
                                <th class="px-4 py-3 text-right">Conversieratio</th>
                                <th class="px-4 py-3">Top source</th>
                                <th class="px-4 py-3">Laatste registratie</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white dark:divide-white/5 dark:bg-transparent">
                            @foreach ($rows as $row)
                                <tr class="align-top text-slate-700 transition hover:bg-slate-50/70 dark:text-slate-200 dark:hover:bg-white/[0.03]">
                                    <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ $row['landing_page'] ?: '—' }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ $row['visits'] === null ? '—' : number_format($row['visits'], 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row['registrations'], 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ $row['conversion_rate'] === null ? '—' : number_format($row['conversion_rate'], 1, ',', '.') . '%' }}</td>
                                    <td class="px-4 py-3">{{ $row['top_source'] ?: '—' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $row['latest_registration'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
