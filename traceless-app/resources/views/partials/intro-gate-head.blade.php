<script>
    (() => {
        const root = document.documentElement;
        const key = 'traceless:intro-complete:v1';

        try {
            const completed = window.localStorage.getItem(key) === '1';
            root.classList.toggle('intro-gate-pending', !completed);
        } catch (error) {
            // If storage cannot be read, preserve the intro rather than silently
            // claiming it has already been completed.
            root.classList.add('intro-gate-pending');
        }

        // If the JS bundle fails, never trap the page behind a dead intro.
        window.setTimeout(() => {
            if (root.hasAttribute('data-intro-booted')) return;
            root.classList.remove('intro-gate-pending');
            document.querySelector('[data-intro-gate]')?.remove();
            document.querySelectorAll('header, main, footer').forEach((el) => { el.inert = false; });
            window.dispatchEvent(new Event('traceless:intro-complete'));
        }, 12000);
    })();
</script>
