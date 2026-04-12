<!DOCTYPE html>
<html>
<head>
    <title>GarageBook Onderhoud</title>
</head>
<body style="font-family:Arial, sans-serif; margin:0; background:#fff;">

    <div style="background:black; color:white; padding:20px 40px;">
        <table style="width:100%;">
            <tr>
                <td style="vertical-align:middle;">
                    <img
                        src="{{ request()->is('maintenance/pdf') ? public_path('images/garagebook-logo-white.png') : asset('images/garagebook-logo-white.png') }}"
                        alt="GarageBook"
                        style="height:40px;"
                    >
                </td>

                <td style="text-align:right; vertical-align:middle;">
                    <a
                        href="https://garagebook.nl"
                        style="
                            background:#ffd200;
                            color:black;
                            padding:10px 18px;
                            text-decoration:none;
                            border-radius:12px;
                            font-weight:700;
                            font-family:Arial, sans-serif;
                            display:inline-block;
                        "
                    >
                        Maak gratis jouw garage aan op GarageBook.nl
                    </a>
                </td>
            </tr>
        </table>
    </div>

    <div style="padding:40px; max-width:900px; margin:0 auto;">
        <h1 style="margin-bottom:10px;">Onderhoudstijdlijn</h1>

        @if(isset($vehicle))
            <div style="font-size:18px; color:#666; margin-bottom:30px;">
                {{ $vehicle->brand }} {{ $vehicle->model }}
            </div>
        @endif

        @foreach($logs as $log)
            @php
                $firstAttachment = is_array($log->attachments ?? null)
                    ? ($log->attachments[0] ?? null)
                    : null;

                $imageSrc = $firstAttachment
                    ? (
                        request()->is('maintenance/pdf')
                            ? public_path('storage/' . $firstAttachment)
                            : asset('storage/' . $firstAttachment)
                    )
                    : null;
            @endphp

            <div style="border-bottom:1px solid #ddd; padding:25px 0;">
                <table style="width:100%;">
                    <tr>
                        <td style="width:140px; vertical-align:top;">
                            @if($imageSrc)
                                <img
                                    src="{{ $imageSrc }}"
                                    style="width:120px; border-radius:12px;"
                                >
                            @else
                                <div style="width:120px; height:120px; background:#f3f4f6; border-radius:12px;"></div>
                            @endif
                        </td>

                        <td style="vertical-align:top; padding-left:20px;">
                            <div style="font-weight:700; margin-bottom:10px;">
                                Beschrijving: {{ $log->description }}
                            </div>

                            <div style="margin-bottom:6px;">
                                Datum: {{ \Carbon\Carbon::parse($log->maintenance_date)->format('d-m-Y') }}
                            </div>

                            <div>
                                Kilometerstand: {{ $log->km_reading }} km
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        @endforeach
    </div>

</body>
</html>