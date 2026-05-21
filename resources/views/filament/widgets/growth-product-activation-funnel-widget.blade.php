@php
    $activationCards = [
        ['label' => 'Totaal users', 'value' => $stats['total_users'], 'tone' => 'bg-sky-50 text-sky-700'],
        ['label' => 'Users met minimaal 1 voertuig', 'value' => $stats['users_with_vehicle'], 'tone' => 'bg-cyan-50 text-cyan-700'],
        ['label' => 'Users met minimaal 1 maintenance log', 'value' => $stats['users_with_maintenance'], 'tone' => 'bg-indigo-50 text-indigo-700'],
        ['label' => 'Users met minimaal 3 maintenance logs', 'value' => $stats['users_with_three_maintenance'], 'tone' => 'bg-violet-50 text-violet-700'],
        ['label' => 'Users met minimaal 1 document/upload', 'value' => $stats['users_with_documents'], 'tone' => 'bg-amber-50 text-amber-700'],
        ['label' => 'Users met fuel entries', 'value' => $stats['users_with_fuel_entries'], 'tone' => 'bg-emerald-50 text-emerald-700'],
    ];
@endphp

<x-filament-widgets::widget>
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
        <div class="flex items-start justify-between gap-4 border-b border-gray-100 p-5 dark:border-white/10">
            <div class="flex items-start gap-3">
                <span class="inline-flex rounded-xl bg-indigo-50 p-2 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-200">
                    <x-filament::icon icon="heroicon-o-chart-pie" class="h-5 w-5" />
                </span>
                <div class="space-y-1">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Funnel / activatie</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Productactivatie op basis van gebruikers-, voertuigen- en onderhoudsdata.</p>
                </div>
            </div>
            <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-200">
                Activatiepad
            </span>
        </div>

        <div class="space-y-6 p-5">
            <div class="grid grid-cols-2 gap-3 xl:grid-cols-3">
                @foreach ($activationCards as $card)
                    <article class="rounded-2xl border border-gray-200 bg-gray-50/80 p-4 dark:border-white/10 dark:bg-white/[0.03]">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $card['label'] }}</p>
                                <p class="mt-3 text-2xl font-bold tracking-tight text-gray-950 dark:text-white">{{ $card['value'] === null ? 'niet beschikbaar' : number_format($card['value'], 0, ',', '.') }}</p>
                            </div>
                            <span class="inline-flex rounded-xl px-2.5 py-1 text-xs font-semibold {{ $card['tone'] }} dark:bg-white/5 dark:text-white/80">{{ $loop->iteration }}</span>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="rounded-2xl border border-gray-200 bg-gray-50/60 p-5 dark:border-white/10 dark:bg-white/[0.03]">
                <div class="mb-4 space-y-1">
                    <h4 class="text-sm font-semibold text-gray-950 dark:text-white">Funnelstappen</h4>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Per stap het aantal users en het aandeel ten opzichte van alle registraties.</p>
                </div>
                <div class="space-y-4">
                    @foreach ($funnel as $row)
                        @php
                            $displayCount = $row['count'] === null ? 'niet beschikbaar' : number_format($row['count'], 0, ',', '.');
                            $displayPercentage = $row['percentage'] === null ? 'niet beschikbaar' : number_format($row['percentage'], 1, ',', '.') . '%';
                            $barWidth = $row['percentage'] === null ? 0 : max(0, min(100, $row['percentage']));
                        @endphp
                        <div>
                            <div class="flex items-center justify-between gap-4 text-sm">
                                <span class="font-medium text-gray-900 dark:text-white">{{ $row['step'] }}</span>
                                <span class="tabular-nums text-gray-500 dark:text-gray-400">{{ $displayCount }}{{ $row['count'] === null ? '' : ' · ' . $displayPercentage }}</span>
                            </div>
                            <div class="mt-2 h-2 rounded-full bg-gray-200 dark:bg-white/10">
                                <div class="h-2 rounded-full bg-gradient-to-r from-sky-500 via-cyan-500 to-emerald-500" style="width: {{ $barWidth }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
