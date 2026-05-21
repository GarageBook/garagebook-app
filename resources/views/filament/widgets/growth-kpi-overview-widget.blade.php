<x-filament-widgets::widget>
    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <div class="mb-4">
            <h3 class="text-base font-semibold text-gray-950">KPI-overzicht</h3>
            <p class="mt-1 text-sm text-gray-600">Bezoekersdata komt uit lokaal opgeslagen GA4-dagstatistieken. Registraties komen direct uit de gebruikersdatabase.</p>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($cards as $card)
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <p class="text-sm text-gray-600">{{ $card['label'] }}</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-950">
                        @if ($card['is_available'])
                            @if (is_numeric($card['value']) && ! isset($card['suffix']))
                                {{ number_format((float) $card['value'], 0, ',', '.') }}
                            @elseif (is_numeric($card['value']) && isset($card['suffix']))
                                {{ number_format((float) $card['value'], 2, ',', '.') }}{{ $card['suffix'] }}
                            @else
                                {{ $card['value'] }}
                            @endif
                        @else
                            niet beschikbaar
                        @endif
                    </p>
                    @if (! empty($card['meta']))
                        <p class="mt-2 text-xs text-gray-500">{{ $card['meta'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</x-filament-widgets::widget>
