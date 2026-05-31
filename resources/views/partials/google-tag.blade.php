@php
    use App\Support\Analytics;

    if (! Analytics::ga4Enabled()) {
        return;
    }

    $measurementId = Analytics::ga4MeasurementId();
    $linkerDomains = Analytics::ga4LinkerDomains();
@endphp
<script async src="https://www.googletagmanager.com/gtag/js?id={{ $measurementId }}"></script>
<script>
    window.dataLayer = window.dataLayer || [];
    window.gtag = window.gtag || function gtag() {
        window.dataLayer.push(arguments);
    };

    window.garageBookAnalyticsState = window.garageBookAnalyticsState || {
        configuredMeasurementIds: [],
        consentDefaultApplied: false,
        consentGranted: false,
        consentUpdateApplied: false,
    };

    window.garageBookAnalyticsConsent = {
        enabled: true,
        measurementId: @json($measurementId),
        linkerDomains: @json($linkerDomains),
        serviceName: 'google-analytics',
        storageName: 'garagebook-cookie-consent',
    };

    window.garageBookReadAnalyticsConsent = window.garageBookReadAnalyticsConsent || function () {
        try {
            const consent = JSON.parse(
                window.localStorage.getItem(window.garageBookAnalyticsConsent.storageName) || 'null'
            );

            return consent?.[window.garageBookAnalyticsConsent.serviceName] === true;
        } catch (error) {
            return false;
        }
    };

    window.garageBookGrantAnalyticsConsent = window.garageBookGrantAnalyticsConsent || function () {
        window.gtag('consent', 'update', {
            analytics_storage: 'granted',
        });

        window.garageBookAnalyticsState.consentGranted = true;
        window.garageBookAnalyticsState.consentUpdateApplied = true;

        if (typeof window.garagebookFlushAnalyticsQueue === 'function') {
            window.garagebookFlushAnalyticsQueue();
        }
    };

    window.garageBookDenyAnalyticsConsent = window.garageBookDenyAnalyticsConsent || function () {
        window.gtag('consent', 'update', {
            analytics_storage: 'denied',
        });

        window.garageBookAnalyticsState.consentGranted = false;
    };

    window.gtag('consent', 'default', {
        analytics_storage: 'denied',
    });

    window.garageBookAnalyticsState.consentDefaultApplied = true;
    window.gtag('js', new Date());
    window.gtag('config', @json($measurementId), {
        send_page_view: false,
        linker: {
            accept_incoming: true,
            domains: @json($linkerDomains),
        },
    });

    window.garageBookAnalyticsState.configuredMeasurementIds.push(@json($measurementId));

    if (window.garageBookReadAnalyticsConsent()) {
        window.garageBookGrantAnalyticsConsent();
    }
</script>
