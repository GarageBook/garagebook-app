<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>{{ __('emails.welcome_subject') }}</title>
</head>
<body style="margin:0; padding:0; background:#f8fafc; color:#111827; font-family:Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%; background:#f8fafc; margin:0; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%; max-width:620px; background:#ffffff; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden;">
                    <tr>
                        <td style="padding:28px 28px 12px;">
                            <p style="margin:0 0 12px; color:#6b7280; font-size:14px; line-height:1.5;">GarageBook</p>
                            <h1 style="margin:0; color:#111827; font-size:24px; line-height:1.25; font-weight:700;">Welkom bij GarageBook</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:12px 28px 4px; color:#374151; font-size:16px; line-height:1.7;">
                            <p style="margin:0 0 16px;">{{ $greetingName ? 'Hoi '.$greetingName.',' : 'Hoi,' }}</p>
                            <p style="margin:0 0 16px;">Welkom bij GarageBook. Je kunt hier onderhoud, documenten en voertuiggeschiedenis centraal bewaren, zodat je alles rond je voertuig netjes op een plek hebt.</p>
                            <p style="margin:0 0 18px;">De logische eerste stappen zijn:</p>
                            <ol style="margin:0 0 22px; padding-left:22px; color:#374151;">
                                <li style="margin:0 0 8px;">Voeg je eerste voertuig toe.</li>
                                <li style="margin:0 0 8px;">Leg onderhoud of documenten vast.</li>
                                <li style="margin:0;">Vul je voertuigprofiel verder aan.</li>
                            </ol>
                            <p style="margin:0 0 24px;">
                                <a href="{{ $ctaUrl }}" style="display:inline-block; background:#111827; color:#ffffff; text-decoration:none; font-weight:700; font-size:15px; line-height:1; padding:14px 18px; border-radius:6px;">Eerste voertuig toevoegen</a>
                            </p>
                            <p style="margin:0 0 16px;">Heb je vragen of loop je ergens tegenaan? Reageer gerust op deze e-mail.</p>
                            <p style="margin:0 0 4px;">Groet,</p>
                            <p style="margin:0 0 20px; font-weight:700; color:#111827;">GarageBook</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 28px 26px; border-top:1px solid #e5e7eb; color:#6b7280; font-size:13px; line-height:1.6;">
                            Je ontvangt deze e-mail omdat je een GarageBook-account hebt aangemaakt.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
