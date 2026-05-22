<x-filament-widgets::widget>
    <section class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-950/5">
        <div class="border-b border-slate-200/80 px-6 py-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-start gap-3">
                    <span class="inline-flex rounded-2xl bg-slate-100 p-3 text-slate-700">
                        <x-filament::icon icon="heroicon-o-users" class="h-5 w-5" />
                    </span>
                    <div>
                        <h3 class="text-base font-semibold text-slate-950">Partner performance</h3>
                        <p class="mt-1 text-sm text-slate-500">Partner- en PR-bronnen op basis van lokaal opgeslagen attribution data en registration sources.</p>
                    </div>
                </div>

                <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-600">
                    PR / partners
                </span>
            </div>
        </div>

        <div class="px-6 py-6">
            @if (count($rows) === 0)
                <div class="rounded-[1.75rem] border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center">
                    <p class="text-sm font-semibold text-slate-700">Nog geen data beschikbaar</p>
                    <p class="mt-2 text-sm text-slate-500">Partnerperformance wordt zichtbaar zodra registraties aan bekende bronnen gekoppeld zijn.</p>
                </div>
            @else
                <div class="overflow-hidden rounded-[1.75rem] border border-slate-200/80 bg-gradient-to-b from-white to-slate-50/70 shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="min-w-full table-fixed divide-y divide-slate-200 text-sm">
                            <thead class="bg-white text-left text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                                <tr>
                                    <th class="w-[24%] px-4 py-3">Partner / source</th>
                                    <th class="w-[14%] px-4 py-3 text-right">Clicks / bezoeken</th>
                                    <th class="w-[12%] px-4 py-3 text-right">Registraties</th>
                                    <th class="w-[12%] px-4 py-3 text-right">Conversieratio</th>
                                    <th class="w-[16%] px-4 py-3">Laatste registratie</th>
                                    <th class="w-[22%] px-4 py-3">Opmerking / status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                @foreach (collect($rows)->take(8) as $row)
                                    <tr class="align-top text-slate-700 transition hover:bg-slate-50/80">
                                        <td class="px-4 py-3 font-medium text-slate-900"><span class="block truncate">{{ $row['partner'] ?: '—' }}</span></td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ $row['visits'] === null ? '—' : number_format($row['visits'], 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row['registrations'], 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ $row['conversion_rate'] === null ? '—' : number_format($row['conversion_rate'], 1, ',', '.') . '%' }}</td>
                                        <td class="px-4 py-3"><span class="block truncate">{{ $row['latest_registration'] ?? '—' }}</span></td>
                                        <td class="px-4 py-3"><span class="block truncate">{{ $row['status'] ?: '—' }}</span></td>
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
