import Klaro from 'klaro/dist/klaro-no-translations-no-css';

const consentOptions = window.garageBookAnalyticsConsent;

if (consentOptions?.enabled && consentOptions.measurementId) {
    Klaro.setup({
        version: 1,
        elementID: 'garagebook-klaro',
        storageMethod: 'localStorage',
        storageName: consentOptions.storageName || 'garagebook-cookie-consent',
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
                name: consentOptions.serviceName || 'google-analytics',
                title: 'Google Analytics',
                purposes: ['analytics'],
                default: false,
                required: false,
                onAccept: () => {
                    if (typeof window.garageBookGrantAnalyticsConsent === 'function') {
                        window.garageBookGrantAnalyticsConsent();
                    }
                },
                onDecline: () => {
                    if (typeof window.garageBookDenyAnalyticsConsent === 'function') {
                        window.garageBookDenyAnalyticsConsent();
                    }
                },
            },
        ],
    });
}
