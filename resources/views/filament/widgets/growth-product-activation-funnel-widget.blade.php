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
    <x-filament::section
        heading="Funnel / activatie"
        description="Productactivatie op basis van gebruikers-, voertuigen-, onderhouds-, document- en fueldata uit de database."
        icon="heroicon-o-chart-pie"
        compact
    >
        <div class="space-y-6">
            <div class="flex justify-end">
                <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-500/20">
                    Activatiepad
                </span>
            </div>

            <div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-4">
                @foreach ($activationCards as $card)
                    <article class="rounded-2xl border border-slate-200/80 bg-slate-50/60 p-4 dark:border-white/10 dark:bg-white/[0.03]">
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $card['label'] }}</p>
                        <p class="mt-3 text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">
                            {{ $card['value'] === null ? 'niet beschikbaar' : number_format($card['value'], 0, ',', '.') }}
                        </p>
                    </article>
                @endforeach
            </div>

            <div class="space-y-4">
                <div class="space-y-1">
                    <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Funnelstappen</h4>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Per stap het aantal users en het aandeel ten opzichte van alle registraties.</p>
                </div>

                <div class="space-y-4">
                    @foreach ($funnel as $row)
                        <div class="rounded-2xl border border-slate-200/80 bg-slate-50/50 p-4 dark:border-white/10 dark:bg-white/[0.03]">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $row['step'] }}</p>
                                    <p class="text-sm text-slate-500 dark:text-slate-400">
                                        {{ $row['count'] === null ? 'niet beschikbaar' : number_format($row['count'], 0, ',', '.') . ' users' }}
                                    </p>
                                </div>
                                <div class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                                    {{ $row['percentage'] === null ? 'niet beschikbaar' : number_format($row['percentage'], 1, ',', '.') . '%' }}
                                </div>
                            </div>
                            <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-slate-200 dark:bg-white/10">
                                <div class="h-full rounded-full bg-gradient-to-r from-sky-500 via-cyan-500 to-emerald-500" style="width: {{ $row['percentage'] === null ? 0 : max(0, min(100, $row['percentage'])) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
