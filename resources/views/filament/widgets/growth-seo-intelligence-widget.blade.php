@php
    $sections = [
        [
            'title' => 'Top queries op clicks',
            'subtitle' => 'Zoekopdrachten met de meeste clicks uit lokaal opgeslagen Search Console data.',
            'value_key' => 'label',
            'value_label' => 'Query',
            'rows' => $top_queries_by_clicks,
        ],
        [
            'title' => 'Top queries op impressions',
            'subtitle' => 'Zoekopdrachten met het grootste bereik in Search Console.',
            'value_key' => 'label',
            'value_label' => 'Query',
            'rows' => $top_queries_by_impressions,
        ],
        [
            'title' => 'Hoge impressies, lage CTR',
            'subtitle' => 'Zoekopdrachten met zichtbaarheid maar relatief lage doorklikratio.',
            'value_key' => 'label',
            'value_label' => 'Query',
            'rows' => $high_impression_low_ctr_queries,
        ],
        [
            'title' => 'Positie 4 t/m 15',
            'subtitle' => 'Zoekopdrachten die dicht bij topposities zitten en SEO-kansen bieden.',
            'value_key' => 'label',
            'value_label' => 'Query',
            'rows' => $position_opportunity_queries,
        ],
        [
            'title' => 'Top SEO landing pages',
            'subtitle' => 'Pagina\'s met de meeste organische clicks uit Search Console.',
            'value_key' => 'label',
            'value_label' => 'Pagina',
            'rows' => $top_pages,
            'span' => 'xl:col-span-2',
        ],
        [
            'title' => 'SEO pages met lage CTR',
            'subtitle' => 'Pagina\'s met hoge impressies waar snippetverbetering kansrijk is.',
            'value_key' => 'label',
            'value_label' => 'Pagina',
            'rows' => $high_impression_low_ctr_pages,
        ],
    ];
@endphp

<x-filament-widgets::widget>
    <section class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-950/5 dark:border-white/10 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex flex-col gap-3 border-b border-slate-200/80 px-6 py-5 dark:border-white/10 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-1">
                <h3 class="text-base font-semibold text-slate-950 dark:text-white">SEO intelligence</h3>
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    Zoekgedrag en contentkansen uit lokaal opgeslagen Search Console data, zonder live API-calls.
                </p>
            </div>
            <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-500/20">
                Search Console snapshots
            </span>
        </div>

        <div class="grid gap-6 px-6 py-6 xl:grid-cols-2">
            @foreach ($sections as $section)
                <article class="overflow-hidden rounded-2xl border border-slate-200/80 bg-slate-50/40 shadow-sm dark:border-white/10 dark:bg-white/[0.03] {{ $section['span'] ?? '' }}">
                    <div class="flex flex-col gap-1 border-b border-slate-200/80 px-5 py-4 dark:border-white/10">
                        <h4 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $section['title'] }}</h4>
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $section['subtitle'] }}</p>
                    </div>

                    @if (count($section['rows']) === 0)
                        <div class="px-5 py-10 text-center">
                            <p class="text-sm font-medium text-slate-700 dark:text-slate-200">Nog geen data beschikbaar</p>
                            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Deze sectie wordt gevuld zodra er Search Console snapshots zijn opgeslagen.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full table-auto divide-y divide-slate-200 text-sm dark:divide-white/10">
                                <thead class="bg-white/80 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-white/5 dark:text-slate-400">
                                    <tr>
                                        <th class="px-4 py-3">{{ $section['value_label'] }}</th>
                                        <th class="px-4 py-3 text-right">Clicks</th>
                                        <th class="px-4 py-3 text-right">Impressions</th>
                                        <th class="px-4 py-3 text-right">CTR</th>
                                        <th class="px-4 py-3 text-right">Gem. positie</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                                    @foreach ($section['rows'] as $row)
                                        <tr class="align-top text-slate-700 transition hover:bg-white/80 dark:text-slate-200 dark:hover:bg-white/[0.03]">
                                            <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ $row[$section['value_key']] ?: '—' }}</td>
                                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row['clicks'], 0, ',', '.') }}</td>
                                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row['impressions'], 0, ',', '.') }}</td>
                                            <td class="px-4 py-3 text-right tabular-nums">{{ $row['ctr'] === null ? '—' : number_format($row['ctr'], 2, ',', '.') . '%' }}</td>
                                            <td class="px-4 py-3 text-right tabular-nums">{{ $row['position'] === null ? '—' : number_format($row['position'], 1, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    </section>
</x-filament-widgets::widget>
