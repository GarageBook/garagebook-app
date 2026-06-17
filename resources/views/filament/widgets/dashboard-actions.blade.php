<x-filament-widgets::widget>
    <x-filament::section>
        <div class="space-y-6">
            <div>
                <div style="display:flex; flex-wrap:wrap; align-items:flex-start; justify-content:space-between; gap:16px;">
                    <div style="max-width:42rem; min-width:0;">
                        <h2 class="text-lg font-bold mb-3"><strong>Je GarageBook is actief</strong></h2>
                        <p style="margin:0; color:#64748b; line-height:1.7;">
                            Je onboarding is afgerond. Werk nu verder aan je historie met onderhoud, herinneringen, ritten, documenten en deelbare voertuiggegevens.
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

            @if ($booklet)
                <div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:16px; padding:20px; border-radius:20px; background:linear-gradient(180deg, #fffdf5 0%, #ffffff 100%); border:1px solid #fde68a;">
                    <div style="max-width:42rem; min-width:0;">
                        <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:#92400e; margin-bottom:8px;">
                            Jouw onderhoudsboekje
                        </div>
                        <div style="color:#0f172a; font-size:18px; font-weight:700; line-height:1.3;">
                            {{ $booklet['summary'] }}
                        </div>
                        <p style="margin:8px 0 0; color:#6b7280; font-size:14px; line-height:1.6;">
                            Download een nette PDF van je onderhoudshistorie en maak de waarde van je dossier direct tastbaar.
                        </p>
                    </div>

                    <div style="display:flex; flex-wrap:wrap; gap:10px;">
                        <x-filament::button
                            :href="$booklet['download_url']"
                            tag="a"
                            color="warning"
                            class="shadow-sm"
                            :attributes="new \Illuminate\View\ComponentAttributeBag($booklet['download_attributes'])"
                            style="background-color:#ffd200; color:#111827; min-width:fit-content;"
                        >
                            Download onderhoudsboekje
                        </x-filament::button>

                        @if ($booklet['public_cta'])
                            <x-filament::button
                                :href="$booklet['public_cta']['url']"
                                tag="a"
                                color="gray"
                                outlined
                                :attributes="new \Illuminate\View\ComponentAttributeBag($booklet['public_cta']['attributes'])"
                                style="min-width:fit-content;"
                            >
                                {{ $booklet['public_cta']['label'] }}
                            </x-filament::button>
                        @endif
                    </div>
                </div>
            @endif

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
