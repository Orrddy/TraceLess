{{--
    Runs before first paint on every page.

    A clicked full-page link already showed the loader on the previous page.
    When that navigation arrives, the destination must not paint the loader a
    second time while its JavaScript boots. The page loader clears this class
    once it has been dismissed.
--}}
<script>
    (() => {
        try {
            if (sessionStorage.getItem('traceless:page-loader-navigation') === '1') {
                document.documentElement.classList.add('page-loader-skip');
                sessionStorage.removeItem('traceless:page-loader-navigation');
            }
        } catch (error) {
            // Private modes can disable sessionStorage; the loader still works.
        }
    })();
</script>
