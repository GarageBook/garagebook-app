@php
    $bodyHtml = Illuminate\Support\Str::markdown($renderedBody);
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
