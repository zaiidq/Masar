// Shared account menu (student and admin)
document.addEventListener('DOMContentLoaded', () => {
    const userMenus = Array.from(document.querySelectorAll('[data-user-menu]'));

    function closeMenu(menu, returnFocus = false) {
        const toggle = menu.querySelector('[data-user-menu-toggle]');
        const panel = menu.querySelector('[data-user-menu-panel]');

        menu.classList.remove('is-open');
        toggle?.setAttribute('aria-expanded', 'false');

        if (panel) {
            panel.hidden = true;
        }

        if (returnFocus) {
            toggle?.focus();
        }
    }

    function closeAll(except = null) {
        userMenus.forEach((menu) => {
            if (menu !== except) {
                closeMenu(menu);
            }
        });
    }

    userMenus.forEach((menu) => {
        const toggle = menu.querySelector('[data-user-menu-toggle]');
        const panel = menu.querySelector('[data-user-menu-panel]');

        if (!toggle || !panel) {
            return;
        }

        toggle.addEventListener('click', () => {
            const willOpen = panel.hidden;

            closeAll(menu);

            menu.classList.toggle('is-open', willOpen);
            panel.hidden = !willOpen;
            toggle.setAttribute('aria-expanded', String(willOpen));

            if (willOpen) {
                panel.querySelector('a')?.focus();
            }
        });

        panel.addEventListener('keydown', (event) => {
            const items = Array.from(panel.querySelectorAll('a'));
            const index = items.indexOf(document.activeElement);

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                items[(index + 1 + items.length) % items.length]?.focus();
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                items[(index - 1 + items.length) % items.length]?.focus();
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                closeMenu(menu, true);
            }
        });
    });

    document.addEventListener('click', (event) => {
        userMenus.forEach((menu) => {
            if (!menu.contains(event.target)) {
                closeMenu(menu);
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            userMenus.forEach((menu) => closeMenu(menu));
        }
    });
});
