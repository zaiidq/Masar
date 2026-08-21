<aside
    class="sidebar"
    id="sidebar"
>
    <div class="sidebar-heading">
        <div class="sidebar-logo">
            <h2>Masar</h2>
            <p>Student Portal</p>
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

        <a href="/masar/student/dashboard.php">
            Dashboard
        </a>

        <a href="/masar/student/profile.php">
            My Profile
        </a>

        <a href="/masar/student/academic-record.php">
            Academic Record
        </a>

        <a href="/masar/student/recommendations.php">
            Recommendations
        </a>

        <a href="/masar/student/course-schedule.php">
            Course Schedule
        </a>

        <a href="/masar/student/university-links.php">
            University Links
        </a>

        <a href="/masar/student/chatbot.php">
            AI Assistant
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