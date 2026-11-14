<?php
/*
 * Shared <head> assets: theme bootstrap (applied before paint, so dark
 * mode never flashes white), stylesheet and theme toggle script.
 * Used by the app header and by the standalone auth pages.
 */
?>
    <meta name="color-scheme" content="light dark">

    <script>
        (function () {
            var theme = 'light';

            try {
                var saved = localStorage.getItem('masar-theme');

                if (saved === 'light' || saved === 'dark') {
                    theme = saved;
                } else if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    theme = 'dark';
                }
            } catch (error) {
                theme = 'light';
            }

            document.documentElement.dataset.theme = theme;
            document.documentElement.style.colorScheme = theme;
        })();
    </script>

    <link rel="stylesheet" href="/masar/assets/css/style.css?v=20">

    <script src="/masar/assets/js/theme.js?v=2" defer></script>
