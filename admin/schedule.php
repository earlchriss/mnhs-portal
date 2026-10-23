<?php
session_start();
require_once __DIR__ . "/../includes/react.php";

/* =====================================================================
   DUMMY DATA — replace this whole block with database queries later.
   Schedules are kept in $_SESSION only so that assigning and removing
   works while you test. They reset when the session ends.
   ===================================================================== */
$term = "1st Semester, AY 2026-2027";
$days_all = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

// Subjects come from the Subjects page (functions/subjects.php), so anything added or edited there shows up here
require_once __DIR__ . "/functions/subjects.php";
$subjects = subjects_for_schedule();
$teachers = [
    1 => "Prof. M. Reyes",
    2 => "Prof. L. Santos",
    3 => "Prof. J. Cruz",
    4 => "Prof. A. Garcia",
    5 => "Prof. R. Lim",
    6 => "Prof. D. Ramos",
];
$sections = ["IT-3A", "IT-3B", "IT-2A", "CS-1C"];
$rooms = ["Room 101", "Room 102", "Room 201", "Computer Lab 1", "Computer Lab 2", "Gym"];

// Visit ?reset=1 to throw away saved dummy schedules and reload the sample ones
if (isset($_GET["reset"])) {
    unset($_SESSION["schedules"]);
    header("Location: " . strtok($_SERVER["REQUEST_URI"], "?"));
    exit;
}

if (!isset($_SESSION["schedules"])) {
    $_SESSION["schedules"] = [
        ["id" => 1, "subject_id" => 1, "teacher_id" => 1, "section" => "IT-3A", "days" => ["Mon", "Wed"], "start" => "08:00", "end" => "09:30", "room" => "Computer Lab 1"],
        ["id" => 2, "subject_id" => 2, "teacher_id" => 2, "section" => "IT-3A", "days" => ["Tue", "Thu"], "start" => "10:00", "end" => "11:30", "room" => "Room 201"],
        ["id" => 3, "subject_id" => 3, "teacher_id" => 3, "section" => "IT-3A", "days" => ["Mon", "Wed"], "start" => "13:00", "end" => "14:30", "room" => "Room 102"],
        ["id" => 4, "subject_id" => 4, "teacher_id" => 4, "section" => "IT-3B", "days" => ["Fri"], "start" => "09:00", "end" => "12:00", "room" => "Computer Lab 2"],
        ["id" => 5, "subject_id" => 5, "teacher_id" => 5, "section" => "IT-2A", "days" => ["Tue", "Thu"], "start" => "08:00", "end" => "09:30", "room" => "Room 101"],
        ["id" => 6, "subject_id" => 6, "teacher_id" => 6, "section" => "CS-1C", "days" => ["Sat"], "start" => "08:00", "end" => "10:00", "room" => "Gym"],
        ["id" => 7, "subject_id" => 1, "teacher_id" => 1, "section" => "IT-3B", "days" => ["Tue", "Thu"], "start" => "13:00", "end" => "14:30", "room" => "Computer Lab 1"],
    ];
}
// Drop any saved schedule whose subject, teacher or section no longer exists above
// (happens when the dummy lists change while an old session is still alive)
$schedules = array_values(array_filter(
    $_SESSION["schedules"],
    fn($s) =>
    isset($subjects[$s["subject_id"]], $teachers[$s["teacher_id"]])
        && in_array($s["section"], $sections, true)
        && !empty($s["days"])
));
$_SESSION["schedules"] = $schedules;
/* ===================== end of dummy data ===================== */

// 30-minute time slots from 7:00 AM to 6:00 PM
$slots = [];
for ($m = 7 * 60; $m <= 18 * 60; $m += 30) {
    $slots[] = sprintf("%02d:%02d", intdiv($m, 60), $m % 60);
}
$fmt = fn($t) => date("g:i A", strtotime($t));
$minutes = fn($t) => (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);

$_SESSION["csrf"] = $_SESSION["csrf"] ?? bin2hex(random_bytes(16));

$flash = $_SESSION["flash"] ?? null;
unset($_SESSION["flash"]);
$errors = [];
$old = ["subject_id" => "", "teacher_id" => "", "section" => "", "days" => [], "start" => "", "end" => "", "room" => ""];

/* ---------- Handle POST (before any output so we can redirect) ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if (!hash_equals($_SESSION["csrf"], $_POST["csrf"] ?? "")) {
        $errors[] = "Your session expired. Please try again.";
    } elseif ($action === "delete") {
        $del = (int) ($_POST["id"] ?? 0);
        $schedules = array_values(array_filter($schedules, fn($s) => $s["id"] !== $del));
        $_SESSION["schedules"] = $schedules; // TODO: DELETE FROM schedules WHERE id = ?
        $_SESSION["flash"] = ["success", "Schedule removed."];
        header("Location: " . $_SERVER["REQUEST_URI"]);
        exit;
    } elseif ($action === "assign") {
        $old = [
            "subject_id" => (int) ($_POST["subject_id"] ?? 0),
            "teacher_id" => (int) ($_POST["teacher_id"] ?? 0),
            "section"    => $_POST["section"] ?? "",
            "days"       => array_values(array_intersect($days_all, (array) ($_POST["days"] ?? []))),
            "start"      => $_POST["start"] ?? "",
            "end"        => $_POST["end"] ?? "",
            "room"       => $_POST["room"] ?? "",
        ];

        if (!isset($subjects[$old["subject_id"]])) $errors[] = "Choose a subject.";
        if (!isset($teachers[$old["teacher_id"]])) $errors[] = "Choose a teacher.";
        if (!in_array($old["section"], $sections, true)) $errors[] = "Choose a section.";
        if (!in_array($old["room"], $rooms, true)) $errors[] = "Choose a room.";
        if (!$old["days"]) $errors[] = "Select at least one day.";
        if (!in_array($old["start"], $slots, true) || !in_array($old["end"], $slots, true)) {
            $errors[] = "Choose a start and end time.";
        } elseif ($minutes($old["end"]) <= $minutes($old["start"])) {
            $errors[] = "The end time must be after the start time.";
        }

        // Conflict checks: same day + overlapping time + same teacher, section or room
        if (!$errors) {
            $found = [];
            foreach ($schedules as $s) {
                $shared = array_intersect($old["days"], $s["days"]);
                $overlap = $minutes($old["start"]) < $minutes($s["end"]) && $minutes($old["end"]) > $minutes($s["start"]);
                if (!$shared || !$overlap) continue;

                $when = implode("/", $shared) . " " . $fmt($s["start"]) . "–" . $fmt($s["end"]);
                $code = $subjects[$s["subject_id"]]["code"];
                if ($s["teacher_id"] === $old["teacher_id"]) {
                    $found[] = $teachers[$old["teacher_id"]] . " already teaches " . $code . " on " . $when . ".";
                }
                if ($s["section"] === $old["section"]) {
                    $found[] = "Section " . $old["section"] . " already has " . $code . " on " . $when . ".";
                }
                if ($s["room"] === $old["room"]) {
                    $found[] = $old["room"] . " is already used for " . $code . " on " . $when . ".";
                }
            }
            $errors = array_values(array_unique($found));
        }

        if (!$errors) {
            $next_id = $schedules ? max(array_column($schedules, "id")) + 1 : 1;
            $schedules[] = ["id" => $next_id] + $old;
            $_SESSION["schedules"] = $schedules; // TODO: INSERT INTO schedules (...) VALUES (...)
            $_SESSION["flash"] = ["success", "Schedule assigned to " . $old["section"] . " and " . $teachers[$old["teacher_id"]] . "."];
            header("Location: " . $_SERVER["REQUEST_URI"]);
            exit;
        }
    }
}

/* ---------- Viewer filters ---------- */
$view = ($_GET["view"] ?? "") === "teachers" ? "teachers" : "students";
$filter = $_GET["filter"] ?? "";

$shown = array_filter($schedules, function ($s) use ($view, $filter) {
    if ($filter === "") return true;
    return $view === "students" ? $s["section"] === $filter : (string) $s["teacher_id"] === (string) $filter;
});
$day_index = fn($s) => min(array_map(fn($d) => array_search($d, ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat"]), $s["days"]));
usort($shown, fn($a, $b) => [$day_index($a), $a["start"]] <=> [$day_index($b), $b["start"]]);

/* ---------- Summary numbers ---------- */
$total_entries = count($schedules);
$sections_used = count(array_unique(array_column($schedules, "section")));
$teachers_used = count(array_unique(array_column($schedules, "teacher_id")));
$weekly_hours = 0;
foreach ($schedules as $s) {
    $weekly_hours += count($s["days"]) * ($minutes($s["end"]) - $minutes($s["start"])) / 60;
}

// Keep our data safe: included files must not overwrite these variables
$_page_data = compact("subjects", "teachers", "sections", "rooms", "schedules", "shown");
include "includes/header.php";
include "includes/navbar.php";
extract($_page_data);
unset($_page_data);

react_mount("admin-schedule", [
    "term" => $term,
    "days" => $days_all,
    "slots" => $slots,
    "subjects" => $subjects,
    "teachers" => $teachers,
    "sections" => $sections,
    "rooms" => $rooms,
    "schedules" => $schedules,
    "shown" => array_values($shown),
    "view" => $view,
    "filter" => $filter,
    "flash" => $flash,
    "errors" => $errors,
    "old" => $old,
    "csrf" => $_SESSION["csrf"],
    "totalEntries" => $total_entries,
    "sectionsUsed" => $sections_used,
    "teachersUsed" => $teachers_used,
    "weeklyHours" => $weekly_hours,
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
                    '<strong>The Schedule page did not start.</strong> Make sure <code>AdminSchedule</code> is registered as ' +
                    '<code>"admin-schedule"</code> in <code>src/main.jsx</code>, run <code>npm run build</code>, ' +
                    'then hard refresh (Ctrl+F5).</div></div>';
            }
        }, 1500);
    });
</script>
<?php
include "includes/footer.php";
exit;
