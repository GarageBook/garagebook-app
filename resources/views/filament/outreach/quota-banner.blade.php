@if($banner)
    @php
        $isDanger = $banner['level'] === 'danger';
    @endphp

    <div style="
        border:1px solid {{ $isDanger ? '#fecaca' : '#fde68a' }};
        border-radius:12px;
        padding:14px 16px;
        background:{{ $isDanger ? '#fef2f2' : '#fffbeb' }};
        color:{{ $isDanger ? '#991b1b' : '#92400e' }};
        font-size:14px;
        font-weight:600;
        line-height:1.5;
    ">
        {{ $banner['message'] }}
    </div>
@endif
