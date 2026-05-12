<div style="display:flex; flex-direction:column; gap:10px; min-width:0;">
    <span style="
        display:inline-flex;
        align-self:flex-start;
        align-items:center;
        min-height:34px;
        padding:6px 12px;
        border-radius:12px;
        border:1px solid #f2df9a;
        background:#fff9e7;
        color:#9a6b00;
        font-size:0.88rem;
        font-weight:700;
        line-height:1.2;
        white-space:nowrap;
    ">
        {{ $primary }}
    </span>

    @if (filled($secondary ?? null))
        <span style="
            color:#6b7280;
            font-size:0.82rem;
            line-height:1.35;
            white-space:nowrap;
        ">
            {{ $secondary }}
        </span>
    @endif
</div>
