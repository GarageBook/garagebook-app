<x-filament-panels::page>
    @php
        $card = 'rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900';
        $summary = $dashboard['summary'] ?? [];
        $formatCtr = fn ($value) => number_format(((float) $value) * 100, 2, ',', '.') . '%';
    @endphp

    <div class="space-y-6">
        <section class="{{ $card }}">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Laatste import</p>
                    <h2 class="text-2xl font-semibold">{{ $dashboard['latest_date'] ?? 'Geen import gevonden' }}</h2>
                </div>
                @if($dashboard['previous_date'] ?? null)
                    <div class="text-right text-sm text-gray-500 dark:text-gray-400">
                        Vorige import: {{ $dashboard['previous_date'] }}
                    </div>
                @endif
            </div>
        </section>

        <section>
            <h3 class="mb-3 text-lg font-semibold">Overzicht</h3>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                @foreach([
                    'Clicks' => number_format($summary['clicks'] ?? 0, 0, ',', '.'),
                    'Impressions' => number_format($summary['impressions'] ?? 0, 0, ',', '.'),
                    'Gem. CTR' => $formatCtr($summary['ctr'] ?? 0),
                    'Gem. positie' => number_format((float) ($summary['position'] ?? 0), 2, ',', '.'),
                    'Pagina\'s' => number_format($summary['pages'] ?? 0, 0, ',', '.'),
                    'Zoekwoorden' => number_format($summary['queries'] ?? 0, 0, ',', '.'),
                ] as $label => $value)
                    <div class="{{ $card }}">
                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</div>
                        <div class="mt-1 text-2xl font-semibold">{{ $value }}</div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            @include('filament.pages.partials.search-console-table', [
                'title' => 'Pagina\'s net buiten top 10',
                'rows' => $dashboard['quick_wins'] ?? [],
                'columns' => ['path', 'page_type', 'impressions', 'clicks', 'ctr', 'position'],
            ])

            @include('filament.pages.partials.search-console-table', [
                'title' => 'Hoge impressies, lage CTR',
                'rows' => $dashboard['low_ctr'] ?? [],
                'columns' => ['path', 'page_type', 'impressions', 'clicks', 'ctr', 'position'],
            ])

            @include('filament.pages.partials.search-console-table', [
                'title' => 'Nieuwe zoekwoorden',
                'rows' => $dashboard['new_queries'] ?? [],
                'columns' => ['query', 'path', 'impressions', 'clicks', 'position'],
            ])

            @include('filament.pages.partials.search-console-table', [
                'title' => 'Grootste stijgers',
                'rows' => $dashboard['winners'] ?? [],
                'columns' => ['query', 'path', 'previous_position', 'position', 'position_delta'],
            ])

            @include('filament.pages.partials.search-console-table', [
                'title' => 'Grootste dalers',
                'rows' => $dashboard['losers'] ?? [],
                'columns' => ['query', 'path', 'previous_position', 'position', 'position_delta'],
            ])

            <div class="{{ $card }}">
                <h3 class="mb-3 text-lg font-semibold">Vehicle Authority prestaties</h3>
                <dl class="mb-4 space-y-2 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt>Gemiddelde positie</dt>
                        <dd>{{ ($dashboard['vehicle_authority']['average_position'] ?? null) !== null ? number_format((float) $dashboard['vehicle_authority']['average_position'], 2, ',', '.') : '-' }}</dd>
                    </div>
                </dl>

                <h4 class="mb-2 font-semibold">Top pages</h4>
                @include('filament.pages.partials.search-console-table-inner', [
                    'rows' => $dashboard['vehicle_authority']['top_pages'] ?? [],
                    'columns' => ['path', 'impressions', 'clicks', 'ctr', 'position'],
                ])

                <h4 class="mb-2 mt-5 font-semibold">Impressies maar 0 clicks</h4>
                @include('filament.pages.partials.search-console-table-inner', [
                    'rows' => $dashboard['vehicle_authority']['zero_click_pages'] ?? [],
                    'columns' => ['path', 'impressions', 'clicks', 'position'],
                ])
            </div>
        </section>

        <section class="{{ $card }}">
            <h3 class="mb-3 text-lg font-semibold">SEO Prioriteiten</h3>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left text-sm">
                    <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="py-2 pr-4">Aanbeveling</th>
                            <th class="py-2 pr-4">Query / path</th>
                            <th class="py-2 pr-4">Impressions</th>
                            <th class="py-2 pr-4">Clicks</th>
                            <th class="py-2 pr-4">CTR</th>
                            <th class="py-2 pr-4">Positie</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($dashboard['priorities'] ?? [] as $row)
                            <tr>
                                <td class="py-3 pr-4 font-medium">{{ $row['recommendation'] }}</td>
                                <td class="py-3 pr-4">{{ $row['query'] ?? $row['path'] ?? '-' }}</td>
                                <td class="py-3 pr-4">{{ $row['impressions'] ?? 0 }}</td>
                                <td class="py-3 pr-4">{{ $row['clicks'] ?? 0 }}</td>
                                <td class="py-3 pr-4">{{ isset($row['ctr']) ? $formatCtr($row['ctr']) : '-' }}</td>
                                <td class="py-3 pr-4">{{ $row['position'] ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td class="py-3 text-gray-500" colspan="6">Geen prioriteiten gevonden.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
