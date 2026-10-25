<!DOCTYPE html>
<html lang="nl">
<head><meta charset="utf-8"><title>{{ \App\Mail\PhpSecurityServiceMail::SUBJECT }}</title></head>
<body style="margin:0; padding:0; background:#f8fafc; color:#111827; font-family:Arial, Helvetica, sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:32px 16px; background:#f8fafc;">
    <tr><td align="center">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px; background:#ffffff; border:1px solid #e5e7eb; border-radius:8px;">
            <tr><td style="padding:28px; color:#374151; font-size:16px; line-height:1.7;">
                <p style="margin:0 0 24px; color:#6b7280; font-size:14px;">GarageBook</p>
                @foreach (explode("\n\n", $body) as $paragraph)
                    <p style="margin:0 0 16px;">{{ $paragraph }}</p>
                @endforeach
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
