<?php
// Admins only: signs in through Moodle and starts the session (replaces session_start()).
require_once __DIR__ . "/includes/guard.php";
require_once __DIR__ . "/../includes/react.php";
require_once __DIR__ . "/functions/subjects.php";
require_once __DIR__ . "/functions/schedules.php";
require_once __DIR__ . "/functions/moodle_teachers.php";
require_once __DIR__ . "/functions/students.php";
require_once __DIR__ . "/../functions/redis_cache.php";

$term = "1st Semester, AY 2026-2027";
$days_all = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

/* ---------- Load everything from the database ---------- */
$subjects = [];   // Subjects come from the Subjects page, so anything added or edited there shows up here
$teachers = [];
$sections = [];
$rooms = [];
$schedules = [];
$students = [];         // registered students (students table, see functions/students.php)
$student_sections = []; // [student id => section name]
$load_error = null; // shown as a banner on the page
$student_warning = null; // students tables missing or unreadable: the rest of the page still works
$teacher_warning = null; // LMS problem: the page still works with the teachers saved earlier

try {
    // Teachers come from Moodle (LMS). The sync runs at most once every 10 minutes for the
    // whole site (Redis keeps the timer), or right away with ?sync=1.
    $force_sync = isset($_GET["sync"]);
    try {
        if ($force_sync || rcache_claim("teachers:sync", 600)) {
            $teacher_warning = teachers_sync($force_sync);
            rcache_forget("schedule:teachers"); // the teachers table may have changed
            // After an LMS problem, try again in a minute instead of waiting 10
            rcache_put("teachers:sync", "1", $teacher_warning ? 60 : 600);
        }
    } catch (Throwable $e) {
        error_log("Teacher sync failed: " . $e->getMessage());
        rcache_put("teachers:sync", "1", 60);
        $teacher_warning = "Could not update teachers from LMS: " . $e->getMessage();
    }

    // Short cache: the Subjects page also clears it right away when a subject changes
    $subjects = rcache_remember("schedule:subjects", 60, "subjects_for_schedule");
    $teachers = schedule_teachers();
    $sections = schedule_sections();
    $rooms = schedule_rooms();
    $schedules = schedules_all();
} catch (Throwable $e) {
    error_log("Schedule load failed: " . $e->getMessage());
    $load_error = "Could not load the schedule from the database: " . $e->getMessage();
}

// Students are loaded on their own so a problem here never hides the schedules
try {
    $students = students_registered();
    $student_sections = students_assignments();
} catch (Throwable $e) {
    error_log("Student load failed: " . $e->getMessage());
    $student_warning = "Could not load the students (have you run students.sql?): " . $e->getMessage();
}

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

// Errors for the "Add section" / "Add room" modals (kept apart from the assign form)
const NAME_MAX = 50;
$section_errors = [];
$room_errors = [];
$old_section_name = "";
$old_room_name = "";
$student_errors = [];      // errors for the "Assign students" form
$old_student_section = "";
$old_student_ids = [];

/* ---------- Handle POST (before any output so we can redirect) ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($load_error) {
        $errors[] = "The schedule could not be loaded, so nothing was saved.";
    } elseif (!hash_equals($_SESSION["csrf"], $_POST["csrf"] ?? "")) {
        $errors[] = "Your session expired. Please try again.";
    } elseif ($action === "delete") {
        try {
            schedule_delete((int) ($_POST["id"] ?? 0));
            $_SESSION["flash"] = ["success", "Schedule removed."];
            header("Location: " . $_SERVER["REQUEST_URI"]);
            exit;
        } catch (Throwable $e) {
            error_log("Schedule delete failed: " . $e->getMessage());
            $errors[] = "Could not remove the schedule: " . $e->getMessage();
        }
    } elseif ($action === "add_section" || $action === "add_room") {
        $is_section = $action === "add_section";
        $label = $is_section ? "section" : "room";
        $existing = $is_section ? $sections : $rooms;
        $name = preg_replace('/\s+/', " ", trim((string) ($_POST["name"] ?? "")));
        $modal_errors = [];

        if ($name === "") {
            $modal_errors[] = "Enter a {$label} name.";
        } elseif (mb_strlen($name) > NAME_MAX) {
            $modal_errors[] = "The {$label} name must be " . NAME_MAX . " characters or fewer.";
        } elseif (in_array(mb_strtolower($name), array_map("mb_strtolower", $existing), true)) {
            $modal_errors[] = "A {$label} named \"{$name}\" already exists.";
        }

        if (!$modal_errors) {
            try {
                $is_section ? section_create($name) : room_create($name);
                $_SESSION["flash"] = ["success", ucfirst($label) . " " . $name . " added."];
                header("Location: " . $_SERVER["REQUEST_URI"]);
                exit;
            } catch (PDOException $e) {
                if (($e->errorInfo[1] ?? 0) === 1062) {
                    $modal_errors[] = "A {$label} named \"{$name}\" already exists.";
                } else {
                    error_log("Add {$label} failed: " . $e->getMessage());
                    $modal_errors[] = "Could not save the {$label}: " . $e->getMessage();
                }
            } catch (Throwable $e) {
                error_log("Add {$label} failed: " . $e->getMessage());
                $modal_errors[] = "Could not save the {$label}: " . $e->getMessage();
            }
        }

        if ($is_section) {
            $section_errors = $modal_errors;
            $old_section_name = $name;
        } else {
            $room_errors = $modal_errors;
            $old_room_name = $name;
        }
    } elseif ($action === "assign_students") {
        $old_student_section = (string) ($_POST["student_section"] ?? "");
        $old_student_ids = array_values(array_unique(array_map("intval", (array) ($_POST["student_ids"] ?? []))));

        if (!in_array($old_student_section, $sections, true)) {
            $student_errors[] = "Choose a section for the students.";
        }
        if (!$old_student_ids) {
            $student_errors[] = "Select at least one student.";
        } elseif (array_diff($old_student_ids, array_column($students, "id"))) {
            $student_errors[] = "One of the selected students is not registered.";
        }

        if (!$student_errors) {
            try {
                students_assign($old_student_ids, $old_student_section);
                $count = count($old_student_ids);
                $_SESSION["flash"] = ["success", $count . ($count === 1 ? " student" : " students") . " assigned to " . $old_student_section . "."];
                header("Location: " . $_SERVER["REQUEST_URI"]);
                exit;
            } catch (Throwable $e) {
                error_log("Student assign failed: " . $e->getMessage());
                $student_errors[] = "Could not assign the students: " . $e->getMessage();
            }
        }
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
                $code = $subjects[$s["subject_id"]]["code"] ?? "another subject";
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
            try {
                schedule_create($old);
                $_SESSION["flash"] = ["success", "Schedule assigned to " . $old["section"] . " and " . $teachers[$old["teacher_id"]] . "."];
                header("Location: " . $_SERVER["REQUEST_URI"]);
                exit;
            } catch (Throwable $e) {
                error_log("Schedule save failed: " . $e->getMessage());
                $errors[] = "Could not save the schedule: " . $e->getMessage();
            }
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
$_page_data = compact("subjects", "teachers", "sections", "rooms", "schedules", "shown", "load_error", "students", "student_sections");
include "includes/header.php";
include "includes/navbar.php";
extract($_page_data);
unset($_page_data);

if ($load_error) {
    echo '<div class="container-xl mt-3"><div class="alert alert-danger" role="alert">'
        . '<div class="fw-semibold">Something went wrong</div>'
        . '<div class="small">' . htmlspecialchars($load_error, ENT_QUOTES, "UTF-8") . '</div>'
        . '</div></div>';
}

if ($student_warning) {
    echo '<div class="container-xl mt-3"><div class="alert alert-warning" role="alert">'
        . '<div class="fw-semibold">Students</div>'
        . '<div class="small">' . htmlspecialchars($student_warning, ENT_QUOTES, "UTF-8") . '</div>'
        . '</div></div>';
}

if ($teacher_warning) {
    echo '<div class="container-xl mt-3"><div class="alert alert-warning" role="alert">'
        . '<div class="fw-semibold">Teachers</div>'
        . '<div class="small">' . htmlspecialchars($teacher_warning, ENT_QUOTES, "UTF-8") . '</div>'
        . '</div></div>';
}

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
    "sectionErrors" => $section_errors,
    "roomErrors" => $room_errors,
    "oldSectionName" => $old_section_name,
    "oldRoomName" => $old_room_name,
    "totalEntries" => $total_entries,
    "sectionsUsed" => $sections_used,
    "teachersUsed" => $teachers_used,
    "weeklyHours" => $weekly_hours,
    "students" => $students,
    "studentSections" => (object) $student_sections,
    "studentErrors" => $student_errors,
    "oldStudentSection" => $old_student_section,
    "oldStudentIds" => $old_student_ids,
]);
react_assets();
?>
<script>
    // If React never took over the page (page not registered in src/main.jsx, or the
    // bundle was not rebuilt), say so instead of leaving an empty screen.
    var pageErrors = [];
    window.addEventListener("error", function(event) {
        pageErrors.push(event.message || "Unknown script error");
    });
    window.addEventListener("unhandledrejection", function(event) {
        pageErrors.push(String(event.reason && event.reason.message || event.reason));
    });

    function escapeHtml(text) {
        var div = document.createElement("div");
        div.textContent = text;
        return div.innerHTML;
    }
    window.addEventListener("load", function() {
        setTimeout(function() {
            var root = document.getElementById("root");
            if (root && !root.children.length) {
                var details = pageErrors.length ?
                    '<div class="mt-2"><strong>Error:</strong> <code>' + escapeHtml(pageErrors.join(" | ")) + '</code></div>' : '';
                root.innerHTML = '<div class="container-xl"><div class="alert alert-warning" role="alert">' +
                    '<strong>The Schedule page did not start.</strong> Make sure <code>AdminSchedule</code> is registered as ' +
                    '<code>"admin-schedule"</code> in <code>src/main.jsx</code>, run <code>npm run build</code>, ' +
                    'then hard refresh (Ctrl+F5).' + details + '</div></div>';
            }
        }, 1500);
    });
</script>
<?php
include "includes/footer.php";
exit;
