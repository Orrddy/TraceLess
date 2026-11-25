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
    // The loader is a deliberate brand moment: keep it visible long enough to
    // read the orbit instead of flashing for 80ms on a warm cache.
    const minVisibleMs = reduced ? 250 : 1100;
    const shownAt = performance.now();
    let hideTimer = null;
    let hideAt = null;
    let interactionHide = null;

    const show = () => {
        window.clearTimeout(hideTimer);
        window.clearTimeout(interactionHide);
        loader.classList.remove('is-hidden', 'is-leaving');
        document.documentElement.classList.add('is-page-loading');
        // If a navigation is prevented (Livewire, client-side guard), never
        // leave the page locked behind the loader.
        interactionHide = window.setTimeout(hide, 6000);
    };

    const hide = () => {
        window.clearTimeout(hideTimer);
        window.clearTimeout(interactionHide);
        loader.classList.add('is-leaving');
        loader.classList.add('is-hidden');
        document.documentElement.classList.remove('is-page-loading');
    };

    const hideAfterMinimum = () => {
        const remaining = Math.max(0, minVisibleMs - (performance.now() - shownAt));
        window.clearTimeout(hideAt);
        hideAt = window.setTimeout(hide, remaining);
    };

    if (document.readyState === 'complete') {
        hideAfterMinimum();
    } else {
        window.addEventListener('load', hideAfterMinimum, { once: true });
        // Never trap the user behind the loader if a third-party asset hangs.
        hideTimer = window.setTimeout(hide, reduced ? 2500 : 7000);
    }

    window.addEventListener('pageshow', hideAfterMinimum);
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

    // Only full-page form posts show the intro loader. Livewire forms submit
    // over XHR and must never freeze the page behind the loader.
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;
        if (form.hasAttribute('wire:submit') || form.closest('[wire\\:id]') || form.querySelector('[wire\\:model]')) return;

        show();
    }, { capture: true });
}
