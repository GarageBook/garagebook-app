@php($formatCtr = fn ($value) => number_format(((float) $value) * 100, 2, ',', '.') . '%')

<div class="overflow-x-auto">
    <table class="w-full min-w-[720px] text-left text-sm">
        <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
            <tr>
                @foreach($columns as $column)
                    <th class="py-2 pr-4">{{ str_replace('_', ' ', $column) }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @forelse($rows as $row)
                <tr>
                    @foreach($columns as $column)
                        <td class="py-3 pr-4">
                            @if($column === 'ctr' && isset($row[$column]))
                                {{ $formatCtr($row[$column]) }}
                            @else
                                {{ $row[$column] ?? '-' }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td class="py-3 text-gray-500" colspan="{{ count($columns) }}">Geen data gevonden.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
