<x-filament-widgets::widget>
    <section class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-950/5">
        <div class="border-b border-slate-200/80 px-6 py-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex min-w-0 items-start gap-3">
                    <span class="inline-flex shrink-0 rounded-2xl bg-sky-50 p-3 text-sky-700 ring-1 ring-sky-200">
                        <x-filament::icon icon="heroicon-o-bolt" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <h3 class="text-base font-semibold text-slate-950">Activatie per bron</h3>
                        <p class="mt-1 text-sm text-slate-500">Registratiebronnen vergeleken op eerste voertuig en eerste onderhoudslog.</p>
                    </div>
                </div>

                <span class="inline-flex items-center self-start rounded-full border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-700">
                    Bronkwaliteit
                </span>
            </div>
        </div>

        <div class="px-6 py-6">
            @if (count($rows) === 0)
                <div class="flex min-h-[16rem] items-center justify-center rounded-[1.75rem] border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center">
                    <div>
                        <p class="text-sm font-semibold text-slate-700">Nog geen registraties beschikbaar</p>
                        <p class="mt-2 text-sm text-slate-500">Bronactivatie wordt zichtbaar zodra gebruikers zich registreren.</p>
                    </div>
                </div>
            @else
                <div class="overflow-hidden rounded-[1.75rem] border border-slate-200/80 bg-gradient-to-b from-white to-slate-50/70 shadow-sm">
                    <div class="w-full overflow-x-auto">
                        <table class="min-w-full table-fixed divide-y divide-slate-200 text-sm">
                            <thead class="bg-white text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="w-[18%] px-4 py-3">Bron</th>
                                    <th class="w-[12%] px-4 py-3 text-right">Registraties</th>
                                    <th class="w-[14%] px-4 py-3 text-right">≥1 voertuig</th>
                                    <th class="w-[14%] px-4 py-3 text-right">≥1 log</th>
                                    <th class="w-[14%] px-4 py-3 text-right">Activatie</th>
                                    <th class="w-[14%] px-4 py-3">Campagnes</th>
                                    <th class="w-[14%] px-4 py-3">Partners</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white text-sm">
                                @foreach (collect($rows)->take(10) as $row)
                                    <tr class="align-top text-slate-700 transition hover:bg-slate-50/80">
                                        <td class="px-4 py-3 font-medium text-slate-900"><span class="block truncate">{{ $row['source'] }}</span></td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row['registrations'], 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row['users_with_vehicle'], 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row['users_with_maintenance_log'], 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row['activation_percentage'], 1, ',', '.') }}%</td>
                                        <td class="px-4 py-3"><span class="block truncate">{{ $row['campaigns'] }}</span></td>
                                        <td class="px-4 py-3"><span class="block truncate">{{ $row['partners'] }}</span></td>
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
