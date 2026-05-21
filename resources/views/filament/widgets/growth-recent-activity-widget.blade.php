@php
    $renderActivityList = function (string $title, array $rows): void {
        echo '<div class="rounded-lg border border-gray-200 bg-gray-50 p-4">';
        echo '<h4 class="text-sm font-semibold text-gray-950">' . e($title) . '</h4>';

        if ($rows === []) {
            echo '<p class="mt-3 text-sm text-gray-600">Nog geen recente activiteit.</p>';
            echo '</div>';
            return;
        }

        echo '<ul class="mt-3 space-y-3">';
        foreach ($rows as $row) {
            echo '<li class="rounded-md border border-gray-200 bg-white px-3 py-3">';
            echo '<p class="text-sm font-medium text-gray-900">' . e($row['label']) . '</p>';
            echo '<p class="mt-1 text-xs text-gray-500">' . e($row['timestamp']) . ' · Bron: ' . e($row['source']) . '</p>';
            echo '</li>';
        }
        echo '</ul></div>';
    };
@endphp

<x-filament-widgets::widget>
    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <div class="mb-4">
            <h3 class="text-base font-semibold text-gray-950">Recente activiteit</h3>
            <p class="mt-1 text-sm text-gray-600">Laatste registraties, voertuigen en onderhoudslogs, met broninformatie waar die betrouwbaar bekend is.</p>
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
            @php($renderActivityList('Laatste 5 registraties', $registrations))
            @php($renderActivityList('Laatste 5 aangemaakte voertuigen', $vehicles))
            @php($renderActivityList('Laatste 5 onderhoudslogs', $maintenance_logs))
        </div>
    </div>
</x-filament-widgets::widget>
