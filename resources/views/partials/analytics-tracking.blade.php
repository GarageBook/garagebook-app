@php
    use App\Support\Analytics;
    use App\Support\AnalyticsEventTracker;

    if (! Analytics::frontendTrackingEnabled()) {
        return;
    }

    $analyticsEvents = session(AnalyticsEventTracker::SESSION_KEY, []);
    $debugEnabled = Analytics::frontendDebugEnabled();
@endphp
<script>
    window.dataLayer = window.dataLayer || [];
    window.garagebookAnalyticsEvents = window.garagebookAnalyticsEvents || [];

    window.garagebookTrack = window.garagebookTrack || function (eventName, params = {}) {
        if (typeof eventName !== 'string' || eventName === '') {
            return;
        }

        const payload = {
            page_path: window.location.pathname,
            hostname: window.location.hostname,
            ...(params && typeof params === 'object' ? params : {}),
        };

        try {
            if (window.google_tag_manager && Array.isArray(window.dataLayer)) {
                window.dataLayer.push({
                    event: eventName,
                    ...payload,
                });
            } else if (typeof window.gtag === 'function') {
                window.gtag('event', eventName, payload);
            } else if (Array.isArray(window.dataLayer)) {
                window.dataLayer.push({
                    event: eventName,
                    ...payload,
                });
            }

            window.garagebookAnalyticsEvents.push({
                event: eventName,
                params: payload,
            });

            if (@json($debugEnabled)) {
                console.info('[GarageBook analytics]', eventName, payload);
            }
        } catch (error) {
        }
    };

    (() => {
        const analyticsEvents = @json($analyticsEvents);

        if (!Array.isArray(analyticsEvents) || analyticsEvents.length === 0) {
            return;
        }

        for (const analyticsEvent of analyticsEvents) {
            if (!analyticsEvent || typeof analyticsEvent.name !== 'string') {
                continue;
            }

            window.garagebookTrack(
                analyticsEvent.name,
                analyticsEvent.params && typeof analyticsEvent.params === 'object' ? analyticsEvent.params : {}
            );
        }
    })();
</script>
