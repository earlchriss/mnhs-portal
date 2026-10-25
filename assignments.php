<?php
require_once __DIR__ . "/functions/moodle.php";
auth_require_login(); // must run before any output

include "includes/header.php";
include "includes/navbar.php";
require_once __DIR__ . "/includes/react.php";

$user = auth_user();
$student = [
    "name" => $user["name"],
    "id" => $user["username"],
    "program" => "BS Information Technology",
    "section" => "IT-3A",
];
$term = "1st Semester, AY 2026-2027";

// Load the assignments here so they are in the first paint. If Moodle fails,
// pass nothing and the page falls back to fetching and showing the error.
$initial = null;
$feed = moodle_assignment_feed();
if ($feed["ok"]) {
    $pulse = moodle_change_fingerprint();
    $initial = [
        "data" => $feed["data"],
        "version" => $pulse["ok"] ? ($pulse["data"]["version"] ?? null) : null,
    ];
}

react_mount("assignments", [
    "student" => $student,
    "term" => $term,
    "initial" => $initial,
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
                    '<strong>The Assignments page did not start.</strong> Make sure <code>Assignments</code> is registered as ' +
                    '<code>"assignments"</code> in <code>src/main.jsx</code>, run <code>npm run build</code>, ' +
                    'then hard refresh (Ctrl+F5).</div></div>';
            }
        }, 1500);
    });
</script>
<?php
include "includes/footer.php";
