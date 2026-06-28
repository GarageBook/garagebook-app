<x-filament-widgets::widget>
    <section class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-950/5">
        <div class="border-b border-slate-200/80 px-6 py-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex min-w-0 items-start gap-3">
                    <span class="inline-flex shrink-0 rounded-2xl bg-amber-50 p-3 text-amber-700 ring-1 ring-amber-200">
                        <x-filament::icon icon="heroicon-o-calendar-days" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <h3 class="text-base font-semibold text-slate-950">Prospect follow-up</h3>
                        <p class="mt-1 text-sm text-slate-500">Concrete Growth prospects die vandaag of eerder opvolging nodig hebben.</p>
                    </div>
                </div>

                <span class="inline-flex items-center self-start rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700">
                    Top 10
                </span>
            </div>
        </div>

        <div class="space-y-6 px-6 py-6">
            <div class="grid gap-3 md:grid-cols-3">
                <div class="rounded-[1.25rem] border border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase text-slate-500">Vandaag</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-950 tabular-nums">{{ number_format($today_count, 0, ',', '.') }}</p>
                </div>
                <div class="rounded-[1.25rem] border border-red-200 bg-red-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase text-red-600">Achterstallig</p>
                    <p class="mt-1 text-2xl font-semibold text-red-700 tabular-nums">{{ number_format($overdue_count, 0, ',', '.') }}</p>
                </div>
                <div class="rounded-[1.25rem] border border-sky-200 bg-sky-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase text-sky-600">Interested zonder datum</p>
                    <p class="mt-1 text-2xl font-semibold text-sky-700 tabular-nums">{{ number_format($interested_without_follow_up_count, 0, ',', '.') }}</p>
                </div>
            </div>

            @if (count($rows) === 0)
                <div class="flex min-h-[12rem] items-center justify-center rounded-[1.75rem] border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center">
                    <p class="text-sm text-slate-500">Geen open prospectopvolging gevonden.</p>
                </div>
            @else
                <div class="overflow-hidden rounded-[1.75rem] border border-slate-200/80 bg-gradient-to-b from-white to-slate-50/70 shadow-sm">
                    <div class="w-full overflow-x-auto">
                        <table class="min-w-full table-fixed divide-y divide-slate-200 text-sm">
                            <thead class="bg-white text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="w-[24%] px-4 py-3">Prospect</th>
                                    <th class="w-[16%] px-4 py-3">Campagne</th>
                                    <th class="w-[12%] px-4 py-3">Status</th>
                                    <th class="w-[10%] px-4 py-3">Prioriteit</th>
                                    <th class="w-[10%] px-4 py-3">Warmte</th>
                                    <th class="w-[8%] px-4 py-3 text-right">Score</th>
                                    <th class="w-[10%] px-4 py-3">Laatst</th>
                                    <th class="w-[10%] px-4 py-3">Opvolging</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white text-sm">
                                @foreach ($rows as $row)
                                    <tr class="align-top text-slate-700 transition hover:bg-slate-50/80">
                                        <td class="px-4 py-3 font-medium text-slate-900">
                                            <a href="{{ $row['edit_url'] }}" class="block truncate text-slate-950 hover:text-primary-700">
                                                {{ $row['name'] }}
                                            </a>
                                            <span class="mt-1 inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600">{{ $row['follow_up_state'] }}</span>
                                        </td>
                                        <td class="px-4 py-3"><span class="block truncate">{{ $row['campaign'] }}</span></td>
                                        <td class="px-4 py-3"><span class="block truncate">{{ $row['status'] }}</span></td>
                                        <td class="px-4 py-3"><span class="block truncate">{{ $row['priority'] }}</span></td>
                                        <td class="px-4 py-3"><span class="block truncate">{{ $row['warmth'] }}</span></td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ $row['score'] ?? '—' }}</td>
                                        <td class="px-4 py-3"><span class="block truncate">{{ $row['last_contacted_at'] }}</span></td>
                                        <td class="px-4 py-3"><span class="block truncate">{{ $row['next_follow_up_at'] }}</span></td>
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
