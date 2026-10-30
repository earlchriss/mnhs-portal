<?php
require_once __DIR__ . '/../functions/auth.php';
require_once __DIR__ . '/../functions/db.php';
require_once __DIR__ . '/../includes/react.php';
auth_require_login();
header('Cache-Control: no-store');

$user = auth_user();
try {
    // Access and data are tied to the authenticated Moodle ID, never a browser-supplied ID.
    $stmt = db_connect()->prepare('SELECT id FROM teachers WHERE moodle_id = :id LIMIT 1');
    $stmt->execute(['id' => $user['id']]);
    $teacherId = $stmt->fetchColumn();
    if ($teacherId === false) {
        http_response_code(403);
        exit('Teacher access is unavailable for this account. Contact an administrator to sync your Moodle teacher account.');
    }
    $stmt = db_connect()->prepare("SELECT sc.id, sub.code, sub.title, sec.name AS section, room.name AS room,
        sc.days, TIME_FORMAT(sc.start_time, '%H:%i') AS start_time, TIME_FORMAT(sc.end_time, '%H:%i') AS end_time
        FROM schedules sc JOIN subjects sub ON sub.id = sc.subject_id
        JOIN sections sec ON sec.id = sc.section_id JOIN rooms room ON room.id = sc.room_id
        WHERE sc.teacher_id = :id ORDER BY sc.start_time, sub.title, sec.name");
    $stmt->execute(['id' => $teacherId]);
    $schedule = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('Teacher portal: ' . $e->getMessage());
    http_response_code(503);
    exit('The teacher portal is temporarily unavailable. Please try again later.');
}
$h = fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$name = trim($user['name']) ?: $user['username'];
$now = new DateTime('now', new DateTimeZone('Asia/Manila'));
$hour = (int) $now->format('G');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Teacher Portal</title>
    <link rel="stylesheet" href="/admin/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="/admin/assets/css/custom.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css">
</head>

<body>
    <nav class="moodle-navbar">
        <button type="button" class="nav-icon-btn" id="drawerOpen" aria-controls="drawer" aria-expanded="false" aria-label="Open navigation"><i class="fas fa-bars"></i></button>
        <div class="navbar-right">
            <div class="dropdown">
                <button class="user-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu for <?= $h($name) ?>"><span class="avatar"><?= $h(mb_strtoupper(mb_substr($name, 0, 1))) ?></span><i class="fas fa-chevron-down caret"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <form method="post" action="/admin/logout.php" class="m-0"><input type="hidden" name="csrf" value="<?= $h(auth_csrf_token()) ?>"><button class="dropdown-item" type="submit"><i class="fas fa-sign-out me-2"></i>Logout</button></form>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <aside class="moodle-drawer" id="drawer" aria-hidden="true">
        <div class="drawer-header"><button type="button" class="drawer-close" id="drawerClose" aria-label="Close navigation"><i class="fas fa-xmark"></i></button><img src="/assets/img/banner.png" alt="Mabolo National High School" class="drawer-logo"></div>
        <nav class="drawer-nav"><a class="drawer-link active" href="/teacher/">Home</a><a class="drawer-link" href="/teacher/schedule.php">My Schedule</a><a class="drawer-link" href="/">Student Portal</a><?php if (auth_is_admin()): ?><a class="drawer-link" href="/admin/">Admin Portal</a><?php endif; ?></nav>
    </aside>
    <div class="drawer-backdrop" id="drawerBackdrop"></div>
    <?php
    react_mount('teacher-home', [
        'name' => $name,
        'greeting' => $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening'),
        'today' => $now->format('l, F j, Y'),
        'schedule' => $schedule,
    ]);
    react_assets();
    ?>
    <script src="/assets/js/bootstrap.bundle.min.js"></script>
    <script src="/admin/assets/js/custom.js"></script>
</body>

</html>