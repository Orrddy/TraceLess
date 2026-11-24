/**
 * Global page loader.
 *
 * The first paint shows the branded loader, and it fades out when the document
 * is ready. Link clicks and form submissions re-show it for the next page so
 * slow server responses never look like a frozen screen.
 */
export function initPageLoader() {
    const loader = document.querySelector('[data-page-loader]');
    if (!loader) return;

    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let hideTimer = null;

    const show = () => {
        window.clearTimeout(hideTimer);
        loader.classList.remove('is-hidden');
        document.documentElement.classList.add('is-page-loading');
    };

    const hide = () => {
        loader.classList.add('is-hidden');
        document.documentElement.classList.remove('is-page-loading');
    };

    if (document.readyState === 'complete') {
        hide();
    } else {
        window.addEventListener('load', hide, { once: true });
        // Never trap the user behind the loader if a third-party asset hangs.
        hideTimer = window.setTimeout(hide, reduced ? 2500 : 6000);
    }

    window.addEventListener('pageshow', hide);
    window.addEventListener('beforeunload', show);

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (!link || event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        if (link.target && link.target !== '_self') return;
        if (link.hasAttribute('download')) return;

        const href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:') || href.startsWith('javascript:')) return;

        const url = new URL(link.href, window.location.href);
        if (url.origin !== window.location.origin) return;
        if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) return;

        show();
    }, { passive: true });

    document.addEventListener('submit', show, { capture: true });
}
