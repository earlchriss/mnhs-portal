<?php
// Admins only: signs in through Moodle and starts the session (replaces session_start()).
require_once __DIR__ . "/includes/guard.php";
require_once __DIR__ . "/../includes/react.php";
require_once __DIR__ . "/functions/subjects.php";
require_once __DIR__ . "/functions/subjects_export.php";

/* NOTE: includes/header.php loads functions/function.php, which defines its own
   $subjects, $term, $today ... so this page uses different variable names. */

$page_term = "1st Semester, AY 2026-2027";

$_SESSION["csrf"] = $_SESSION["csrf"] ?? bin2hex(random_bytes(16));

$flash = $_SESSION["flash"] ?? null;
unset($_SESSION["flash"]);
$errors = [];
$old = ["action" => "add", "id" => 0, "code" => "", "title" => "", "description" => ""];

/* ---------- Excel export for Moodle (GET ?export=xlsx) ---------- */
if ($_SERVER["REQUEST_METHOD"] === "GET" && ($_GET["export"] ?? "") === "xlsx") {
    try {
        $grade = ($_GET["grade"] ?? "") !== "" ? (int) $_GET["grade"] : null; // blank = all grades
        $export = subjects_all();
        if ($grade !== null) {
            $export = array_values(array_filter($export, fn($s) => subject_grade($s["title"]) === $grade));
            if (!$export) {
                throw new RuntimeException("No subjects found for Grade " . $grade . ".");
            }
        }
        subjects_send_xlsx($export, $grade); // sends the file and exits
    } catch (Throwable $e) {
        error_log("Subjects export failed: " . $e->getMessage());
        $_SESSION["flash"] = ["danger", "Could not create the Excel file: " . $e->getMessage()];
        header("Location: " . strtok($_SERVER["REQUEST_URI"], "?"));
        exit;
    }
}

/* ---------- Handle POST (before any output so we can redirect) ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = ($_POST["action"] ?? "") === "edit" ? "edit" : "add";
    $edit_id = $action === "edit" ? (int) ($_POST["id"] ?? 0) : 0;

    $old = [
        "action"      => $action,
        "id"          => $edit_id,
        "code"        => "",
        "title"       => (string) ($_POST["title"] ?? ""),
        "description" => (string) ($_POST["description"] ?? ""),
    ];

    if (!hash_equals($_SESSION["csrf"], (string) ($_POST["csrf"] ?? ""))) {
        $errors[] = "Your session expired. Please try again.";
    } else {
        $existing = $action === "edit" ? subject_find($edit_id) : null;
        if ($existing) $old["code"] = $existing["code"];

        if ($action === "edit" && !$existing) {
            $errors[] = "That subject no longer exists.";
        } else {
            [$errors, $clean] = subject_validate($_POST);
        }

        if (!$errors) {
            try {
                if ($action === "edit") {
                    // The code is fixed once created: only title and description change
                    subject_update($edit_id, $clean);
                    $message = "Subject " . $existing["code"] . " updated.";
                } else {
                    $code = subject_create($clean);
                    $message = "Subject " . $code . " added.";
                }
                $_SESSION["flash"] = ["success", $message];
                header("Location: " . $_SERVER["REQUEST_URI"]);
                exit;
            } catch (Throwable $e) {
                error_log("Subjects save failed: " . $e->getMessage());
                $errors[] = "Could not save the subject: " . $e->getMessage();
            }
        }
    }
}

/* ---------- Data for the page ---------- */
// Which sections each subject is scheduled in (from the schedules table).
// If the schedule tables don't exist yet, the "Scheduled in" column is simply hidden.
$schedule_known = false;
$used_in = [];
try {
    require_once __DIR__ . "/functions/schedules.php";
    $used_in = schedules_sections_by_subject();
    $schedule_known = true;
} catch (Throwable $e) {
    error_log("Schedule lookup failed: " . $e->getMessage());
}

$subject_rows = [];
$load_error = null; // shown as a banner on the page (not inside the add/edit form)
try {
    foreach (subjects_all() as $row) {
        $row["sections"] = array_values(array_unique($used_in[$row["id"]] ?? []));
        $subject_rows[] = $row;
    }
} catch (Throwable $e) {
    error_log("Subjects load failed: " . $e->getMessage());
    $load_error = "Could not load subjects from the database: " . $e->getMessage();
}

include "includes/header.php";
include "includes/navbar.php";

react_mount("admin-subjects", [
    "term" => $page_term,
    "subjects" => $subject_rows,
    "scheduleKnown" => $schedule_known,
    "flash" => $flash,
    "errors" => $errors,
    "loadError" => $load_error,
    "old" => $old,
    "csrf" => $_SESSION["csrf"],
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
                    '<strong>The Subjects page did not start.</strong> Make sure <code>AdminSubjects</code> is registered as ' +
                    '<code>"admin-subjects"</code> in <code>src/main.jsx</code>, run <code>npm run build</code>, ' +
                    'then hard refresh (Ctrl+F5).' + details + '</div></div>';
            }
        }, 1500);
    });
</script>
<?php
include "includes/footer.php";
exit;
