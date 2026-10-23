<?php
include "includes/header.php";
include "includes/navbar.php";
require_once __DIR__ . "/includes/react.php";

/* ---------- Sample data (replace with database queries) ---------- */
$student = [
    'name'    => 'Edward Tan',
    'id'      => '2023-01234',
    'program' => 'BS Information Technology',
    'year'    => '3rd Year',
    'section' => 'IT-3A',
];

$terms = [
    '2026-1' => '1st Semester, A.Y. 2026-2027',
    '2025-2' => '2nd Semester, A.Y. 2025-2026',
    '2025-1' => '1st Semester, A.Y. 2025-2026',
];

// grade scale: 1.0 (highest) to 5.0 (failed). null = not yet posted
$grades = [
    '2026-1' => [
        ['code' => 'IT 301', 'title' => 'Web Systems and Technologies', 'units' => 3, 'prelim' => 1.50, 'midterm' => 1.25, 'prefinal' => null, 'final' => null, 'instructor' => 'Prof. M. Reyes'],
        ['code' => 'IT 302', 'title' => 'Information Assurance and Security', 'units' => 3, 'prelim' => 1.75, 'midterm' => 2.00, 'prefinal' => null, 'final' => null, 'instructor' => 'Prof. L. Santos'],
        ['code' => 'IT 303', 'title' => 'Systems Integration and Architecture', 'units' => 3, 'prelim' => 2.00, 'midterm' => 1.75, 'prefinal' => null, 'final' => null, 'instructor' => 'Prof. J. Cruz'],
        ['code' => 'IT 304', 'title' => 'Capstone Project 1', 'units' => 3, 'prelim' => 1.25, 'midterm' => 1.50, 'prefinal' => null, 'final' => null, 'instructor' => 'Prof. A. Garcia'],
        ['code' => 'GE 105', 'title' => 'Ethics', 'units' => 3, 'prelim' => null, 'midterm' => null, 'prefinal' => null, 'final' => null, 'instructor' => 'Prof. R. Lim'],
        ['code' => 'PE 4',   'title' => 'Physical Education 4', 'units' => 2, 'prelim' => 1.00, 'midterm' => 1.00, 'prefinal' => null, 'final' => null, 'instructor' => 'Prof. D. Ramos'],
    ],
    '2025-2' => [
        ['code' => 'IT 201', 'title' => 'Database Management Systems', 'units' => 3, 'prelim' => 1.50, 'midterm' => 1.50, 'prefinal' => 1.25, 'final' => 1.25, 'instructor' => 'Prof. M. Reyes'],
        ['code' => 'IT 202', 'title' => 'Networking 2', 'units' => 3, 'prelim' => 2.00, 'midterm' => 2.25, 'prefinal' => 2.00, 'final' => 2.00, 'instructor' => 'Prof. L. Santos'],
        ['code' => 'IT 203', 'title' => 'Object-Oriented Programming', 'units' => 3, 'prelim' => 1.75, 'midterm' => 1.50, 'prefinal' => 1.50, 'final' => 1.50, 'instructor' => 'Prof. J. Cruz'],
        ['code' => 'IT 204', 'title' => 'Human-Computer Interaction', 'units' => 3, 'prelim' => 1.25, 'midterm' => 1.25, 'prefinal' => 1.50, 'final' => 1.25, 'instructor' => 'Prof. A. Garcia'],
        ['code' => 'GE 104', 'title' => 'Science, Technology and Society', 'units' => 3, 'prelim' => 2.50, 'midterm' => 2.25, 'prefinal' => 2.25, 'final' => 2.25, 'instructor' => 'Prof. R. Lim'],
        ['code' => 'PE 3',   'title' => 'Physical Education 3', 'units' => 2, 'prelim' => 1.00, 'midterm' => 1.00, 'prefinal' => 1.00, 'final' => 1.00, 'instructor' => 'Prof. D. Ramos'],
    ],
    '2025-1' => [
        ['code' => 'IT 101', 'title' => 'Data Structures and Algorithms', 'units' => 3, 'prelim' => 2.00, 'midterm' => 1.75, 'prefinal' => 1.75, 'final' => 1.75, 'instructor' => 'Prof. J. Cruz'],
        ['code' => 'IT 102', 'title' => 'Networking 1', 'units' => 3, 'prelim' => 2.25, 'midterm' => 2.00, 'prefinal' => 2.00, 'final' => 2.00, 'instructor' => 'Prof. L. Santos'],
        ['code' => 'IT 103', 'title' => 'Discrete Mathematics', 'units' => 3, 'prelim' => 2.75, 'midterm' => 2.50, 'prefinal' => 2.50, 'final' => 2.50, 'instructor' => 'Prof. K. Dela Cruz'],
        ['code' => 'GE 103', 'title' => 'The Contemporary World', 'units' => 3, 'prelim' => 1.75, 'midterm' => 1.75, 'prefinal' => 1.50, 'final' => 1.50, 'instructor' => 'Prof. R. Lim'],
        ['code' => 'PE 2',   'title' => 'Physical Education 2', 'units' => 2, 'prelim' => 1.00, 'midterm' => 1.25, 'prefinal' => 1.00, 'final' => 1.00, 'instructor' => 'Prof. D. Ramos'],
    ],
];

$termKey = $_GET['term'] ?? array_key_first($terms);
if (!isset($terms[$termKey])) {
    $termKey = array_key_first($terms);
}
$subjects = $grades[$termKey];
react_mount("egrade", [
    "student" => $student,
    "terms" => $terms,
    "termKey" => $termKey,
    "term" => $terms[$termKey],
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
                    '<strong>The EGrade page did not start.</strong> Make sure <code>EGrade</code> is registered as ' +
                    '<code>"egrade"</code> in <code>src/main.jsx</code>, run <code>npm run build</code>, ' +
                    'then hard refresh (Ctrl+F5).</div></div>';
            }
        }, 1500);
    });
</script>
<?php
include "includes/footer.php";
