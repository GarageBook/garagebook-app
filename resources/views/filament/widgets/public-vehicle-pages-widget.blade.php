<x-filament-widgets::widget>
    <x-filament::section>
        <div style="display:grid; gap:16px;">
            <div style="display:flex; justify-content:space-between; gap:14px; align-items:flex-start; flex-wrap:wrap;">
                <div style="min-width:0; max-width:42rem;">
                    <h2 class="text-lg font-bold mb-2"><strong>Publieke voertuigpagina's</strong></h2>
                    <p style="margin:0; color:#64748b; line-height:1.65;">
                        Maak je onderhoudshistorie zichtbaar voor kopers, vrienden of je garage.
                    </p>
                </div>
            </div>

            @if($publicVehicles->isEmpty())
                <div style="display:flex; justify-content:space-between; gap:14px; align-items:center; flex-wrap:wrap; border:1px solid #e5e7eb; border-radius:14px; background:#f8fafc; padding:14px;">
                    <p style="margin:0; color:#475569; line-height:1.55;">
                        Je hebt nog geen openbare voertuigpagina. Zet je eerste publieke voertuigpagina live wanneer je klaar bent om je historie te delen.
                    </p>
                    @if($activationUrl)
                        <a href="{{ $activationUrl }}" class="fi-btn fi-btn-color-warning rounded-lg px-3 py-2 text-sm">
                            Zet je eerste publieke voertuigpagina live
                        </a>
                    @endif
                </div>
            @else
                <div style="display:grid; gap:10px;">
                    @foreach($publicVehicles as $vehicle)
                        @php
                            $publicUrl = $vehicle['public_url'];
                        @endphp
                        <div style="display:grid; grid-template-columns:minmax(0,1fr); gap:10px; border:1px solid #e5e7eb; border-radius:14px; padding:14px; background:#fff;">
                            <div style="min-width:0;">
                                <div style="font-weight:700; color:#111827; overflow-wrap:anywhere;">{{ $vehicle['name'] }}</div>
                                <div style="color:#64748b; font-size:.9rem; margin-top:2px;">{{ $vehicle['maintenance_count'] }} onderhoudslog{{ $vehicle['maintenance_count'] === 1 ? '' : 's' }}</div>
                            </div>

                            <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                                <a
                                    href="{{ $publicUrl }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="fi-btn fi-btn-color-warning fi-btn-outlined rounded-lg px-3 py-2 text-sm"
                                    @foreach($vehicle['view_attributes'] as $attribute => $value)
                                        {{ $attribute }}="{{ $value }}"
                                    @endforeach
                                >
                                    Bekijken
                                </a>
                                <button
                                    type="button"
                                    class="fi-btn fi-btn-color-gray fi-btn-outlined rounded-lg px-3 py-2 text-sm"
                                    x-data="{ copied: false }"
                                    x-on:click="navigator.clipboard.writeText(@js($publicUrl)); copied = true; setTimeout(() => copied = false, 1800)"
                                    @foreach($vehicle['copy_attributes'] as $attribute => $value)
                                        {{ $attribute }}="{{ $value }}"
                                    @endforeach
                                >
                                    <span x-show="! copied">Kopieer link</span>
                                    <span x-cloak x-show="copied">Gekopieerd</span>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
