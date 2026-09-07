(() => {
    const STORAGE_KEY = 'masar-theme';

    function getCurrentTheme() {
        return document.documentElement.dataset.theme === 'dark'
            ? 'dark'
            : 'light';
    }

    function applyTheme(theme, save = true) {
        const normalizedTheme =
            theme === 'dark'
                ? 'dark'
                : 'light';

        document.documentElement.dataset.theme =
            normalizedTheme;

        document.documentElement.style.colorScheme =
            normalizedTheme;

        if (save) {
            try {
                localStorage.setItem(
                    STORAGE_KEY,
                    normalizedTheme
                );
            } catch (error) {
                // Theme still works even if storage is unavailable.
            }
        }

        updateThemeButtons();
    }

    function updateThemeButtons() {
        const theme = getCurrentTheme();
        const isDark = theme === 'dark';

        document
            .querySelectorAll('[data-theme-toggle]')
            .forEach((button) => {
                button.setAttribute(
                    'aria-pressed',
                    isDark ? 'true' : 'false'
                );

                button.setAttribute(
                    'aria-label',
                    isDark
                        ? 'Switch to light mode'
                        : 'Switch to dark mode'
                );

                const icon =
                    button.querySelector(
                        '[data-theme-icon]'
                    );

                if (icon) {
                    icon.textContent =
                        isDark ? '☀' : '☾';
                }
            });
    }

    document.addEventListener(
        'click',
        (event) => {
            const button =
                event.target.closest(
                    '[data-theme-toggle]'
                );

            if (!button) {
                return;
            }

            const nextTheme =
                getCurrentTheme() === 'dark'
                    ? 'light'
                    : 'dark';

            applyTheme(nextTheme);
        }
    );

    document.addEventListener(
        'DOMContentLoaded',
        updateThemeButtons
    );
})();