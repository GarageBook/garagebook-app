<x-filament-widgets::widget>
    <section class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-950/5">
        <div class="space-y-6 p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-start gap-3">
                    <span class="inline-flex rounded-2xl bg-sky-50 p-3 text-sky-700 ring-1 ring-sky-200">
                        <x-filament::icon icon="heroicon-o-arrow-trending-up" class="h-5 w-5" />
                    </span>
                    <div class="space-y-1">
                        <h3 class="text-base font-semibold text-slate-950">Landingpage conversie</h3>
                        <p class="text-sm text-slate-500">{{ $disclaimer }}</p>
                    </div>
                </div>

                <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600">
                    Alleen betrouwbare koppelingen
                </span>
            </div>

            @if (count($rows) === 0)
                <div class="rounded-[1.75rem] border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center">
                    <p class="text-sm font-semibold text-slate-700">Nog geen data beschikbaar</p>
                    <p class="mt-2 text-sm text-slate-500">Landingpageconversie wordt zichtbaar zodra landing pages in attribution data zijn opgeslagen.</p>
                </div>
            @else
                <div class="overflow-hidden rounded-[1.75rem] border border-slate-200/80 bg-slate-50/70">
                    <div class="overflow-x-auto">
                        <table class="min-w-full table-fixed divide-y divide-slate-200 text-sm">
                            <thead class="bg-white text-left text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                                <tr>
                                    <th class="w-[30%] px-4 py-3">Landing page</th>
                                    <th class="w-[12%] px-4 py-3 text-right">Visits / users</th>
                                    <th class="w-[12%] px-4 py-3 text-right">Registraties</th>
                                    <th class="w-[12%] px-4 py-3 text-right">Conversieratio</th>
                                    <th class="w-[16%] px-4 py-3">Top source</th>
                                    <th class="w-[18%] px-4 py-3">Laatste registratie</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                @foreach (collect($rows)->take(8) as $row)
                                    <tr class="align-top text-slate-700 transition hover:bg-slate-50/80">
                                        <td class="px-4 py-3 font-medium text-slate-900">
                                            <span class="block truncate">{{ $row['landing_page'] ?: '—' }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ $row['visits'] === null ? '—' : number_format($row['visits'], 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row['registrations'], 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ $row['conversion_rate'] === null ? '—' : number_format($row['conversion_rate'], 1, ',', '.') . '%' }}</td>
                                        <td class="px-4 py-3">
                                            <span class="block truncate">{{ $row['top_source'] ?: '—' }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="block truncate">{{ $row['latest_registration'] ?? '—' }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </section>
</x-filament-widgets::widget>
