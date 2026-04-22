@php use App\Support\MediaPath; @endphp
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
                $attachments = $log->attachments;

                if (is_string($attachments)) {
                    $attachments = json_decode($attachments, true);
                }

                $firstAttachment = null;

                if (is_array($attachments)) {
                    foreach ($attachments as $attachment) {
                        if (MediaPath::isImage($attachment)) {
                            $firstAttachment = $attachment;
                            break;
                        }
                    }
                }

                $imageSrc = null;

                if ($firstAttachment) {
                    if (request()->is('maintenance/pdf')) {
                        $absolutePath = storage_path('app/public/' . ltrim($firstAttachment, '/'));

                        if (file_exists($absolutePath)) {
                            $imageData = base64_encode(file_get_contents($absolutePath));
                            $mimeType = mime_content_type($absolutePath);

                            $imageSrc = 'data:' . $mimeType . ';base64,' . $imageData;
                        }
                    } else {
                        $imageSrc = asset('storage/' . ltrim($firstAttachment, '/'));
                    }
                }
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

                            @if(is_array($attachments) && count($attachments))
                                <div style="margin-top:10px;">
                                    Bestanden:
                                    @foreach($attachments as $attachment)
                                        @php
                                            $label = MediaPath::label($attachment);
                                            $url = asset('storage/' . ltrim($attachment, '/'));
                                        @endphp

                                        @if(request()->is('maintenance/pdf'))
                                            <div>{{ $label }}</div>
                                        @else
                                            <div>
                                                <a href="{{ $url }}" target="_blank" rel="noopener noreferrer">{{ $label }}</a>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </td>
                    </tr>
                </table>
            </div>
        @endforeach
    </div>

</body>
</html>
