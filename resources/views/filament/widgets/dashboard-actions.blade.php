@php
    $vehicles = \App\Models\Vehicle::where('user_id', auth()->id())
        ->latest()
        ->take(6)
        ->get();
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <div class="space-y-6">

            <div>
                <h2 class="text-lg font-bold mb-4"><strong>Mijn voertuigen</strong></h2>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4" style="margin:15px 0 0 0;">
                    @foreach($vehicles as $vehicle)
                        <div class="rounded-xl border border-gray-200 p-4 text-center">
                            <div class="flex justify-center mb-3">
                                @if($vehicle->photo)
                                    <img
                                        src="{{ \Illuminate\Support\Facades\Storage::url($vehicle->photo) }}"
                                        alt="{{ $vehicle->nickname ?: $vehicle->brand . ' ' . $vehicle->model }}"
                                        style="width: 100%; max-width: 280px; height: auto; border-radius: 12px;"
                                    >
                                @else
                                    <div style="width: 100%; max-width: 280px; aspect-ratio: 1 / 1; background: #f3f4f6; border-radius: 12px;"></div>
                                @endif
                            </div>

                            <div class="font-semibold" style="margin:15px 0 0 0;font-style:italic;">
                                {{ $vehicle->nickname ?: $vehicle->brand . ' ' . $vehicle->model }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div style="margin:30px 0 0 0;">
                <div style="display:flex; flex-direction:row; gap:15px; align-items:center;">
                    <a
                        href="/admin/vehicles"
                        class="fi-btn rounded-xl px-5 py-3"
                        style="display:inline-flex;"
                    >
                        Beheer je voertuigen
                    </a>

                    <a
                        href="/admin/maintenance-logs/create"
                        class="fi-btn rounded-xl px-5 py-3"
                        style="display:inline-flex;"
                    >
                        Voeg onderhoud toe
                    </a>
                </div>
            </div>

        </div>
    </x-filament::section>
</x-filament-widgets::widget>