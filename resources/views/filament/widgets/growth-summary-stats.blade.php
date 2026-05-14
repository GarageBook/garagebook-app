<x-filament::widget>
    <x-filament::section>
        <div style="display:flex; flex-direction:column; gap:18px;">
            <div>
                <h2 style="font-size:1.05rem; font-weight:700; color:#111827;">Analytics overzicht</h2>
                <p style="margin-top:6px; color:#6b7280; font-size:0.94rem;">Samenvatting van lokaal gesynchroniseerde GA4- en Search Console-data.</p>
            </div>

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
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament::widget>
