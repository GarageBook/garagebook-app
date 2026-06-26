<x-filament-widgets::widget>
    <section class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-950/5">
        <div class="border-b border-slate-200/80 px-6 py-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex min-w-0 items-start gap-3">
                    <span class="inline-flex shrink-0 rounded-2xl bg-emerald-50 p-3 text-emerald-700 ring-1 ring-emerald-200">
                        <x-filament::icon icon="heroicon-o-megaphone" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <h3 class="text-base font-semibold text-slate-950">Campaign performance</h3>
                        <p class="mt-1 text-sm text-slate-500">Growth-campagnes op basis van bestaande campaign attribution.</p>
                    </div>
                </div>

                <span class="inline-flex items-center self-start rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700">
                    Campaigns
                </span>
            </div>
        </div>

        <div class="px-6 py-6">
            @if (count($rows) === 0)
                <div class="flex min-h-[16rem] items-center justify-center rounded-[1.75rem] border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center">
                    <div>
                        <p class="text-sm font-semibold text-slate-700">Nog geen growth-campagnes beschikbaar</p>
                        <p class="mt-2 text-sm text-slate-500">Campaign performance wordt zichtbaar zodra campagnes in het register staan.</p>
                    </div>
                </div>
            @else
                <div class="overflow-hidden rounded-[1.75rem] border border-slate-200/80 bg-gradient-to-b from-white to-slate-50/70 shadow-sm">
                    <div class="w-full overflow-x-auto">
                        <table class="min-w-full table-fixed divide-y divide-slate-200 text-sm">
                            <thead class="bg-white text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="w-[20%] px-4 py-3">Campaign</th>
                                    <th class="w-[10%] px-4 py-3">Status</th>
                                    <th class="w-[12%] px-4 py-3 text-right">Registraties</th>
                                    <th class="w-[12%] px-4 py-3 text-right">≥1 voertuig</th>
                                    <th class="w-[12%] px-4 py-3 text-right">≥1 log</th>
                                    <th class="w-[12%] px-4 py-3 text-right">Activatie</th>
                                    <th class="w-[12%] px-4 py-3 text-right">Log-activatie</th>
                                    <th class="w-[10%] px-4 py-3">Laatste registratie</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white text-sm">
                                @foreach (collect($rows)->take(10) as $row)
                                    <tr class="align-top text-slate-700 transition hover:bg-slate-50/80">
                                        <td class="px-4 py-3 font-medium text-slate-900">
                                            <span class="block truncate">{{ $row['name'] }}</span>
                                            <span class="block truncate text-xs font-normal text-slate-500">{{ $row['slug'] }}</span>
                                        </td>
                                        <td class="px-4 py-3"><span class="block truncate">{{ $row['status'] }}</span></td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row['registrations'], 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row['users_with_vehicle'], 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row['users_with_maintenance_log'], 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row['activation_percentage'], 1, ',', '.') }}%</td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row['maintenance_activation_percentage'], 1, ',', '.') }}%</td>
                                        <td class="px-4 py-3"><span class="block truncate">{{ $row['latest_registration'] }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <p class="mt-4 text-xs text-slate-500">{{ $disclaimer }}</p>
            @endif
        </div>
    </section>
</x-filament-widgets::widget>