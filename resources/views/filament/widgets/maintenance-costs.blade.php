<x-filament::widget>
    <x-filament::card>
        <h2 style="font-size:20px; font-weight:700; margin-bottom:18px;">
            Kosten
        </h2>

        @if($hasVehicles)
            <div style="
                display:grid;
                grid-template-columns:repeat(2, minmax(0, 1fr));
                gap:16px;
            ">
                <div style="
                    padding:18px;
                    border-radius:16px;
                    background:linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
                    border:1px solid #e2e8f0;
                ">
                    <div style="font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.08em;">
                        Totale kosten
                    </div>
                    <div style="margin-top:8px; font-size:24px; line-height:1.05; font-weight:800; color:#0f172a;">
                        EUR {{ number_format((float) $overallTotalCost, 2, ',', '.') }}
                    </div>
                </div>

                <div style="
                    padding:18px;
                    border-radius:16px;
                    background:linear-gradient(180deg, #fffdf2 0%, #fff7d1 100%);
                    border:1px solid #fde68a;
                ">
                    <div style="font-size:12px; font-weight:700; color:#92400e; text-transform:uppercase; letter-spacing:0.08em;">
                        Maandelijkse kosten
                    </div>
                    <div style="margin-top:8px; font-size:24px; line-height:1.05; font-weight:800; color:#111827;">
                        EUR {{ number_format((float) $overallMonthlyCost, 2, ',', '.') }}
                    </div>
                </div>
            </div>
        @else
            <div style="color:#9ca3af;">
                Nog geen kosten geregistreerd
            </div>
        @endif
    </x-filament::card>
</x-filament::widget>
