<?php
include "includes/header.php";
include "includes/navbar.php";
require_once __DIR__ . "/includes/react.php";

/* Sample data: replace with database queries */
$term = "1st Semester, AY 2026-2027";
$student = [
    'name'    => 'Edward Tan',
    'id'      => '2023-01234',
    'grade'   => 'Grade 12',
    'section' => 'IT-3A',
];
$subjects = [
    ["code" => "CS 301",   "title" => "Data Structures and Algorithms", "units" => 3, "instructor" => "A. Reyes",      "days" => ["Mon", "Wed"], "start" => "08:00", "end" => "09:30", "room" => "CCS 301"],
    ["code" => "CS 305",   "title" => "Database Management Systems",    "units" => 3, "instructor" => "M. Santos",     "days" => ["Tue", "Thu"], "start" => "10:00", "end" => "11:30", "room" => "CCS Lab 2"],
    ["code" => "CS 312",   "title" => "Web Systems and Technologies",   "units" => 3, "instructor" => "J. Villanueva", "days" => ["Mon", "Wed"], "start" => "13:00", "end" => "14:30", "room" => "CCS Lab 1"],
    ["code" => "MATH 210", "title" => "Discrete Mathematics",           "units" => 3, "instructor" => "L. Garcia",     "days" => ["Tue", "Thu"], "start" => "14:30", "end" => "16:00", "room" => "RM 204"],
    ["code" => "GE 104",   "title" => "Ethics",                         "units" => 3, "instructor" => "R. Dela Cruz",  "days" => ["Fri"],        "start" => "09:00", "end" => "12:00", "room" => "RM 118"],
    ["code" => "PE 4",     "title" => "Physical Education 4",           "units" => 2, "instructor" => "T. Flores",     "days" => ["Sat"],        "start" => "08:00", "end" => "10:00", "room" => "Gym"],
];

/* ---------- Hand everything to React ---------- */
react_mount("schedule", [
    "student"  => $student,
    "term"     => $term,
    "today"    => date("D"),
    "subjects" => $subjects,
]);
?>
<script>
    // If React never took over the page (page not registered in src/main.jsx, or the
    // bundle was not rebuilt), say so instead of leaving an empty screen.
    window.addEventListener("load", function() {
        setTimeout(function() {
            var root = document.getElementById("root");
            if (root && !root.children.length) {
                root.innerHTML = '<div class="container-xl"><div class="alert alert-warning" role="alert">' +
                    '<strong>The Schedule page did not start.</strong> Make sure <code>Schedule</code> is registered as ' +
                    '<code>"schedule"</code> in <code>src/main.jsx</code>, run <code>npm run build</code>, ' +
                    'then hard refresh (Ctrl+F5).</div></div>';
            }
        }, 1500);
    });
</script>
<?php
include "includes/footer.php";
