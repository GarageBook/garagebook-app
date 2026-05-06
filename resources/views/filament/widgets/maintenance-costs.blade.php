<x-filament::widget>
    <x-filament::card>
        <h2 style="font-size:20px; font-weight:700; margin-bottom:20px;">
            Onderhoudskosten
        </h2>

        <div style="display:flex; flex-direction:column; gap:12px;">
            @forelse($vehicles as $vehicle)
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
                        EUR {{ number_format((float) ($vehicle->maintenance_costs_total ?? 0), 2, ',', '.') }}
                    </div>
                </div>
            @empty
                <div style="color:#9ca3af;">
                    Nog geen onderhoudskosten geregistreerd
                </div>
            @endforelse
        </div>

        @if($vehicles->isNotEmpty())
            <div style="
                display:flex;
                justify-content:space-between;
                align-items:center;
                margin-top:20px;
                padding-top:16px;
                border-top:1px solid #e5e7eb;
                font-size:16px;
            ">
                <span style="font-weight:600; color:#111827;">Totaal</span>
                <span style="font-weight:700; color:#111827;">
                    EUR {{ number_format((float) $totalCost, 2, ',', '.') }}
                </span>
            </div>
        @endif
    </x-filament::card>
</x-filament::widget>
