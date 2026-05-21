<x-filament-widgets::widget>
    <section class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-950/5 dark:border-white/10 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex flex-col gap-3 border-b border-slate-200/80 px-6 py-5 dark:border-white/10 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-1">
                <h3 class="text-base font-semibold text-slate-950 dark:text-white">Acquisitie</h3>
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    {{ $disclaimer }}
                </p>
            </div>
            <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-500/20">
                {{ $disclaimer }}
            </span>
        </div>

        @if (count($rows) === 0)
            <div class="px-6 py-12">
                <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/70 px-6 py-10 text-center dark:border-white/10 dark:bg-white/5">
                    <p class="text-sm font-medium text-slate-700 dark:text-slate-200">Nog geen attributionregistraties beschikbaar.</p>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Acquisitiegegevens verschijnen zodra attributiedata is opgeslagen.</p>
                </div>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto divide-y divide-slate-200 text-sm dark:divide-white/10">
                    <thead class="bg-slate-50/90 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-white/5 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3">Source</th>
                            <th class="px-4 py-3">Medium</th>
                            <th class="px-4 py-3">Campaign</th>
                            <th class="px-4 py-3 text-right">Users / visits</th>
                            <th class="px-4 py-3 text-right">Registraties</th>
                            <th class="px-4 py-3 text-right">Conversieratio</th>
                            <th class="px-4 py-3">Laatste activiteit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                        @foreach ($rows as $row)
                            <tr class="align-top text-slate-700 transition hover:bg-slate-50/70 dark:text-slate-200 dark:hover:bg-white/[0.03]">
                                <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ $row['source'] ?: '—' }}</td>
                                <td class="px-4 py-3">{{ $row['medium'] ?: '—' }}</td>
                                <td class="px-4 py-3">{{ $row['campaign'] ?: '—' }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $row['visits'] === null ? '—' : number_format($row['visits'], 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row['registrations'], 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $row['conversion_rate'] === null ? '—' : number_format($row['conversion_rate'], 1, ',', '.') . '%' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $row['latest_activity'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-filament-widgets::widget>
