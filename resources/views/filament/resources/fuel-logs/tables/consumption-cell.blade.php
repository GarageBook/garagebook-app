<div style="display:flex; flex-direction:column; gap:12px; min-width:240px; max-width:320px;">
    @if (filled($mpgLabel ?? null))
        <div style="display:flex; align-items:baseline; gap:10px; flex-wrap:wrap;">
            <span style="font-size:1.1rem; font-weight:800; line-height:1; color:#111827;">
                {{ $mpgLabel }}
            </span>
            <span style="font-size:0.9rem; font-weight:500; color:#374151;">
                MPG (US)
            </span>
        </div>

        <div style="height:1px; width:100%; background:repeating-linear-gradient(to right, #dbe1ea 0 8px, transparent 8px 14px);"></div>
    @endif

    <div style="display:flex; align-items:baseline; gap:12px; flex-wrap:wrap; line-height:1.3;">
        <span style="font-size:0.98rem; font-weight:600; color:#2f7d32;">
            {{ $litersPer100KmLabel }}
        </span>

        @if (filled($ratioLabel ?? null))
            <span style="color:#c7cdd6;">|</span>
            <span style="font-size:0.98rem; font-weight:600; color:#2563eb;">
                {{ $ratioLabel }}
            </span>
        @endif
    </div>

    @if (filled($distanceLabel ?? null))
        <div style="font-size:0.8rem; color:#94a3b8; line-height:1.3;">
            ({{ $distanceLabel }} gereden)
        </div>
    @endif
</div>
