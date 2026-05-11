@php
    use App\Support\AnalyticsEventTracker;

    $analyticsEvents = session(AnalyticsEventTracker::SESSION_KEY, []);
@endphp
<script>
    window.garagebookTrack = window.garagebookTrack || function (eventName, params = {}) {
        if (typeof window.gtag !== 'function') {
            return;
        }

        try {
            window.gtag('event', eventName, params);
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
