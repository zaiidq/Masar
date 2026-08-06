document.addEventListener('DOMContentLoaded', () => {
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const sidebarClose = document.getElementById('sidebarClose');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    function openSidebar() {
        sidebar?.classList.add('is-open');
        sidebarOverlay?.classList.add('is-visible');
        document.body.classList.add('menu-open');

        menuToggle?.setAttribute('aria-expanded', 'true');
    }

    function closeSidebar() {
        sidebar?.classList.remove('is-open');
        sidebarOverlay?.classList.remove('is-visible');
        document.body.classList.remove('menu-open');

        menuToggle?.setAttribute('aria-expanded', 'false');
    }

    menuToggle?.addEventListener('click', openSidebar);
    sidebarClose?.addEventListener('click', closeSidebar);
    sidebarOverlay?.addEventListener('click', closeSidebar);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });

    sidebar?.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', closeSidebar);
    });
});