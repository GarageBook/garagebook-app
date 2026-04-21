<x-filament::widget>
    <x-filament::card>
        <h2 style="font-size:20px; font-weight:700; margin-bottom:20px;">
            Mijn voertuigen
        </h2>

        @forelse($vehicles as $vehicle)
            <div style="
                border-radius:16px;
                overflow:hidden;
                border:1px solid #e5e7eb;
                background:#fff;
                margin-bottom:16px;
            ">

                <!-- IMAGE -->
                <img
                    src="{{ $vehicle->photo 
                        ? asset('storage/' . $vehicle->photo) 
                        : asset('images/garagebook-hero-workshop-motor.jpg') }}"
                    alt="{{ $vehicle->brand }} {{ $vehicle->model }}"
                    style="
                        width:100%;
                        height:400px;
                        object-fit:cover;
                        display:block;
                    "
                >

                <!-- CONTENT -->
                <div style="padding:18px;">
                    <div style="
                        font-weight:700;
                        font-size:16px;
                        margin-bottom:4px;
                    ">
                        {{ $vehicle->nickname ?? ($vehicle->brand . ' ' . $vehicle->model) }}
                    </div>

                    <div style="
                        color:#6b7280;
                        font-size:14px;
                        margin-bottom:14px;
                    ">
                        {{ number_format($vehicle->current_km ?? 0) }} km
                    </div>

                    <div style="display:flex; gap:10px;">
                        <a href="/admin/vehicles/{{ $vehicle->id }}/edit"
                           style="
                            padding:10px 14px;
                            border-radius:10px;
                            background:#f3f4f6;
                            text-decoration:none;
                            font-size:13px;
                            color:#111827;
                           ">
                            Bekijken
                        </a>

                        <a href="/admin/maintenance-logs/create?vehicle_id={{ $vehicle->id }}"
                           style="
                            padding:10px 14px;
                            border-radius:10px;
                            background:#ffd200;
                            color:#000;
                            text-decoration:none;
                            font-size:13px;
                            font-weight:600;
                           ">
                            + Onderhoud
                        </a>
                    </div>
                </div>
            </div>

        @empty
            <div style="color:#9ca3af;">
                Geen voertuigen toegevoegd
            </div>
        @endforelse

    </x-filament::card>
</x-filament::widget>