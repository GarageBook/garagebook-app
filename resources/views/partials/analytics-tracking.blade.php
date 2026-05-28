@php
    use App\Support\Analytics;
    use App\Support\AnalyticsEventTracker;

    if (! Analytics::frontendTrackingEnabled()) {
        return;
    }

    $analyticsEvents = app(AnalyticsEventTracker::class)->consume();
    $debugEnabled = Analytics::frontendDebugEnabled();
@endphp
<script>
    window.garagebookAnalyticsEvents = window.garagebookAnalyticsEvents || [];
    window.garagebookTrackState = window.garagebookTrackState || {
        lastPageViewKey: null,
        lastPageViewAt: 0,
        livewireListenerRegistered: false,
        initialPageViewTracked: false,
    };

    window.garagebookTrack = window.garagebookTrack || function (eventName, params = {}) {
        if (typeof eventName !== 'string' || eventName === '') {
            return;
        }

        const now = Date.now();

        const payload = {
            page_location: window.location.href,
            page_path: window.location.pathname,
            ...(params && typeof params === 'object' ? params : {}),
        };

        if (eventName === 'page_view') {
            const dedupeKey = `${payload.page_location}|${payload.page_path}`;

            if (
                window.garagebookTrackState.lastPageViewKey === dedupeKey &&
                now - window.garagebookTrackState.lastPageViewAt < 1500
            ) {
                return;
            }

            window.garagebookTrackState.lastPageViewKey = dedupeKey;
            window.garagebookTrackState.lastPageViewAt = now;
        }

        try {
            if (typeof window.gtag === 'function') {
                window.gtag('event', eventName, payload);
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

    if (!window.garagebookTrackState.initialPageViewTracked) {
        window.garagebookTrack('page_view');
        window.garagebookTrackState.initialPageViewTracked = true;
    }

    if (!window.garagebookTrackState.livewireListenerRegistered) {
        document.addEventListener('livewire:navigated', () => {
            window.garagebookTrack('page_view');
        });

        window.garagebookTrackState.livewireListenerRegistered = true;
    }

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
