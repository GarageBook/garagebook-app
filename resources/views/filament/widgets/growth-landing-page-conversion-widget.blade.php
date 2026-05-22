<x-filament-widgets::widget>
    <section class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-950/5">
        <div class="border-b border-slate-200/80 px-6 py-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex min-w-0 items-start gap-3">
                    <span class="inline-flex shrink-0 rounded-2xl bg-sky-50 p-3 text-sky-700 ring-1 ring-sky-200">
                        <x-filament::icon icon="heroicon-o-arrow-trending-up" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <h3 class="text-base font-semibold text-slate-950">Landingpage conversie</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ $disclaimer }}</p>
                    </div>
                </div>

                <span class="inline-flex items-center self-start rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-600">
                    Alleen betrouwbare koppelingen
                </span>
            </div>
        </div>

        <div class="space-y-4 px-6 py-6">
            @if (count($rows) === 0)
                <div class="flex min-h-[16rem] items-center justify-center rounded-[1.75rem] border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center">
                    <div>
                        <p class="text-sm font-semibold text-slate-700">Nog geen data beschikbaar</p>
                        <p class="mt-2 text-sm text-slate-500">Landingpageconversie wordt zichtbaar zodra landing pages in attribution data zijn opgeslagen.</p>
                    </div>
                </div>
            @else
                <div class="overflow-hidden rounded-[1.75rem] border border-slate-200/80 bg-gradient-to-b from-white to-slate-50/70 shadow-sm">
                    <div class="w-full overflow-x-auto">
                        <table class="min-w-full table-fixed divide-y divide-slate-200 text-sm">
                            <thead class="bg-white text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="w-[32%] px-4 py-3">Landing page</th>
                                    <th class="w-[11%] px-4 py-3 text-right">Visits / users</th>
                                    <th class="w-[11%] px-4 py-3 text-right">Registraties</th>
                                    <th class="w-[11%] px-4 py-3 text-right">Conversieratio</th>
                                    <th class="w-[15%] px-4 py-3">Top source</th>
                                    <th class="w-[20%] px-4 py-3">Laatste registratie</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white text-sm">
                                @foreach (collect($rows)->take(8) as $row)
                                    <tr class="align-top text-slate-700 transition hover:bg-slate-50/80">
                                        <td class="px-4 py-3 font-medium text-slate-900"><span class="block truncate">{{ $row['landing_page'] ?: '—' }}</span></td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ $row['visits'] === null ? '—' : number_format($row['visits'], 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row['registrations'], 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ $row['conversion_rate'] === null ? '—' : number_format($row['conversion_rate'], 1, ',', '.') . '%' }}</td>
                                        <td class="px-4 py-3"><span class="block truncate">{{ $row['top_source'] ?: '—' }}</span></td>
                                        <td class="px-4 py-3"><span class="block truncate">{{ $row['latest_registration'] ?? '—' }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <p class="text-sm text-slate-500">Alleen betrouwbare koppelingen worden getoond.</p>
            @endif
        </div>
    </section>
</x-filament-widgets::widget>
