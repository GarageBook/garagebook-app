@php
    $stats = $report['stats'];
    $conversions = $report['conversions'];
    $interpretation = $report['interpretation'];
@endphp
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>GarageBook growth report</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.6;">
    <h1 style="font-size: 20px; margin-bottom: 16px;">GarageBook activation/retention rapportage</h1>

    <h2 style="font-size: 16px; margin-top: 24px;">Stand van zaken</h2>
    <ul>
        <li>Totaal gebruikers: {{ number_format((int) $stats['total_users'], 0, ',', '.') }}</li>
        <li>Registraties laatste 7 dagen: {{ number_format((int) $stats['registrations_last_7_days'], 0, ',', '.') }}</li>
        <li>Registraties laatste 30 dagen: {{ number_format((int) $stats['registrations_last_30_days'], 0, ',', '.') }}</li>
        <li>Users met voertuig: {{ $stats['users_with_vehicle'] === null ? 'niet beschikbaar' : number_format((int) $stats['users_with_vehicle'], 0, ',', '.') }}</li>
        <li>Users met minimaal 1 onderhoudslog: {{ $stats['users_with_maintenance'] === null ? 'niet beschikbaar' : number_format((int) $stats['users_with_maintenance'], 0, ',', '.') }}</li>
        <li>Users met actieve reminder: {{ $stats['users_with_active_reminder'] === null ? 'niet beschikbaar' : number_format((int) $stats['users_with_active_reminder'], 0, ',', '.') }}</li>
        <li>Users met onderhoudsboekje/PDF-download: {{ $stats['users_with_booklet_download'] === null ? 'niet beschikbaar' : number_format((int) $stats['users_with_booklet_download'], 0, ',', '.') }}</li>
        <li>Users actief laatste 7 dagen: {{ $stats['active_last_7_days'] === null ? 'niet beschikbaar' : number_format((int) $stats['active_last_7_days'], 0, ',', '.') }}</li>
        <li>Users actief laatste 30 dagen: {{ $stats['active_last_30_days'] === null ? 'niet beschikbaar' : number_format((int) $stats['active_last_30_days'], 0, ',', '.') }}</li>
    </ul>

    <h2 style="font-size: 16px; margin-top: 24px;">Conversies</h2>
    <ul>
        @foreach ($conversions as $conversion)
            <li>
                {{ $conversion['label'] }}:
                {{ $conversion['percentage'] === null ? 'niet beschikbaar' : number_format((float) $conversion['percentage'], 1, ',', '.') . '%' }}
                ({{ $conversion['to'] === null ? 'niet beschikbaar' : number_format((int) $conversion['to'], 0, ',', '.') }} van {{ $conversion['from'] === null ? 'niet beschikbaar' : number_format((int) $conversion['from'], 0, ',', '.') }})
            </li>
        @endforeach
    </ul>

    <h2 style="font-size: 16px; margin-top: 24px;">Korte interpretatie</h2>
    <ul>
        <li>Grootste drop-off: {{ $interpretation['largest_drop_off'] }}</li>
        <li>Belangrijkste aandachtspunt: {{ $interpretation['attention_point'] }}</li>
        <li>Samenvatting: {{ $interpretation['summary'] }}</li>
    </ul>
</body>
</html>
