@php
    $rows = collect($rows ?? [])->values();
    $limit = $limit ?? 10;
    $defaultRows = $rows->take($limit);
    $rowKey = $rowKey ?? str($title)->slug()->toString();
    $labelColumn = $isDate ?? false ? 'Datum' : 'Dimensie';
@endphp

@if($rows->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400">Geen data geimporteerd voor deze dimensie.</p>
@else
    <div class="overflow-x-auto">
        <table class="w-full min-w-[620px] text-left text-sm">
            <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                <tr>
                    <th class="py-2 pr-4">{{ $labelColumn }}</th>
                    <th class="py-2 pr-4">Clicks</th>
                    <th class="py-2 pr-4">Impressions</th>
                    <th class="py-2 pr-4">CTR</th>
                    <th class="py-2 pr-4">Positie</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach($defaultRows as $row)
                    <tr data-gsc-dimension-row="{{ $rowKey }}-default">
                        <td class="py-2 pr-4">{{ $row['label'] ?? $row['date'] ?? '-' }}</td>
                        <td class="py-2 pr-4">{{ $row['clicks'] ?? 0 }}</td>
                        <td class="py-2 pr-4">{{ $row['impressions'] ?? 0 }}</td>
                        <td class="py-2 pr-4">{{ isset($row['ctr']) ? $formatCtr($row['ctr']) : '-' }}</td>
                        <td class="py-2 pr-4">{{ $row['position'] ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if($rows->count() > $limit)
        <details class="mt-3 rounded-lg border border-gray-200 p-3 text-sm dark:border-gray-800">
            <summary class="cursor-pointer font-medium text-gray-700 dark:text-gray-200">{{ $expandLabel }}</summary>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full min-w-[620px] text-left text-sm">
                    <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="py-2 pr-4">{{ $labelColumn }}</th>
                            <th class="py-2 pr-4">Clicks</th>
                            <th class="py-2 pr-4">Impressions</th>
                            <th class="py-2 pr-4">CTR</th>
                            <th class="py-2 pr-4">Positie</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($rows as $row)
                            <tr data-gsc-dimension-row="{{ $rowKey }}-full">
                                <td class="py-2 pr-4">{{ $row['label'] ?? $row['date'] ?? '-' }}</td>
                                <td class="py-2 pr-4">{{ $row['clicks'] ?? 0 }}</td>
                                <td class="py-2 pr-4">{{ $row['impressions'] ?? 0 }}</td>
                                <td class="py-2 pr-4">{{ isset($row['ctr']) ? $formatCtr($row['ctr']) : '-' }}</td>
                                <td class="py-2 pr-4">{{ $row['position'] ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif
@endif
