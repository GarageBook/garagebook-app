@php
    use App\Support\Analytics;

    if (! Analytics::ga4Enabled()) {
        return;
    }

    $measurementId = Analytics::ga4MeasurementId();
    $linkerDomains = Analytics::ga4LinkerDomains();
@endphp
<script>
    window.garageBookAnalyticsConsent = {
        enabled: true,
        measurementId: @json($measurementId),
        linkerDomains: @json($linkerDomains),
    };
</script>
