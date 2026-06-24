@php
    $sections = [
        [
            'title' => 'Top queries op clicks',
            'subtitle' => 'Zoekopdrachten met de meeste clicks uit lokaal opgeslagen Search Console data.',
            'value_key' => 'label',
            'value_label' => 'Query',
            'rows' => $top_queries_by_clicks,
            'label_width' => 'w-[46%]',
        ],
        [
            'title' => 'Top queries op impressions',
            'subtitle' => 'Zoekopdrachten met het grootste bereik in Search Console.',
            'value_key' => 'label',
            'value_label' => 'Query',
            'rows' => $top_queries_by_impressions,
            'label_width' => 'w-[46%]',
        ],
        [
            'title' => 'Hoge impressies, lage CTR',
            'subtitle' => 'Zoekopdrachten met zichtbaarheid maar relatief lage doorklikratio.',
            'value_key' => 'label',
            'value_label' => 'Query',
            'rows' => $high_impression_low_ctr_queries,
            'label_width' => 'w-[46%]',
        ],
        [
            'title' => 'Positie 4 t/m 15',
            'subtitle' => 'Zoekopdrachten die dicht bij topposities zitten en SEO-kansen bieden.',
            'value_key' => 'label',
            'value_label' => 'Query',
            'rows' => $position_opportunity_queries,
            'label_width' => 'w-[46%]',
        ],
        [
            'title' => 'Top SEO landing pages',
            'subtitle' => "Pagina's met de meeste organische clicks uit Search Console.",
            'value_key' => 'label',
            'value_label' => 'Pagina',
            'rows' => $top_pages,
            'span' => 'xl:col-span-2',
            'label_width' => 'w-[58%]',
        ],
        [
            'title' => 'SEO pages met lage CTR',
            'subtitle' => "Pagina's met hoge impressies waar snippetverbetering kansrijk is.",
            'value_key' => 'label',
            'value_label' => 'Pagina',
            'rows' => $high_impression_low_ctr_pages,
            'label_width' => 'w-[46%]',
        ],
    ];
@endphp

<x-filament-widgets::widget>
    <section class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-950/5">
        <div class="border-b border-slate-200/80 px-6 py-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="flex min-w-0 items-start gap-3">
                    <span class="inline-flex shrink-0 rounded-2xl bg-emerald-50 p-3 text-emerald-700 ring-1 ring-emerald-200">
                        <x-filament::icon icon="heroicon-o-globe-alt" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <h3 class="text-base font-semibold text-slate-950">SEO intelligence</h3>
                        <p class="mt-1 max-w-3xl text-sm text-slate-500">Zoekgedrag en contentkansen uit lokaal opgeslagen Search Console data, zonder live API-calls.</p>
                    </div>
                </div>

                <div class="flex flex-col items-start gap-2 lg:items-end">
                    @if (($query_window['label'] ?? null) || ($page_window['label'] ?? null))
                        <span class="inline-flex items-center self-start rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 lg:self-end">
                            {{ $query_window['label'] ?? $page_window['label'] }}
                        </span>
                    @else
                        <span class="inline-flex items-center self-start rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-600 lg:self-end">
                            Geen gesynchroniseerde Search Console data
                        </span>
                    @endif

                    @if (($query_window['warning'] ?? null) || ($page_window['warning'] ?? null))
                        <span class="inline-flex items-center self-start rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 lg:self-end">
                            {{ $query_window['warning'] ?? $page_window['warning'] }}
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <div class="px-6 py-6">
            <div class="grid gap-6 xl:grid-cols-2">
                @foreach ($sections as $section)
                    <article class="overflow-hidden rounded-[1.75rem] border border-slate-200/80 bg-gradient-to-b from-white to-slate-50/70 shadow-sm {{ $section['span'] ?? '' }}">
                        <div class="flex min-h-24 flex-col justify-center border-b border-slate-200/80 px-5 py-4">
                            <h4 class="text-sm font-semibold text-slate-900">{{ $section['title'] }}</h4>
                            <p class="mt-1 text-sm text-slate-500">{{ $section['subtitle'] }}</p>
                        </div>

                        @if (count($section['rows']) === 0)
                            <div class="flex min-h-[16rem] items-center justify-center px-5 py-10 text-center">
                                <div>
                                    <p class="text-sm font-semibold text-slate-700">Nog geen data beschikbaar</p>
                                    <p class="mt-2 text-sm text-slate-500">Deze sectie wordt gevuld zodra er gesynchroniseerde Search Console data is opgeslagen.</p>
                                </div>
                            </div>
                        @else
                            <div class="w-full overflow-x-auto">
                                <table class="min-w-full table-fixed divide-y divide-slate-200 text-sm">
                                    <thead class="bg-white text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        <tr>
                                            <th class="{{ $section['label_width'] }} px-4 py-3">{{ $section['value_label'] }}</th>
                                            <th class="w-[10.5%] px-4 py-3 text-right">Clicks</th>
                                            <th class="w-[10.5%] px-4 py-3 text-right">Impressions</th>
                                            <th class="w-[10.5%] px-4 py-3 text-right">CTR</th>
                                            <th class="w-[10.5%] px-4 py-3 text-right">Gem. positie</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200 bg-white text-sm">
                                        @foreach (collect($section['rows'])->take(8) as $row)
                                            <tr class="align-top text-slate-700 transition hover:bg-slate-50/80">
                                                <td class="px-4 py-3 font-medium text-slate-900"><span class="block truncate">{{ $row[$section['value_key']] ?: '—' }}</span></td>
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
        </div>
    </section>
</x-filament-widgets::widget>
