@php
    $palette = [
        [
            'badge' => 'bg-sky-50 text-sky-700 ring-sky-200',
            'icon' => 'heroicon-o-users',
        ],
        [
            'badge' => 'bg-cyan-50 text-cyan-700 ring-cyan-200',
            'icon' => 'heroicon-o-chart-bar',
        ],
        [
            'badge' => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
            'icon' => 'heroicon-o-globe-alt',
        ],
        [
            'badge' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'icon' => 'heroicon-o-user-plus',
        ],
        [
            'badge' => 'bg-teal-50 text-teal-700 ring-teal-200',
            'icon' => 'heroicon-o-users',
        ],
        [
            'badge' => 'bg-amber-50 text-amber-700 ring-amber-200',
            'icon' => 'heroicon-o-calendar-days',
        ],
        [
            'badge' => 'bg-violet-50 text-violet-700 ring-violet-200',
            'icon' => 'heroicon-o-bolt',
        ],
        [
            'badge' => 'bg-rose-50 text-rose-700 ring-rose-200',
            'icon' => 'heroicon-o-clock',
        ],
    ];
@endphp

<x-filament-widgets::widget>
    <section class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-950/5">
        <div class="border-b border-slate-200/80 px-6 py-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-start gap-3">
                    <span class="inline-flex rounded-2xl bg-slate-100 p-3 text-slate-700">
                        <x-filament::icon icon="heroicon-o-chart-bar-square" class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-base font-semibold text-slate-950">KPI-overzicht</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Bezoekers- en registratiecijfers uit lokaal opgeslagen analytics- en gebruikersdata.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    @if (($analytics_window['warning'] ?? null) || $is_analytics_incomplete)
                        <div class="flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700">
                            <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-4 w-4" />
                            <span>{{ $analytics_window['warning'] ?? 'Analytics data mogelijk incompleet' }}</span>
                        </div>
                    @endif

                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-600">
                        {{ $analytics_window['label'] ?? 'Laatste 30 dagen' }}
                    </span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 px-6 py-6 md:grid-cols-2 xl:grid-cols-4">
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

                <article class="rounded-[1.75rem] border border-slate-200/80 bg-gradient-to-b from-white to-slate-50/80 p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 space-y-3">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">{{ $card['label'] }}</p>
                            <p class="text-4xl font-semibold tracking-tight text-slate-950">{{ $displayValue }}</p>
                            <p class="text-sm text-slate-500">{{ $card['meta'] ?? 'Lokaal opgeslagen data' }}</p>
                        </div>

                        <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl ring-1 {{ $accent['badge'] }}">
                            <x-filament::icon :icon="$accent['icon']" class="h-5 w-5" />
                        </span>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
</x-filament-widgets::widget>
