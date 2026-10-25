<?php
// Admins only: signs in through Moodle and starts the session.
require_once __DIR__ . "/includes/guard.php";
require_once __DIR__ . "/functions/students.php";
include "includes/header.php";
include "includes/navbar.php";
require_once __DIR__ . "/../includes/react.php";

/* ---------- Real data only ---------- */
$term = "1st Semester, AY 2026-2027";

// The signed-in administrator (from the Moodle login)
$me = auth_user() ?: [];
$full_name = trim((string) ($me["name"] ?? "")) ?: (string) ($me["username"] ?? "Administrator");
$admin = [
    "first_name" => preg_split('/\s+/', $full_name)[0],
    "name"       => $full_name,
    "role"       => "Administrator",
];

// Registered students (students table). A problem here shows a warning, not a broken page.
$total_students = 0;
$home_warning = null;
try {
    $total_students = count(students_registered());
} catch (Throwable $e) {
    error_log("Home: could not count students: " . $e->getMessage());
    $home_warning = "Could not load the students (have you run students.sql?): " . $e->getMessage();
}

$hour = (int) date("G");
$greeting = $hour < 12 ? "Good morning" : ($hour < 18 ? "Good afternoon" : "Good evening");

// Evaluation results are not read from anywhere yet: these stay empty until they are
// connected to the ratings (LMS Feedback) data, so nothing made-up is shown.
$faculty_stats = []; // each: code, title, instructor, responses, enrolled, avg (or null)
$recent = [];        // each: subject, section, time
$eval_open = null;      // "Y-m-d" when the evaluation period opens
$eval_deadline = null;  // "Y-m-d" when it closes

/* ---------- Summary numbers ---------- */
$submitted = array_sum(array_column($faculty_stats, "responses"));
$expected = array_sum(array_column($faculty_stats, "enrolled"));
$completion = $expected ? round($submitted / $expected * 100) : 0;

$today_ts = strtotime(date("Y-m-d"));
$days_left = 0;
$period_percent = 0;
$period_open = false;
if ($eval_open && $eval_deadline) {
    $days_left = (int) ceil((strtotime($eval_deadline) - $today_ts) / 86400);
    $period_total = max(1, strtotime($eval_deadline) - strtotime($eval_open));
    $period_elapsed = min($period_total, max(0, $today_ts - strtotime($eval_open)));
    $period_percent = round($period_elapsed / $period_total * 100);
    $period_open = $days_left >= 0;
}

if ($home_warning) {
    echo '<div class="container-xl mt-3"><div class="alert alert-warning" role="alert">'
        . '<div class="fw-semibold">Students</div>'
        . '<div class="small">' . htmlspecialchars($home_warning, ENT_QUOTES, "UTF-8") . '</div>'
        . '</div></div>';
}

react_mount("admin-home", [
    "admin" => $admin,
    "term" => $term,
    "today" => date("l, F j, Y"),
    "greeting" => $greeting,
    "totalStudents" => $total_students,
    "submitted" => $submitted,
    "expected" => $expected,
    "completion" => $completion,
    "daysLeft" => $days_left,
    "periodOpen" => $period_open,
    "periodPercent" => $period_percent,
    "evalOpen" => $eval_open ? date("M j", strtotime($eval_open)) : "",
    "evalDeadline" => $eval_deadline ? date("M j, Y", strtotime($eval_deadline)) : "",
    "facultyStats" => $faculty_stats,
    "recent" => $recent,
]);
react_assets();
?>
<script>
    // If React never took over the page (page not registered in src/main.jsx, or the
    // bundle was not rebuilt), say so instead of leaving an empty screen.
    window.addEventListener("load", function() {
        setTimeout(function() {
            var root = document.getElementById("root");
            if (root && !root.children.length) {
                root.innerHTML = '<div class="container-xl"><div class="alert alert-warning" role="alert">' +
                    '<strong>The Home page did not start.</strong> Make sure <code>AdminHome</code> is registered as ' +
                    '<code>"admin-home"</code> in <code>src/main.jsx</code>, run <code>npm run build</code>, ' +
                    'then hard refresh (Ctrl+F5).</div></div>';
            }
        }, 1500);
    });
</script>
<?php
include "includes/footer.php";
exit;
