@php
    use App\Filament\Resources\Vehicles\VehicleResource;
    use App\Services\PublicGarageService;
    use App\Support\Analytics;

    /** @var \App\Models\Vehicle|null $vehicle */
    $vehicle = $vehicle ?? $record ?? null;
    $context = $context ?? 'vehicle_detail';
    $title = $title ?? 'Publieke pagina';
    $description = $description ?? 'Deel deze pagina met kopers, vrienden of je garage.';
@endphp

@if($vehicle instanceof \App\Models\Vehicle)
    @php
        $publicGarage = app(PublicGarageService::class);
        $publicUrl = $vehicle->is_public ? $publicGarage->publicUrl($vehicle) : null;
        $publicStats = $publicGarage->publicStats($vehicle);
        $timelineItems = $publicGarage->publicTimelineItems($vehicle);
        $photoCount = $publicStats['public_vehicle_photo_count'] + collect($timelineItems)->sum(fn (array $item): int => count($item['public_image_attachments'] ?? []));
        $documentCount = $vehicle->documents()->count();
        $maintenanceCount = $publicStats['maintenance_count'];
        $editUrl = VehicleResource::getUrl('edit', ['record' => $vehicle]);
        $vehicleHash = Analytics::anonymizeIdentifier('vehicle', $vehicle->id);
        $viewAttributes = Analytics::clickTrackingAttributes('public_vehicle_page_view_clicked', [
            'location' => $context,
            'vehicle_id_hash' => $vehicleHash,
        ]);
        $copyAttributes = Analytics::clickTrackingAttributes('public_vehicle_page_link_copied', [
            'location' => $context,
            'vehicle_id_hash' => $vehicleHash,
        ]);
    @endphp

    <section style="border:1px solid #e5e7eb; border-radius:18px; background:#fff; padding:18px; box-shadow:0 1px 2px rgba(15,23,42,.04);">
        <div style="display:flex; justify-content:space-between; gap:14px; align-items:flex-start; flex-wrap:wrap;">
            <div style="min-width:0; max-width:42rem;">
                <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:8px;">
                    <h3 style="margin:0; color:#111827; font-size:1.05rem; line-height:1.25; font-weight:700;">{{ $title }}</h3>
                    <span style="display:inline-flex; align-items:center; border-radius:999px; padding:4px 9px; font-size:.78rem; font-weight:700; background:{{ $vehicle->is_public ? '#dcfce7' : '#f3f4f6' }}; color:{{ $vehicle->is_public ? '#166534' : '#374151' }};">
                        {{ $vehicle->is_public ? 'Openbaar' : 'Niet openbaar' }}
                    </span>
                </div>

                <p style="margin:0; color:#4b5563; font-size:.94rem; line-height:1.6;">
                    {{ $vehicle->is_public ? $description : 'Zet deze voertuigpagina openbaar om je onderhoudshistorie makkelijk te delen met kopers, vrienden of je garage.' }}
                </p>
            </div>

            <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                @if($vehicle->is_public)
                    <a
                        href="{{ $publicUrl }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="fi-btn fi-btn-color-warning fi-btn-outlined rounded-lg px-3 py-2 text-sm"
                        @foreach($viewAttributes as $attribute => $value)
                            {{ $attribute }}="{{ $value }}"
                        @endforeach
                    >
                        Bekijk publieke pagina
                    </a>

                    <button
                        type="button"
                        class="fi-btn fi-btn-color-gray fi-btn-outlined rounded-lg px-3 py-2 text-sm"
                        x-data="{ copied: false }"
                        x-on:click="navigator.clipboard.writeText(@js($publicUrl)); copied = true; setTimeout(() => copied = false, 1800)"
                        @foreach($copyAttributes as $attribute => $value)
                            {{ $attribute }}="{{ $value }}"
                        @endforeach
                    >
                        <span x-show="! copied">Kopieer link</span>
                        <span x-cloak x-show="copied">Gekopieerd</span>
                    </button>
                @else
                    <a href="{{ $editUrl }}" class="fi-btn fi-btn-color-warning rounded-lg px-3 py-2 text-sm">
                        Publieke pagina activeren
                    </a>
                @endif
            </div>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(min(100%, 150px), 1fr)); gap:10px; margin-top:16px;">
            <div style="border:1px solid #e5e7eb; border-radius:12px; padding:12px; background:#f9fafb;">
                <div style="font-size:.78rem; color:#6b7280; font-weight:700; text-transform:uppercase; letter-spacing:.06em;">Onderhoud</div>
                <div style="font-size:1.35rem; color:#111827; font-weight:700; line-height:1.1; margin-top:4px;">{{ $maintenanceCount }}</div>
            </div>
            <div style="border:1px solid #e5e7eb; border-radius:12px; padding:12px; background:#f9fafb;">
                <div style="font-size:.78rem; color:#6b7280; font-weight:700; text-transform:uppercase; letter-spacing:.06em;">Foto's</div>
                <div style="font-size:1.35rem; color:#111827; font-weight:700; line-height:1.1; margin-top:4px;">{{ $photoCount }}</div>
            </div>
            <div style="border:1px solid #e5e7eb; border-radius:12px; padding:12px; background:#f9fafb;">
                <div style="font-size:.78rem; color:#6b7280; font-weight:700; text-transform:uppercase; letter-spacing:.06em;">Documenten</div>
                <div style="font-size:1.35rem; color:#111827; font-weight:700; line-height:1.1; margin-top:4px;">{{ $documentCount }}</div>
            </div>
        </div>
    </section>
@endif
