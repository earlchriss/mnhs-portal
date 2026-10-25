<!-- Navbar -->
<nav class="moodle-navbar">
    <button type="button" class="nav-icon-btn" id="drawerOpen" aria-controls="drawer" aria-expanded="false" aria-label="Open navigation">
        <i class="fas fa-bars"></i>
    </button>

    <div class="navbar-right">
        <a href="#" class="nav-icon-btn position-relative" aria-label="Notifications">
            <i class="fas fa-bell"></i>
            <span class="count-badge">5</span>
        </a>
        <a href="#" class="nav-icon-btn" aria-label="Messages">
            <i class="fas fa-comment"></i>
        </a>

        <div class="dropdown">
            <button class="user-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="avatar">ET</span>
                <i class="fas fa-chevron-down caret"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="#">Profile</a></li>
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li><a class="dropdown-item" href="#"><i class="fas fa-sign-out me-2"></i>Logout</a></li>
            </ul>
        </div>
    </div>
</nav>

<!-- Drawer -->
<aside class="moodle-drawer" id="drawer" aria-hidden="true">
    <div class="drawer-header">
        <button type="button" class="drawer-close" id="drawerClose" aria-label="Close navigation">
            <i class="fas fa-xmark"></i>
        </button>
        <img src="/assets/img/banner.png" alt="Mabolo National High School" class="drawer-logo">
    </div>
    <nav class="drawer-nav">
        <a class="drawer-link <?= isActive('index.php') ?>" href="./">Home</a>
        <a class="drawer-link <?= isActive('faculty.php') ?>" href="./faculty.php">Faculties</a>
        <a class="drawer-link <?= isActive('schedule.php') ?>" href="./schedule.php">Schedule</a>
        <a class="drawer-link <?= isActive('subjects.php') ?>" href="./subjects.php">Subjects</a>
        <a class="drawer-link <?= isActive('users.php') ?>" href="./users.php">Users</a>
        <a class="drawer-link <?= isActive('site-settings.php') ?>" href="./site-settings.php">Site Settings</a>
    </nav>
</aside>
<div class="drawer-backdrop" id="drawerBackdrop"></div>