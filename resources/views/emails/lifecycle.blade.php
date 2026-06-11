@php
    $htmlBody = Illuminate\Support\Str::markdown($template->body);
@endphp
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>{{ $template->subject }}</title>
</head>
<body style="margin:0; padding:32px 16px; background:#f8fafc; color:#0f172a; font-family:Arial, sans-serif;">
    <div style="max-width:640px; margin:0 auto; background:#ffffff; border:1px solid #e2e8f0; border-radius:20px; padding:32px;">
        <p style="margin:0 0 16px; color:#64748b; font-size:14px;">GarageBook</p>
        <div style="font-size:16px; line-height:1.7; color:#334155;">
            {!! $htmlBody !!}
        </div>
        <p style="margin:28px 0 0;">
            <a href="{{ $ctaUrl }}" style="display:inline-block; background:#ffd200; color:#111827; text-decoration:none; font-weight:700; padding:14px 20px; border-radius:14px;">
                {{ $template->cta_text }}
            </a>
        </p>
        <p style="margin:24px 0 0; color:#64748b; font-size:13px; line-height:1.6;">
            Wil je deze lifecycle-mails niet meer ontvangen?
            <a href="{{ $unsubscribeUrl }}" style="color:#475569;">Schrijf je hier uit</a>.
        </p>
    </div>
</body>
</html>
