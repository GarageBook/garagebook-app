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
            'subtitle' => "Pagina's met de meeste organische clicks uit Search Console.",
            'value_key' => 'label',
            'value_label' => 'Pagina',
            'rows' => $top_pages,
            'span' => 'xl:col-span-2',
        ],
        [
            'title' => 'SEO pages met lage CTR',
            'subtitle' => "Pagina's met hoge impressies waar snippetverbetering kansrijk is.",
            'value_key' => 'label',
            'value_label' => 'Pagina',
            'rows' => $high_impression_low_ctr_pages,
        ],
    ];
@endphp

<x-filament-widgets::widget>
    <section class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-950/5">
        <div class="space-y-6 p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="flex items-start gap-3">
                    <span class="inline-flex rounded-2xl bg-emerald-50 p-3 text-emerald-700 ring-1 ring-emerald-200">
                        <x-filament::icon icon="heroicon-o-globe-alt" class="h-5 w-5" />
                    </span>
                    <div class="space-y-1">
                        <h3 class="text-base font-semibold text-slate-950">SEO intelligence</h3>
                        <p class="max-w-3xl text-sm text-slate-500">Zoekgedrag en contentkansen uit lokaal opgeslagen Search Console data, zonder live API-calls.</p>
                    </div>
                </div>

                <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200">
                    Search Console snapshots
                </span>
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                @foreach ($sections as $section)
                    <article class="overflow-hidden rounded-[1.75rem] border border-slate-200/80 bg-slate-50/70 shadow-sm {{ $section['span'] ?? '' }}">
                        <div class="flex flex-col gap-1 border-b border-slate-200/80 bg-white/80 px-5 py-4">
                            <h4 class="text-sm font-semibold text-slate-900">{{ $section['title'] }}</h4>
                            <p class="text-sm text-slate-500">{{ $section['subtitle'] }}</p>
                        </div>

                        @if (count($section['rows']) === 0)
                            <div class="px-5 py-10 text-center">
                                <p class="text-sm font-semibold text-slate-700">Nog geen data beschikbaar</p>
                                <p class="mt-2 text-sm text-slate-500">Deze sectie wordt gevuld zodra er Search Console snapshots zijn opgeslagen.</p>
                            </div>
                        @else
                            <div class="overflow-x-auto">
                                <table class="min-w-full table-fixed divide-y divide-slate-200 text-sm">
                                    <thead class="bg-white text-left text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                                        <tr>
                                            <th class="w-[46%] px-4 py-3">{{ $section['value_label'] }}</th>
                                            <th class="w-[13.5%] px-4 py-3 text-right">Clicks</th>
                                            <th class="w-[13.5%] px-4 py-3 text-right">Impressions</th>
                                            <th class="w-[13.5%] px-4 py-3 text-right">CTR</th>
                                            <th class="w-[13.5%] px-4 py-3 text-right">Gem. positie</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200 bg-white">
                                        @foreach (collect($section['rows'])->take(8) as $row)
                                            <tr class="align-top text-slate-700 transition hover:bg-slate-50/80">
                                                <td class="px-4 py-3 font-medium text-slate-900">
                                                    <span class="block truncate">{{ $row[$section['value_key']] ?: '—' }}</span>
                                                </td>
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
