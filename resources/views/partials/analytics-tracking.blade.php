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
        nextEventId: 1,
        lastPageViewKey: null,
        lastPageViewAt: 0,
        livewireListenerRegistered: false,
        initialPageViewQueued: false,
    };

    window.garagebookDispatchAnalyticsEvent = window.garagebookDispatchAnalyticsEvent || function (queuedEvent) {
        if (
            !queuedEvent ||
            queuedEvent.dispatched ||
            !window.garageBookAnalyticsState?.consentGranted ||
            typeof window.gtag !== 'function'
        ) {
            return false;
        }

        window.gtag('event', queuedEvent.event, queuedEvent.params);
        queuedEvent.dispatched = true;

        return true;
    };

    window.garagebookFlushAnalyticsQueue = window.garagebookFlushAnalyticsQueue || function () {
        if (!Array.isArray(window.garagebookAnalyticsEvents)) {
            return;
        }

        for (const queuedEvent of window.garagebookAnalyticsEvents) {
            window.garagebookDispatchAnalyticsEvent(queuedEvent);
        }
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

        const queuedEvent = {
            id: window.garagebookTrackState.nextEventId++,
            event: eventName,
            params: payload,
            dispatched: false,
        };

        try {
            window.garagebookAnalyticsEvents.push(queuedEvent);
            window.garagebookDispatchAnalyticsEvent(queuedEvent);

            if (@json($debugEnabled)) {
                console.info('[GarageBook analytics]', eventName, payload);
            }
        } catch (error) {
        }
    };

    if (!window.garagebookTrackState.initialPageViewQueued) {
        window.garagebookTrack('page_view');
        window.garagebookTrackState.initialPageViewQueued = true;
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
            window.garagebookFlushAnalyticsQueue();

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

        window.garagebookFlushAnalyticsQueue();
    })();
</script>
