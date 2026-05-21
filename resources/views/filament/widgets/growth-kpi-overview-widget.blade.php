@php
    $cards = collect($cards)
        ->map(function (array $card, int $index): array {
            $icons = [
                'heroicon-o-users',
                'heroicon-o-chart-bar',
                'heroicon-o-globe-alt',
                'heroicon-o-user-plus',
                'heroicon-o-sparkles',
                'heroicon-o-calendar-days',
                'heroicon-o-bolt',
                'heroicon-o-clock',
            ];

            $badgeClasses = [
                'bg-sky-50 text-sky-700',
                'bg-cyan-50 text-cyan-700',
                'bg-indigo-50 text-indigo-700',
                'bg-emerald-50 text-emerald-700',
                'bg-teal-50 text-teal-700',
                'bg-amber-50 text-amber-700',
                'bg-violet-50 text-violet-700',
                'bg-rose-50 text-rose-700',
            ];

            $value = $card['is_available'] ? $card['value'] : 'niet beschikbaar';

            if ($card['is_available'] && is_numeric($value)) {
                $value = number_format((float) $value, ($card['suffix'] ?? null) === '%' ? 1 : 0, ',', '.');
            }

            if ($card['is_available'] && filled($card['suffix'] ?? null) && is_string($value)) {
                $value .= $card['suffix'];
            }

            return [
                ...$card,
                'display_value' => $value,
                'meta' => $card['meta'] ?? ($card['is_available'] ? 'Lokaal opgeslagen data' : 'Nog geen data beschikbaar'),
                'icon' => $icons[$index] ?? 'heroicon-o-chart-bar-square',
                'badge_class' => $badgeClasses[$index] ?? 'bg-gray-50 text-gray-700',
            ];
        })
        ->all();
@endphp

<x-filament-widgets::widget>
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">KPI-overzicht</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Bezoekers- en registratiecijfers uit lokaal opgeslagen analytics- en gebruikersdata.</p>
            </div>
            <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600 dark:bg-white/5 dark:text-gray-300">Laatste 30 dagen</span>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($cards as $card)
                <article class="overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1 space-y-3">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $card['label'] }}</p>
                            <div class="space-y-2">
                                <p class="truncate text-3xl font-bold tracking-tight text-gray-950 dark:text-white">{{ $card['display_value'] }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $card['meta'] }}</p>
                            </div>
                        </div>
                        <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl {{ $card['badge_class'] }} dark:bg-white/5 dark:text-white/80">
                            <x-filament::icon :icon="$card['icon']" class="h-5 w-5" />
                        </span>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</x-filament-widgets::widget>
