@php
    $stats = $report['stats'];
    $conversions = $report['conversions'];
    $extraKpis = $report['extra_product_seo_kpis'] ?? [];
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

    <h2 style="font-size: 16px; margin-top: 24px;">Extra product/SEO KPI’s</h2>
    <ul>
        <li>Gem. onderhoudslogs per voertuig: {{ ($extraKpis['average_maintenance_logs_per_vehicle'] ?? null) === null ? 'niet beschikbaar' : number_format((float) ($extraKpis['average_maintenance_logs_per_vehicle'] ?? 0), 1, ',', '.') }}</li>
        <li>
            Users met ≥2 onderhoudslogs:
            @if (($extraKpis['users_with_two_maintenance_logs'] ?? null) === null)
                niet beschikbaar
            @else
                {{ number_format((int) $extraKpis['users_with_two_maintenance_logs']['count'], 0, ',', '.') }}
                ({{ number_format((float) $extraKpis['users_with_two_maintenance_logs']['percentage'], 1, ',', '.') }}%)
            @endif
        </li>
        <li>
            Publieke voertuigen:
            @if (($extraKpis['public_vehicles'] ?? null) === null)
                niet beschikbaar
            @else
                {{ number_format((int) $extraKpis['public_vehicles']['count'], 0, ',', '.') }}
                ({{ number_format((float) $extraKpis['public_vehicles']['percentage'], 1, ',', '.') }}%)
            @endif
        </li>
        <li>Publieke voertuigpagina’s: {{ ($extraKpis['public_vehicle_pages'] ?? null) === null ? 'niet beschikbaar' : number_format((int) ($extraKpis['public_vehicle_pages'] ?? 0), 0, ',', '.') }}</li>
        <li>Indexeerbare voertuigpagina’s: {{ ($extraKpis['indexable_vehicle_pages'] ?? null) === null ? 'niet beschikbaar' : number_format((int) ($extraKpis['indexable_vehicle_pages'] ?? 0), 0, ',', '.') }}</li>
        <li>Onderhoudslogs toegevoegd laatste 7 dagen: {{ ($extraKpis['maintenance_logs_last_7_days']['count'] ?? null) === null ? 'niet beschikbaar' : number_format((int) $extraKpis['maintenance_logs_last_7_days']['count'], 0, ',', '.') }}</li>
        <li>Nieuwe publieke pagina’s laatste 7 dagen: {{ ($extraKpis['new_public_pages_last_7_days']['count'] ?? null) === null ? 'niet beschikbaar' : number_format((int) $extraKpis['new_public_pages_last_7_days']['count'], 0, ',', '.') }}</li>
        <li>Gem. tijd tot eerste onderhoud: {{ ($extraKpis['average_days_to_first_maintenance'] ?? null) === null ? 'niet beschikbaar' : number_format((float) ($extraKpis['average_days_to_first_maintenance'] ?? 0), 1, ',', '.') . ' dagen' }}</li>
        <li>Gem. sessies per gebruiker: {{ ($extraKpis['average_sessions_per_user'] ?? null) === null ? 'niet beschikbaar' : number_format((float) ($extraKpis['average_sessions_per_user'] ?? 0), 1, ',', '.') }}</li>
    </ul>

    <h3 style="font-size: 14px; margin-top: 16px;">Toekomstige KPI’s</h3>
    <ul>
        @foreach (($extraKpis['future_kpis'] ?? []) as $futureKpi)
            <li>{{ $futureKpi['label'] }}: {{ $futureKpi['value'] }}</li>
        @endforeach
    </ul>

    <h2 style="font-size: 16px; margin-top: 24px;">Korte interpretatie</h2>
    <ul>
        <li>Grootste drop-off: {{ $interpretation['largest_drop_off'] }}</li>
        <li>Belangrijkste aandachtspunt: {{ $interpretation['attention_point'] }}</li>
        <li>Samenvatting: {{ $interpretation['summary'] }}</li>
        @foreach (($interpretation['product_seo'] ?? []) as $productSeoInterpretation)
            <li>{{ $productSeoInterpretation }}</li>
        @endforeach
    </ul>
</body>
</html>
