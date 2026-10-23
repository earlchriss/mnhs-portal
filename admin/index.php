<?php
include "includes/header.php";
include "includes/navbar.php";
require_once __DIR__ . "/../includes/react.php";

/* ---------- Sample data (replace with database queries) ---------- */
$admin = [
    "first_name" => "Maria",
    "name"       => "Maria Dela Cruz",
    "role"       => "Evaluation Administrator",
];
$term = "1st Semester, AY 2026-2027";
$eval_open = "2026-10-01";
$eval_deadline = "2026-11-15";
$total_students = 842;

$hour = (int) date("G");
$greeting = $hour < 12 ? "Good morning" : ($hour < 18 ? "Good afternoon" : "Good evening");

// responses = submitted forms, enrolled = students who must evaluate, avg = average rating (1-5) or null
$faculty_stats = [
    ["code" => "IT 301", "title" => "Web Systems and Technologies",         "instructor" => "Prof. M. Reyes",  "responses" => 38, "enrolled" => 42, "avg" => 4.52],
    ["code" => "IT 302", "title" => "Information Assurance and Security",   "instructor" => "Prof. L. Santos", "responses" => 27, "enrolled" => 40, "avg" => 4.18],
    ["code" => "IT 303", "title" => "Systems Integration and Architecture", "instructor" => "Prof. J. Cruz",   "responses" => 15, "enrolled" => 41, "avg" => 3.94],
    ["code" => "IT 304", "title" => "Capstone Project 1",                   "instructor" => "Prof. A. Garcia", "responses" => 9,  "enrolled" => 38, "avg" => 4.30],
    ["code" => "GE 105", "title" => "Ethics",                               "instructor" => "Prof. R. Lim",    "responses" => 0,  "enrolled" => 45, "avg" => null],
];

// Student identities are not shown, to keep evaluations confidential
$recent = [
    ["subject" => "IT 301 – Web Systems and Technologies",       "section" => "IT-3A", "time" => "5 minutes ago"],
    ["subject" => "Library Services",                            "section" => "IT-2B", "time" => "22 minutes ago"],
    ["subject" => "IT 302 – Information Assurance and Security", "section" => "IT-3A", "time" => "1 hour ago"],
    ["subject" => "Campus Facilities",                           "section" => "CS-1C", "time" => "3 hours ago"],
];

/* ---------- Summary numbers ---------- */
$submitted = array_sum(array_column($faculty_stats, "responses"));
$expected = array_sum(array_column($faculty_stats, "enrolled"));
$completion = $expected ? round($submitted / $expected * 100) : 0;
$days_left = (int) ceil((strtotime($eval_deadline) - strtotime(date("Y-m-d"))) / 86400);

$period_total = max(1, strtotime($eval_deadline) - strtotime($eval_open));
$period_elapsed = min($period_total, max(0, strtotime(date("Y-m-d")) - strtotime($eval_open)));
$period_percent = round($period_elapsed / $period_total * 100);
$period_open = $days_left >= 0;

function response_status($pct)
{
    if ($pct >= 75) return ["On track", "success"];
    if ($pct >= 40) return ["In progress", "warning"];
    return ["Low response", "danger"];
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
    "evalOpen" => date("M j", strtotime($eval_open)),
    "evalDeadline" => date("M j, Y", strtotime($eval_deadline)),
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
