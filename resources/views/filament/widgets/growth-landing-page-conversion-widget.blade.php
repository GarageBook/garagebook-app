<x-filament-widgets::widget>
    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <div class="mb-4 flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
            <div>
                <h3 class="text-base font-semibold text-gray-950">Landingpage conversion</h3>
                <p class="mt-1 text-sm text-gray-600">Registraties per eerste landing page, aangevuld met GA4-paginausers waar een betrouwbare path-match beschikbaar is.</p>
            </div>
            <p class="text-xs font-medium uppercase tracking-wide text-amber-700">{{ $disclaimer }}</p>
        </div>

        @if ($rows === [])
            <p class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-sm text-gray-600">Nog geen landingpage-attribution beschikbaar.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-3 py-2 font-medium">Landing page</th>
                            <th class="px-3 py-2 font-medium">Visits/users</th>
                            <th class="px-3 py-2 font-medium">Registraties</th>
                            <th class="px-3 py-2 font-medium">Conversieratio</th>
                            <th class="px-3 py-2 font-medium">Top source</th>
                            <th class="px-3 py-2 font-medium">Laatste registratie</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-800">
                        @foreach ($rows as $row)
                            <tr>
                                <td class="px-3 py-2">{{ $row['landing_page'] }}</td>
                                <td class="px-3 py-2">{{ $row['visits'] !== null ? number_format($row['visits'], 0, ',', '.') : '—' }}</td>
                                <td class="px-3 py-2">{{ number_format($row['registrations'], 0, ',', '.') }}</td>
                                <td class="px-3 py-2">{{ $row['conversion_rate'] !== null ? number_format($row['conversion_rate'], 2, ',', '.') . '%' : '—' }}</td>
                                <td class="px-3 py-2">{{ $row['top_source'] }}</td>
                                <td class="px-3 py-2">{{ $row['latest_registration'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
