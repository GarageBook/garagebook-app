@php
    $primarySections = [
        [
            'title' => 'Top queries op clicks',
            'value_label' => 'Query',
            'rows' => collect($top_queries_by_clicks)->take(5),
        ],
        [
            'title' => 'Top queries op impressies',
            'value_label' => 'Query',
            'rows' => collect($top_queries_by_impressions)->take(5),
        ],
        [
            'title' => 'Top SEO landing pages',
            'value_label' => 'Pagina',
            'rows' => collect($top_pages)->take(5),
        ],
    ];

    $secondarySections = [
        [
            'title' => 'Hoge impressies, lage CTR',
            'value_label' => 'Query',
            'rows' => collect($high_impression_low_ctr_queries)->take(5),
        ],
        [
            'title' => 'Positie 4 t/m 15',
            'value_label' => 'Query',
            'rows' => collect($position_opportunity_queries)->take(5),
        ],
        [
            'title' => 'SEO pages met lage CTR',
            'value_label' => 'Pagina',
            'rows' => collect($high_impression_low_ctr_pages)->take(5),
        ],
    ];
@endphp

<x-filament-widgets::widget>
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
        <div class="flex items-start justify-between gap-4 border-b border-gray-100 p-5 dark:border-white/10">
            <div class="flex items-start gap-3">
                <span class="inline-flex rounded-xl bg-emerald-50 p-2 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-200">
                    <x-filament::icon icon="heroicon-o-globe-alt" class="h-5 w-5" />
                </span>
                <div class="space-y-1">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">SEO intelligence</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Zoekgedrag en contentkansen uit lokaal opgeslagen Search Console data.</p>
                </div>
            </div>
            <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-200">
                Search Console snapshots
            </span>
        </div>

        <div class="space-y-5 p-5">
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                @foreach ($primarySections as $section)
                    <article class="overflow-hidden rounded-xl border border-gray-100 bg-gray-50/60 dark:border-white/10 dark:bg-white/[0.03]">
                        <div class="border-b border-gray-100 px-4 py-4 dark:border-white/10">
                            <h4 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $section['title'] }}</h4>
                        </div>
                        @if ($section['rows']->isEmpty())
                            <div class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Nog geen data beschikbaar</div>
                        @else
                            <div class="overflow-x-auto">
                                <table class="min-w-full table-fixed divide-y divide-gray-100 text-sm dark:divide-white/10">
                                    <thead class="bg-gray-50 dark:bg-white/5">
                                        <tr>
                                            <th class="w-[44%] px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $section['value_label'] }}</th>
                                            <th class="w-[14%] px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Clicks</th>
                                            <th class="w-[18%] px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Impr.</th>
                                            <th class="w-[12%] px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">CTR</th>
                                            <th class="w-[12%] px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Pos.</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 bg-white dark:divide-white/10 dark:bg-transparent">
                                        @foreach ($section['rows'] as $row)
                                            <tr class="hover:bg-gray-50/70 dark:hover:bg-white/[0.03]">
                                                <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">
                                                    <div class="max-w-xs truncate" title="{{ $row['label'] ?: '—' }}">{{ $row['label'] ?: '—' }}</div>
                                                </td>
                                                <td class="px-4 py-3 text-right text-sm tabular-nums whitespace-nowrap text-gray-900 dark:text-white">{{ number_format($row['clicks'], 0, ',', '.') }}</td>
                                                <td class="px-4 py-3 text-right text-sm tabular-nums whitespace-nowrap text-gray-900 dark:text-white">{{ number_format($row['impressions'], 0, ',', '.') }}</td>
                                                <td class="px-4 py-3 text-right text-sm tabular-nums whitespace-nowrap text-gray-900 dark:text-white">{{ $row['ctr'] === null ? '—' : number_format($row['ctr'], 2, ',', '.') . '%' }}</td>
                                                <td class="px-4 py-3 text-right text-sm tabular-nums whitespace-nowrap text-gray-900 dark:text-white">{{ $row['position'] === null ? '—' : number_format($row['position'], 1, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="border-t border-gray-100 px-4 py-3 text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">Toont top {{ $section['rows']->count() }}.</div>
                        @endif
                    </article>
                @endforeach
            </div>

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                @foreach ($secondarySections as $section)
                    <article class="overflow-hidden rounded-xl border border-gray-100 bg-white dark:border-white/10 dark:bg-white/[0.02]">
                        <div class="border-b border-gray-100 px-4 py-4 dark:border-white/10">
                            <h4 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $section['title'] }}</h4>
                        </div>
                        @if ($section['rows']->isEmpty())
                            <div class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Nog geen data beschikbaar</div>
                        @else
                            <div class="overflow-x-auto">
                                <table class="min-w-full table-fixed divide-y divide-gray-100 text-sm dark:divide-white/10">
                                    <thead class="bg-gray-50 dark:bg-white/5">
                                        <tr>
                                            <th class="w-[46%] px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $section['value_label'] }}</th>
                                            <th class="w-[14%] px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Clicks</th>
                                            <th class="w-[18%] px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Impr.</th>
                                            <th class="w-[12%] px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">CTR</th>
                                            <th class="w-[10%] px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Pos.</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 bg-white dark:divide-white/10 dark:bg-transparent">
                                        @foreach ($section['rows'] as $row)
                                            <tr class="hover:bg-gray-50/70 dark:hover:bg-white/[0.03]">
                                                <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">
                                                    <div class="max-w-xs truncate" title="{{ $row['label'] ?: '—' }}">{{ $row['label'] ?: '—' }}</div>
                                                </td>
                                                <td class="px-4 py-3 text-right text-sm tabular-nums whitespace-nowrap text-gray-900 dark:text-white">{{ number_format($row['clicks'], 0, ',', '.') }}</td>
                                                <td class="px-4 py-3 text-right text-sm tabular-nums whitespace-nowrap text-gray-900 dark:text-white">{{ number_format($row['impressions'], 0, ',', '.') }}</td>
                                                <td class="px-4 py-3 text-right text-sm tabular-nums whitespace-nowrap text-gray-900 dark:text-white">{{ $row['ctr'] === null ? '—' : number_format($row['ctr'], 2, ',', '.') . '%' }}</td>
                                                <td class="px-4 py-3 text-right text-sm tabular-nums whitespace-nowrap text-gray-900 dark:text-white">{{ $row['position'] === null ? '—' : number_format($row['position'], 1, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
