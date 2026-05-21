@php
    $palette = [
        [
            'badge' => 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-200 dark:ring-sky-500/20',
            'icon' => 'heroicon-o-users',
        ],
        [
            'badge' => 'bg-cyan-50 text-cyan-700 ring-cyan-200 dark:bg-cyan-500/10 dark:text-cyan-200 dark:ring-cyan-500/20',
            'icon' => 'heroicon-o-chart-bar',
        ],
        [
            'badge' => 'bg-indigo-50 text-indigo-700 ring-indigo-200 dark:bg-indigo-500/10 dark:text-indigo-200 dark:ring-indigo-500/20',
            'icon' => 'heroicon-o-globe-alt',
        ],
        [
            'badge' => 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-500/20',
            'icon' => 'heroicon-o-user-plus',
        ],
        [
            'badge' => 'bg-teal-50 text-teal-700 ring-teal-200 dark:bg-teal-500/10 dark:text-teal-200 dark:ring-teal-500/20',
            'icon' => 'heroicon-o-users',
        ],
        [
            'badge' => 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-500/20',
            'icon' => 'heroicon-o-calendar-days',
        ],
        [
            'badge' => 'bg-violet-50 text-violet-700 ring-violet-200 dark:bg-violet-500/10 dark:text-violet-200 dark:ring-violet-500/20',
            'icon' => 'heroicon-o-bolt',
        ],
        [
            'badge' => 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-200 dark:ring-rose-500/20',
            'icon' => 'heroicon-o-clock',
        ],
    ];
@endphp

<x-filament-widgets::widget>
    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
        <div class="border-b border-gray-100 px-6 py-5 dark:border-white/10">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-start gap-3">
                    <span class="inline-flex rounded-xl bg-gray-50 p-2 text-gray-700 dark:bg-white/5 dark:text-gray-200">
                        <x-filament::icon icon="heroicon-o-chart-bar-square" class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-sm font-semibold text-gray-950 dark:text-white">KPI-overzicht</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Bezoekers- en registratiecijfers uit lokaal opgeslagen analytics- en gebruikersdata.
                        </p>
                    </div>
                </div>

                <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600 dark:bg-white/5 dark:text-gray-300">
                    Laatste 30 dagen
                </span>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 px-6 py-6 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($cards as $index => $card)
                @php
                    $accent = $palette[$index] ?? $palette[0];
                    $displayValue = $card['is_available']
                        ? (is_numeric($card['value']) ? number_format((float) $card['value'], str_contains((string) ($card['suffix'] ?? ''), '%') ? 1 : 0, ',', '.') : $card['value'])
                        : 'niet beschikbaar';

                    if ($card['is_available'] && isset($card['suffix']) && is_numeric($card['value'])) {
                        $displayValue .= $card['suffix'];
                    }
                @endphp

                <article class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-950/40">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 space-y-2">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $card['label'] }}</p>
                            <p class="text-3xl font-bold tracking-tight text-gray-950 dark:text-white">{{ $displayValue }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $card['meta'] ?? 'Lokaal opgeslagen data' }}</p>
                        </div>

                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl ring-1 {{ $accent['badge'] }}">
                            <x-filament::icon :icon="$accent['icon']" class="h-5 w-5" />
                        </span>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
</x-filament-widgets::widget>
