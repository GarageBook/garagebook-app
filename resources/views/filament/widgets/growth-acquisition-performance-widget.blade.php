<x-filament-widgets::widget>
    <section class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-950/5">
        <div class="border-b border-slate-200/80 px-6 py-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-start gap-3">
                    <span class="inline-flex rounded-2xl bg-amber-50 p-3 text-amber-700 ring-1 ring-amber-200">
                        <x-filament::icon icon="heroicon-o-megaphone" class="h-5 w-5" />
                    </span>
                    <div>
                        <h3 class="text-base font-semibold text-slate-950">Acquisitie</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ $disclaimer }}</p>
                    </div>
                </div>

                <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700">
                    {{ $disclaimer }}
                </span>
            </div>
        </div>

        <div class="px-6 py-6">
            @if (count($rows) === 0)
                <div class="rounded-[1.75rem] border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center">
                    <p class="text-sm font-semibold text-slate-700">Nog geen attributionregistraties beschikbaar.</p>
                    <p class="mt-2 text-sm text-slate-500">Acquisitiegegevens verschijnen zodra attributiedata is opgeslagen.</p>
                </div>
            @else
                <div class="overflow-hidden rounded-[1.75rem] border border-slate-200/80 bg-gradient-to-b from-white to-slate-50/70 shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="min-w-full table-fixed divide-y divide-slate-200 text-sm">
                            <thead class="bg-white text-left text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                                <tr>
                                    <th class="w-[14%] px-4 py-3">Source</th>
                                    <th class="w-[14%] px-4 py-3">Medium</th>
                                    <th class="w-[22%] px-4 py-3">Campaign</th>
                                    <th class="w-[12%] px-4 py-3 text-right">Users / visits</th>
                                    <th class="w-[12%] px-4 py-3 text-right">Registraties</th>
                                    <th class="w-[12%] px-4 py-3 text-right">Conversieratio</th>
                                    <th class="w-[14%] px-4 py-3">Laatste activiteit</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                @foreach (collect($rows)->take(8) as $row)
                                    <tr class="align-top text-slate-700 transition hover:bg-slate-50/80">
                                        <td class="px-4 py-3 font-medium text-slate-900"><span class="block truncate">{{ $row['source'] ?: '—' }}</span></td>
                                        <td class="px-4 py-3"><span class="block truncate">{{ $row['medium'] ?: '—' }}</span></td>
                                        <td class="px-4 py-3"><span class="block truncate">{{ $row['campaign'] ?: '—' }}</span></td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ $row['visits'] === null ? '—' : number_format($row['visits'], 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row['registrations'], 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ $row['conversion_rate'] === null ? '—' : number_format($row['conversion_rate'], 1, ',', '.') . '%' }}</td>
                                        <td class="px-4 py-3"><span class="block truncate">{{ $row['latest_activity'] ?? '—' }}</span></td>
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
