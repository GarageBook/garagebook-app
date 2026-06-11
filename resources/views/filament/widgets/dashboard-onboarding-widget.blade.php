@php
    $progressLabel = $progress['completed_steps'] . ' van ' . $progress['total_steps'] . ' stappen voltooid';
    $stepStyles = [
        'completed' => [
            'badge' => 'background:#ecfdf3; color:#166534; border:1px solid #bbf7d0;',
            'dot' => 'background:#16a34a; color:#fff; border:1px solid #16a34a;',
            'label' => 'Voltooid',
        ],
        'open' => [
            'badge' => 'background:#f8fafc; color:#475569; border:1px solid #e2e8f0;',
            'dot' => 'background:#fff; color:#94a3b8; border:1px solid #cbd5e1;',
            'label' => 'Open',
        ],
    ];
@endphp

<x-filament::widget>
    <x-filament::card>
        <div style="
            display:flex;
            flex-direction:column;
            gap:24px;
            padding:4px;
        ">
            <div style="
                display:flex;
                flex-direction:column;
                gap:18px;
            ">
                <div style="
                    display:flex;
                    flex-wrap:wrap;
                    align-items:flex-start;
                    justify-content:space-between;
                    gap:16px;
                ">
                    <div style="max-width:44rem; min-width:0;">
                        <div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-bottom:10px;">
                            <span style="
                                display:inline-flex;
                                align-items:center;
                                border-radius:999px;
                                background:linear-gradient(180deg, #fffbe6 0%, #fff3bf 100%);
                                border:1px solid #fde68a;
                                color:#92400e;
                                font-size:12px;
                                font-weight:700;
                                letter-spacing:0.02em;
                                padding:6px 10px;
                            ">
                                {{ $progressLabel }}
                            </span>

                            @if ($progress['has_vehicle'] && ! $progress['has_maintenance'])
                                <span style="
                                    display:inline-flex;
                                    align-items:center;
                                    border-radius:999px;
                                    background:#f8fafc;
                                    border:1px solid #e2e8f0;
                                    color:#0f172a;
                                    font-size:12px;
                                    font-weight:600;
                                    padding:6px 10px;
                                ">
                                    Eerste stap staat al
                                </span>
                            @endif
                        </div>

                        <h2 style="
                            margin:0;
                            color:#0f172a;
                            font-size:clamp(1.375rem, 2.2vw, 1.875rem);
                            line-height:1.1;
                            font-weight:700;
                            letter-spacing:-0.03em;
                        ">{{ $title }}</h2>

                        <p style="
                            margin:12px 0 0;
                            color:#475569;
                            font-size:15px;
                            line-height:1.7;
                            max-width:42rem;
                        ">{{ $description }}</p>

                        @if ($microcopy)
                            <p style="
                                margin:10px 0 0;
                                color:#64748b;
                                font-size:13px;
                                line-height:1.6;
                            ">{{ $microcopy }}</p>
                        @endif
                    </div>

                    <div style="
                        min-width:min(100%, 220px);
                        padding:16px 18px;
                        border-radius:18px;
                        background:linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
                        border:1px solid #e2e8f0;
                        box-shadow:0 10px 30px rgba(15, 23, 42, 0.04);
                    ">
                        <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:#64748b;">
                            Inrichting
                        </div>
                        <div style="margin-top:8px; font-size:26px; line-height:1; font-weight:800; color:#0f172a;">
                            {{ $progress['completion_percentage'] }}%
                        </div>
                        <p style="margin:8px 0 0; color:#64748b; font-size:13px; line-height:1.5;">
                            Je onderhoudshistorie is voor {{ $progress['completion_percentage'] }}% ingericht.
                        </p>
                    </div>
                </div>

                <div>
                    <div style="
                        width:100%;
                        height:10px;
                        border-radius:999px;
                        background:#eef2f7;
                        overflow:hidden;
                    ">
                        <div style="
                            width:{{ $progress['completion_percentage'] }}%;
                            height:100%;
                            border-radius:999px;
                            background:linear-gradient(90deg, #ffd200 0%, #f59e0b 100%);
                            box-shadow:0 4px 12px rgba(245, 158, 11, 0.22);
                        "></div>
                    </div>
                </div>
            </div>

            <div style="
                display:grid;
                grid-template-columns:repeat(auto-fit, minmax(min(100%, 220px), 1fr));
                gap:14px;
            ">
                @foreach ($progress['steps'] as $step)
                    @php
                        $style = $stepStyles[$step['status']];
                    @endphp
                    <div style="
                        padding:18px;
                        border-radius:20px;
                        border:1px solid #e2e8f0;
                        background:{{ $step['status'] === 'completed' ? 'linear-gradient(180deg, #ffffff 0%, #f8fafc 100%)' : '#ffffff' }};
                        box-shadow:0 8px 24px rgba(15, 23, 42, 0.04);
                    ">
                        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:14px;">
                            <span style="
                                display:inline-flex;
                                align-items:center;
                                justify-content:center;
                                width:32px;
                                height:32px;
                                border-radius:999px;
                                font-size:14px;
                                font-weight:700;
                                {{ $style['dot'] }}
                            ">
                                {{ $step['status'] === 'completed' ? '✓' : $loop->iteration }}
                            </span>

                            <span style="
                                display:inline-flex;
                                align-items:center;
                                border-radius:999px;
                                padding:5px 9px;
                                font-size:12px;
                                font-weight:600;
                                {{ $style['badge'] }}
                            ">
                                {{ $style['label'] }}{{ $step['is_optional'] ? ' · Optioneel' : '' }}
                            </span>
                        </div>

                        <div style="color:#0f172a; font-size:15px; font-weight:700; line-height:1.4; margin-bottom:6px;">
                            {{ $step['label'] }}
                        </div>

                        <div style="color:#64748b; font-size:13px; line-height:1.6;">
                            @if ($step['key'] === 'vehicle')
                                Voeg je voertuig toe als start van je digitale onderhoudsdossier.
                            @elseif ($step['key'] === 'maintenance')
                                Leg een recente beurt of reparatie vast en geef je historie direct waarde.
                            @else
                                Bewaar een factuur of foto als extra onderbouwing zolang je toch bezig bent.
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div style="
                display:flex;
                flex-wrap:wrap;
                align-items:center;
                justify-content:space-between;
                gap:16px;
                padding:20px;
                border-radius:20px;
                background:linear-gradient(180deg, #fffdf5 0%, #ffffff 100%);
                border:1px solid #fde68a;
            ">
                <div style="max-width:38rem; min-width:0;">
                    <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:#92400e; margin-bottom:8px;">
                        Aanbevolen volgende stap
                    </div>
                    <div style="color:#0f172a; font-size:18px; font-weight:700; line-height:1.3;">
                        {{ $primaryCta['label'] }}
                    </div>
                    <p style="margin:8px 0 0; color:#6b7280; font-size:14px; line-height:1.6;">
                        @if ($progress['next_step'] === 'maintenance')
                            Deze registratie maakt van een toegevoegd voertuig direct een bruikbare onderhoudsgeschiedenis.
                        @elseif ($progress['next_step'] === 'document')
                            Niet nodig voor activatie, wel handig als extra bewijs in je dossier.
                        @else
                            Begin met je voertuig, daarna kun je meteen je eerste onderhoud vastleggen.
                        @endif
                    </p>
                </div>

                <x-filament::button
                    :href="$primaryCta['url']"
                    tag="a"
                    color="warning"
                    class="shadow-sm"
                    :attributes="new \Illuminate\View\ComponentAttributeBag($primaryCta['attributes'])"
                    style="background-color:#ffd200; color:#111827; min-width:fit-content;"
                >
                    {{ $primaryCta['label'] }}
                </x-filament::button>
            </div>
        </div>
    </x-filament::card>
</x-filament::widget>
