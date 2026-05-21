<x-filament-widgets::widget>
    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <div class="mb-4 flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
            <div>
                <h3 class="text-base font-semibold text-gray-950">Acquisitie</h3>
                <p class="mt-1 text-sm text-gray-600">Source-, medium- en campaign-overzicht voor registraties.</p>
            </div>
            <p class="text-xs font-medium uppercase tracking-wide text-amber-700">{{ $disclaimer }}</p>
        </div>

        @if ($rows === [])
            <p class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-sm text-gray-600">Nog geen attributionregistraties beschikbaar.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-3 py-2 font-medium">Source</th>
                            <th class="px-3 py-2 font-medium">Medium</th>
                            <th class="px-3 py-2 font-medium">Campaign</th>
                            <th class="px-3 py-2 font-medium">Visits/users</th>
                            <th class="px-3 py-2 font-medium">Registraties</th>
                            <th class="px-3 py-2 font-medium">Conversieratio</th>
                            <th class="px-3 py-2 font-medium">Laatste activiteit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-800">
                        @foreach ($rows as $row)
                            <tr>
                                <td class="px-3 py-2">{{ $row['source'] }}</td>
                                <td class="px-3 py-2">{{ $row['medium'] }}</td>
                                <td class="px-3 py-2">{{ $row['campaign'] }}</td>
                                <td class="px-3 py-2">{{ $row['visits'] !== null ? number_format($row['visits'], 0, ',', '.') : '—' }}</td>
                                <td class="px-3 py-2">{{ number_format($row['registrations'], 0, ',', '.') }}</td>
                                <td class="px-3 py-2">{{ $row['conversion_rate'] !== null ? number_format($row['conversion_rate'], 2, ',', '.') . '%' : '—' }}</td>
                                <td class="px-3 py-2">{{ $row['latest_activity'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
