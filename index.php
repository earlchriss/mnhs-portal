<?php
// Student Portal home page (rebuilt from src/pages/Home.jsx).
require_once __DIR__ . "/functions/moodle.php";
auth_require_login(); // must run before any output

include "includes/header.php";
include "includes/navbar.php";
require_once __DIR__ . "/includes/react.php";

$user = auth_user();
$now = new DateTime("now", new DateTimeZone(MOODLE_TIMEZONE)); // Philippine time
$hour = (int) $now->format("G");
$greeting = $hour < 12 ? "Good morning" : ($hour < 18 ? "Good afternoon" : "Good evening");

$nameParts = preg_split('/\s+/', trim($user["name"])) ?: [];

$student = [
    "first_name" => $nameParts[0] ?? $user["name"],
    "id" => $user["username"],
    "program" => "BS Information Technology",
    "year" => "3rd Year",
    "section" => "IT-3A",
];
$term = "1st Semester, AY 2026-2027";

$forms = moodle_evaluation_forms(isset($_GET["fresh"]));
$initial = $forms["ok"] ? $forms["data"] : null; // ["faculty" => [...], "services" => [...]]

react_mount("home", [
    "student" => $student,
    "term" => $term,
    "today" => $now->format("l, F j, Y"),
    "greeting" => $greeting,
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
                    '<strong>The Home page did not start.</strong> Make sure <code>Home</code> is registered as ' +
                    '<code>"home"</code> in <code>src/main.jsx</code>, run <code>npm run build</code>, ' +
                    'then hard refresh (Ctrl+F5).</div></div>';
            }
        });
    });
</script>
<?php
include "includes/footer.php";
