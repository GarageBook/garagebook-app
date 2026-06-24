<x-filament::widget>
    <x-filament::section>
        <div style="display:flex; flex-direction:column; gap:18px;">
            <div>
                <h2 style="font-size:1.05rem; font-weight:700; color:#111827;">Analytics overzicht</h2>
                <p style="margin-top:6px; color:#6b7280; font-size:0.94rem;">Primaire product-KPI's uit de database en lokaal gesynchroniseerde GA4- en Search Console-data.</p>
            </div>

            <div style="display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:14px;">
                @foreach ($productStats as $stat)
                    <div style="padding:18px; border-radius:18px; background:#ffffff; border:1px solid #e5e7eb;">
                        <div style="font-size:0.82rem; color:#6b7280;">{{ $stat['label'] }}</div>
                        <div style="margin-top:8px; font-size:1.7rem; line-height:1; font-weight:700; color:#111827;">
                            {{ $stat['value'] === null ? 'niet beschikbaar' : number_format($stat['value'], 0, ',', '.') }}
                        </div>
                    </div>
                @endforeach
            </div>

            @if (! empty($syncWarnings))
                <div style="padding:12px 14px; border-radius:14px; background:#fffbeb; color:#92400e; border:1px solid #fde68a; font-size:0.9rem;">
                    {{ implode(' · ', $syncWarnings) }}
                </div>
            @endif

            @if (! $hasAnyData)
                <div style="padding:18px 20px; border-radius:18px; background:#f9fafb; color:#4b5563;">
                    {{ $emptyStateMessage }}
                </div>
            @else
                <div style="display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:14px;">
                    @foreach ($stats as $stat)
                        <div style="padding:18px; border-radius:18px; background:#ffffff; border:1px solid #e5e7eb;">
                            <div style="font-size:0.82rem; color:#6b7280;">{{ $stat['label'] }}</div>
                            <div style="margin-top:8px; font-size:1.7rem; line-height:1; font-weight:700; color:#111827;">
                                {{ $stat['value'] }}
                            </div>
                            @if (! empty($stat['meta']))
                                <div style="margin-top:8px; font-size:0.78rem; color:#6b7280;">{{ $stat['meta'] }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament::widget>
