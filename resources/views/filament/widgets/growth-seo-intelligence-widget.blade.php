@php
    $renderSeoTable = function (string $title, array $rows): void {
        echo '<div class="rounded-lg border border-gray-200 bg-gray-50 p-4">';
        echo '<h4 class="text-sm font-semibold text-gray-950">' . e($title) . '</h4>';

        if ($rows === []) {
            echo '<p class="mt-3 text-sm text-gray-600">Niet beschikbaar.</p>';
            echo '</div>';
            return;
        }

        echo '<div class="mt-3 overflow-x-auto">';
        echo '<table class="min-w-full divide-y divide-gray-200 text-sm">';
        echo '<thead class="text-left text-gray-600"><tr><th class="px-3 py-2 font-medium">Query / pagina</th><th class="px-3 py-2 font-medium">Clicks</th><th class="px-3 py-2 font-medium">Impressions</th><th class="px-3 py-2 font-medium">CTR</th><th class="px-3 py-2 font-medium">Gem. positie</th></tr></thead>';
        echo '<tbody class="divide-y divide-gray-100 text-gray-800">';

        foreach ($rows as $row) {
            echo '<tr>';
            echo '<td class="px-3 py-2">' . e($row['label']) . '</td>';
            echo '<td class="px-3 py-2">' . e(number_format($row['clicks'], 0, ',', '.')) . '</td>';
            echo '<td class="px-3 py-2">' . e(number_format($row['impressions'], 0, ',', '.')) . '</td>';
            echo '<td class="px-3 py-2">' . e($row['ctr'] !== null ? number_format($row['ctr'], 2, ',', '.') . '%' : '—') . '</td>';
            echo '<td class="px-3 py-2">' . e($row['position'] !== null ? number_format($row['position'], 2, ',', '.') : '—') . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table></div></div>';
    };
@endphp

<x-filament-widgets::widget>
    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <div class="mb-4">
            <h3 class="text-base font-semibold text-gray-950">SEO intelligence</h3>
            <p class="mt-1 text-sm text-gray-600">Search Console-overzichten op basis van lokaal opgeslagen query- en paginadata. Delta-kolommen zijn bewust weggelaten totdat ze betrouwbaar beschikbaar zijn.</p>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            @php($renderSeoTable('Top queries op clicks', $top_queries_by_clicks))
            @php($renderSeoTable('Top queries op impressions', $top_queries_by_impressions))
            @php($renderSeoTable('Queries met hoge impressies maar lage CTR', $high_impression_low_ctr_queries))
            @php($renderSeoTable('Queries met gemiddelde positie tussen 4 en 15', $position_opportunity_queries))
            @php($renderSeoTable('Top SEO landing pages', $top_pages))
            @php($renderSeoTable('SEO pages met hoge impressies maar lage CTR', $high_impression_low_ctr_pages))
        </div>
    </div>
</x-filament-widgets::widget>
