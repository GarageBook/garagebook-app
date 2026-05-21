<x-filament-widgets::widget>
    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <div class="mb-4">
            <h3 class="text-base font-semibold text-gray-950">PR / partner performance</h3>
            <p class="mt-1 text-sm text-gray-600">Herkenbare partner- en PR-bronnen op basis van opgeslagen attribution- en registratiebrondata.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-gray-600">
                    <tr>
                        <th class="px-3 py-2 font-medium">Partner/source</th>
                        <th class="px-3 py-2 font-medium">Clicks/bezoeken</th>
                        <th class="px-3 py-2 font-medium">Registraties</th>
                        <th class="px-3 py-2 font-medium">Conversieratio</th>
                        <th class="px-3 py-2 font-medium">Laatste registratie</th>
                        <th class="px-3 py-2 font-medium">Opmerking/status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-800">
                    @foreach ($rows as $row)
                        <tr>
                            <td class="px-3 py-2">{{ $row['partner'] }}</td>
                            <td class="px-3 py-2">{{ $row['visits'] !== null ? number_format($row['visits'], 0, ',', '.') : '—' }}</td>
                            <td class="px-3 py-2">{{ number_format($row['registrations'], 0, ',', '.') }}</td>
                            <td class="px-3 py-2">{{ $row['conversion_rate'] !== null ? number_format($row['conversion_rate'], 2, ',', '.') . '%' : '—' }}</td>
                            <td class="px-3 py-2">{{ $row['latest_registration'] }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $row['status'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-filament-widgets::widget>
