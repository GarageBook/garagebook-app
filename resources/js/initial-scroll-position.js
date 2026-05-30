const hasInitialHash = () => window.location.hash.length > 0;

const scrollToTop = () => {
    if (hasInitialHash()) {
        return;
    }

    window.scrollTo({
        top: 0,
        left: 0,
        behavior: 'auto',
    });
};

if (!hasInitialHash()) {
    try {
        if ('scrollRestoration' in window.history) {
            window.history.scrollRestoration = 'manual';
        }
    } catch (error) {
    }

    scrollToTop();
    window.requestAnimationFrame(scrollToTop);

    document.addEventListener('DOMContentLoaded', () => {
        window.requestAnimationFrame(scrollToTop);
        window.setTimeout(scrollToTop, 50);
        window.setTimeout(scrollToTop, 250);
    }, { once: true });

    window.addEventListener('load', () => {
        window.requestAnimationFrame(scrollToTop);
        window.setTimeout(scrollToTop, 100);
        window.setTimeout(scrollToTop, 500);
    }, { once: true });
}
