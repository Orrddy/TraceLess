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
    const navigationKey = 'traceless:page-loader-navigation';
    let hideTimer = null;
    let hideAt = null;
    let interactionHide = null;
    let navigatingAway = false;
    // If the link click already showed the loader on the previous page, this
    // page must not hold it for the full brand delay again.
    const skipLoader = document.documentElement.classList.contains('page-loader-skip');
    const introPending = document.documentElement.classList.contains('intro-gate-pending');
    const introComplete = document.documentElement.classList.contains('intro-gate-complete');
    const arrivedViaNavigation = (() => {
        if (skipLoader) return true;
        try {
            if (window.sessionStorage.getItem(navigationKey) === '1') {
                window.sessionStorage.removeItem(navigationKey);

                return true;
            }
        } catch (error) {
            // sessionStorage can be unavailable in private modes; fall back.
        }

        return false;
    })();

    const clearNavigationMark = () => {
        try {
            window.sessionStorage.removeItem(navigationKey);
        } catch (error) {
            // Ignore.
        }
    };

    const show = (navigating = false) => {
        window.clearTimeout(hideTimer);
        document.documentElement.classList.remove('page-loader-skip');
        window.clearTimeout(interactionHide);
        if (navigating) {
            navigatingAway = true;
            try {
                window.sessionStorage.setItem(navigationKey, '1');
            } catch (error) {
                // Ignore.
            }
        }
        loader.classList.remove('is-hidden', 'is-leaving');
        document.documentElement.classList.add('is-page-loading');
        // If a navigation is prevented (Livewire, client-side guard), never
        // leave the page locked behind the loader. The longer window lets the
        // destination arrive before a slow navigation is treated as cancelled.
        interactionHide = window.setTimeout(() => {
            navigatingAway = false;
            hide();
        }, 15000);
    };

    const hide = () => {
        window.clearTimeout(hideTimer);
        window.clearTimeout(interactionHide);
        if (!navigatingAway) clearNavigationMark();
        // The destination page may need to show the loader again for a later
        // full-page action (logout, external auth handoff, etc.).
        document.documentElement.classList.remove('page-loader-skip');
        loader.classList.add('is-leaving');
        loader.classList.add('is-hidden');
        document.documentElement.classList.remove('is-page-loading');
    };

    const hideAfterMinimum = () => {
        const remaining = Math.max(0, minVisibleMs - (performance.now() - shownAt));
        window.clearTimeout(hideAt);
        hideAt = window.setTimeout(hide, remaining);
    };

    // The landing intro owns the first paint on a first visit. Once the film
    // finishes, reveal the branded loader for one short beat before the landing.
    const revealAfterIntro = () => {
        window.clearTimeout(hideTimer);
        window.clearTimeout(hideAt);
        loader.style.animation = 'none';
        loader.classList.remove('is-hidden', 'is-leaving');
        document.documentElement.classList.add('is-page-loading');
        // The intro fade lasts ~520ms. Give the branded loader a full beat
        // after the film has cleared, not while it is still behind the fade.
        hideAt = window.setTimeout(hide, reduced ? 900 : 1700);
    };

    if (introPending) {
        if (document.documentElement.classList.contains('intro-gate-complete')) {
            revealAfterIntro();
        } else {
            window.addEventListener('traceless:intro-complete', revealAfterIntro, { once: true });
        }
    } else if (introComplete) {
        revealAfterIntro();
    } else if (arrivedViaNavigation) {
        // One continuous loader across the navigation: dismiss as soon as the
        // new page is ready instead of replaying the minimum brand delay.
        if (document.readyState === 'complete') {
            hide();
        } else {
            window.addEventListener('load', hide, { once: true });
            hideTimer = window.setTimeout(hide, reduced ? 1800 : 5000);
        }
    } else if (document.readyState === 'complete') {
        hideAfterMinimum();
    } else {
        window.addEventListener('load', hideAfterMinimum, { once: true });
        // Never trap the user behind the loader if a third-party asset hangs.
        hideTimer = window.setTimeout(hide, reduced ? 2500 : 7000);
    }

    window.addEventListener('pageshow', () => {
        if (document.documentElement.classList.contains('intro-gate-pending')) return;

        arrivedViaNavigation ? hide() : hideAfterMinimum();
    });
    window.addEventListener('beforeunload', () => show(true));

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (!link || event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        if (link.target && link.target !== '_self') return;
        if (link.hasAttribute('download') || link.hasAttribute('data-no-page-loader')) return;
        // Links handled in-place by Livewire/Alpine do not navigate; showing
        // the intro loader there leaves the user staring at a frozen screen.
        if (link.hasAttribute('wire:click') || link.hasAttribute('wire:navigate')) return;
        if (link.hasAttribute('x-on:click') || link.hasAttribute('@click')) return;

        const href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:') || href.startsWith('javascript:')) return;

        const url = new URL(link.href, window.location.href);
        if (url.origin !== window.location.origin) return;
        // Same page, including same-page hash/query updates.
        if (url.pathname === window.location.pathname && url.search === window.location.search) return;

        show(true);
    }, { passive: true });

    // Only full-page form posts show the intro loader. Livewire forms submit
    // over XHR and must never freeze the page behind the loader.
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;
        if (form.hasAttribute('wire:submit') || form.closest('[wire\\:id]') || form.querySelector('[wire\\:model]')) return;

        show(true);
    }, { capture: true });
}
