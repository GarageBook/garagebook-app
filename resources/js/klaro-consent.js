import Klaro from 'klaro/dist/klaro-no-translations-no-css';

const consentOptions = window.garageBookAnalyticsConsent;

if (consentOptions?.enabled && consentOptions.measurementId) {
    const measurementId = consentOptions.measurementId || 'G-HZE3QJPSBR';
    const linkerDomains = Array.isArray(consentOptions.linkerDomains)
        ? consentOptions.linkerDomains
        : [];

    const trackInitialPageView = () => {
        if (typeof window.gtag !== 'function') {
            return;
        }

        window.gtag('event', 'page_view', {
            page_location: window.location.href,
            page_path: window.location.pathname,
        });
    };

    const loadGoogleAnalytics = () => {
        if (window.garageBookAnalyticsLoaded) {
            return;
        }

        window.garageBookAnalyticsLoaded = true;
        window.dataLayer = window.dataLayer || [];
        window.gtag = window.gtag || function gtag() {
            window.dataLayer.push(arguments);
        };

        window.gtag('js', new Date());
        window.gtag('config', measurementId, {
            send_page_view: false,
            linker: {
                accept_incoming: true,
                domains: linkerDomains,
            },
        });

        const script = document.createElement('script');
        script.async = true;
        script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(measurementId)}`;
        script.addEventListener('load', trackInitialPageView, { once: true });
        document.head.appendChild(script);
    };

    Klaro.setup({
        version: 1,
        elementID: 'garagebook-klaro',
        storageMethod: 'localStorage',
        storageName: 'garagebook-cookie-consent',
        htmlTexts: false,
        embedded: false,
        groupByPurpose: false,
        acceptAll: true,
        hideDeclineAll: false,
        hideLearnMore: true,
        disablePoweredBy: true,
        mustConsent: false,
        noAutoLoad: false,
        lang: 'nl',
        default: false,
        translations: {
            nl: {
                consentNotice: {
                    description: 'GarageBook gebruikt analytische cookies om de website te verbeteren.',
                },
                ok: 'OK',
                decline: 'Nee bedankt',
                purposes: {
                    analytics: {
                        title: 'Analytics',
                    },
                },
            },
        },
        services: [
            {
                name: 'google-analytics',
                title: 'Google Analytics',
                purposes: ['analytics'],
                default: false,
                required: false,
                onAccept: loadGoogleAnalytics,
            },
        ],
    });
}
