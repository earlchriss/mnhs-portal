<?php
require_once __DIR__ . "/../../functions/auth.php"; // auth_user() / auth_csrf_token(), even if the page did not load it
// Who is signed in (the admin guard has already checked this)
$nav_user = auth_user() ?: [];
$nav_name = trim((string) ($nav_user["name"] ?? "")) ?: (string) ($nav_user["username"] ?? "Admin");
$nav_words = preg_split('/\s+/', $nav_name, -1, PREG_SPLIT_NO_EMPTY) ?: ["A"];
$nav_initials = mb_strtoupper(implode("", array_map(fn($w) => mb_substr($w, 0, 1), array_slice($nav_words, 0, 2))));
$nav_h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, "UTF-8");
?>
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
            <button class="user-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu for <?= $nav_h($nav_name) ?>">
                <span class="avatar"><?= $nav_h($nav_initials) ?></span>
                <i class="fas fa-chevron-down caret"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="#">Profile</a></li>
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li>
                    <form method="post" action="/admin/logout.php" class="m-0">
                        <input type="hidden" name="csrf" value="<?= $nav_h(auth_csrf_token()) ?>">
                        <button type="submit" class="dropdown-item"><i class="fas fa-sign-out me-2"></i>Logout</button>
                    </form>
                </li>
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
        <a class="drawer-link" href="/teacher/">Teacher Portal</a>
    </nav>
</aside>
<div class="drawer-backdrop" id="drawerBackdrop"></div>
