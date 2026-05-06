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
            <label for="activeVehicleId" style="
                display:block;
                margin-bottom:8px;
                font-size:14px;
                font-weight:600;
                color:#111827;
            ">
                Actief voertuig
            </label>

            <select
                id="activeVehicleId"
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
    </div>
</div>
