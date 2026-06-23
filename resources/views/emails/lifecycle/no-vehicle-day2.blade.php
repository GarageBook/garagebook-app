@php
    $template = (object) [
        'subject' => 'Je GarageBook is nog een beetje leeg... 😉',
        'cta_text' => 'Mijn eerste voertuig toevoegen',
    ];

    $firstName = trim(str($user->name)->before(' ')->value()) ?: $user->name;
    $bodyHtml = Illuminate\Support\Str::markdown(<<<BODY
Hoi {$firstName},

Leuk dat je een GarageBook-account hebt aangemaakt!

We zien alleen nog geen voertuig in je garage.

Het toevoegen duurt minder dan een minuut.

Daarna kun je direct:

• onderhoud bijhouden  
• foto's bewaren  
• documenten opslaan  
• een complete onderhoudshistorie opbouwen

Groet,  
GarageBook
BODY);
@endphp

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>{{ $template->subject }}</title>
</head>
<body style="margin:0; padding:32px 16px; background:#f8fafc; color:#0f172a; font-family:Arial, sans-serif;">
    @include('emails.partials.lifecycle-card', [
        'template' => $template,
        'bodyHtml' => $bodyHtml,
        'ctaUrl' => $ctaUrl,
        'unsubscribeUrl' => $unsubscribeUrl,
    ])
</body>
</html>
