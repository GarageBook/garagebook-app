<div style="display:flex; flex-direction:column; gap:20px;">
    <x-filament-panels::header
        :actions="$actions"
        :heading="$heading"
        :subheading="$subheading"
    >
        @if ($heading instanceof \Illuminate\Contracts\Support\Htmlable)
            <x-slot name="heading">
                {{ $heading }}
            </x-slot>
        @endif

        @if ($subheading instanceof \Illuminate\Contracts\Support\Htmlable)
            <x-slot name="subheading">
                {{ $subheading }}
            </x-slot>
        @endif
    </x-filament-panels::header>

    <div style="
        display:flex;
        justify-content:space-between;
        align-items:end;
        gap:16px;
        flex-wrap:wrap;
    ">
        <div style="min-width:280px;">
            <label for="activeFuelVehicleId" style="
                display:block;
                margin-bottom:8px;
                font-size:14px;
                font-weight:600;
                color:#111827;
            ">
                Actief voertuig
            </label>

            <select
                id="activeFuelVehicleId"
                wire:model.live="activeVehicleId"
                style="
                    width:100%;
                    border:1px solid #d1d5db;
                    border-radius:12px;
                    min-height:42px;
                    padding:10px 42px 10px 14px;
                    background:#fff;
                    color:#111827;
                    font-size:14px;
                    font-weight:500;
                    line-height:1.25;
                    box-shadow:0 1px 2px rgba(16, 24, 40, 0.05);
                    appearance:none;
                    -webkit-appearance:none;
                    -moz-appearance:none;
                    background-image:url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 20 20%22 fill=%22none%22%3E%3Cpath d=%22M5 7.5 10 12.5 15 7.5%22 stroke=%22%236b7280%22 stroke-width=%221.8%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22/%3E%3C/svg%3E');
                    background-repeat:no-repeat;
                    background-position:right 14px center;
                    background-size:16px 16px;
                "
            >
                @forelse($vehicles as $vehicle)
                    <option value="{{ $vehicle->id }}">
                        {{ $vehicle->nickname ?: ($vehicle->brand . ' ' . $vehicle->model) }}
                    </option>
                @empty
                    <option value="">Geen voertuigen beschikbaar</option>
                @endforelse
            </select>
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

    @if($hasFuelLogs && $activeVehicle && $summary)
        <section style="
            display:grid;
            gap:16px;
            padding:24px;
            border-radius:28px;
            background:linear-gradient(145deg, rgba(15, 23, 42, 0.94), rgba(30, 41, 59, 0.92));
            color:white;
            box-shadow:0 24px 60px rgba(15, 23, 42, 0.10);
        ">
            <div style="display:flex; justify-content:space-between; gap:16px; align-items:end; flex-wrap:wrap;">
                <div>
                    <div style="font-size:12px; letter-spacing:0.16em; text-transform:uppercase; color:rgba(255,255,255,0.66);">
                        Verbruiksoverzicht
                    </div>
                    <div style="margin-top:6px; font-size:clamp(1.9rem, 4vw, 2.7rem); line-height:0.96; font-weight:700;">
                        {{ $activeVehicle->nickname ?: ($activeVehicle->brand . ' ' . $activeVehicle->model) }}
                    </div>
                    <div style="margin-top:10px; max-width:44rem; color:rgba(255,255,255,0.74); font-size:0.98rem; line-height:1.6;">
                        Alle tankbeurten van dit voertuig samengevat in liters, kosten, afstand en gemiddeld verbruik.
                    </div>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:12px;">
                <div style="padding:16px 18px; border-radius:18px; background:rgba(255,255,255,0.08);">
                    <div style="color:rgba(255,255,255,0.62); font-size:0.78rem; text-transform:uppercase; letter-spacing:0.08em;">Totaal km</div>
                    <div style="margin-top:6px; font-size:1.35rem; font-weight:700;">{{ number_format((float) $summary['distance_km'], 1, ',', '.') }} km</div>
                </div>
                <div style="padding:16px 18px; border-radius:18px; background:rgba(255,255,255,0.08);">
                    <div style="color:rgba(255,255,255,0.62); font-size:0.78rem; text-transform:uppercase; letter-spacing:0.08em;">Totaal liters</div>
                    <div style="margin-top:6px; font-size:1.35rem; font-weight:700;">{{ number_format((float) $summary['fuel_liters'], 2, ',', '.') }} L</div>
                </div>
                <div style="padding:16px 18px; border-radius:18px; background:rgba(255,255,255,0.08);">
                    <div style="color:rgba(255,255,255,0.62); font-size:0.78rem; text-transform:uppercase; letter-spacing:0.08em;">Brandstofkosten</div>
                    <div style="margin-top:6px; font-size:1.35rem; font-weight:700;">EUR {{ number_format((float) $summary['total_cost'], 2, ',', '.') }}</div>
                </div>
                <div style="padding:16px 18px; border-radius:18px; background:rgba(255,255,255,0.08);">
                    <div style="color:rgba(255,255,255,0.62); font-size:0.78rem; text-transform:uppercase; letter-spacing:0.08em;">Gemiddeld verbruik</div>
                    <div style="margin-top:6px; font-size:1.35rem; font-weight:700;">{{ $summary['average_label'] }}</div>
                </div>
            </div>
        </section>
    @endif
</div>
