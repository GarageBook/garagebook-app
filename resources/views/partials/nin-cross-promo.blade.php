@php
    $isPdf = $isPdf ?? false;
    $vehicleLabel = trim($vehicle->brand.' '.$vehicle->model);
@endphp

@if(filled($ninUrl ?? null))
<aside
    data-nin-cross-promo="true"
    aria-label="Nog in Nederland"
    style="box-sizing:border-box; width:100%; min-width:0; padding:24px; margin:0 0 32px; border:1px solid #e5e7eb; border-radius:20px; background:#f9fafb; color:#111827;"
>
    <img
        src="{{ $isPdf ? public_path('images/noginnederland-logo.png') : asset('images/noginnederland-logo.png') }}"
        alt="Nog in Nederland"
        style="display:block; width:220px; max-width:100%; height:auto; margin-bottom:16px;"
    >
    <h2 style="margin:0 0 8px; color:#111827; font-size:22px; line-height:1.25;">
        Hoeveel {{ $vehicleLabel }} zijn er nog in Nederland?
    </h2>
    <p style="margin:0 0 18px; color:#374151; font-size:16px; line-height:1.5;">
        Benieuwd hoe zeldzaam jouw voertuig is?
    </p>
    <a
        href="{{ $ninUrl }}"
        style="display:inline-block; max-width:100%; box-sizing:border-box; padding:12px 20px; border-radius:12px; background:#0757f9; color:#fff; font-weight:700; text-decoration:none; text-align:center;"
    >Doe de check →</a>
</aside>
@endif
