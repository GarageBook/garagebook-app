<x-filament::widget>
    <x-filament::card>
        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:20px; flex-wrap:wrap;">
            <div>
                <h2 style="font-size:20px; font-weight:700;">
                    Verbruik
                </h2>
                <div style="font-size:13px; color:#6b7280; margin-top:4px;">
                    Gemiddeld verbruik per voertuig
                </div>
            </div>

            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                @foreach($supportedUnits as $unitKey => $unitLabel)
                    <button
                        type="button"
                        wire:click="setConsumptionUnit('{{ $unitKey }}')"
                        style="
                            border:1px solid {{ $consumptionUnit === $unitKey ? '#111827' : '#d1d5db' }};
                            border-radius:999px;
                            padding:10px 14px;
                            font-size:13px;
                            font-weight:700;
                            background:{{ $consumptionUnit === $unitKey ? '#111827' : '#fff' }};
                            color:{{ $consumptionUnit === $unitKey ? '#fff' : '#111827' }};
                        "
                    >
                        {{ $unitLabel }}
                    </button>
                @endforeach
            </div>
        </div>

        <div style="display:flex; flex-direction:column; gap:12px;">
            @foreach($vehicles as $vehicle)
                <div style="
                    display:flex;
                    justify-content:space-between;
                    align-items:center;
                    gap:16px;
                    padding:16px;
                    border-radius:12px;
                    background:#f9fafb;
                    border:1px solid #e5e7eb;
                ">
                    <div>
                        <div style="font-weight:600; color:#111827;">
                            {{ $vehicle->nickname ?? ($vehicle->brand . ' ' . $vehicle->model) }}
                        </div>

                        <div style="font-size:13px; color:#6b7280; margin-top:4px;">
                            {{ $vehicle->brand }} {{ $vehicle->model }}
                        </div>
                    </div>

                    <div style="
                        font-size:16px;
                        font-weight:700;
                        color:#111827;
                        text-align:right;
                        white-space:nowrap;
                    ">
                        {{ $vehicle->fuel_average_label }}
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::card>
</x-filament::widget>
