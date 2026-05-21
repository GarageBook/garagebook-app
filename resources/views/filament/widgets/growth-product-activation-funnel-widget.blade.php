<x-filament-widgets::widget>
    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <div class="mb-4">
            <h3 class="text-base font-semibold text-gray-950">Funnel / activatie</h3>
            <p class="mt-1 text-sm text-gray-600">Productactivatie op basis van gebruikers-, voertuigen-, onderhouds-, document- en fueldata uit de database.</p>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4"><p class="text-sm text-gray-600">Totaal users</p><p class="mt-2 text-2xl font-semibold text-gray-950">{{ number_format($stats['total_users'], 0, ',', '.') }}</p></div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4"><p class="text-sm text-gray-600">Users met minimaal 1 voertuig</p><p class="mt-2 text-2xl font-semibold text-gray-950">{{ number_format($stats['users_with_vehicle'], 0, ',', '.') }}</p></div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4"><p class="text-sm text-gray-600">Users met minimaal 1 maintenance log</p><p class="mt-2 text-2xl font-semibold text-gray-950">{{ number_format($stats['users_with_maintenance'], 0, ',', '.') }}</p></div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4"><p class="text-sm text-gray-600">Users met minimaal 3 maintenance logs</p><p class="mt-2 text-2xl font-semibold text-gray-950">{{ number_format($stats['users_with_three_maintenance'], 0, ',', '.') }}</p></div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4"><p class="text-sm text-gray-600">Users met minimaal 1 document/upload</p><p class="mt-2 text-2xl font-semibold text-gray-950">{{ number_format($stats['users_with_documents'], 0, ',', '.') }}</p></div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4"><p class="text-sm text-gray-600">Users met fuel entries</p><p class="mt-2 text-2xl font-semibold text-gray-950">{{ number_format($stats['users_with_fuel_entries'], 0, ',', '.') }}</p></div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4"><p class="text-sm text-gray-600">Users actief laatste 7 dagen</p><p class="mt-2 text-2xl font-semibold text-gray-950">{{ number_format($stats['active_last_7_days'], 0, ',', '.') }}</p></div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4"><p class="text-sm text-gray-600">Users actief laatste 30 dagen</p><p class="mt-2 text-2xl font-semibold text-gray-950">{{ number_format($stats['active_last_30_days'], 0, ',', '.') }}</p></div>
        </div>

        <div class="mt-6 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-gray-600">
                    <tr>
                        <th class="px-3 py-2 font-medium">Stap</th>
                        <th class="px-3 py-2 font-medium">Aantal users</th>
                        <th class="px-3 py-2 font-medium">Percentage van registraties</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-800">
                    @foreach ($funnel as $row)
                        <tr>
                            <td class="px-3 py-2">{{ $row['step'] }}</td>
                            <td class="px-3 py-2">{{ number_format($row['count'], 0, ',', '.') }}</td>
                            <td class="px-3 py-2">{{ number_format($row['percentage'], 1, ',', '.') }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-filament-widgets::widget>
