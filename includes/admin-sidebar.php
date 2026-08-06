<aside
    class="sidebar"
    id="sidebar"
>
    <div class="sidebar-heading">

        <div class="sidebar-logo">
            <h2>Masar</h2>
            <p>Admin Portal</p>
        </div>

        <button
            type="button"
            class="sidebar-close"
            id="sidebarClose"
            aria-label="Close navigation menu"
        >
            ×
        </button>

    </div>

    <nav class="sidebar-nav">

        <a href="/masar/admin/dashboard.php">
            Dashboard
        </a>

        <a href="/masar/admin/university-links.php">
            University Links
        </a>

        <a href="#">
            Marketplace Moderation
        </a>

        <a href="#">
            Users
        </a>

    </nav>

    <div class="sidebar-user">

        <strong>
            <?= htmlspecialchars($_SESSION['full_name']) ?>
        </strong>

        <a href="/masar/auth/logout.php">
            Logout
        </a>

    </div>
</aside>