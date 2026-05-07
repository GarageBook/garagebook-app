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
            <label for="activeDocumentVehicleId" style="
                display:block;
                margin-bottom:8px;
                font-size:14px;
                font-weight:600;
                color:#111827;
            ">
                Actief voertuig
            </label>

            <select
                id="activeDocumentVehicleId"
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

    @if($activeVehicle)
        <section style="
            display:grid;
            gap:16px;
            padding:24px;
            border-radius:28px;
            background:linear-gradient(145deg, rgba(15, 23, 42, 0.94), rgba(30, 41, 59, 0.92));
            color:white;
            box-shadow:0 24px 60px rgba(15, 23, 42, 0.10);
        ">
            <div>
                <div style="font-size:12px; letter-spacing:0.16em; text-transform:uppercase; color:rgba(255,255,255,0.66);">
                    Prive documentkluis
                </div>
                <div style="margin-top:6px; font-size:clamp(1.9rem, 4vw, 2.7rem); line-height:0.96; font-weight:700;">
                    {{ $activeVehicle->nickname ?: ($activeVehicle->brand . ' ' . $activeVehicle->model) }}
                </div>
                <div style="margin-top:10px; max-width:44rem; color:rgba(255,255,255,0.74); font-size:0.98rem; line-height:1.6;">
                    Upload hier prive documenten zoals verzekeringsbewijzen, garantiebewijzen, aankoopbewijzen, kentekenbewijs, handleidingen, PDF-bestanden of video-opnames. Deze documentkluis is alleen zichtbaar binnen jouw account en bestanden worden nooit gedeeld of openbaar gepubliceerd.
                </div>
            </div>

            <div style="display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:12px;">
                <div style="padding:16px 18px; border-radius:18px; background:rgba(255,255,255,0.08);">
                    <div style="color:rgba(255,255,255,0.62); font-size:0.78rem; text-transform:uppercase; letter-spacing:0.08em;">Documenten</div>
                    <div style="margin-top:6px; font-size:1.35rem; font-weight:700;">{{ $activeVehicle->documents_count }}</div>
                </div>
                <div style="padding:16px 18px; border-radius:18px; background:rgba(255,255,255,0.08);">
                    <div style="color:rgba(255,255,255,0.62); font-size:0.78rem; text-transform:uppercase; letter-spacing:0.08em;">Privacy</div>
                    <div style="margin-top:6px; font-size:1.1rem; font-weight:700;">
                        {{ $hasDocuments ? 'Alleen zichtbaar voor jou' : '100% prive in jouw account' }}
                    </div>
                </div>
            </div>
        </section>
    @endif
</div>
