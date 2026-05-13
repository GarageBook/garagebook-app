@php
    use App\Support\Analytics;

    if (! Analytics::ga4Enabled()) {
        return;
    }

    $measurementId = Analytics::ga4MeasurementId();
    $linkerDomains = Analytics::ga4LinkerDomains();
@endphp
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id={{ $measurementId }}"></script>
<script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());

    gtag('config', @json($measurementId), {
        linker: {
            accept_incoming: true,
            domains: @json($linkerDomains),
        },
    });
</script>
