@php
    $activationCards = [
        ['label' => 'Totaal users', 'value' => $stats['total_users']],
        ['label' => 'Users met minimaal 1 voertuig', 'value' => $stats['users_with_vehicle']],
        ['label' => 'Users met minimaal 1 maintenance log', 'value' => $stats['users_with_maintenance']],
        ['label' => 'Users met minimaal 3 maintenance logs', 'value' => $stats['users_with_three_maintenance']],
        ['label' => 'Users met minimaal 1 document/upload', 'value' => $stats['users_with_documents']],
        ['label' => 'Users met fuel entries', 'value' => $stats['users_with_fuel_entries']],
        ['label' => 'Users actief laatste 7 dagen', 'value' => $stats['active_last_7_days']],
        ['label' => 'Users actief laatste 30 dagen', 'value' => $stats['active_last_30_days']],
    ];
@endphp

<x-filament-widgets::widget>
    <section class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-950/5">
        <div class="border-b border-slate-200/80 px-6 py-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-start gap-3">
                    <span class="inline-flex rounded-2xl bg-emerald-50 p-3 text-emerald-700 ring-1 ring-emerald-200">
                        <x-filament::icon icon="heroicon-o-chart-pie" class="h-5 w-5" />
                    </span>
                    <div>
                        <h3 class="text-base font-semibold text-slate-950">Funnel / activatie</h3>
                        <p class="mt-1 text-sm text-slate-500">Productactivatie op basis van gebruikers-, voertuigen-, onderhouds-, document- en fueldata uit de database.</p>
                    </div>
                </div>

                <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700">
                    Activatiepad
                </span>
            </div>
        </div>

        <div class="space-y-6 px-6 py-6">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($activationCards as $card)
                    <article class="rounded-[1.75rem] border border-slate-200/80 bg-gradient-to-b from-white to-slate-50/80 p-5 shadow-sm">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">{{ $card['label'] }}</p>
                        <p class="mt-4 text-3xl font-semibold tracking-tight text-slate-950">
                            {{ $card['value'] === null ? 'niet beschikbaar' : number_format($card['value'], 0, ',', '.') }}
                        </p>
                    </article>
                @endforeach
            </div>

            <div class="rounded-[1.75rem] border border-slate-200/80 bg-gradient-to-b from-white to-slate-50/70 p-5 shadow-sm">
                <div class="mb-5">
                    <h4 class="text-sm font-semibold text-slate-900">Funnelstappen</h4>
                    <p class="mt-1 text-sm text-slate-500">Per stap het aantal users en het aandeel ten opzichte van alle registraties.</p>
                </div>

                <div class="space-y-4">
                    @foreach ($funnel as $row)
                        @php
                            $percentage = $row['percentage'] === null ? null : max(0, min(100, $row['percentage']));
                        @endphp

                        <div class="rounded-[1.5rem] border border-slate-200/80 bg-white p-5">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-900">{{ $row['step'] }}</p>
                                    <p class="mt-1 text-sm text-slate-500">{{ $row['count'] === null ? 'niet beschikbaar' : number_format($row['count'], 0, ',', '.') . ' users' }}</p>
                                </div>
                                <div class="text-sm font-semibold text-slate-700 tabular-nums">{{ $row['percentage'] === null ? 'niet beschikbaar' : number_format($row['percentage'], 1, ',', '.') . '%' }}</div>
                            </div>
                            <div class="mt-4 h-2.5 overflow-hidden rounded-full bg-slate-200">
                                <div class="h-full rounded-full bg-gradient-to-r from-slate-900 via-sky-600 to-emerald-500" style="width: {{ $percentage ?? 0 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
</x-filament-widgets::widget>
