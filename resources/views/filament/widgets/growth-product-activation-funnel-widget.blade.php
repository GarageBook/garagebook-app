@php
    $activationCards = [
        ['label' => 'Registraties 7 dagen', 'value' => $stats['registrations_last_7_days']],
        ['label' => 'Registraties 30 dagen', 'value' => $stats['registrations_last_30_days']],
        ['label' => '≥1 voertuig', 'legacy_label' => 'Users met minimaal 1 voertuig', 'value' => $stats['users_with_vehicle']],
        ['label' => '≥1 onderhoudslog', 'legacy_label' => 'Users met minimaal 1 maintenance log', 'value' => $stats['users_with_maintenance']],
        ['label' => '≥2 logs', 'legacy_label' => 'Users met minimaal 2 maintenance logs', 'value' => $stats['users_with_two_maintenance']],
        ['label' => 'Reminder actief', 'value' => $stats['users_with_active_reminder']],
        ['label' => 'Boekje gedownload', 'value' => $stats['users_with_booklet_download']],
        ['label' => 'Publieke voertuigen', 'value' => $stats['public_vehicles']],
        ['label' => 'Actief 7 dagen', 'legacy_label' => 'Teruggekomen na 7 dagen', 'value' => $stats['active_last_7_days']],
        ['label' => 'Actief 30 dagen', 'legacy_label' => 'Teruggekomen na 30 dagen', 'value' => $stats['active_last_30_days']],
        ['label' => 'Uitgesloten users', 'value' => $stats['excluded_users'] ?? 0],
    ];
@endphp

<x-filament-widgets::widget>
    <section class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-950/5">
        <div class="border-b border-slate-200/80 px-6 py-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex min-w-0 items-start gap-3">
                    <span class="inline-flex shrink-0 rounded-2xl bg-emerald-50 p-3 text-emerald-700 ring-1 ring-emerald-200">
                        <x-filament::icon icon="heroicon-o-chart-pie" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <h3 class="text-base font-semibold text-slate-950">Funnel / activatie</h3>
                        <p class="mt-1 text-sm text-slate-500">Activatie en retentie op basis van gebruikers-, voertuigen-, onderhouds-, reminder- en onderhoudsboekje-data uit de database.</p>
                    </div>
                </div>

                <span class="inline-flex items-center self-start rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700">
                    Activatiepad
                </span>
            </div>
        </div>

        <div class="space-y-6 px-6 py-6">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($activationCards as $card)
                    <article class="flex h-full min-h-[8.75rem] flex-col justify-between rounded-[1.75rem] border border-slate-200/80 bg-gradient-to-b from-white to-slate-50/80 p-4 shadow-sm">
                        <p class="text-[11px] font-semibold leading-tight text-slate-500">{{ $card['label'] }}</p>
                        @if (($card['legacy_label'] ?? null) && $card['legacy_label'] !== $card['label'])
                            <span class="sr-only">{{ $card['legacy_label'] }}</span>
                        @endif
                        <p class="mt-4 text-3xl font-semibold tracking-tight text-slate-950">
                            {{ $card['value'] === null ? 'niet beschikbaar' : number_format($card['value'], 0, ',', '.') }}
                        </p>
                    </article>
                @endforeach
            </div>

            <div class="rounded-[1.75rem] border border-slate-200/80 bg-white p-5 shadow-sm">
                <div class="mb-4">
                    <h4 class="text-sm font-semibold text-slate-900">Kernconversies</h4>
                    <p class="mt-1 text-sm text-slate-500">Compact overzicht van de belangrijkste activatie- en retentiestappen.</p>
                </div>

                <div class="grid gap-3 lg:grid-cols-2">
                    @foreach ($conversions as $conversion)
                        <div class="rounded-2xl border border-slate-200/80 bg-slate-50/70 p-4">
                            <div class="text-sm font-semibold text-slate-900">{{ $conversion['label'] }}</div>
                            <div class="mt-2 flex items-end justify-between gap-4">
                                <div class="text-xs text-slate-500">
                                    {{ $conversion['to'] === null ? 'niet beschikbaar' : number_format($conversion['to'], 0, ',', '.') }} van {{ $conversion['from'] === null ? 'niet beschikbaar' : number_format($conversion['from'], 0, ',', '.') }} users
                                </div>
                                <div class="text-lg font-semibold text-slate-950 tabular-nums">
                                    {{ $conversion['percentage'] === null ? 'niet beschikbaar' : number_format($conversion['percentage'], 1, ',', '.') . '%' }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>



            @if (! empty($cohorts))
                <div class="rounded-[1.75rem] border border-slate-200/80 bg-white p-5 shadow-sm">
                    <div class="mb-4">
                        <h4 class="text-sm font-semibold text-slate-900">Registratiecohorten per week</h4>
                        <p class="mt-1 text-sm text-slate-500">Core-funnel activatie per registratiecohort. Eerste-log vensters worden gemeten vanaf registratie.</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead>
                                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <th class="py-2 pr-4">Week</th>
                                    <th class="py-2 pr-4">Registraties</th>
                                    <th class="py-2 pr-4">Voertuig</th>
                                    <th class="py-2 pr-4">Log 1d</th>
                                    <th class="py-2 pr-4">Log 3d</th>
                                    <th class="py-2 pr-4">Log 7d</th>
                                    <th class="py-2 pr-4">2e log</th>
                                    <th class="py-2 pr-4">Return 7d</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($cohorts as $cohort)
                                    <tr class="text-slate-700">
                                        <td class="py-2 pr-4 font-medium text-slate-900">{{ \Illuminate\Support\Carbon::parse($cohort['week'])->format('d-m-Y') }}</td>
                                        <td class="py-2 pr-4 tabular-nums">{{ number_format($cohort['registrations'], 0, ',', '.') }}</td>
                                        <td class="py-2 pr-4 tabular-nums">{{ number_format($cohort['vehicle_added'], 0, ',', '.') }}</td>
                                        <td class="py-2 pr-4 tabular-nums">{{ number_format($cohort['first_log_within_1_day'], 0, ',', '.') }}</td>
                                        <td class="py-2 pr-4 tabular-nums">{{ number_format($cohort['first_log_within_3_days'], 0, ',', '.') }}</td>
                                        <td class="py-2 pr-4 tabular-nums">{{ number_format($cohort['first_log_within_7_days'], 0, ',', '.') }}</td>
                                        <td class="py-2 pr-4 tabular-nums">{{ number_format($cohort['second_log'], 0, ',', '.') }}</td>
                                        <td class="py-2 pr-4 tabular-nums">{{ number_format($cohort['returned_within_7_days'], 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div class="rounded-[1.75rem] border border-slate-200/80 bg-gradient-to-b from-white to-slate-50/70 p-5 shadow-sm">
                <div class="mb-5">
                    <h4 class="text-sm font-semibold text-slate-900">Funnelstappen</h4>
                    <p class="mt-1 text-sm text-slate-500">Per stap het aantal core-funnel users en het aandeel ten opzichte van alle core registraties. Outreach, demo, test en interne accounts zijn uitgesloten.</p>
                </div>

                <div class="space-y-4">
                    @foreach ($funnel as $row)
                        @php
                            $percentage = $row['percentage'] === null ? null : max(0, min(100, $row['percentage']));
                        @endphp

                        <div class="rounded-[1.5rem] border border-slate-200/80 bg-white p-5">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex items-start justify-between gap-3 sm:hidden">
                                        <p class="text-sm font-semibold text-slate-900">{{ $row['step'] }}</p>
                                        <div class="text-sm font-semibold text-slate-700 tabular-nums">{{ $row['percentage'] === null ? 'niet beschikbaar' : number_format($row['percentage'], 1, ',', '.') . '%' }}</div>
                                    </div>
                                    <p class="hidden text-sm font-semibold text-slate-900 sm:block">{{ $row['step'] }}</p>
                                    <p class="mt-1 text-xs text-slate-500 sm:text-sm">{{ $row['count'] === null ? 'niet beschikbaar' : number_format($row['count'], 0, ',', '.') . ' users' }}</p>
                                </div>
                                <div class="hidden text-sm font-semibold text-slate-700 tabular-nums sm:block">{{ $row['percentage'] === null ? 'niet beschikbaar' : number_format($row['percentage'], 1, ',', '.') . '%' }}</div>
                            </div>
                            <div class="mt-4 h-2.5 max-w-full overflow-hidden rounded-full bg-slate-200">
                                <div class="h-full max-w-full rounded-full bg-gradient-to-r from-slate-900 via-sky-600 to-emerald-500" style="width: {{ $percentage ?? 0 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
</x-filament-widgets::widget>
