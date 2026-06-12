<x-filament-widgets::widget>
    <x-filament::section>
        <div class="space-y-6">
            <div>
                <div style="display:flex; flex-wrap:wrap; align-items:flex-start; justify-content:space-between; gap:16px;">
                    <div style="max-width:42rem; min-width:0;">
                        <h2 class="text-lg font-bold mb-3"><strong>Je GarageBook is actief</strong></h2>
                        <p style="margin:0; color:#64748b; line-height:1.7;">
                            Je onboarding is afgerond. Werk nu verder aan je historie met onderhoud, ritten, documenten en deelbare voertuiggegevens.
                        </p>
                    </div>

                    @if ($vehicle)
                        <div style="padding:14px 16px; border-radius:16px; border:1px solid #e2e8f0; background:#f8fafc; min-width:220px;">
                            <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:#64748b;">Actief voertuig</div>
                            <div style="margin-top:8px; font-size:18px; font-weight:700; color:#0f172a; overflow-wrap:anywhere;">
                                {{ $vehicle->nickname ?: $vehicle->brand . ' ' . $vehicle->model }}
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(min(100%, 220px), 1fr)); gap:14px;">
                @foreach ($actions as $action)
                    <a
                        href="{{ $action['url'] }}"
                        class="fi-btn fi-btn-color-gray fi-btn-outlined rounded-xl px-5 py-4"
                        @foreach ($action['attributes'] as $attribute => $value)
                            {{ $attribute }}="{{ $value }}"
                        @endforeach
                        style="display:flex; align-items:center; justify-content:center; min-height:72px; text-align:center; white-space:normal; overflow-wrap:anywhere;"
                    >
                        {{ $action['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
